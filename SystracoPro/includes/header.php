<?php
// includes/header.php
if(!defined('APP_NAME')){ require_once __DIR__.'/config.php'; requireLogin(); }
$_u=currentUser(); $_flash=getFlash();
$_notifCount=isLoggedIn()?countUnreadNotifs($pdo,$_SESSION['user_id']):0;
$_appName=getParam('nom_entreprise',APP_NAME);
$_agence=null;
if($_u['agence_id']??false){
    $s=$pdo->prepare("SELECT * FROM agences WHERE id=?"); $s->execute([$_u['agence_id']]); $_agence=$s->fetch();
}
$_agenceAff=null;
if($_u['agence_affectation_id']??false){
    $s2=$pdo->prepare("SELECT * FROM agences WHERE id=?"); $s2->execute([$_u['agence_affectation_id']]); $_agenceAff=$s2->fetch();
}
$_sf=basename($_SERVER['PHP_SELF']);
$_path=$_SERVER['PHP_SELF']??'';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($pageTitle)?sanitize($pageTitle).' — ':'' ?><?= sanitize($_appName) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrap">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="sb-logo"><i class="fas fa-bus-alt"></i></div>
    <div>
      <div class="sb-name"><?= sanitize($_appName) ?></div>
      <div class="sb-sub"><?= sanitize(($_agenceAff?$_agenceAff['nom']:($_agence?$_agence['nom']:($_SESSION['role_nom']??'')))) ?></div>
    </div>
  </div>
  <nav class="sb-nav">
    <?php $b=BASE_URL; ?>
    <div class="nav-lbl">ACCUEIL</div>
    <a href="<?= $b ?>index.php" class="nav-link <?= preg_match('#/index\.php$#',$_path)&&!preg_match('#/modules/#',$_path)?'active':'' ?>"><i class="fas fa-tachometer-alt"></i><span>Tableau de bord</span></a>

    <?php if(can('tickets.create')||can('tickets.view')||can('reservations.manage')||can('voyages.create')): ?>
    <div class="nav-lbl">VOYAGES</div>
    <?php if(can('tickets.create')||can('tickets.view')): ?>
    <a href="<?= $b ?>modules/tickets/liste.php" class="nav-link <?= strpos($_path,'tickets/')!==false?'active':'' ?>"><i class="fas fa-ticket-alt"></i><span>Vente tickets</span></a>
    <?php endif; ?>
    <?php if(can('tickets.view')): ?>
    <a href="<?= $b ?>modules/tickets/gestion.php" class="nav-link <?= strpos($_path,'tickets/gestion')!==false?'active':'' ?>"><i class="fas fa-list-alt"></i><span>Gestion tickets</span></a>
    <?php endif; ?>
    <?php if(can('voyages.create')): ?>
    <a href="<?= $b ?>modules/voyages/" class="nav-link <?= strpos($_path,'voyages/')!==false?'active':'' ?>"><i class="fas fa-route"></i><span>Départs voyage</span></a>
    <?php endif; ?>
    <?php if(can('reservations.manage')): ?>
    <a href="<?= $b ?>modules/reservations/" class="nav-link <?= strpos($_path,'reservations')!==false?'active':'' ?>"><i class="fas fa-bookmark"></i><span>Réservations</span></a>
    <?php endif; ?>
    <?php endif; ?>
    <?php if(can('transits.manage')): ?>
    <a href="<?= $b ?>modules/transits/" class="nav-link <?= strpos($_path,'transits')!==false?'active':'' ?>"><i class="fas fa-exchange-alt"></i><span>Transits & Correspondances</span></a>
    <?php endif; ?>
    <?php if(can('itineraires.manage')): ?>
    <div class="nav-lbl">ITINÉRAIRES</div>
    <a href="<?= $b ?>modules/itineraires/" class="nav-link <?= strpos($_path,'itineraires')!==false?'active':'' ?>"><i class="fas fa-route"></i><span>Itinéraires</span></a>
    <a href="<?= $b ?>modules/correspondances/" class="nav-link <?= strpos($_path,'correspondances')!==false?'active':'' ?>"><i class="fas fa-exchange-alt"></i><span>Correspondances</span></a>
    <a href="<?= $b ?>modules/destinations/" class="nav-link <?= strpos($_path,'destinations')!==false?'active':'' ?>"><i class="fas fa-map-marked-alt"></i><span>Destinations</span></a>
    <?php endif; ?>

    <?php if(can('bordereaux.create')||can('bordereaux.view')||can('bordereaux.saisie')): ?>
    <div class="nav-lbl">BORDEREAUX</div>
    <a href="<?= $b ?>modules/bordereaux/" class="nav-link <?= strpos($_path,'bordereaux')!==false?'active':'' ?>"><i class="fas fa-file-invoice"></i><span>Bordereaux</span></a>
    <?php endif; ?>

    <?php if(can('rapports.agence')||can('rapports.guichet')||can('rapports.direction')): ?>
    <div class="nav-lbl">RAPPORTS</div>
    <?php if(can('rapports.agence')): ?>
    <a href="<?= $b ?>modules/rapports/agence.php" class="nav-link <?= strpos($_path,'rapports/agence')!==false?'active':'' ?>"><i class="fas fa-chart-bar"></i><span>Rapport agence</span></a>
    <?php endif; ?>
    <?php if(can('rapports.guichet')): ?>
    <a href="<?= $b ?>modules/rapports/guichetier.php" class="nav-link <?= strpos($_path,'rapports/guichet')!==false?'active':'' ?>"><i class="fas fa-user-chart"></i><span>Rapport guichetier</span></a>
    <?php endif; ?>
    <?php if(can('rapports.direction')): ?>
    <a href="<?= $b ?>modules/rapports/direction.php" class="nav-link <?= strpos($_path,'rapports/direction')!==false?'active':'' ?>"><i class="fas fa-chart-line"></i><span>Rapports Direction</span></a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if(can('caisse.manage')): ?>
    <div class="nav-lbl">FINANCES</div>
    <a href="<?= $b ?>modules/caisse/index.php" class="nav-link <?= strpos($_path,'caisse')!==false?'active':'' ?>"><i class="fas fa-cash-register"></i><span>Caisse</span></a>
    <?php endif; ?>
    <?php if(can('depenses.create')): ?>
    <?php if(!can('caisse.manage')): ?><div class="nav-lbl">FINANCES</div><?php endif; ?>
    <a href="<?= $b ?>modules/depenses/" class="nav-link <?= strpos($_path,'depenses')!==false?'active':'' ?>"><i class="fas fa-money-bill-wave"></i><span>Dépenses</span></a>
    <?php endif; ?>
    <?php if(can('versements.create')): ?>
    <a href="<?= $b ?>modules/versements/" class="nav-link <?= strpos($_path,'versements')!==false?'active':'' ?>"><i class="fas fa-university"></i><span>Versements</span></a>
    <?php endif; ?>

    <?php if(can('vehicules.manage')||can('groupes.manage')||can('proprietaires.manage')): ?>
    <div class="nav-lbl">GESTION</div>
    <a href="<?= $b ?>modules/vehicules/" class="nav-link <?= strpos($_path,'vehicules')!==false?'active':'' ?>"><i class="fas fa-bus"></i><span>Véhicules</span></a>
    <?php if(can('groupes.manage')): ?>
    <a href="<?= $b ?>modules/groupes/" class="nav-link <?= strpos($_path,'groupes')!==false?'active':'' ?>"><i class="fas fa-layer-group"></i><span>Groupes</span></a>
    <?php endif; ?>
    <?php if(can('proprietaires.manage')): ?>
    <a href="<?= $b ?>modules/proprietaires/" class="nav-link <?= strpos($_path,'proprietaires')!==false?'active':'' ?>"><i class="fas fa-user-tie"></i><span>Propriétaires</span></a>
    <?php endif; ?>
    <?php if(can('concessionnaires.manage')): ?>
    <a href="<?= $b ?>modules/concessionnaires/" class="nav-link <?= strpos($_path,'concessionnaires')!==false?'active':'' ?>"><i class="fas fa-store"></i><span>Concessionnaires</span></a>
    <?php endif; ?>
    <?php endif; ?>
    <?php if(can('agences.manage')): ?>
    <a href="<?= $b ?>modules/agences/" class="nav-link <?= strpos($_path,'agences')!==false?'active':'' ?>"><i class="fas fa-building"></i><span>Agences</span></a>
    <?php endif; ?>
    <?php if(can('personnel.manage')): ?>
    <a href="<?= $b ?>modules/personnel/" class="nav-link <?= strpos($_path,'personnel')!==false?'active':'' ?>"><i class="fas fa-id-badge"></i><span>Personnel</span></a>
    <?php endif; ?>
    <?php if(can('tarifs.manage')): ?>
    <a href="<?= $b ?>modules/tarifs/" class="nav-link <?= strpos($_path,'tarifs')!==false?'active':'' ?>"><i class="fas fa-tags"></i><span>Tarifs</span></a>
    <?php endif; ?>
    <?php if(can('users.manage')): ?>
    <a href="<?= $b ?>modules/users/" class="nav-link <?= strpos($_path,'users')!==false?'active':'' ?>"><i class="fas fa-users-cog"></i><span>Utilisateurs</span></a>
    <?php endif; ?>
    <?php if(can('permissions.manage')): ?>
    <a href="<?= $b ?>modules/roles/" class="nav-link <?= strpos($_path,'roles')!==false?'active':'' ?>"><i class="fas fa-shield-alt"></i><span>Rôles & Permissions</span></a>
    <?php endif; ?>
    <?php if(can('parametres.manage')): ?>
    <a href="<?= $b ?>modules/parametres/" class="nav-link <?= strpos($_path,'parametres')!==false?'active':'' ?>"><i class="fas fa-cog"></i><span>Paramètres</span></a>
    <?php endif; ?>
    <?php if(can('rapports.direction')): ?>
    <a href="<?= $b ?>modules/export.php" class="nav-link <?= strpos($_path,'export')!==false?'active':'' ?>"><i class="fas fa-file-excel"></i><span>Export CSV/Excel</span></a>
    <?php endif; ?>
    <?php if(isSuperAdmin()): ?>
    <a href="<?= $b ?>modules/sauvegarde.php" class="nav-link <?= strpos($_path,'sauvegarde')!==false?'active':'' ?>"><i class="fas fa-database"></i><span>Sauvegarde BD</span></a>
    <?php endif; ?>
  </nav>

  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-av" style="background:<?= sanitize($_u['avatar_color']??'#2563eb') ?>"><?= initials(($_u['prenom']??'').' '.($_u['nom']??'')) ?></div>
      <div class="sb-uinfo">
        <div class="sb-uname"><?= sanitize(($_u['prenom']??'').' '.($_u['nom']??'')) ?></div>
        <div class="sb-urole"><?= sanitize($_SESSION['role_nom']??'') ?></div>
      </div>
      <a href="<?= $b ?>logout.php" style="color:var(--sidebar-t);padding:4px 6px;border-radius:4px;transition:color .1s;margin-left:auto;" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <button class="tb-toggle" id="toggle-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="tb-title"><?= isset($pageTitle)?sanitize($pageTitle):'Tableau de bord' ?></div>
    <div class="tb-right">
      <?php if($_agenceAff): ?>
      <div class="tb-badge" style="background:#fef3c7;color:#92400e;"><i class="fas fa-exchange-alt"></i>Affecté à <?= sanitize($_agenceAff['ville']) ?></div>
      <?php elseif($_agence): ?>
      <div class="tb-badge" style="background:#dbeafe;color:#1e3a8a;"><i class="fas fa-map-marker-alt"></i><?= sanitize($_agence['ville']) ?></div>
      <?php endif; ?>
      <div class="tb-badge" style="background:<?= sanitize($_SESSION['role_color']??'#2563eb') ?>20;color:<?= sanitize($_SESSION['role_color']??'#2563eb') ?>;"><i class="fas fa-shield-alt"></i><?= sanitize($_SESSION['role_nom']??'') ?></div>
      <button class="notif-btn" onclick="window.location='<?= $b ?>modules/notifications.php'" title="Notifications">
        <i class="fas fa-bell"></i>
        <?php if($_notifCount>0): ?><span class="nb"><?= $_notifCount ?></span><?php endif; ?>
      </button>
      <a href="<?= $b ?>logout.php" class="btn btn-ghost btn-sm no-print" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </header>

  <div id="toast-container"></div>

  <?php if($_flash): ?>
  <script>document.addEventListener('DOMContentLoaded',function(){showToast('<?= $_flash['type'] ?>','<?= addslashes(sanitize($_flash['msg'])) ?>');});</script>
  <?php endif; ?>

  <div class="page">
