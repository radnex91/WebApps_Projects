<?php
// ============================================================
//  MediCore ERP - Systeme de notifications
// ============================================================

/**
 * Creer une notification pour un ou plusieurs utilisateurs
 */
function notifier(array $userIds, string $type, string $titre, string $message = '', string $lien = ''): void {
    if (empty($userIds)) return;
    try {
        $placeholders = implode(',', array_fill(0, count($userIds), '(?,?,?,?,?)'));
        $flatValues = [];
        foreach ($userIds as $uid) {
            array_push($flatValues, $uid ?: null, $type, $titre, $message, $lien);
        }
        db_exec("INSERT INTO notifications (utilisateur_id, type, titre, message, lien) VALUES $placeholders", $flatValues);
    } catch (Exception $e) { _log_error('NOTIFIER', 'Echec création notification', __FILE__, __LINE__, $e); }
}

/**
 * Creer une notification pour tous les utilisateurs d'un role
 */
function notifier_role(string $role, string $type, string $titre, string $message = '', string $lien = ''): void {
    try {
        $users = db_select("SELECT id FROM utilisateurs WHERE role=? AND statut='actif'", [$role]);
        $ids = array_column($users, 'id');
        notifier($ids, $type, $titre, $message, $lien);
    } catch (Exception $e) { _log_error('NOTIFIER', 'Echec notification par rôle', __FILE__, __LINE__, $e); }
}

/**
 * Notifications automatiques selon des regles metier
 */
function notifier_verifications(): void {
    // Resultats labo anormaux (appele regulierement)
    $dernieres = db_select("SELECT a.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom
        FROM analyses a JOIN patients p ON p.id=a.patient_id
        WHERE a.statut='disponible' AND a.date_creation >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND a.resultat IS NOT NULL ORDER BY a.date_creation DESC LIMIT 50");

    foreach ($dernieres as $a) {
        // Detection mots-cles alarmants
        $alarmants = ['critique','urgent','anormal','eleve','tres bas','alerte','positif','severe','grave'];
        $detecte = false;
        foreach ($alarmants as $mot) {
            if (stripos($a['resultat'], $mot) !== false) { $detecte = true; break; }
        }
        if ($detecte) {
            notifier_role('medecin', 'alerte',
                "Resultat anormal - {$a['patient_nom']}",
                "Type: {$a['type_examen']}",
                APP_URL.'/laboratoire.php'
            );
        }
    }

    // Medicaments expires ou critiques
    $critiques = (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut IN ('critique','expire')");
    if ($critiques > 0) {
        notifier_role('pharmacien', 'critique',
            "Stock medicaments critique",
            "$critiques medicament(s) en alerte",
            APP_URL.'/pharmacie.php'
        );
    }

    // Deces non traites (corps en attente depuis > 48h)
    $enAttente = (int)db_scalar("SELECT COUNT(*) FROM certificats_deces WHERE sortie_corps='non' AND date_heure_deces < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
    if ($enAttente > 0) {
        notifier_role('admin', 'alerte',
            "Corps en attente",
            "$enAttente corps en attente depuis plus de 48h",
            APP_URL.'/deces.php'
        );
    }
}

/**
 * Recupere les notifications non lues de l'utilisateur courant
 */
function get_unread_notifications(): array {
    $uid = $_SESSION['user_id'] ?? 0;
    if (!$uid) return [];
    try {
        return db_select("SELECT * FROM notifications WHERE utilisateur_id=? AND lu=0 ORDER BY date_notification DESC LIMIT 20", [$uid]);
    } catch (Exception $e) { _log_error('NOTIFIER', 'Echec lecture notifications non lues', __FILE__, __LINE__, $e); return []; }
}

/**
 * Recupere le compteur de notifications non lues
 */
function get_unread_count(): int {
    $uid = $_SESSION['user_id'] ?? 0;
    if (!$uid) return 0;
    try {
        return (int)db_scalar("SELECT COUNT(*) FROM notifications WHERE utilisateur_id=? AND lu=0", [$uid]);
    } catch (Exception $e) { _log_error('NOTIFIER', 'Echec compteur notifications', __FILE__, __LINE__, $e); return 0; }
}
