<?php
// includes/header.php
if(!defined('APP_NAME')){ require_once __DIR__.'/config.php'; requireLogin(); }
$_u=currentUser(); $_flash=getFlash();
$_path=$_SERVER['PHP_SELF']??'';
$_sf=basename($_path);
$_module=explode('/',$_path);
$_mod=in_array('modules',$_module)?$_module[array_search('modules',$_module)+1]??'':'';
$_appName=getParam('nom_entreprise',APP_NAME);
$_couleur=getParam('couleur_primaire','#1e40af');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($pageTitle)?h($pageTitle).' — ':'' ?><?= h($_appName) ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php if($_couleur !== '#1e40af'): ?>
<style>:root{--primary:<?= h($_couleur) ?>;--primary-d:<?= h($_couleur) ?>cc;--sidebar-bg:<?= h($_couleur) ?>22;}</style>
<?php endif; ?>
</head>
<body>
<div class="wrap">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sb-brand">
    <div class="sb-logo"><i class="fas fa-bus-alt"></i></div>
    <div>
      <div class="sb-name"><?= h($_appName) ?></div>
      <div class="sb-sub">v<?= APP_VER ?></div>
    </div>
  </div>
  <nav class="sb-nav">
    <?php $b=BASE_URL; ?>
    <div class="nav-lbl">ACCUEIL</div>
    <a href="<?= $b ?>index.php" class="nav-link <?= $_sf==='index.php'&&!$_mod?'active':'' ?>"><i class="fas fa-tachometer-alt"></i><span class="lbl">Tableau de bord</span></a>

    <?php if(can('bordereaux.view')): ?>
    <div class="nav-lbl">EXPLOITATION</div>
    <a href="<?= $b ?>modules/bordereaux/" class="nav-link <?= $_mod==='bordereaux'?'active':'' ?>"><i class="fas fa-file-invoice"></i><span class="lbl">Bordereaux</span></a>
    <?php endif; ?>

    <?php if(can('versements.view')): ?>
    <a href="<?= $b ?>modules/versements/" class="nav-link <?= $_mod==='versements'?'active':'' ?>"><i class="fas fa-university"></i><span class="lbl">Versements bancaires</span></a>
    <?php endif; ?>

    <?php if(can('depenses.view')): ?>
    <a href="<?= $b ?>modules/depenses/" class="nav-link <?= $_mod==='depenses'?'active':'' ?>"><i class="fas fa-money-bill-wave"></i><span class="lbl">Dépenses</span></a>
    <?php endif; ?>

    <?php if(can('bons.view')): ?>
    <a href="<?= $b ?>modules/bons/" class="nav-link <?= $_mod==='bons'?'active':'' ?>"><i class="fas fa-handshake"></i><span class="lbl">Bons actionnaires</span></a>
    <?php endif; ?>

    <?php if(can('acomptes.manage')): ?>
    <a href="<?= $b ?>modules/acomptes/" class="nav-link <?= $_mod==='acomptes'?'active':'' ?>"><i class="fas fa-user-clock"></i><span class="lbl">Acomptes / Avances</span></a>
    <?php endif; ?>

    <?php if(can('rapports.agence')||can('rapports.direction')): ?>
    <div class="nav-lbl">RAPPORTS</div>
    <?php if(can('rapports.agence')): ?>
    <a href="<?= $b ?>modules/rapports/journalier.php" class="nav-link <?= (strpos($_path,'rapports')!==false&&strpos($_path,'journalier')!==false)?'active':'' ?>"><i class="fas fa-chart-bar"></i><span class="lbl">Rapport journalier</span></a>
    <a href="<?= $b ?>modules/rapports/mensuel.php" class="nav-link <?= (strpos($_path,'rapports')!==false&&strpos($_path,'mensuel')!==false)?'active':'' ?>"><i class="fas fa-calendar-alt"></i><span class="lbl">Rapport mensuel</span></a>
    <a href="<?= $b ?>modules/rapports/vehicule.php" class="nav-link <?= (strpos($_path,'rapports')!==false&&strpos($_path,'vehicule')!==false)?'active':'' ?>"><i class="fas fa-bus"></i><span class="lbl">Rapport par véhicule</span></a>
    <?php endif; ?>
    <?php if(can('rapports.direction')): ?>
    <a href="<?= $b ?>modules/rapports/direction.php" class="nav-link <?= (strpos($_path,'rapports')!==false&&strpos($_path,'direction')!==false)?'active':'' ?>"><i class="fas fa-chart-line"></i><span class="lbl">Rapport direction</span></a>
    <a href="<?= $b ?>modules/rapports/rapprochement.php" class="nav-link <?= strpos($_path,'rapprochement')!==false?'active':'' ?>"><i class="fas fa-balance-scale"></i><span class="lbl">Rapprochement bancaire</span></a>
    <a href="<?= $b ?>modules/rapports/actionnaires.php" class="nav-link <?= strpos($_path,'actionnaires')!==false?'active':'' ?>"><i class="fas fa-users"></i><span class="lbl">Situation actionnaires</span></a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if(can('agences.view')): ?>
    <div class="nav-lbl">RÉFÉRENTIELS</div>
    <a href="<?= $b ?>modules/agences/" class="nav-link <?= $_mod==='agences'?'active':'' ?>"><i class="fas fa-building"></i><span class="lbl">Agences</span></a>
    <?php endif; ?>
    <?php if(can('groupes.view')): ?>
    <a href="<?= $b ?>modules/groupes/" class="nav-link <?= $_mod==='groupes'?'active':'' ?>"><i class="fas fa-layer-group"></i><span class="lbl">Groupes / Actionnaires</span></a>
    <?php endif; ?>
    <?php if(can('vehicules.view')): ?>
    <a href="<?= $b ?>modules/vehicules/" class="nav-link <?= $_mod==='vehicules'?'active':'' ?>"><i class="fas fa-bus"></i><span class="lbl">Véhicules</span></a>
    <?php endif; ?>
    <?php if(can('personnel.view')): ?>
    <a href="<?= $b ?>modules/personnel/" class="nav-link <?= $_mod==='personnel'?'active':'' ?>"><i class="fas fa-id-badge"></i><span class="lbl">Personnel</span></a>
    <?php endif; ?>

    <?php if(can('export.access')): ?>
    <div class="nav-lbl">OUTILS</div>
    <a href="<?= $b ?>modules/export/" class="nav-link <?= $_mod==='export'?'active':'' ?>"><i class="fas fa-file-excel"></i><span class="lbl">Export Excel/CSV</span></a>
    <?php endif; ?>
    <?php if(can('users.manage')): ?>
    <a href="<?= $b ?>modules/users/" class="nav-link <?= $_mod==='users'?'active':'' ?>"><i class="fas fa-users-cog"></i><span class="lbl">Utilisateurs</span></a>
    <?php endif; ?>
    <?php if(can('parametres.manage')): ?>
    <a href="<?= $b ?>modules/parametres/" class="nav-link <?= $_mod==='parametres'?'active':'' ?>"><i class="fas fa-cog"></i><span class="lbl">Paramètres</span></a>
    <?php endif; ?>
    <?php if(isSuperAdmin()): ?>
    <a href="<?= $b ?>modules/sauvegarde/" class="nav-link <?= $_mod==='sauvegarde'?'active':'' ?>"><i class="fas fa-database"></i><span class="lbl">Sauvegarde BD</span></a>
    <?php endif; ?>
  </nav>
  <div class="sb-footer">
    <div class="sb-user">
      <div class="sb-av" style="background:<?= h($_u['avatar_color']??'#1e40af') ?>"><?= initials(($_u['prenom']??'').' '.($_u['nom']??'')) ?></div>
      <div class="sb-uinfo">
        <div class="sb-uname"><?= h(trim(($_u['prenom']??'').' '.($_u['nom']??''))) ?></div>
        <div class="sb-urole"><?= h($_SESSION['role_nom']??'') ?></div>
      </div>
      <a href="<?= $b ?>logout.php" style="color:var(--sidebar-t);padding:4px 6px;border-radius:4px;font-size:14px;margin-left:auto;" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</aside>

<!-- MAIN -->
<div class="main">
  <header class="topbar">
    <button class="tb-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <div class="tb-title"><?= isset($pageTitle)?h($pageTitle):h($_appName) ?></div>
    <div class="tb-right">
      <?php if($_u['agence_id']??false): 
        $agn=$pdo->prepare("SELECT nom,ville FROM agences WHERE id=?"); $agn->execute([$_u['agence_id']]); $agn=$agn->fetch();
        if($agn): ?>
      <div class="tb-badge" style="background:#dbeafe;color:#1e3a8a;"><i class="fas fa-map-marker-alt"></i><?= h($agn['nom']) ?></div>
      <?php endif; endif; ?>
      <div class="tb-badge" style="background:<?= h($_SESSION['role_color']??'#1e40af') ?>20;color:<?= h($_SESSION['role_color']??'#1e40af') ?>;"><i class="fas fa-shield-alt"></i><?= h($_SESSION['role_nom']??'') ?></div>
      <a href="<?= $b ?>logout.php" class="btn btn-ghost btn-sm no-print" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </header>
  <?php if($_flash): ?>
  <div class="flash flash-<?= $_flash['type'] ?>" id="flash-msg">
    <i class="fas fa-<?= $_flash['type']==='success'?'check-circle':'exclamation-triangle' ?>"></i>
    <?= h($_flash['msg']) ?>
    <button class="flash-close" onclick="this.parentElement.remove()">✕</button>
  </div>
  <?php endif; ?>
  <div class="page">
