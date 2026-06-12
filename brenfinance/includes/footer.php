  </main><!-- /content -->

</div><!-- /main-wrap -->

<?php include __DIR__ . '/ai_assistant.php'; ?>

<!-- ═══ MOBILE BOTTOM NAV ════════════════════════════════════════ -->
<?php
$mod = $currentModule ?? '';
function mobileNavItem(string $href, string $icon, string $label, string $module, string $current): string {
    $active = ($module === $current) ? 'active' : '';
    $ariaCurrent = ($module === $current) ? ' aria-current="page"' : '';
    return "<a href=\"$href\" class=\"mobile-nav-item $active\"$ariaCurrent><span class=\"mn-icon\"><i class=\"ph-bold $icon\"></i></span><span>$label</span></a>";
}
?>
<nav class="mobile-nav" aria-label="Navigation mobile">
  <div class="mobile-nav-items">
    <?= mobileNavItem(BASE_URL.'/dashboard.php',                    'ph-squares-four', 'Accueil',      'dashboard',    $mod) ?>
    <?= mobileNavItem(BASE_URL.'/modules/caisse/index.php',         'ph-wallet', 'Caisse',       'caisse',       $mod) ?>
    <?= mobileNavItem(BASE_URL.'/modules/engagements/index.php',    'ph-clipboard-text', 'Demandes',     'engagements',  $mod) ?>
    <?= mobileNavItem(BASE_URL.'/modules/reporting/index.php',      'ph-chart-bar', 'Reporting',    'reporting',    $mod) ?>
    <button class="mobile-nav-item" onclick="toggleMobileSidebar()" aria-label="Ouvrir le menu complet">
      <span class="mn-icon"><i class="ph-bold ph-list"></i></span>
      <span>Menu</span>
    </button>
  </div>
</nav>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
function toggleNotifDropdown() {
  const dd = document.getElementById('notif-dropdown');
  const btn = dd.previousElementSibling || document.querySelector('.notif-bell');
  const isOpen = dd.classList.toggle('open');
  if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
}
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    const dd = document.getElementById('notif-dropdown');
    const btn = document.querySelector('.notif-bell');
    if (dd) dd.classList.remove('open');
    if (btn) btn.setAttribute('aria-expanded', 'false');
  }
});
// Keyboard support for notification dropdown
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    const dd = document.getElementById('notif-dropdown');
    const btn = document.querySelector('.notif-bell');
    if (dd && dd.classList.contains('open')) {
      dd.classList.remove('open');
      if (btn) { btn.setAttribute('aria-expanded', 'false'); btn.focus(); }
    }
  }
});
async function markAllRead() {
  try {
    const res = await postJSON('<?= BASE_URL ?>/includes/mark_notification.php', {id: 'all'});
    if (res.success) {
      document.querySelectorAll('.notif-item.unread').forEach(function(el) { el.classList.remove('unread'); });
      const badge = document.getElementById('notif-badge');
      if (badge) badge.remove();
      const btn = document.querySelector('.notif-bell');
      if (btn) btn.setAttribute('aria-label', 'Notifications');
    }
  } catch(e) {
    console.error('markAllRead error', e);
  }
}
</script>