<?php
/**
 * BrenFinance — Automatisation caisse
 *
 * Ouverture automatique le 1er de chaque mois
 * Clôture automatique le dernier jour du mois à 23h59
 *
 * Planification cron (Linux) :
 *   59 23 28-31 * * php /path/to/brenfinance/cron/caisse_auto.php
 *
 * Planification cron (Windows — Task Scheduler) :
 *   Déclencher le 28-31 de chaque mois à 23:59
 *   Commande : php C:\xampp\htdocs\brenfinance\cron\caisse_auto.php
 *
 * Le script vérifie lui-même s'il est le dernier jour du mois
 * ou le 1er, et agit en conséquence.
 */

// Sécurité : ne pas exécuter via le navigateur web
if (php_sapi_name() !== 'cli' && !defined('BRENFINANCE_CRON')) {
    http_response_code(403);
    die('Accès interdit.');
}

require_once __DIR__ . '/../includes/functions.php';

$db = getDB();
$now = new DateTime('now', new DateTimeZone('Africa/Douala'));
$jour = (int)$now->format('j');
$mois = (int)$now->format('n');
$annee = (int)$now->format('Y');
$dernierJourMois = (int)$now->format('t');

$log = function (string $msg) use ($now) {
    echo '[' . $now->format('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
};

// ── Clôture automatique : dernier jour du mois à 23h59 ──
if ($jour === $dernierJourMois) {
    $log('Clôture automatique des caisses — dernier jour du mois');

    // Trouver toutes les sessions ouvertes
    $sessions = $db->query("SELECT s.id, s.caisse_id, c.solde_actuel, c.libelle
                            FROM sessions_caisse s
                            JOIN caisses c ON s.caisse_id = c.id
                            WHERE s.statut = 'ouverte'")->fetchAll();

    if (empty($sessions)) {
        $log('Aucune session ouverte à clôturer.');
    }

    foreach ($sessions as $sess) {
        $soldeTheorique = (float)$sess['solde_actuel'];
        // Le solde de fermeture est le solde théorique (pas de comptage physique en automatique)
        $soldeFermeture = $soldeTheorique;
        $ecart = 0;

        $db->prepare("UPDATE sessions_caisse
                       SET date_fermeture = NOW(),
                           solde_fermeture = ?,
                           solde_theorique = ?,
                           ecart = ?,
                           observations = CONCAT(IFNULL(observations,''), 'Clôture automatique fin de mois'),
                           statut = 'fermee'
                       WHERE id = ?")
           ->execute([$soldeFermeture, $soldeTheorique, $ecart, $sess['id']]);

        $db->prepare("UPDATE caisses SET statut = 'fermee' WHERE id = ?")
           ->execute([$sess['caisse_id']]);

        auditLog('fermeture_auto_caisse', 'caisse', 'sessions_caisse', $sess['id'], null, [
            'caisse' => $sess['libelle'],
            'solde_fermeture' => $soldeFermeture,
            'motif' => 'Clôture automatique fin de mois'
        ]);

        $log("Caisse « {$sess['libelle']} » clôturée — solde : " . formatMontant($soldeFermeture));
    }
}

// ── Ouverture automatique : 1er de chaque mois ──
if ($jour === 1) {
    $log('Ouverture automatique des caisses — 1er du mois');

    // Récupérer toutes les caisses (même fermées, on les ouvre)
    $caisses = $db->query("SELECT c.id, c.libelle, c.solde_actuel, c.responsable_id
                           FROM caisses c")->fetchAll();

    foreach ($caisses as $c) {
        // Vérifier qu'il n'y a pas déjà une session ouverte
        $existing = $db->prepare("SELECT id FROM sessions_caisse WHERE caisse_id = ? AND statut = 'ouverte'");
        $existing->execute([$c['id']]);
        if ($existing->fetch()) {
            $log("Caisse « {$c['libelle']} » déjà ouverte — ignorée.");
            continue;
        }

        // Trouver un utilisateur système ou le responsable de la caisse
        $userId = $c['responsable_id'];
        if (!$userId) {
            // Prendre le premier super_admin
            $adminR = $db->query("SELECT u.id FROM utilisateurs u JOIN roles r ON u.role_id = r.id WHERE r.permissions LIKE '%\"all\":true%' OR r.nom = 'super_admin' LIMIT 1");
            $admin = $adminR->fetch();
            $userId = $admin ? (int)$admin['id'] : 1;
        }

        $soldeOuverture = (float)$c['solde_actuel'];

        $db->prepare("INSERT INTO sessions_caisse (caisse_id, utilisateur_id, solde_ouverture, date_ouverture, statut)
                       VALUES (?, ?, ?, NOW(), 'ouverte')")
           ->execute([$c['id'], $userId, $soldeOuverture]);

        $db->prepare("UPDATE caisses SET statut = 'ouverte' WHERE id = ?")
           ->execute([$c['id']]);

        auditLog('ouverture_auto_caisse', 'caisse', 'caisses', $c['id'], null, [
            'caisse' => $c['libelle'],
            'solde_ouverture' => $soldeOuverture,
            'motif' => 'Ouverture automatique début de mois'
        ]);

        $log("Caisse « {$c['libelle']} » ouverte — solde d'ouverture : " . formatMontant($soldeOuverture));
    }
}

// ── Nettoyage : s'assurer qu'aucune caisse n'est dans un état incohérent ──
// Si on est entre le 2 et l'avant-dernier jour, vérifier que les caisses sont bien ouvertes
if ($jour > 1 && $jour < $dernierJourMois) {
    $fermees = $db->query("SELECT c.id, c.libelle FROM caisses c WHERE c.statut = 'fermee'")->fetchAll();
    foreach ($fermees as $c) {
        // Vérifier s'il existe une session ouverte
        $sess = $db->prepare("SELECT id FROM sessions_caisse WHERE caisse_id = ? AND statut = 'ouverte'");
        $sess->execute([$c['id']]);
        if (!$sess->fetch()) {
            $log("Caisse « {$c['libelle']} » fermée en cours de mois sans session ouverte — à vérifier manuellement.");
        }
    }
}

$log('Terminé.');