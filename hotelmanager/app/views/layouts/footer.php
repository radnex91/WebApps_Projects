  </main><!-- /page-content -->
</div><!-- /main-wrapper -->

<!-- Bootstrap JS -->
<script src="<?= APP_URL ?>/public/js/bootstrap.bundle.min.js"></script>
<!-- Chart.js (offline-first: fichier local, sinon CDN) -->
<script src="<?= APP_URL ?>/public/js/chart.min.js"></script>

<script>
// ── Sidebar toggle ───────────────────────────────────────────
function toggleSidebar() {
  document.getElementById('sidebar').classList.toggle('collapsed');
  document.getElementById('mainWrapper').classList.toggle('expanded');
}

// ── Auto-dismiss alerts after 5s ─────────────────────────────
document.querySelectorAll('.alert').forEach(el => {
  setTimeout(() => {
    const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
    bsAlert.close();
  }, 5000);
});

// ── Confirmation suppression ──────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || 'Confirmer cette action ?')) {
      e.preventDefault();
    }
  });
});
</script>
</body>
</html>
