<?php
require_once __DIR__ . '/auth.php';
requireLogin('partenaire');
$user = getCurrentUser();
$waCatalog = defined('WHATSAPP_PHONE') ? preg_replace('/\D+/', '', (string) WHATSAPP_PHONE) : '';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tarifs partenaires — ESADISS</title>
  <script>document.documentElement.dataset.theme=localStorage.getItem('esadiss-theme')||'dark';</script>
  <link rel="stylesheet" href="styles.css?v=20260410-3">
  <script>window.__ESADISS_WA__=<?php echo json_encode($waCatalog, JSON_UNESCAPED_UNICODE); ?>;</script>
</head>
<body>
  <header>
    <h1>Tarifs partenaires</h1>
    <div class="header-right">
      <a href="index.php">Accueil &amp; catalogue public</a>
      <?php if ($user['role'] === 'admin'): ?>
        <a href="admin.php">Administration</a>
      <?php endif; ?>
      <span class="user-name"><?php echo htmlspecialchars($user['login']); ?></span>
      <a href="logout.php">Déconnexion</a>
    </div>
  </header>
  <main class="main-accueil main-partenaire">
    <p class="partenaire-lead">
      Affichage de vos <strong>tarifs partenaire</strong> et des <strong>prix client</strong> par produit.
      <?php if ($waCatalog !== ''): ?>
        Sélectionnez des produits, puis envoyez-nous par <strong>WhatsApp</strong> ci-dessous.
      <?php endif; ?>
    </p>
    <div class="search-zone catalog-search">
      <div class="search-zone-inner">
        <input type="search" id="search-partenaire" class="search-zone-input" placeholder="Rechercher un produit…" autocomplete="off" aria-label="Recherche catalogue partenaire">
      </div>
    </div>
    <?php if ($waCatalog !== ''): ?>
    <div id="catalog-toolbar" class="catalog-toolbar" hidden>
      <div class="catalog-toolbar__left">
        <span class="catalog-toolbar__meta">
          <svg class="catalog-toolbar__toolbar-ico catalog-toolbar__toolbar-ico--list" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 13h2v-2H3v2zm0 4h2v-2H3v2zm0-8h2V7H3v2zm4 4h14v-2H7v2zm0 4h14v-2H7v2zM7 7v2h14V7H7z"/></svg>
          <span class="catalog-toolbar__count">
            <span class="catalog-toolbar__count-live visually-hidden" aria-live="polite" aria-atomic="true">Aucun produit sélectionné</span>
            <span class="catalog-toolbar__count-desktop" aria-hidden="true">Aucun produit sélectionné</span>
            <span class="catalog-toolbar__count-mobile" aria-hidden="true">0</span>
          </span>
        </span>
      </div>
      <div class="catalog-toolbar__actions">
        <button type="button" class="btn btn-secondary btn-sm catalog-toolbar__all catalog-toolbar__btn-iconic" aria-label="Inverser la sélection">
          <svg class="catalog-toolbar__btn-ico" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M6.99 11L3 15l3.99 4v-3H14v-2H6.99v-3zM21 9l-3.99-4v3H10v2h7.01v3L21 9z"/></svg>
          <span class="catalog-toolbar__btn-text">Inverser la sélection</span>
        </button>
        <button type="button" class="btn btn-secondary btn-sm catalog-toolbar__clear catalog-toolbar__btn-iconic is-hidden" aria-label="Tout désélectionner">
          <svg class="catalog-toolbar__btn-ico" width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>
          <span class="catalog-toolbar__btn-text">Tout désélectionner</span>
        </button>
        <button type="button" class="btn btn-whatsapp catalog-toolbar__wa" disabled aria-label="Envoyer la sélection par WhatsApp">
          <svg width="18" height="18" viewBox="0 0 32 32" aria-hidden="true"><path fill="currentColor" d="M16.02 3.2c-7.06 0-12.8 5.74-12.8 12.8 0 2.26.59 4.47 1.7 6.42L3 29l6.74-1.76a12.74 12.74 0 0 0 6.28 1.66h.01c7.05 0 12.79-5.74 12.79-12.8 0-3.42-1.33-6.64-3.76-9.06a12.7 12.7 0 0 0-9.04-3.84Z"/></svg>
          <span class="catalog-toolbar__wa-text-desktop">WhatsApp</span>
          <span class="catalog-toolbar__wa-text-mobile">Envoyer</span>
        </button>
      </div>
    </div>
    <?php endif; ?>

    <div id="content">
      <div class="empty-state">Chargement des produits…</div>
    </div>
  </main>
  <script src="catalog-shared.js?v=20260409-3"></script>
  <script src="partenaire.js?v=20260411-1"></script>
  <script src="theme-toggle.js"></script>
  <?php
  require_once __DIR__ . '/whatsapp_widget.php';
  renderWhatsAppFab();
  ?>
</body>
</html>
