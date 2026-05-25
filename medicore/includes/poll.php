<?php
// ============================================================
//  MediCore ERP — Endpoint AJAX de polling en temps réel
//  Retourne les compteurs de notifications et les dernières activités
//  Optionnellement les stats du dashboard si ?dashboard=1
// ============================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/permissions.php';

// Vérifier que l'utilisateur est connecté
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}

// Vérifier le CSRF token (envoyé en header)
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (empty($csrfSession) || !hash_equals($csrfSession, $csrfHeader)) {
    http_response_code(403);
    echo json_encode(['error' => 'Token CSRF invalide']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

try {
    $response = [
        'ts' => time(),
        'notifs' => [
            'critiques'   => 0,
            'stocks'      => 0,
            'ordonnances' => 0,
            'analyses'    => 0,
            'total'       => 0,
        ],
        'activities' => [],
    ];

    // Compteurs de notifications (filtrés par permissions)
    if (canAccessPage('urgences')) {
        $response['notifs']['critiques'] = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours' AND priorite='critique'");
    }
    if (canAccessPage('pharmacie')) {
        $response['notifs']['stocks']      = (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='critique'");
        $response['notifs']['ordonnances'] = (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'");
    }
    if (canAccessPage('laboratoire')) {
        $response['notifs']['analyses'] = (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='disponible'");
    }
    $response['notifs']['total'] = $response['notifs']['critiques']
                                 + $response['notifs']['stocks']
                                 + $response['notifs']['ordonnances']
                                 + $response['notifs']['analyses'];

    // Dernières activités (10 plus récentes)
    $logs = db_select(
        "SELECT a.id, a.action, a.entite, a.entite_id, a.couleur, a.date_action,
                CONCAT(u.prenom, ' ', u.nom) AS user_nom
         FROM activite_log a
         LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
         ORDER BY a.date_action DESC LIMIT 10"
    );

    $icones = ['green' => '✅', 'blue' => '📋', 'yellow' => '⚠️', 'red' => '🚨'];
    foreach ($logs as $log) {
        $response['activities'][] = [
            'id'      => (int)$log['id'],
            'action'  => mb_substr($log['action'], 0, 60),
            'icone'   => $icones[$log['couleur']] ?? '📌',
            'couleur' => $log['couleur'] ?? 'blue',
            'time'    => date('H:i', strtotime($log['date_action'])),
            'user'    => $log['user_nom'] ?? 'Système',
        ];
    }

    // Stats du dashboard (uniquement si demandé)
    if (isset($_GET['dashboard'])) {
        $today = date('Y-m-d');
        $mois  = (int)date('n');
        $annee = (int)date('Y');

        $response['dashboard'] = [
            'patients_actifs'  => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'"),
            'critiques'         => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours' AND priorite='critique'"),
            'lits_libres'       => (int)db_scalar("SELECT COUNT(*) FROM lits WHERE statut='libre'"),
            'rdv_aujourd_hui'   => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=?", [$today]),
            'ca_jour'           => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE DATE(date_vente)=? AND statut='paye'", [$today]),
            'urgences_actives'  => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'"),
        ];
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur serveur']);
}