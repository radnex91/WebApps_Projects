<?php
// ============================================================
// includes/helpers.php - Fonctions utilitaires
// ============================================================

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: ' . BASE_URL . '/index.php');
        exit;
    }
}

function requireRole(array $roles): void {
    requireLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        http_response_code(403);
        die('<h1>Accès refusé</h1><p>Vous n\'avez pas les permissions nécessaires.</p>');
    }
}

function getPermissionMap(): array {
    return [
        'dashboard'     => ['Tableau de bord',       'Principal'],
        'pos'           => ['Point de Vente',        'Principal'],
        'products'      => ['Produits',              'Inventaire'],
        'categories'    => ['Catégories',            'Inventaire'],
        'stock_view'    => ['Voir le Stock',          'Inventaire'],
        'stock_add'     => ['Entrée / Sortie Stock',  'Inventaire'],
        'stock_adjust'  => ['Ajuster le Stock',       'Inventaire'],
        'transfers'     => ['Transferts',             'Inventaire'],
        'sales'         => ['Historique Ventes',      'Ventes'],
        'customers'     => ['Clients',                'Ventes'],
        'invoices'      => ['Factures',               'Ventes'],
        'reports'       => ['Rapports',               'Analyse'],
        'stores'        => ['Boutiques',              'Administration'],
        'warehouses'    => ['Magasins',               'Administration'],
        'users'         => ['Utilisateurs',            'Administration'],
        'settings'      => ['Paramètres',             'Administration'],
    ];
}

function hasPermission(string $permission): bool {
    if (!isLoggedIn()) return false;
    if (($_SESSION['user_role'] ?? '') === 'admin') return true;
    return in_array($permission, $_SESSION['user_permissions'] ?? [], true);
}

function requirePermission(string $permission): void {
    requireLogin();
    if (!hasPermission($permission)) {
        http_response_code(403);
        die('<h1>Accès refusé</h1><p>Vous n\'avez pas la permission nécessaire pour accéder à cette page.</p>');
    }
}

function currentUser(): array {
    return $_SESSION['user'] ?? [];
}

function currentStoreId(): int {
    return (int)($_SESSION['store_id'] ?? 1);
}

function currentWarehouseId(): int {
    return (int)($_SESSION['warehouse_id'] ?? 1);
}

function currentCaisseId(): int {
    return (int)($_SESSION['caisse_id'] ?? 0);
}

/**
 * Vérifie si l'utilisateur a une session de caisse active.
 * Tout le monde (admin, manager, caissier) doit ouvrir une caisse avant de vendre.
 */
function hasCaisseSessionOpen(): bool {
    if (currentCaisseId() === 0) return false;
    $sessionModel = new CaisseSession();
    $active = $sessionModel->getActiveSession(currentCaisseId());
    if (!$active) {
        unset($_SESSION['caisse_id']);
        return false;
    }
    return true;
}

/**
 * Retourne la session de caisse active de l'utilisateur courant,
 * ou null si aucune session n'est ouverte.
 */
function getActiveCaisseSession(): ?array {
    if (!hasCaisseSessionOpen()) return null;
    $sessionModel = new CaisseSession();
    return $sessionModel->getActiveSession(currentCaisseId());
}

function formatMoney(float $amount, ?string $currency = null): string {
    if ($currency === null) {
        $currency = $GLOBALS['appSettings']['currency_symbol'] ?? 'FCFA';
    }
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

function formatDate(string $date, string $format = 'd/m/Y H:i'): string {
    return date($format, strtotime($date));
}

function generateInvoiceNumber(int $storeId): string {
    $db = Database::getInstance();
    $store = (new Store())->find($storeId);
    $code = $store ? strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $store['code'] ?? '')) : 'STR' . $storeId;
    $date = date('dmY');
    $prefix = $code . '/' . $date . '/';
    $last = $db->prepare("SELECT invoice_number FROM sales WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 1");
    $last->execute([$prefix . '%']);
    $row = $last->fetchColumn();
    if ($row) {
        $parts = explode('/', $row);
        $num = (int)end($parts) + 1;
    } else {
        $num = 1;
    }
    return $prefix . str_pad($num, 4, '0', STR_PAD_LEFT);
}

function generateTransferReference(): string {
    return 'TRF-' . date('YmdHis') . rand(10, 99);
}

function sanitize(mixed $input): string {
    if (is_array($input)) return '';
    return htmlspecialchars(trim((string)$input), ENT_QUOTES, 'UTF-8');
}

function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function jsonResponse(bool $success, string $message = '', mixed $data = null, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message, 'data' => $data]);
    exit;
}

function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function uploadImage(array $file, string $folder = 'products'): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > UPLOAD_MAX_SIZE) return null;
    if (!in_array($file['type'], ALLOWED_IMAGE_TYPES)) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . strtolower($ext);
    $dir = UPLOAD_PATH . $folder . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return 'assets/uploads/' . $folder . '/' . $filename;
    }
    return null;
}

function paginate(int $total, int $page, int $perPage = ITEMS_PER_PAGE): array {
    $totalPages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages,
    ];
}

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function stockStatusClass(int $qty, int $minAlert): string {
    if ($qty <= 0) return 'danger';
    if ($qty <= $minAlert) return 'warning';
    return 'success';
}

function hexToRgba(string $hex, float $alpha): string {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba($r,$g,$b,$alpha)";
}

function lightenHex(string $hex, int $pct): string {
    $hex = ltrim($hex, '#');
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    $r = min(255, $r + round((255 - $r) * $pct / 100));
    $g = min(255, $g + round((255 - $g) * $pct / 100));
    $b = min(255, $b + round((255 - $b) * $pct / 100));
    return '#' . str_pad(dechex($r), 2, '0') . str_pad(dechex($g), 2, '0') . str_pad(dechex($b), 2, '0');
}
