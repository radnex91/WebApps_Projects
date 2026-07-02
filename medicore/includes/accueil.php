<?php
/**
 * Helpers du module Accueil (salle d'attente).
 *
 * Chargé explicitement par pages/accueil.php (pas d'inclusion globale).
 * Toutes les fonctions sont résilientes (try/catch) : si la table
 * arrivees_patients ou les tables v5 sont absentes, elles renvoient des
 * valeurs sûres (false / [] / null) et loguent l'erreur.
 */
require_once __DIR__ . '/config.php';

const ACCUEIL_STATUTS = ['arrive','constantes_prises','en_consultation','termine','parti'];

const ACCUEIL_SEUILS = [
    'temperature'      => ['min' => 36.0, 'max' => 38.0],
    'ta_systolique'    => ['min' => 90,   'max' => 140],
    'ta_diastolique'   => ['min' => 60,   'max' => 90],
    'pouls'            => ['min' => 60,   'max' => 100],
    'spo2'             => ['min' => 95,   'max' => 100],
    'glycemie'         => ['min' => 70,   'max' => 110],
    'freq_respiratoire'=> ['min' => 12,   'max' => 20],
];

const ACCUEIL_UNITES = [
    'temperature' => '°C', 'ta_systolique' => 'mmHg', 'ta_diastolique' => 'mmHg',
    'pouls' => 'bpm', 'spo2' => '%', 'glycemie' => 'mg/dL',
    'freq_respiratoire' => '/min', 'poids' => 'kg', 'taille' => 'cm', 'bmi' => 'kg/m²',
];

function accueil_statut_label(string $statut): string {
    return [
        'arrive'            => 'Arrivé',
        'constantes_prises' => 'Constantes prises',
        'en_consultation'   => 'En consultation',
        'termine'           => 'Terminé',
        'parti'             => 'Parti',
    ][$statut] ?? $statut;
}

/**
 * Arrivées du jour courant (triées par heure d'arrivée asc).
 * Optionnel filtre par statut. Joint patient + médecin + flags dossier/consultation.
 */
function get_arrivees_du_jour(?string $statut = null): array {
    try {
        $where  = "DATE(a.date_arrivee) = CURDATE()";
        $params = [];
        if ($statut && in_array($statut, ACCUEIL_STATUTS, true)) {
            $where .= " AND a.statut = ?";
            $params[] = $statut;
        }
        return db_select(
            "SELECT a.id, a.patient_id, a.date_arrivee, a.statut, a.motif, a.notes,
                    a.constantes_prises_par, a.date_constantes, a.medecin_id,
                    a.date_prise_en_charge, a.date_fin,
                    p.nom, p.prenom, p.numero, p.date_naissance, p.sexe,
                    CONCAT(u.prenom, ' ', u.nom) AS medecin_nom,
                    EXISTS(SELECT 1 FROM dossiers_medicaux dm WHERE dm.patient_id = a.patient_id) AS a_dossier,
                    EXISTS(SELECT 1 FROM caisse_ventes cv WHERE cv.patient_id = a.patient_id AND cv.type_vente = 'consultation' AND cv.statut = 'paye') AS a_consultation
             FROM arrivees_patients a
             JOIN patients p ON p.id = a.patient_id
             LEFT JOIN utilisateurs u ON u.id = a.medecin_id
             WHERE $where
             ORDER BY a.date_arrivee ASC",
            $params
        );
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Table arrivees_patients absente (get_arrivees_du_jour)', __FILE__, __LINE__, $e);
        return [];
    }
}

/**
 * Crée une arrivée (check-in). Retourne l'id inséré ou 0.
 */
function checkin_patient(int $patient_id, int $user_id, string $motif = ''): int {
    if ($patient_id <= 0) return 0;
    try {
        return db_exec(
            "INSERT INTO arrivees_patients (patient_id, cree_par, motif) VALUES (?, ?, ?)",
            [$patient_id, $user_id, mb_substr($motif, 0, 255)]
        );
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec checkin_patient', __FILE__, __LINE__, $e);
        return 0;
    }
}

/**
 * Retourne l'arrivée active (non terminée/partie) du jour pour un patient, ou null.
 */
function arrivee_active_du_jour(int $patient_id): ?array {
    if ($patient_id <= 0) return null;
    try {
        return db_row(
            "SELECT * FROM arrivees_patients
             WHERE patient_id = ? AND DATE(date_arrivee) = CURDATE()
               AND statut IN ('arrive','constantes_prises','en_consultation')
             ORDER BY id DESC LIMIT 1",
            [$patient_id]
        );
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'arrivee_active_du_jour', __FILE__, __LINE__, $e);
        return null;
    }
}

/**
 * Saisit les constantes d'arrivée : insère une ligne observations_infirmieres
 * par constante non vide (hospitalisation_id NULL), calcule le BMI auto depuis
 * poids(kg)+taille(cm), met à jour l'arrivée (statut='constantes_prises').
 * Retourne la liste des type_observation hors-seuil.
 *
 * $constantes : ['temperature'=>36.5, 'poids'=>70, 'taille'=>170, ...]
 */
function prendre_constantes(int $arrivee_id, int $patient_id, int $user_id, array $constantes): array {
    $alertes = [];
    if ($arrivee_id <= 0 || $patient_id <= 0) return $alertes;
    try {
        // BMI automatique si poids + taille fournis.
        $poids  = $constantes['poids'] ?? null;
        $taille = $constantes['taille'] ?? null;
        if ($poids > 0 && $taille > 0) {
            $constantes['bmi'] = round($poids / pow($taille / 100, 2), 1);
        }
        foreach ($constantes as $type => $valeur) {
            if ($valeur === null || $valeur === '' || (float)$valeur <= 0) continue;
            if (!array_key_exists($type, ACCUEIL_UNITES)) continue;
            db_exec(
                "INSERT INTO observations_infirmieres (patient_id, hospitalisation_id, utilisateur_id, type_observation, valeur, unite) VALUES (?, NULL, ?, ?, ?, ?)",
                [$patient_id, $user_id, $type, (float)$valeur, ACCUEIL_UNITES[$type]]
            );
            if (isset(ACCUEIL_SEUILS[$type])) {
                $s = ACCUEIL_SEUILS[$type];
                if ((float)$valeur < $s['min'] || (float)$valeur > $s['max']) {
                    $alertes[] = $type;
                }
            }
        }
        db_exec(
            "UPDATE arrivees_patients SET statut = 'constantes_prises', date_constantes = NOW(), constantes_prises_par = ? WHERE id = ?",
            [$user_id, $arrivee_id]
        );
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec prendre_constantes', __FILE__, __LINE__, $e);
    }
    return $alertes;
}

/**
 * Oriente l'arrivée vers un médecin (statut='en_consultation').
 */
function orienter_arrivee(int $arrivee_id, int $medecin_id): bool {
    if ($arrivee_id <= 0 || $medecin_id <= 0) return false;
    try {
        db_exec(
            "UPDATE arrivees_patients SET statut = 'en_consultation', medecin_id = ?, date_prise_en_charge = NOW() WHERE id = ?",
            [$medecin_id, $arrivee_id]
        );
        return true;
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec orienter_arrivee', __FILE__, __LINE__, $e);
        return false;
    }
}

/**
 * Termine l'arrivée (statut='termine', date_fin).
 */
function terminer_arrivee(int $arrivee_id): bool {
    if ($arrivee_id <= 0) return false;
    try {
        db_exec(
            "UPDATE arrivees_patients SET statut = 'termine', date_fin = NOW() WHERE id = ?",
            [$arrivee_id]
        );
        return true;
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec terminer_arrivee', __FILE__, __LINE__, $e);
        return false;
    }
}

/**
 * Arrivées en consultation assignées au médecin connecté aujourd'hui.
 * Triées par heure de prise en charge asc. Joint patient + flags dossier/consultation.
 */
function get_mes_consultations(int $medecin_id): array {
    if ($medecin_id <= 0) return [];
    try {
        return db_select(
            "SELECT a.id, a.patient_id, a.date_arrivee, a.date_prise_en_charge, a.motif, a.notes,
                    p.nom, p.prenom, p.numero, p.date_naissance, p.sexe,
                    EXISTS(SELECT 1 FROM dossiers_medicaux dm WHERE dm.patient_id = a.patient_id) AS a_dossier,
                    EXISTS(SELECT 1 FROM caisse_ventes cv WHERE cv.patient_id = a.patient_id AND cv.type_vente = 'consultation' AND cv.statut = 'paye') AS a_consultation
             FROM arrivees_patients a
             JOIN patients p ON p.id = a.patient_id
             WHERE a.medecin_id = ? AND a.statut = 'en_consultation' AND DATE(a.date_arrivee) = CURDATE()
             ORDER BY a.date_prise_en_charge ASC",
            [$medecin_id]
        );
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec get_mes_consultations', __FILE__, __LINE__, $e);
        return [];
    }
}

/**
 * Dernières constantes d'un patient : une valeur (la plus récente) par type_observation.
 * Retourne ['temperature'=>['v'=>36.5,'u'=>'°C'], ...].
 */
function get_dernieres_constantes(int $patient_id): array {
    $out = [];
    if ($patient_id <= 0) return $out;
    try {
        $rows = db_select(
            "SELECT type_observation, valeur, unite FROM observations_infirmieres WHERE patient_id = ? ORDER BY date_observation DESC",
            [$patient_id]
        );
        foreach ($rows as $r) {
            if (!array_key_exists($r['type_observation'], $out)) {
                $out[$r['type_observation']] = ['v' => $r['valeur'], 'u' => $r['unite']];
            }
        }
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec get_dernieres_constantes', __FILE__, __LINE__, $e);
    }
    return $out;
}

/**
 * Réoriente une arrivée vers un autre médecin : réassigne medecin_id et remet
 * date_prise_en_charge. Garde le statut 'en_consultation' (le patient reste en
 * consultation, dans la file du nouveau médecin).
 */
function reorienter_vers(int $arrivee_id, int $new_medecin_id): bool {
    if ($arrivee_id <= 0 || $new_medecin_id <= 0) return false;
    try {
        db_exec(
            "UPDATE arrivees_patients SET medecin_id = ?, date_prise_en_charge = NOW() WHERE id = ? AND statut = 'en_consultation'",
            [$new_medecin_id, $arrivee_id]
        );
        return true;
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec reorienter_vers', __FILE__, __LINE__, $e);
        return false;
    }
}