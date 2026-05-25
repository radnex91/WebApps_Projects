<?php
// Footer - DanayLedger
$currentUser = $currentUser ?? getCurrentUser();
$role = $_SESSION['user_role'] ?? '';
$roleLabel = getRoleLabel($role);
$userInitials = strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1) . mb_substr($_SESSION['full_name'] ?? '', 1, 1));
if (mb_strlen($_SESSION['full_name'] ?? '') < 2) $userInitials = strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1));
?>

    </div><!-- /.page-content -->
</div><!-- /.main-content -->
</div><!-- /.wrapper -->

<!-- Main Header -->
<header class="main-header" style="display:none;">
    <!-- Header is rendered in the page, not here -->
</header>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="<?php echo APP_URL; ?>/assets/js/app.js"></script>
</body>
</html>