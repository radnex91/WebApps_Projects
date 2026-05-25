  </main><!-- /content -->

</div><!-- /main-wrap -->

<?php include __DIR__ . '/ai_assistant.php'; ?>

<!-- ═══ MOBILE BOTTOM NAV ════════════════════════════════════════ -->
<?php
$mod = $currentModule ?? '';
function mobileNavItem(string $href, string $icon, string $label, string $module, string $current): string {
    $active = ($module === $current) ? 'active' : '';
    return "<a href=\"$href\" class=\"mobile-nav-item $active\"><span class=\"mn-icon\"><i class=\"ph-bold $icon\"></i></span><span>$label</span></a>";
}
?>
<nav class="mobile-nav" aria-label="Navigation mobile">
  <div class="mobile-nav-items">
    <?= mobileNavItem(BASE_URL.'/dashboard.php',                    'ph-squares-four', 'Accueil',      'dashboard',    $mod) ?>
    <?= mobileNavItem(BASE_URL.'/modules/caisse/index.php',         'ph-wallet', 'Caisse',       'caisse',       $mod) ?>
    <?= mobileNavItem(BASE_URL.'/modules/engagements/index.php',    'ph-clipboard-text', 'Demandes',     'engagements',  $mod) ?>
    <?= mobileNavItem(BASE_URL.'/modules/reporting/index.php',      'ph-chart-bar', 'Reporting',    'reporting',    $mod) ?>
    <button class="mobile-nav-item" onclick="toggleMobileSidebar()" aria-label="Menu complet">
      <span class="mn-icon"><i class="ph-bold ph-list"></i></span>
      <span>Menu</span>
    </button>
  </div>
</nav>

<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
<script>
function toggleNotifDropdown() {
  const dd = document.getElementById('notif-dropdown');
  dd.classList.toggle('open');
}
document.addEventListener('click', function(e) {
  const wrap = document.getElementById('notif-wrap');
  if (wrap && !wrap.contains(e.target)) {
    document.getElementById('notif-dropdown').classList.remove('open');
  }
});
async function markAllRead() {
  try {
    const res = await postJSON('<?= BASE_URL ?>/includes/mark_notification.php', {id: 'all'});
    if (res.success) {
      document.querySelectorAll('.notif-item.unread').forEach(function(el) { el.classList.remove('unread'); });
      const badge = document.getElementById('notif-badge');
      if (badge) badge.remove();
    }
  } catch(e) {
    console.error('markAllRead error', e);
  }
}
</script>
