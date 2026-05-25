<!-- includes/header.php -->
<?php if(!defined('APP_NAME')) { require_once __DIR__ . '/config.php'; requireLogin(); } ?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? sanitize($pageTitle).' — ' : '' ?><?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= BASE_URL ?>css/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="wrapper">

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
  <div class="sidebar-brand">
    <i class="fas fa-school"></i>
    <span><?= APP_NAME ?></span>
  </div>
  <nav class="sidebar-nav">
    <div class="nav-section">TABLEAU DE BORD</div>
    <a href="<?= BASE_URL ?>index.php" class="nav-link <?= basename($_SERVER['PHP_SELF'])=='index.php'?'active':'' ?>">
      <i class="fas fa-tachometer-alt"></i> Accueil
    </a>

    <div class="nav-section">SCOLARITÉ</div>
    <a href="<?= BASE_URL ?>modules/eleves/" class="nav-link">
      <i class="fas fa-user-graduate"></i> Élèves
    </a>
    <a href="<?= BASE_URL ?>modules/inscription/" class="nav-link">
      <i class="fas fa-file-signature"></i> Inscriptions
    </a>
    <a href="<?= BASE_URL ?>modules/classes/" class="nav-link">
      <i class="fas fa-chalkboard"></i> Classes
    </a>
    <a href="<?= BASE_URL ?>modules/parents/" class="nav-link">
      <i class="fas fa-users"></i> Parents
    </a>

    <div class="nav-section">PÉDAGOGIE</div>
    <a href="<?= BASE_URL ?>modules/enseignants/" class="nav-link">
      <i class="fas fa-chalkboard-teacher"></i> Enseignants
    </a>
    <a href="<?= BASE_URL ?>modules/notes/" class="nav-link">
      <i class="fas fa-star-half-alt"></i> Notes
    </a>
    <a href="<?= BASE_URL ?>modules/bulletins/" class="nav-link">
      <i class="fas fa-file-alt"></i> Bulletins
    </a>
    <a href="<?= BASE_URL ?>modules/emploi_temps/" class="nav-link">
      <i class="fas fa-calendar-week"></i> Emploi du temps
    </a>

    <div class="nav-section">ADMINISTRATION</div>
    <a href="<?= BASE_URL ?>modules/rapports/" class="nav-link">
      <i class="fas fa-chart-bar"></i> Rapports
    </a>
    <a href="<?= BASE_URL ?>modules/paiements.php" class="nav-link">
      <i class="fas fa-money-bill-wave"></i> Paiements
    </a>
    <?php if(hasRole('admin','directeur')): ?>
    <a href="<?= BASE_URL ?>modules/parametres.php" class="nav-link">
      <i class="fas fa-cog"></i> Paramètres
    </a>
    <?php endif; ?>

    <div style="margin-top:auto;padding:1rem;">
      <a href="<?= BASE_URL ?>logout.php" class="nav-link" style="color:#ff6b6b;">
        <i class="fas fa-sign-out-alt"></i> Déconnexion
      </a>
    </div>
  </nav>
</aside>

<!-- MAIN -->
<div class="main-content">
  <header class="topbar">
    <button class="toggle-btn" onclick="document.getElementById('sidebar').classList.toggle('collapsed')">
      <i class="fas fa-bars"></i>
    </button>
    <div class="topbar-title"><?= isset($pageTitle) ? sanitize($pageTitle) : 'Tableau de bord' ?></div>
    <div class="topbar-user">
      <i class="fas fa-user-circle"></i>
      <?= sanitize($_SESSION['prenom'] ?? '') ?> <?= sanitize($_SESSION['nom'] ?? '') ?>
      <span class="badge-role"><?= sanitize($_SESSION['role'] ?? '') ?></span>
    </div>
  </header>

  <?php $flash = getFlash(); if($flash): ?>
  <div class="alert alert-<?= $flash['type'] ?>">
    <i class="fas fa-<?= $flash['type']=='success'?'check-circle':'exclamation-triangle' ?>"></i>
    <?= sanitize($flash['msg']) ?>
  </div>
  <?php endif; ?>

  <div class="content-area">
