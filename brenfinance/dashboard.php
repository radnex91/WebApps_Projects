<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'Tableau de bord';

$db = getDB();
$user = currentUser();
$userId = (int)($_SESSION['user_id'] ?? 0);
$role = $user['role_nom'] ?? 'demandeur';

$seeCaisse     = canSeeModule('caisse') || canSeeModule('operations_caisse');
$seeTresorerie = canSeeModule('tresorerie');
$seeEngagements = canSeeModule('engagements');
$seeBudget     = canSeeModule('budget');
$seeMissions   = canSeeModule('ordre_mission');
$seeCompta     = canSeeModule('comptabilite');
$seeAudit      = canSeeModule('audit');

/* ═══ Shared queries ═══════════════════════════════════════════ */
$soldesCaisses = $seeCaisse ? $db->query("SELECT COALESCE(SUM(solde_actuel),0) as total FROM caisses WHERE statut != 'suspendue'")->fetch()['total'] : 0;
$nbCaisses     = $seeCaisse ? $db->query("SELECT COUNT(*) as n FROM caisses WHERE statut='ouverte'")->fetch()['n'] : 0;
$soldesBanque  = $seeTresorerie ? $db->query("SELECT COALESCE(SUM(solde_actuel),0) as total FROM comptes_bancaires WHERE statut='actif'")->fetch()['total'] : 0;
$opsJour       = $seeCaisse ? $db->query("SELECT COUNT(*) as n, COALESCE(SUM(CASE WHEN sens='credit' THEN montant ELSE 0 END),0) as entrees, COALESCE(SUM(CASE WHEN sens='debit' THEN montant ELSE 0 END),0) as sorties FROM operations_caisse WHERE date_operation = CURDATE() AND annule=0")->fetch() : ['n'=>0,'entrees'=>0,'sorties'=>0];
$chartData     = $seeCaisse ? $db->query("SELECT DATE_FORMAT(date_operation,'%Y-%m') as mois, SUM(CASE WHEN sens='credit' THEN montant ELSE 0 END) as entrees, SUM(CASE WHEN sens='debit' THEN montant ELSE 0 END) as sorties FROM operations_caisse WHERE date_operation >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) AND annule=0 GROUP BY DATE_FORMAT(date_operation,'%Y-%m') ORDER BY mois")->fetchAll() : [];
$budgetData    = $seeBudget ? $db->query("SELECT lb.libelle, lb.montant_prevu, lb.montant_realise, lb.seuil_alerte FROM lignes_budget lb JOIN budgets b ON lb.budget_id=b.id WHERE b.statut='valide' ORDER BY (lb.montant_realise/NULLIF(lb.montant_prevu,0)) DESC LIMIT 5")->fetchAll() : [];

/* ═══ Role-specific data ══════════════════════════════════════ */
$kpiCards     = [];
$recentItems  = [];
$recentTitle  = '';
$recentLink   = '';
$extraSections = [];
$pendingMissions = 0;
$roleBanner   = '';

switch ($role) {

/* ─── SUPER ADMIN ──────────────────────────────────────────── */
case 'super_admin':
    $pendingEng = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut IN ('soumis','valide_hierarchie','valide_comptable','valide_daf')")->fetchColumn() : 0;
    $pendingMissions = $seeMissions ? (int)$db->query("SELECT COUNT(*) FROM ordres_mission WHERE statut IN ('soumis','valide_hierarchie','approuve')")->fetchColumn() : 0;
    $totalUsers = (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE statut='actif'")->fetchColumn();
    $auditToday = (int)$db->query("SELECT COUNT(*) FROM journal_audit WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $engApprouves = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut IN ('approuve','execution_partielle')")->fetchColumn() : 0;

    $kpiCards = [
        ['icon'=>'fa-solid fa-coins',          'label'=>'Solde caisses',       'value'=>formatMontant($soldesCaisses),  'sub'=>$nbCaisses.' ouverte(s)',        'mod'=>''],
        ['icon'=>'fa-solid fa-building-columns','label'=>'Solde bancaire',      'value'=>formatMontant($soldesBanque),   'sub'=>'Tous comptes actifs',           'mod'=>'info'],
        ['icon'=>'fa-solid fa-hourglass-half',   'label'=>'En attente',         'value'=>$pendingEng,                    'sub'=>'Tous statuts confondus',        'mod'=>'warning'],
        ['icon'=>'fa-solid fa-check-double',    'label'=>'Approuvés',           'value'=>$engApprouves,                  'sub'=>'À exécuter en caisse',          'mod'=>''],
        ['icon'=>'fa-solid fa-users',           'label'=>'Utilisateurs actifs', 'value'=>$totalUsers,                    'sub'=>'Comptes actifs',                'mod'=>'info'],
        ['icon'=>'fa-solid fa-shield-halved',   'label'=>'Audits aujourd\'hui', 'value'=>$auditToday,                    'sub'=>'Actions tracées',               'mod'=>'danger'],
    ];

    $recentItems = $seeAudit ? $db->query("SELECT ja.*, CONCAT(u.nom,' ',u.prenom) as user_nom FROM journal_audit ja LEFT JOIN utilisateurs u ON ja.utilisateur_id=u.id ORDER BY ja.created_at DESC LIMIT 10")->fetchAll() : [];
    $recentTitle = 'Journal d\'audit récent';
    $recentLink  = BASE_URL.'/modules/audit/index.php';

    if ($seeEngagements) {
        $extraSections[] = [
            'title'=>'Derniers engagements',
            'link'=>BASE_URL.'/modules/engagements/index.php',
            'cols'=>['Numéro','Objet','Statut','Montant','Demandeur'],
            'data'=>$db->query("SELECT de.numero, de.objet, de.statut, de.montant, de.created_at, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id ORDER BY de.created_at DESC LIMIT 5")->fetchAll()
        ];
    }
    if ($seeMissions) {
        $extraSections[] = [
            'title'=>'Dernières missions',
            'link'=>BASE_URL.'/modules/ordre_mission/index.php',
            'cols'=>['Numéro','Objet','Statut','Montant','Demandeur'],
            'data'=>$db->query("SELECT om.numero, om.objet, om.statut, om.montant, om.created_at, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM ordres_mission om JOIN utilisateurs u ON om.demandeur_id=u.id ORDER BY om.created_at DESC LIMIT 5")->fetchAll()
        ];
    }
    break;

/* ─── DAF ──────────────────────────────────────────────────── */
case 'daf':
    $pendingEng = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut='valide_comptable'")->fetchColumn() : 0;
    $pendingMissions = $seeMissions ? (int)$db->query("SELECT COUNT(*) FROM ordres_mission WHERE statut='valide_hierarchie'")->fetchColumn() : 0;
    $totalTresorerie = $soldesCaisses + $soldesBanque;
    $engApprouves = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut IN ('approuve','execution_partielle')")->fetchColumn() : 0;
    $budgetGlobal = $seeBudget ? $db->query("SELECT SUM(montant_prevu) as prevu, SUM(montant_realise) as realise FROM lignes_budget lb JOIN budgets b ON lb.budget_id=b.id WHERE b.statut='valide'")->fetch() : null;
    $budgetPct = ($budgetGlobal && $budgetGlobal['prevu'] > 0) ? round(($budgetGlobal['realise']/$budgetGlobal['prevu'])*100, 1) : 0;

    $kpiCards = [
        ['icon'=>'fa-solid fa-sack-dollar',   'label'=>'Trésorerie totale',  'value'=>formatMontant($totalTresorerie), 'sub'=>'Caisses + Banques',             'mod'=>''],
        ['icon'=>'fa-solid fa-hourglass-half', 'label'=>'Mes validations',     'value'=>$pendingEng,                    'sub'=>'Engagements',                   'mod'=>'warning'],
        ['icon'=>'fa-solid fa-check-double',  'label'=>'Approuvés',           'value'=>$engApprouves,                  'sub'=>'À exécuter en caisse',          'mod'=>'info'],
        ['icon'=>'fa-solid fa-chart-simple',  'label'=>'Budget consommé',     'value'=>$budgetPct.'%',                 'sub'=>'Global',                        'mod'=>'info'],
        ['icon'=>'fa-solid fa-paper-plane',   'label'=>'Missions en attente', 'value'=>$pendingMissions,               'sub'=>'Validation DAF',                'mod'=>'warning'],
    ];

    if ($seeEngagements || $seeMissions) {
        $merged = [];
        if ($seeEngagements) {
            $merged = array_merge($merged, $db->query("SELECT 'engagement' as type, de.numero, de.objet, de.statut, de.montant, de.created_at, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id WHERE de.statut IN ('valide_comptable','valide_daf','approuve') ORDER BY de.created_at DESC LIMIT 5")->fetchAll());
        }
        if ($seeMissions) {
            $merged = array_merge($merged, $db->query("SELECT 'mission' as type, om.numero, om.objet, om.statut, om.montant, om.created_at, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM ordres_mission om JOIN utilisateurs u ON om.demandeur_id=u.id WHERE om.statut IN ('valide_hierarchie','approuve') ORDER BY om.created_at DESC LIMIT 5")->fetchAll());
        }
        usort($merged, fn($a,$b) => strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? ''));
        $recentItems = array_slice($merged, 0, 8);
        $recentTitle = 'Activité récente';
        $recentLink  = BASE_URL.'/modules/engagements/index.php';
    }
    break;

/* ─── COMPTABLE ────────────────────────────────────────────── */
case 'comptable':
    $pendingEng = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut='valide_hierarchie'")->fetchColumn() : 0;
    $totalTresorerie = $soldesCaisses + $soldesBanque;
    $ecrituresJour = $seeCompta ? (int)$db->query("SELECT COUNT(*) FROM ecritures_comptables WHERE date_ecriture=CURDATE()")->fetchColumn() : 0;

    $kpiCards = [
        ['icon'=>'fa-solid fa-sack-dollar',   'label'=>'Trésorerie totale', 'value'=>formatMontant($totalTresorerie), 'sub'=>'Caisses + Banques',    'mod'=>''],
        ['icon'=>'fa-solid fa-hourglass-half', 'label'=>'À valider',         'value'=>$pendingEng,                    'sub'=>'Validation comptable',  'mod'=>'warning'],
        ['icon'=>'fa-solid fa-book-open',     'label'=>'Écritures du jour', 'value'=>$ecrituresJour,                  'sub'=>'Saisies aujourd\'hui',  'mod'=>'info'],
        ['icon'=>'fa-solid fa-arrow-right-arrow-left', 'label'=>'Opérations caisse', 'value'=>$opsJour['n'],       'sub'=>'Aujourd\'hui',          'mod'=>''],
    ];

    if ($seeEngagements) {
        $recentItems = $db->query("SELECT de.numero, de.objet, de.statut, de.montant, de.created_at, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id WHERE de.statut='valide_hierarchie' ORDER BY de.created_at DESC LIMIT 8")->fetchAll();
        $recentTitle = 'Engagements en attente de validation comptable';
        $recentLink  = BASE_URL.'/modules/engagements/index.php?statut=valide_hierarchie';
    }
    break;

/* ─── CAISSIER ─────────────────────────────────────────────── */
case 'caissier':
    $myCaisseIds = $seeCaisse ? $db->query("SELECT id FROM caisses WHERE responsable_id = $userId")->fetchAll(PDO::FETCH_COLUMN) : [];
    $myCaisseIdsStr = !empty($myCaisseIds) ? implode(',', $myCaisseIds) : '0';

    $mySolde = $seeCaisse ? (float)$db->query("SELECT COALESCE(SUM(solde_actuel),0) FROM caisses WHERE id IN ($myCaisseIdsStr) AND statut != 'suspendue'")->fetchColumn() : 0;
    $myNbCaisses = count($myCaisseIds);

    $myOpsJour = $seeCaisse ? $db->query("SELECT COUNT(*) as n, COALESCE(SUM(CASE WHEN sens='credit' THEN montant ELSE 0 END),0) as entrees, COALESCE(SUM(CASE WHEN sens='debit' THEN montant ELSE 0 END),0) as sorties FROM operations_caisse WHERE caisse_id IN ($myCaisseIdsStr) AND date_operation=CURDATE() AND annule=0")->fetch() : ['n'=>0,'entrees'=>0,'sorties'=>0];

    $pendingEng = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut IN ('approuve','execution_partielle') AND caisse_id IN ($myCaisseIdsStr)")->fetchColumn() : 0;
    $pendingMissions = $seeMissions ? (int)$db->query("SELECT COUNT(*) FROM ordres_mission WHERE statut='approuve' AND caisse_id IN ($myCaisseIdsStr)")->fetchColumn() : 0;

    $kpiCards = [
        ['icon'=>'fa-solid fa-coins',          'label'=>'Solde de mes caisses', 'value'=>formatMontant($mySolde), 'sub'=>$myNbCaisses.' caisse(s)',          'mod'=>''],
        ['icon'=>'fa-solid fa-hourglass-half', 'label'=>'À exécuter',            'value'=>$pendingEng,             'sub'=>'Engagements approuvés',             'mod'=>'warning'],
        ['icon'=>'fa-solid fa-paper-plane',   'label'=>'Missions à exécuter',   'value'=>$pendingMissions,        'sub'=>'Ordres de mission',                 'mod'=>'info'],
        ['icon'=>'fa-solid fa-arrow-right-arrow-left', 'label'=>'Mes opérations du jour', 'value'=>$myOpsJour['n'], 'sub'=>'Aujourd\'hui',                 'mod'=>''],
    ];

    if ($seeCaisse) {
        $recentItems = $db->query("SELECT oc.*, c.libelle as caisse_nom, to2.libelle as type_op FROM operations_caisse oc JOIN caisses c ON oc.caisse_id=c.id JOIN types_operations to2 ON oc.type_operation_id=to2.id WHERE oc.caisse_id IN ($myCaisseIdsStr) ORDER BY oc.created_at DESC LIMIT 8")->fetchAll();
        $recentTitle = 'Mes dernières opérations';
        $recentLink  = BASE_URL.'/modules/operations_caisse/index.php';
    }
    break;

/* ─── DEMANDEUR ────────────────────────────────────────────── */
case 'demandeur':
    $mySoumis    = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE demandeur_id=$userId AND statut='soumis'")->fetchColumn() : 0;
    $myEnCours   = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE demandeur_id=$userId AND statut IN ('valide_hierarchie','valide_comptable','valide_daf')")->fetchColumn() : 0;
    $myApprouves = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE demandeur_id=$userId AND statut IN ('approuve','execution_partielle','execute','solde')")->fetchColumn() : 0;
    $myRejetes   = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE demandeur_id=$userId AND statut IN ('rejete','renvoye')")->fetchColumn() : 0;
    $pendingMissions = $seeMissions ? (int)$db->query("SELECT COUNT(*) FROM ordres_mission WHERE demandeur_id=$userId AND statut IN ('soumis','valide_hierarchie')")->fetchColumn() : 0;

    $kpiCards = [
        ['icon'=>'fa-solid fa-paper-plane', 'label'=>'Soumises',    'value'=>$mySoumis,    'sub'=>'En attente de N+1',  'mod'=>'info'],
        ['icon'=>'fa-solid fa-spinner',     'label'=>'En cours',    'value'=>$myEnCours,   'sub'=>'En validation',      'mod'=>'warning'],
        ['icon'=>'fa-solid fa-check-double','label'=>'Approuvées',  'value'=>$myApprouves, 'sub'=>'Exécutées / soldées', 'mod'=>''],
        ['icon'=>'fa-solid fa-ban',         'label'=>'Rejetées/Renvoyées','value'=>$myRejetes,'sub'=>'À corriger',        'mod'=>'danger'],
        ['icon'=>'fa-solid fa-paper-plane', 'label'=>'Missions en attente','value'=>$pendingMissions,'sub'=>'Validation en cours','mod'=>'info'],
    ];

    if ($seeEngagements) {
        $recentItems = $db->query("SELECT de.numero, de.objet, de.statut, de.montant, de.created_at FROM demandes_engagement de WHERE de.demandeur_id=$userId ORDER BY de.created_at DESC LIMIT 8")->fetchAll();
        $recentTitle = 'Mes dernières demandes';
        $recentLink  = BASE_URL.'/modules/engagements/index.php';
    }
    break;

/* ─── VALIDEUR N+1 ──────────────────────────────────────────── */
case 'valideur_n1':
    $myServiceIds = $db->query("SELECT id FROM services WHERE responsable_id = $userId")->fetchAll(PDO::FETCH_COLUMN);
    $myServiceIdsStr = !empty($myServiceIds) ? implode(',', $myServiceIds) : '0';
    $myServiceNames = !empty($myServiceIds) ? $db->query("SELECT nom FROM services WHERE id IN ($myServiceIdsStr)")->fetchAll(PDO::FETCH_COLUMN) : [];

    $pendingEng = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE statut='soumis' AND service_id IN ($myServiceIdsStr)")->fetchColumn() : 0;
    $pendingMissions = $seeMissions ? (int)$db->query("SELECT COUNT(*) FROM ordres_mission WHERE statut='soumis' AND service_id IN ($myServiceIdsStr)")->fetchColumn() : 0;
    $teamEnCours   = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE service_id IN ($myServiceIdsStr) AND statut IN ('valide_hierarchie','valide_comptable','valide_daf')")->fetchColumn() : 0;
    $teamApprouves = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE service_id IN ($myServiceIdsStr) AND statut IN ('approuve','execution_partielle','execute','solde')")->fetchColumn() : 0;

    $kpiCards = [
        ['icon'=>'fa-solid fa-hourglass-half', 'label'=>'À valider',        'value'=>$pendingEng,       'sub'=>'Engagements de mon service', 'mod'=>'warning'],
        ['icon'=>'fa-solid fa-paper-plane',   'label'=>'Missions à valider','value'=>$pendingMissions,  'sub'=>'Ordres de mission',           'mod'=>'info'],
        ['icon'=>'fa-solid fa-spinner',        'label'=>'En cours',          'value'=>$teamEnCours,      'sub'=>'En validation externe',        'mod'=>'info'],
        ['icon'=>'fa-solid fa-check-double',   'label'=>'Finalisés',         'value'=>$teamApprouves,    'sub'=>'Approuvés / exécutés',         'mod'=>''],
    ];

    if (!empty($myServiceNames)) {
        $roleBanner = '<div class="role-banner"><i class="fa-solid fa-building"></i> Service(s) : <strong>'.implode(', ', $myServiceNames).'</strong></div>';
    }

    if ($seeEngagements) {
        $recentItems = $db->query("SELECT de.numero, de.objet, de.statut, de.montant, de.created_at, CONCAT(u.nom,' ',u.prenom) as demandeur_nom FROM demandes_engagement de JOIN utilisateurs u ON de.demandeur_id=u.id WHERE de.statut='soumis' AND de.service_id IN ($myServiceIdsStr) ORDER BY de.created_at DESC LIMIT 8")->fetchAll();
        $recentTitle = 'Demandes de mon service en attente de validation';
        $recentLink  = BASE_URL.'/modules/engagements/index.php?statut=soumis';
    }
    break;

/* ─── DEFAULT (fallback → demandeur) ───────────────────────── */
default:
    $mySoumis  = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE demandeur_id=$userId AND statut='soumis'")->fetchColumn() : 0;
    $myEnCours = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE demandeur_id=$userId AND statut IN ('valide_hierarchie','valide_comptable','valide_daf')")->fetchColumn() : 0;
    $myApprouves = $seeEngagements ? (int)$db->query("SELECT COUNT(*) FROM demandes_engagement WHERE demandeur_id=$userId AND statut IN ('approuve','execution_partielle','execute','solde')")->fetchColumn() : 0;
    $kpiCards = [
        ['icon'=>'fa-solid fa-paper-plane', 'label'=>'Soumises', 'value'=>$mySoumis, 'sub'=>'En attente', 'mod'=>'info'],
        ['icon'=>'fa-solid fa-spinner',     'label'=>'En cours', 'value'=>$myEnCours,'sub'=>'Validation', 'mod'=>'warning'],
        ['icon'=>'fa-solid fa-check-double','label'=>'Approuvées','value'=>$myApprouves,'sub'=>'Finalisées','mod'=>''],
    ];
    $recentTitle = 'Mes demandes';
    $recentLink  = BASE_URL.'/modules/engagements/index.php';
    break;
}

/* ═══ Shared badge/label maps ═════════════════════════════════ */
$badges = ['brouillon'=>'gray','soumis'=>'info','valide_hierarchie'=>'purple','valide_comptable'=>'purple','valide_daf'=>'teal','approuve'=>'teal','execution_partielle'=>'info','execute'=>'success','solde'=>'warning','rejete'=>'danger','renvoye'=>'warning','annule'=>'danger'];
$labels = ['brouillon'=>'Brouillon','soumis'=>'Soumis','valide_hierarchie'=>'Val. N1','valide_comptable'=>'Val. Compta','valide_daf'=>'Val. DAF','approuve'=>'Approuvé','execution_partielle'=>'Exéc. part.','execute'=>'Exécuté','solde'=>'Soldé','rejete'=>'Rejeté','renvoye'=>'Renvoyé','annule'=>'Annulé'];

include __DIR__ . '/includes/header.php';
?>

<?php /* ═══ Role banner ═══ */ ?>
<?= $roleBanner ?>

<?php /* ═══ KPI Cards (data-driven) ═══ */ ?>
<?php if (!empty($kpiCards)): ?>
<div class="stats-grid">
  <?php foreach ($kpiCards as $card): ?>
  <div class="stat-card <?= $card['mod'] ?>">
    <div class="stat-icon"><i class="<?= $card['icon'] ?>"></i></div>
    <div class="stat-label"><?= htmlspecialchars($card['label']) ?></div>
    <div class="stat-value"><?= $card['value'] ?></div>
    <div class="stat-sub"><?= htmlspecialchars($card['sub']) ?></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php /* ═══ Quick-create buttons (demandeur) ═══ */ ?>
<?php if ($role === 'demandeur'): ?>
<div class="card mb-20">
  <div class="card-header"><span class="card-title">Actions rapides</span></div>
  <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap">
    <?php if (hasPermission('engagements','creer')): ?>
    <a href="<?= BASE_URL ?>/modules/engagements/creer.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Nouvelle demande d'engagement</a>
    <?php endif; ?>
    <?php if (hasPermission('ordre_mission','creer')): ?>
    <a href="<?= BASE_URL ?>/modules/ordre_mission/creer.php" class="btn btn-outline"><i class="fa-solid fa-paper-plane"></i> Nouvel ordre de mission</a>
    <?php endif; ?>
    <?php if (hasPermission('decharge','creer')): ?>
    <a href="<?= BASE_URL ?>/modules/decharge/index.php" class="btn btn-outline"><i class="fa-solid fa-receipt"></i> Nouvelle décharge</a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<?php /* ═══ Cash Flow Chart ═══ */ ?>
<?php if ($seeCaisse && in_array($role, ['super_admin','daf','comptable','caissier'])): ?>
<div class="card mb-20" style="overflow:hidden">
  <div class="card-header" style="border-bottom:1px solid var(--border)">
    <span class="card-title"><i class="fa-solid fa-chart-bar"></i> Flux de caisse — Chandelier</span>
    <span style="font-size:11.5px;color:var(--text3);margin-left:auto">6 derniers mois</span>
  </div>
  <div id="chart-wrap" style="position:relative;height:360px;cursor:crosshair">
    <canvas id="chart-flux" style="display:block"></canvas>
    <div id="chart-crosshair-x" style="display:none;position:absolute;top:0;bottom:0;width:1px;background:rgba(100,116,139,.25);pointer-events:none;z-index:3"></div>
    <div id="chart-crosshair-y" style="display:none;position:absolute;left:0;right:0;height:1px;background:rgba(100,116,139,.25);pointer-events:none;z-index:3"></div>
    <div id="chart-price-tag" style="display:none;position:absolute;right:0;padding:2px 8px;font-size:10.5px;font-weight:600;border-radius:3px 0 0 3px;pointer-events:none;z-index:4;transform:translateY(-50%)"></div>
    <div id="chart-tooltip" style="display:none;position:absolute;pointer-events:none;z-index:10;border-radius:8px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.18);min-width:170px"></div>
  </div>
</div>
<?php endif; ?>

<?php /* ═══ Recent items table ═══ */ ?>
<?php if (!empty($recentItems)): ?>
<div class="card mb-20">
  <div class="card-header">
    <span class="card-title"><?= htmlspecialchars($recentTitle) ?></span>
    <?php if ($recentLink): ?>
    <a href="<?= $recentLink ?>" class="btn btn-outline btn-sm ml-auto">Voir tout</a>
    <?php endif; ?>
  </div>

  <?php /* Caissier: operations table */ ?>
  <?php if ($role === 'caissier'): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Libellé</th>
          <th class="hide-mobile">Caisse</th>
          <th>Montant</th>
          <th class="hide-mobile">Solde après</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentItems as $op): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('d/m H:i', strtotime($op['created_at'])) ?></td>
          <td class="truncate" style="max-width:150px" title="<?= htmlspecialchars($op['libelle']) ?>"><?= htmlspecialchars($op['libelle']) ?></td>
          <td class="hide-mobile"><?= htmlspecialchars($op['caisse_nom']) ?></td>
          <td class="amount <?= $op['sens']==='credit'?'amount-credit':'amount-debit' ?>" style="white-space:nowrap">
            <?= $op['sens']==='credit'?'+':'-' ?><?= number_format($op['montant'],0,',',' ') ?>
          </td>
          <td class="amount hide-mobile"><?= number_format($op['solde_apres'],0,',',' ') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php /* Audit table (super_admin) */ ?>
  <?php elseif ($role === 'super_admin'): ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date</th><th>Utilisateur</th><th>Action</th><th class="hide-mobile">Module</th><th class="hide-mobile">Table</th></tr>
      </thead>
      <tbody>
        <?php foreach ($recentItems as $a): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('d/m H:i', strtotime($a['created_at'])) ?></td>
          <td><?= htmlspecialchars($a['user_nom'] ?? 'Système') ?></td>
          <td class="truncate" style="max-width:160px" title="<?= htmlspecialchars($a['action']) ?>"><?= htmlspecialchars($a['action']) ?></td>
          <td class="hide-mobile"><?= htmlspecialchars($a['module'] ?? '') ?></td>
          <td class="hide-mobile"><?= htmlspecialchars($a['table_name'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php /* Generic: engagements / missions / mixed */ ?>
  <?php else: ?>
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Référence</th>
          <th>Objet</th>
          <th>Statut</th>
          <th>Montant</th>
          <th class="hide-mobile">Demandeur</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($recentItems as $item): ?>
        <tr>
          <td style="white-space:nowrap"><?= date('d/m H:i', strtotime($item['created_at'])) ?></td>
          <td style="white-space:nowrap;font-weight:600"><?= htmlspecialchars($item['numero'] ?? '—') ?></td>
          <td class="truncate" style="max-width:180px" title="<?= htmlspecialchars($item['objet'] ?? '') ?>"><?= htmlspecialchars($item['objet'] ?? '—') ?></td>
          <td><span class="badge badge-<?= $badges[$item['statut']] ?? 'gray' ?>"><?= htmlspecialchars($labels[$item['statut']] ?? $item['statut']) ?></span></td>
          <td class="amount"><?= formatMontant($item['montant'] ?? 0) ?></td>
          <td class="hide-mobile"><?= htmlspecialchars($item['demandeur_nom'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ═══ Extra sections (super_admin) ═══ */ ?>
<?php if ($role === 'super_admin' && !empty($extraSections)): ?>
  <?php foreach ($extraSections as $section): ?>
  <div class="card mb-20">
    <div class="card-header">
      <span class="card-title"><?= $section['title'] ?></span>
      <a href="<?= $section['link'] ?>" class="btn btn-outline btn-sm ml-auto">Voir tout</a>
    </div>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <?php foreach ($section['cols'] as $col): ?>
            <th><?= $col ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($section['data'] as $row): ?>
          <tr>
            <td style="white-space:nowrap"><?= date('d/m H:i', strtotime($row['created_at'])) ?></td>
            <td style="white-space:nowrap;font-weight:600"><?= htmlspecialchars($row['numero']) ?></td>
            <td class="truncate" style="max-width:160px" title="<?= htmlspecialchars($row['objet']) ?>"><?= htmlspecialchars($row['objet']) ?></td>
            <td><span class="badge badge-<?= $badges[$row['statut']] ?? 'gray' ?>"><?= htmlspecialchars($labels[$row['statut']] ?? $row['statut']) ?></span></td>
            <td class="amount"><?= formatMontant($row['montant'] ?? 0) ?></td>
            <td class="hide-mobile"><?= htmlspecialchars($row['demandeur_nom'] ?? '') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php /* ═══ Budget consumption ═══ */ ?>
<?php if ($seeBudget && !empty($budgetData) && in_array($role, ['super_admin','daf','comptable'])): ?>
<div class="card mb-20">
  <div class="card-header">
    <span class="card-title">Consommation budgétaire</span>
    <a href="<?= BASE_URL ?>/modules/budget/index.php" class="btn btn-outline btn-sm ml-auto">Gérer</a>
  </div>
  <div class="card-body">
    <?php foreach($budgetData as $b):
      $pct = $b['montant_prevu'] > 0 ? round(($b['montant_realise']/$b['montant_prevu'])*100) : 0;
      $cls = $pct >= 100 ? 'danger' : ($pct >= $b['seuil_alerte'] ? 'warning' : '');
    ?>
    <div style="margin-bottom:14px">
      <div class="d-flex justify-between align-center mb-0" style="margin-bottom:4px">
        <span style="font-size:13px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;margin-right:8px"><?= htmlspecialchars($b['libelle']) ?></span>
        <span style="font-size:12px;font-weight:700;flex-shrink:0;color:<?= $cls==='danger'?'var(--danger)':($cls==='warning'?'var(--warning)':'var(--text2)') ?>"><?= $pct ?>%</span>
      </div>
      <div class="progress"><div class="progress-bar <?= $cls ?>" style="width:<?= min($pct,100) ?>%"></div></div>
      <div style="font-size:11.5px;color:var(--text3);margin-top:3px"><?= formatMontant($b['montant_realise']) ?> / <?= formatMontant($b['montant_prevu']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php /* ═══ Quick Access (mobile) ═══ */ ?>
<?php
$shortcuts = [];
if (canSeeModule('caisse')) $shortcuts[] = [BASE_URL.'/modules/caisse/index.php', '<i class="fa-solid fa-coins"></i>', 'Caisse'];
if (canSeeModule('tresorerie')) $shortcuts[] = [BASE_URL.'/modules/tresorerie/index.php', '<i class="fa-solid fa-building-columns"></i>', 'Banque'];
if (canSeeModule('engagements')) $shortcuts[] = [BASE_URL.'/modules/engagements/index.php', '<i class="fa-solid fa-hourglass-half"></i>', 'Demandes'];
if (canSeeModule('comptabilite')) $shortcuts[] = [BASE_URL.'/modules/comptabilite/index.php', '<i class="fa-solid fa-book-open"></i>', 'Compta'];
if (canSeeModule('budget')) $shortcuts[] = [BASE_URL.'/modules/budget/index.php', '<i class="fa-solid fa-gem"></i>', 'Budget'];
if (canSeeModule('reporting')) $shortcuts[] = [BASE_URL.'/modules/reporting/index.php', '<i class="fa-solid fa-chart-pie"></i>', 'Rapports'];
	if (canSeeModule('paie')) $shortcuts[] = [BASE_URL.'/modules/paie/index.php', '<i class="fa-solid fa-sack-dollar"></i>', 'Paie'];
	if (canSeeModule('bons_commande')) $shortcuts[] = [BASE_URL.'/modules/bons_commande/index.php', '<i class="fa-solid fa-file-invoice"></i>', 'Commandes'];
	if (canSeeModule('cloture')) $shortcuts[] = [BASE_URL.'/modules/cloture/index.php', '<i class="fa-solid fa-door-closed"></i>', 'Clôture'];
if ($role === 'demandeur' && hasPermission('engagements','creer')) $shortcuts[] = [BASE_URL.'/modules/engagements/creer.php', '<i class="fa-solid fa-plus-circle"></i>', 'Créer'];
?>
<?php if (!empty($shortcuts)): ?>
<div class="card" style="display:none" id="quick-access">
  <div class="card-header"><span class="card-title">Accès rapide</span></div>
  <div class="card-body" style="padding:12px">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px">
      <?php foreach($shortcuts as [$href,$icon,$label]): ?>
      <a href="<?= $href ?>" style="display:flex;flex-direction:column;align-items:center;padding:14px 8px;border:1px solid var(--border);border-radius:var(--radius);text-decoration:none;color:var(--text2);font-size:12px;font-weight:600;gap:6px;transition:background var(--transition),border-color var(--transition)" onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
        <span style="font-size:22px;color:var(--primary)"><?= $icon ?></span>
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var qa = document.getElementById('quick-access');
  function checkMobile() { if (qa) qa.style.display = window.innerWidth <= 768 ? 'block' : 'none'; }
  checkMobile();
  window.addEventListener('resize', checkMobile);

  var labels = <?= json_encode(array_map(fn($r) => date('M Y', strtotime($r['mois'].'-01')), $chartData)) ?>;
  var entrees = <?= json_encode(array_map(fn($r) => (float)$r['entrees'], $chartData)) ?>;
  var sorties = <?= json_encode(array_map(fn($r) => (float)$r['sorties'], $chartData)) ?>;

  var wrap = document.getElementById('chart-wrap');
  var canvas = document.getElementById('chart-flux');
  var elTooltip = document.getElementById('chart-tooltip');
  var elCrossX = document.getElementById('chart-crosshair-x');
  var elCrossY = document.getElementById('chart-crosshair-y');
  var elPriceTag = document.getElementById('chart-price-tag');

  if (!labels || labels.length === 0) {
    wrap.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--text3);font-size:14px;gap:8px"><i class="fa-solid fa-box-open" style="font-size:20px"></i> Aucune donnée sur les 6 derniers mois</div>';
    return;
  }
  if (!canvas || !canvas.getContext) return;

  // ── Palette ──
  var C = {
    up: '#22c55e', upFill: 'rgba(34,197,94,0.75)', upGlow: 'rgba(34,197,94,0.12)',
    dn: '#ef4444', dnFill: 'rgba(239,68,68,0.75)', dnGlow: 'rgba(239,68,68,0.12)',
    wick: '#94a3b8', grid: 'rgba(148,163,184,0.09)', gridStrong: 'rgba(148,163,184,0.18)',
    label: '#64748b', labelLight: '#94a3b8',
    volUp: 'rgba(34,197,94,0.18)', volDn: 'rgba(239,68,68,0.18)',
    sma: '#6366f1', smaBg: 'rgba(99,102,241,0.06)',
  };

  // ── OHLC cumulatif ──
  var cumul = 0, ohlc = [], volumes = [];
  for (var i = 0; i < labels.length; i++) {
    var e = entrees[i] || 0, s = sorties[i] || 0;
    var o = cumul, c = o + e - s, h = o + e, l = o - s;
    ohlc.push({ o: o, h: h, l: l, c: c });
    volumes.push(e + s);
    cumul = c;
  }

  // ── SMA 3 periods ──
  var sma = [];
  for (var i = 0; i < ohlc.length; i++) {
    if (i < 2) { sma.push(null); continue; }
    sma.push((ohlc[i].c + ohlc[i-1].c + ohlc[i-2].c) / 3);
  }

  // ── Layout ──
  var padL = 68, padR = 56, padT = 28, padB = 34;
  var VOL_H_RATIO = 0.18;
  var barRects = [];
  var layout = {};

  function fmtK(v) { var abs = Math.abs(v); if (abs >= 1e6) return (v/1e6).toFixed(1).replace('.0','') + 'M'; return (v/1e3).toLocaleString('fr-CM') + 'k'; }
  function fmtFCFA(v) { return v.toLocaleString('fr-CM') + ' FCFA'; }

  function niceScale(min, max, ticks) {
    var range = max - min || 1;
    var rough = range / ticks;
    var mag = Math.pow(10, Math.floor(Math.log10(rough)));
    var res = rough / mag;
    var step = res <= 1.5 ? mag : res <= 3 ? 2*mag : res <= 7 ? 5*mag : 10*mag;
    var nMin = Math.floor(min / step) * step;
    var nMax = Math.ceil(max / step) * step;
    return { min: nMin, max: nMax, step: step };
  }

  var animProgress = 0, animStart = null, animDuration = 600;
  var animRAF = null;

  function easeOut(t) { return 1 - Math.pow(1 - t, 3); }

  function animateDraw(ts) {
    if (!animStart) animStart = ts;
    animProgress = Math.min(1, (ts - animStart) / animDuration);
    draw(easeOut(animProgress));
    if (animProgress < 1) animRAF = requestAnimationFrame(animateDraw);
  }

  function draw(progress) {
    if (progress === undefined) progress = 1;
    var dpr = window.devicePixelRatio || 1;
    var W = wrap.clientWidth, H = wrap.clientHeight;
    canvas.width = W * dpr; canvas.height = H * dpr;
    canvas.style.width = W + 'px'; canvas.style.height = H + 'px';
    var ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    var cW = W - padL - padR;
    var mainH = H - padT - padB;
    var volH = mainH * VOL_H_RATIO;
    var chartH = mainH - volH - 4;

    var yMin = Infinity, yMax = -Infinity;
    for (var i = 0; i < ohlc.length; i++) { yMin = Math.min(yMin, ohlc[i].l); yMax = Math.max(yMax, ohlc[i].h); }
    var ns = niceScale(yMin - (yMax-yMin)*0.08, yMax + (yMax-yMin)*0.08, 5);
    yMin = ns.min; yMax = ns.max;

    function yPx(v) { return padT + chartH - ((v - yMin) / (yMax - yMin)) * chartH; }
    function yVal(px) { return yMin + ((padT + chartH - px) / chartH) * (yMax - yMin); }

    layout = { W:W, H:H, cW:cW, chartH:chartH, volH:volH, yMin:yMin, yMax:yMax, yPx:yPx, yVal:yVal, padL:padL, padR:padR, padT:padT, padB:padB };

    var cs = getComputedStyle(document.documentElement);
    var bg = cs.getPropertyValue('--surface').trim() || '#fff';
    var textC = cs.getPropertyValue('--text2').trim() || '#64748b';

    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);

    ctx.textAlign = 'right'; ctx.textBaseline = 'middle';
    ctx.font = '10.5px system-ui, -apple-system, sans-serif';
    ctx.fillStyle = textC;
    for (var v = ns.min; v <= ns.max + ns.step * 0.01; v += ns.step) {
      var gy = yPx(v);
      var isZero = Math.abs(v) < ns.step * 0.01;
      ctx.strokeStyle = isZero ? C.gridStrong : C.grid;
      ctx.lineWidth = isZero ? 1.2 : 0.7;
      if (isZero) { ctx.save(); ctx.setLineDash([5,3]); }
      ctx.beginPath(); ctx.moveTo(padL, gy); ctx.lineTo(W - padR, gy); ctx.stroke();
      if (isZero) ctx.restore();
      ctx.fillStyle = isZero ? C.label : C.labelLight;
      ctx.fillText(fmtK(v), padL - 8, gy);
    }

    var gap = cW / ohlc.length;
    var bodyW = Math.min(Math.max(gap * 0.45, 14), 48);
    barRects = [];

    for (var i = 0; i < ohlc.length; i++) {
      var d = ohlc[i];
      var cx = padL + i * gap + gap / 2;
      var isUp = d.c >= d.o;

      var aH = chartH * progress;
      var yOpen  = yPx(d.o), yClose = yPx(d.c);
      var yHigh  = yPx(d.h), yLow   = yPx(d.l);
      var base = padT + chartH;

      var visTop = base - aH;
      if (yHigh < visTop) continue;

      var col = isUp ? C.up : C.dn;
      var fill = isUp ? C.upFill : C.dnFill;

      ctx.save();
      ctx.shadowColor = isUp ? C.upGlow : C.dnGlow;
      ctx.shadowBlur = 10;
      ctx.fillStyle = fill;
      var bTop = Math.max(Math.min(yOpen, yClose), visTop);
      var bBot = Math.max(yOpen, yClose);
      var bH = Math.max(bBot - bTop, 1.5);
      var bx = cx - bodyW / 2;

      var r = Math.min(3, bodyW / 4, bH / 2);
      ctx.beginPath();
      ctx.moveTo(bx + r, bTop);
      ctx.lineTo(bx + bodyW - r, bTop);
      ctx.arcTo(bx + bodyW, bTop, bx + bodyW, bTop + r, r);
      ctx.lineTo(bx + bodyW, bTop + bH - r);
      ctx.arcTo(bx + bodyW, bTop + bH, bx + bodyW - r, bTop + bH, r);
      ctx.lineTo(bx + r, bTop + bH);
      ctx.arcTo(bx, bTop + bH, bx, bTop + bH - r, r);
      ctx.lineTo(bx, bTop + r);
      ctx.arcTo(bx, bTop, bx + r, bTop, r);
      ctx.closePath();
      ctx.fill();
      ctx.restore();

      ctx.strokeStyle = col; ctx.lineWidth = 1.2;
      ctx.stroke();

      ctx.strokeStyle = col; ctx.lineWidth = 1.5; ctx.globalAlpha = 0.7;
      ctx.beginPath(); ctx.moveTo(cx, Math.max(Math.min(yOpen, yClose), visTop)); ctx.lineTo(cx, Math.max(yHigh, visTop)); ctx.stroke();
      ctx.beginPath(); ctx.moveTo(cx, bBot); ctx.lineTo(cx, Math.max(yLow, visTop)); ctx.stroke();
      ctx.globalAlpha = 1;

      barRects.push({ x: bx, y: bTop, w: bodyW, h: bH, cx: cx, i: i, isUp: isUp });
    }

    if (progress > 0.5) {
      var smaAlpha = Math.min(1, (progress - 0.5) * 4);
      ctx.save();
      ctx.globalAlpha = smaAlpha * 0.8;
      ctx.strokeStyle = C.sma; ctx.lineWidth = 1.8;
      ctx.setLineDash([6, 4]);
      ctx.beginPath();
      var started = false;
      for (var i = 0; i < sma.length; i++) {
        if (sma[i] === null) continue;
        var px = padL + i * gap + gap / 2;
        var py = yPx(sma[i]);
        if (!started) { ctx.moveTo(px, py); started = true; }
        else ctx.lineTo(px, py);
      }
      ctx.stroke();
      ctx.restore();

      ctx.font = '9.5px system-ui, sans-serif';
      ctx.fillStyle = C.sma; ctx.textAlign = 'left'; ctx.globalAlpha = smaAlpha;
      var lastSmaI = sma.length - 1;
      while (lastSmaI >= 0 && sma[lastSmaI] === null) lastSmaI--;
      if (lastSmaI >= 0) {
        var lx = padL + lastSmaI * gap + gap / 2 + 6;
        var ly = yPx(sma[lastSmaI]) - 6;
        ctx.fillText('SMA3', lx, ly);
      }
      ctx.globalAlpha = 1;
    }

    var maxVol = Math.max.apply(null, volumes) || 1;
    var volBase = padT + chartH + volH + 4;
    var volTop0 = padT + chartH + 4;
    for (var i = 0; i < ohlc.length; i++) {
      var cx = padL + i * gap + gap / 2;
      var vBarH = (volumes[i] / maxVol) * volH * progress;
      var isUp = ohlc[i].c >= ohlc[i].o;
      ctx.fillStyle = isUp ? C.volUp : C.volDn;
      var vx = cx - bodyW / 2;
      ctx.beginPath();
      ctx.rect(vx, volBase - vBarH, bodyW, vBarH);
      ctx.fill();
    }

    ctx.font = '9px system-ui, sans-serif';
    ctx.fillStyle = C.labelLight; ctx.textAlign = 'left';
    ctx.fillText('Vol.', padL + 4, volTop0 + 8);

    ctx.font = '10.5px system-ui, -apple-system, sans-serif';
    ctx.fillStyle = C.labelLight; ctx.textAlign = 'center'; ctx.textBaseline = 'top';
    for (var i = 0; i < labels.length; i++) {
      var cx = padL + i * gap + gap / 2;
      ctx.fillText(labels[i], cx, H - padB + 10);
    }

    var legY = 8;
    ctx.font = '11px system-ui, sans-serif'; ctx.textBaseline = 'middle';
    drawPill(ctx, padL, legY, C.up, C.upFill, 'Hausse');
    drawPill(ctx, padL + 88, legY, C.dn, C.dnFill, 'Baisse');
    ctx.save(); ctx.setLineDash([4,3]); ctx.strokeStyle = C.sma; ctx.lineWidth = 1.8;
    ctx.beginPath(); ctx.moveTo(padL + 168, legY + 8); ctx.lineTo(padL + 186, legY + 8); ctx.stroke();
    ctx.restore();
    ctx.fillStyle = C.sma; ctx.textAlign = 'left'; ctx.font = '10.5px system-ui, sans-serif';
    ctx.fillText('SMA3', padL + 190, legY + 8);
  }

  function drawPill(ctx, x, y, col, fill, text) {
    var pw = 78, ph = 18, pr = 9;
    ctx.fillStyle = fill;
    ctx.beginPath();
    ctx.moveTo(x + pr, y); ctx.lineTo(x + pw - pr, y);
    ctx.arcTo(x + pw, y, x + pw, y + pr, pr);
    ctx.arcTo(x + pw, y + ph, x + pw - pr, y + ph, pr);
    ctx.lineTo(x + pr, y + ph);
    ctx.arcTo(x, y + ph, x, y + ph - pr, pr);
    ctx.arcTo(x, y, x + pr, y, pr);
    ctx.closePath();
    ctx.fill();
    ctx.fillStyle = col; ctx.font = '600 10.5px system-ui, sans-serif'; ctx.textAlign = 'center';
    ctx.fillText(text, x + pw / 2, y + ph / 2 + 0.5);
  }

  if (animRAF) cancelAnimationFrame(animRAF);
  animProgress = 0; animStart = null;
  animRAF = requestAnimationFrame(animateDraw);

  var resizeTimer;
  window.addEventListener('resize', function() {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(function() { draw(1); }, 80);
  });

  var hoverIdx = -1;
  canvas.addEventListener('mousemove', function(ev) {
    var rect = canvas.getBoundingClientRect();
    var mx = ev.clientX - rect.left;
    var my = ev.clientY - rect.top;

    elCrossX.style.display = 'block';
    elCrossY.style.display = 'block';
    elCrossX.style.left = mx + 'px';
    elCrossY.style.top = my + 'px';

    if (layout.yVal) {
      var price = layout.yVal(my);
      elPriceTag.style.display = 'block';
      elPriceTag.style.top = my + 'px';
      elPriceTag.textContent = fmtK(price);
      var isNeg = price < 0;
      elPriceTag.style.background = isNeg ? C.dn : C.up;
      elPriceTag.style.color = '#fff';
    }

    var nearIdx = -1, nearDist = Infinity;
    for (var k = 0; k < barRects.length; k++) {
      var dist = Math.abs(mx - barRects[k].cx);
      if (dist < nearDist) { nearDist = dist; nearIdx = barRects[k].i; }
    }
    if (nearIdx === hoverIdx) return;
    hoverIdx = nearIdx;
    if (nearIdx < 0) { elTooltip.style.display = 'none'; return; }

    var d = ohlc[nearIdx];
    var isUp = d.c >= d.o;
    var col = isUp ? C.up : C.dn;
    var net = d.c - d.o;
    var netSign = net >= 0 ? '+' : '';
    var pctD = d.o !== 0 ? ((net / Math.abs(d.o)) * 100).toFixed(1) : '0.0';

    elTooltip.innerHTML =
      '<div style="padding:10px 14px;background:' + (isUp ? 'rgba(34,197,94,0.06)' : 'rgba(239,68,68,0.06)') + ';border-bottom:1px solid ' + col + '33">' +
        '<div style="font-weight:700;font-size:13px;color:' + col + '">' + labels[nearIdx] + '</div>' +
      '</div>' +
      '<div style="padding:10px 14px;font-size:11.5px;line-height:1.9">' +
        '<div style="display:flex;justify-content:space-between"><span style="color:#94a3b8">Ouverture</span><span style="font-weight:600">' + fmtFCFA(d.o) + '</span></div>' +
        '<div style="display:flex;justify-content:space-between"><span style="color:#94a3b8">Fermeture</span><span style="font-weight:600">' + fmtFCFA(d.c) + '</span></div>' +
        '<div style="display:flex;justify-content:space-between"><span style="color:#94a3b8">Haut</span><span style="font-weight:600">' + fmtFCFA(d.h) + '</span></div>' +
        '<div style="display:flex;justify-content:space-between"><span style="color:#94a3b8">Bas</span><span style="font-weight:600">' + fmtFCFA(d.l) + '</span></div>' +
        '<div style="border-top:1px solid #e2e8f022;margin:4px 0"></div>' +
        '<div style="display:flex;justify-content:space-between"><span style="color:#5ee8b7">Entrées</span><span style="font-weight:600;color:#5ee8b7">+' + fmtFCFA(entrees[nearIdx]||0) + '</span></div>' +
        '<div style="display:flex;justify-content:space-between"><span style="color:#fca5a5">Sorties</span><span style="font-weight:600;color:#fca5a5">-' + fmtFCFA(sorties[nearIdx]||0) + '</span></div>' +
        '<div style="border-top:1px solid #e2e8f022;margin:4px 0"></div>' +
        '<div style="display:flex;justify-content:space-between"><span style="color:#94a3b8">Net</span><span style="font-weight:700;color:' + col + '">' + netSign + fmtFCFA(Math.abs(net)) + ' (' + netSign + pctD + '%)</span></div>' +
      '</div>';
    elTooltip.style.display = 'block';
    elTooltip.style.background = 'rgba(15,23,42,0.92)';
    elTooltip.style.backdropFilter = 'blur(8px)';

    var tx = mx + 16, ty = my - 40;
    if (tx + 190 > rect.width) tx = mx - 200;
    if (ty < 0) ty = my + 16;
    elTooltip.style.left = tx + 'px';
    elTooltip.style.top = ty + 'px';
  });

  canvas.addEventListener('mouseleave', function() {
    elTooltip.style.display = 'none';
    elCrossX.style.display = 'none';
    elCrossY.style.display = 'none';
    elPriceTag.style.display = 'none';
    hoverIdx = -1;
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
