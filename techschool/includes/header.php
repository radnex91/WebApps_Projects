<?php
// includes/header.php
if(!defined('APP_NAME')){ require_once __DIR__.'/config.php'; requireLogin(); }
$_user    = currentUser();
$_annee   = getAnneeActive($pdo);
$_flash   = getFlash();
$_navRole = $_SESSION['role_code'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= isset($pageTitle)?sanitize($pageTitle).' — ':'' ?><?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/app.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">

<!-- ═══ SIDEBAR ═════════════════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <div class="brand-icon"><i class="fas fa-graduation-cap"></i></div>
    <div class="brand-text">
      <div class="brand-name"><?= APP_NAME ?></div>
      <div class="brand-year"><?= sanitize($_annee['libelle']??'') ?></div>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php $cu=BASE_URL; $sf=basename($_SERVER['PHP_SELF']); ?>

    <div class="nav-group-label">TABLEAU DE BORD</div>
    <a href="<?= $cu ?>index.php" class="nav-link <?= $sf==='index.php'?'active':'' ?>">
      <i class="fas fa-tachometer-alt"></i><span>Accueil</span>
    </a>

    <?php if(can('eleves.view')): ?>
    <div class="nav-group-label">SCOLARITÉ</div>
    <a href="<?= $cu ?>modules/eleves/" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'eleves')!==false?'active':'' ?>">
      <i class="fas fa-user-graduate"></i><span>Élèves</span>
    </a>
    <a href="<?= $cu ?>modules/inscription.php" class="nav-link <?= $sf==='inscription.php'?'active':'' ?>">
      <i class="fas fa-file-signature"></i><span>Inscriptions</span>
    </a>
    <?php endif; ?>

    <?php if(can('classes.manage')): ?>
    <a href="<?= $cu ?>modules/classes/" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'classes')!==false?'active':'' ?>">
      <i class="fas fa-chalkboard"></i><span>Classes</span>
    </a>
    <?php endif; ?>

    <?php if(can('enseignants.manage')): ?>
    <a href="<?= $cu ?>modules/enseignants/" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'enseignants')!==false?'active':'' ?>">
      <i class="fas fa-chalkboard-teacher"></i><span>Enseignants</span>
    </a>
    <?php endif; ?>

    <?php if(can('notes.view')): ?>
    <div class="nav-group-label">PÉDAGOGIE</div>
    <a href="<?= $cu ?>modules/notes/" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'notes')!==false?'active':'' ?>">
      <i class="fas fa-star-half-alt"></i><span>Notes</span>
    </a>
    <a href="<?= $cu ?>modules/absences.php" class="nav-link <?= $sf==='absences.php'?'active':'' ?>">
      <i class="fas fa-user-clock"></i><span>Absences</span>
    </a>
    <?php endif; ?>

    <?php if(can('bulletins.view')): ?>
    <a href="<?= $cu ?>modules/bulletins/" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'bulletins')!==false?'active':'' ?>">
      <i class="fas fa-file-alt"></i><span>Bulletins</span>
    </a>
    <?php endif; ?>

    <?php if(can('paiements.view')): ?>
    <div class="nav-group-label">ADMINISTRATION</div>
    <a href="<?= $cu ?>modules/paiements.php" class="nav-link <?= $sf==='paiements.php'?'active':'' ?>">
      <i class="fas fa-money-bill-wave"></i><span>Paiements</span>
    </a>
    <?php endif; ?>

    <?php if(can('rapports.view')): ?>
    <a href="<?= $cu ?>modules/rapports.php" class="nav-link <?= $sf==='rapports.php'?'active':'' ?>">
      <i class="fas fa-chart-bar"></i><span>Rapports</span>
    </a>
    <?php endif; ?>

    <?php if(can('users.manage') || isSuperAdmin()): ?>
    <a href="<?= $cu ?>modules/users/" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'users')!==false?'active':'' ?>">
      <i class="fas fa-users-cog"></i><span>Utilisateurs</span>
    </a>
    <?php endif; ?>

    <?php if(can('bulletins.config') || can('parametres.manage')): ?>
    <a href="<?= $cu ?>modules/parametres/" class="nav-link <?= strpos($_SERVER['PHP_SELF'],'parametres')!==false?'active':'' ?>">
      <i class="fas fa-cog"></i><span>Paramètres</span>
    </a>
    <?php endif; ?>
  </nav>

  <div class="sidebar-footer">
    <div class="sidebar-user">
      <div class="user-avatar" style="background:<?= sanitize($_user['avatar_color']??'#2563eb') ?>">
        <?= initials(($_user['prenom']??'').' '.($_user['nom']??'')) ?>
      </div>
      <div class="user-info">
        <div class="user-name"><?= sanitize(($_user['prenom']??'').' '.($_user['nom']??'')) ?></div>
        <div class="user-role"><?= sanitize($_SESSION['role_nom']??'') ?></div>
      </div>
      <a href="<?= $cu ?>logout.php" class="user-logout" title="Déconnexion"><i class="fas fa-sign-out-alt"></i></a>
    </div>
  </div>
</aside>

<!-- ═══ MAIN ════════════════════════════════════════════════════ -->
<div class="main-content">
  <header class="topbar">
    <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('collapsed')" id="toggle-btn">
      <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-title"><?= isset($pageTitle)?sanitize($pageTitle):APP_NAME ?></div>
    <div class="topbar-right">
      <?php if($_annee): ?>
      <div class="topbar-badge"><i class="fas fa-calendar-alt"></i> <?= sanitize($_annee['libelle']) ?></div>
      <?php endif; ?>
      <div class="topbar-badge" style="background:<?= sanitize($_SESSION['role_color']??'#2563eb') ?>20;color:<?= sanitize($_SESSION['role_color']??'#2563eb') ?>;">
        <i class="fas fa-shield-alt"></i> <?= sanitize($_SESSION['role_nom']??'') ?>
      </div>
    </div>
  </header>

  <?php if($_flash): ?>
  <div class="flash-alert flash-<?= $_flash['type'] ?>" id="flash-alert">
    <i class="fas fa-<?= $_flash['type']==='success'?'check-circle':'exclamation-triangle' ?>"></i>
    <?= sanitize($_flash['msg']) ?>
    <button onclick="this.parentElement.remove()" class="flash-close">✕</button>
  </div>
  <?php endif; ?>

  <div class="page-content">
