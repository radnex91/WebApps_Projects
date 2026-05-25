<?php
// ============================================================
// ATLAS PRIME LOGISTICS - Fonctions utilitaires
// ============================================================

function genererNumeroColis(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $specials = ['#', '$', '&'];
    $maxAttempts = 20;

    for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
        $parts = [];
        for ($i = 0; $i < 3; $i++) {
            $seg = '';
            $seg .= $chars[random_int(0, strlen($chars) - 1)];
            $seg .= $specials[random_int(0, count($specials) - 1)];
            $seg .= $chars[random_int(0, strlen($chars) - 1)];
            $parts[] = $seg;
        }
        $numero = 'APL' . implode('', $parts);

        $exists = Database::fetchOne("SELECT id FROM colis WHERE numero_colis = ?", [$numero]);
        if (!$exists) return $numero;
    }

    return 'APL' . bin2hex(random_bytes(8));
}

function genererNumeroVoyage(): string {
    $seq = Database::fetchOne("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM voyages")['next_id'] ?? 1;
    return 'VYG' . date('Ym') . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function genererNumeroTrajet(): string {
    $seq = Database::fetchOne("SELECT COALESCE(MAX(id),0)+1 AS next_id FROM trajets")['next_id'] ?? 1;
    return 'TRJ' . date('Ym') . str_pad($seq, 4, '0', STR_PAD_LEFT);
}

function getVehiculesDisponibles(): array {
    return Database::fetchAll(
        "SELECT v.id, v.immatriculation, v.designation
         FROM vehicules v
         WHERE v.statut = 'disponible' AND v.actif = 1
           AND v.id NOT IN (
               SELECT vh.id FROM vehicules vh
               JOIN voyages vy ON vy.vehicule_id = vh.id
               WHERE vy.statut IN ('planifie','en_cours')
           )
         ORDER BY v.designation"
    );
}

function getTrajetsActifs(): array {
    return Database::fetchAll(
        "SELECT t.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee
         FROM trajets t
         JOIN agences ad ON t.agence_depart_id = ad.id
         JOIN agences aa ON t.agence_arrivee_id = aa.id
         WHERE t.actif = 1
         ORDER BY t.designation"
    );
}

function formatMontant(float $montant, string $devise = 'FCFA'): string {
    return number_format($montant, 0, ',', ' ') . ' ' . $devise;
}

function formatDate(?string $date, string $format = 'd/m/Y H:i'): string {
    if (!$date) return '-';
    return date($format, strtotime($date));
}

function statutBadge(string $statut): string {
    $map = [
        'enregistre'  => ['label' => 'Enregistré',    'class' => 'badge-info'],
        'en_transit'  => ['label' => 'En transit',    'class' => 'badge-warning'],
        'en_livraison'=> ['label' => 'En livraison',  'class' => 'badge-primary'],
        'livre'       => ['label' => 'Livré',         'class' => 'badge-success'],
        'retourne'    => ['label' => 'Retourné',      'class' => 'badge-secondary'],
        'perdu'       => ['label' => 'Perdu',         'class' => 'badge-danger'],
        'planifie'    => ['label' => 'Planifié',      'class' => 'badge-info'],
        'en_cours'    => ['label' => 'En cours',      'class' => 'badge-warning'],
        'arrive'      => ['label' => 'Arrivé',        'class' => 'badge-success'],
        'annule'      => ['label' => 'Annulé',        'class' => 'badge-danger'],
        'paye'        => ['label' => 'Payé',          'class' => 'badge-success'],
        'en_attente'  => ['label' => 'En attente',    'class' => 'badge-warning'],
        'partiel'     => ['label' => 'Partiel',       'class' => 'badge-info'],
        'normal'      => ['label' => 'Expédition',    'class' => 'badge-blue'],
        'accompagne'  => ['label' => 'Accompagné',    'class' => 'badge-purple'],
        'disponible'  => ['label' => 'Disponible',    'class' => 'badge-success'],
        'en_panne'    => ['label' => 'En panne',      'class' => 'badge-danger'],
        'actif'       => ['label' => 'Actif',          'class' => 'badge-success'],
        'inactif'     => ['label' => 'Inactif',        'class' => 'badge-secondary'],
    ];
    $info = $map[$statut] ?? ['label' => ucfirst($statut), 'class' => 'badge-secondary'];
    return "<span class=\"badge {$info['class']}\">{$info['label']}</span>";
}

function sanitize(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

function getAgences(): array {
    return Database::fetchAll("SELECT id, code, nom FROM agences WHERE actif=1 ORDER BY nom");
}

function getVoyagesOuverts(): array {
    return Database::fetchAll(
        "SELECT v.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee,
                vh.immatriculation, vh.designation AS vehicule_designation,
                t.designation AS trajet_designation, t.numero_trajet
         FROM voyages v
         JOIN agences ad ON v.agence_depart_id = ad.id
         JOIN agences aa ON v.agence_arrivee_id = aa.id
         LEFT JOIN vehicules vh ON v.vehicule_id = vh.id
         LEFT JOIN trajets t ON v.trajet_id = t.id
         WHERE v.statut IN ('planifie','en_cours')
         ORDER BY v.date_depart DESC"
    );
}

function statsGlobales(): array {
    return [
        'total_colis'      => Database::fetchOne("SELECT COUNT(*) c FROM colis")['c'],
        'colis_transit'    => Database::fetchOne("SELECT COUNT(*) c FROM colis WHERE statut='en_transit'")['c'],
        'colis_livres'     => Database::fetchOne("SELECT COUNT(*) c FROM colis WHERE statut='livre'")['c'],
        'total_voyages'    => Database::fetchOne("SELECT COUNT(*) c FROM voyages")['c'],
        'voyages_actifs'   => Database::fetchOne("SELECT COUNT(*) c FROM voyages WHERE statut IN ('planifie','en_cours')")['c'],
        'ca_jour'          => Database::fetchOne("SELECT COALESCE(SUM(montant_total),0) c FROM colis WHERE DATE(date_expedition)=CURDATE()")['c'],
        'ca_mois'          => Database::fetchOne("SELECT COALESCE(SUM(montant_total),0) c FROM colis WHERE MONTH(date_expedition)=MONTH(NOW()) AND YEAR(date_expedition)=YEAR(NOW())")['c'],
        'colis_non_payes'  => Database::fetchOne("SELECT COUNT(*) c FROM colis WHERE statut_paiement='en_attente'")['c'],
    ];
}

function transitionColisStatut(int $colisId, string $newStatut, string $localisation, string $commentaire, int $userId, bool $setDateLivre = false): void {
    $validTransitions = [
        'enregistre' => ['en_transit'],
        'en_transit' => ['en_livraison', 'enregistre'],
        'en_livraison' => ['livre', 'retourne'],
    ];
    $colis = Database::fetchOne("SELECT statut FROM colis WHERE id=?", [$colisId]);
    if (!$colis) return;
    $current = $colis['statut'];
    if ($newStatut === 'perdu') {
        // perdu depuis n'importe quel statut
    } elseif (!isset($validTransitions[$current]) || !in_array($newStatut, $validTransitions[$current])) {
        return;
    }
    Database::execute("UPDATE colis SET statut=? WHERE id=?", [$newStatut, $colisId]);
    if ($setDateLivre) {
        Database::execute("UPDATE colis SET date_livraison_reelle=NOW(), escale_actuelle_id=NULL WHERE id=?", [$colisId]);
    }
    Database::execute(
        "INSERT INTO suivi_colis (colis_id,statut,localisation,commentaire,cree_par) VALUES (?,?,?,?,?)",
        [$colisId, $newStatut, $localisation, $commentaire, $userId]
    );
}

function transitionVoyageStatut(int $voyageId, string $action, ?int $escaleId, int $userId): void {
    $voyage = Database::fetchOne("SELECT v.*, ad.nom AS nom_depart, aa.nom AS nom_arrivee FROM voyages v JOIN agences ad ON v.agence_depart_id=ad.id JOIN agences aa ON v.agence_arrivee_id=aa.id WHERE v.id=?", [$voyageId]);
    if (!$voyage) return;

    $numVoyage = $voyage['numero_voyage'];
    $colisList = Database::fetchAll("SELECT id, agence_arrivee_id, statut FROM colis WHERE voyage_id=? AND statut NOT IN ('livre','retourne','perdu')", [$voyageId]);

    if ($action === 'demarrer' && $voyage['statut'] === 'planifie') {
        Database::execute("UPDATE voyages SET statut='en_cours' WHERE id=?", [$voyageId]);
        foreach ($colisList as $c) {
            transitionColisStatut($c['id'], 'en_transit', $voyage['nom_depart'], "Voyage $numVoyage demarre", $userId);
        }
        Auth::logAction('VOYAGE_DEMARRE', 'voyages', $voyageId, $numVoyage);

    } elseif ($action === 'arrivee_escale' && $voyage['statut'] === 'en_cours' && $escaleId) {
        $escaleNom = Database::fetchOne("SELECT nom FROM agences WHERE id=?", [$escaleId])['nom'] ?? '';
        Database::execute("UPDATE voyages SET escale_actuelle_id=?, date_derniere_escale=NOW() WHERE id=?", [$escaleId, $voyageId]);
        foreach ($colisList as $c) {
            if ($c['agence_arrivee_id'] == $escaleId) {
                Database::execute("UPDATE colis SET statut='en_livraison', voyage_id=NULL, escale_actuelle_id=? WHERE id=?", [$escaleId, $c['id']]);
                transitionColisStatut($c['id'], 'en_livraison', $escaleNom, "Arrive a destination - $escaleNom (voyage $numVoyage)", $userId);
            } else {
                Database::execute(
                    "INSERT INTO suivi_colis (colis_id,statut,localisation,commentaire,cree_par) VALUES (?,?,?,?,?)",
                    [$c['id'], $c['statut'], $escaleNom, "Passage a l'escale $escaleNom", $userId]
                );
            }
        }
        Auth::logAction('VOYAGE_ESCALE', 'voyages', $voyageId, "Arrivee escale $escaleNom");

    } elseif ($action === 'arrivee_finale' && $voyage['statut'] === 'en_cours') {
        Database::execute("UPDATE voyages SET statut='arrive', escale_actuelle_id=?, date_derniere_escale=NOW() WHERE id=?", [$voyage['agence_arrivee_id'], $voyageId]);
        foreach ($colisList as $c) {
            if ($c['agence_arrivee_id'] == $voyage['agence_arrivee_id']) {
                Database::execute("UPDATE colis SET statut='en_livraison', voyage_id=NULL, escale_actuelle_id=? WHERE id=?", [$voyage['agence_arrivee_id'], $c['id']]);
                transitionColisStatut($c['id'], 'en_livraison', $voyage['nom_arrivee'], "Arrive a destination (voyage $numVoyage)", $userId);
            } else {
                Database::execute("UPDATE colis SET voyage_id=NULL, escale_actuelle_id=? WHERE id=?", [$voyage['agence_arrivee_id'], $c['id']]);
                Database::execute(
                    "INSERT INTO suivi_colis (colis_id,statut,localisation,commentaire,cree_par) VALUES (?,?,?,?,?)",
                    [$c['id'], 'en_transit', $voyage['nom_arrivee'], "Arrivee escale intermediaire $voyage[nom_arrivee] - en attente de transfert", $userId]
                );
            }
        }
        Auth::logAction('VOYAGE_ARRIVE', 'voyages', $voyageId, $numVoyage);

    } elseif ($action === 'annuler' && in_array($voyage['statut'], ['planifie', 'en_cours'])) {
        Database::execute("UPDATE voyages SET statut='annule' WHERE id=?", [$voyageId]);
        foreach ($colisList as $c) {
            if ($c['statut'] === 'en_transit') {
                Database::execute("UPDATE colis SET statut='enregistre', voyage_id=NULL, escale_actuelle_id=NULL WHERE id=?", [$c['id']]);
                transitionColisStatut($c['id'], 'enregistre', $voyage['nom_depart'], "Voyage $numVoyage annule", $userId);
            }
        }
        Auth::logAction('VOYAGE_ANNULE', 'voyages', $voyageId, $numVoyage);
    }
}
