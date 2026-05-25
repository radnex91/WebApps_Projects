<?php
// Fonctions utilitaires - DanayLedger v2

require_once __DIR__ . '/../config/constants.php';

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token']) || (isset($_SESSION['csrf_token_time']) && (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRY)) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    startSecureSession();
    if (!isset($_SESSION['csrf_token'])) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(generateCSRFToken()) . '">';
}

function setFlash(string $type, string $message): void {
    startSecureSession();
    $_SESSION['flash_' . $type] = $message;
}

function getFlash(string $type): ?string {
    startSecureSession();
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function displayFlashMessages(): string {
    $html = '';
    foreach (['success', 'error', 'warning', 'info'] as $type) {
        $msg = getFlash($type);
        if ($msg) {
            $icon = match($type) {
                'success' => 'check-circle-fill',
                'error'   => 'exclamation-triangle-fill',
                'warning' => 'exclamation-triangle-fill',
                'info'    => 'info-circle-fill',
                default   => 'info-circle-fill',
            };
            $html .= '<div class="alert alert-' . ($type === 'error' ? 'danger' : $type) . ' alert-dismissible fade show d-flex align-items-center" role="alert">';
            $html .= '<i class="bi bi-' . $icon . ' me-2"></i><div>' . e($msg) . '</div>';
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
        }
    }
    return $html;
}

function formatMoney(float $amount, string $currency = 'XOF'): string {
    $symbols = ['XOF' => 'FCFA', 'EUR' => '€', 'USD' => '$'];
    $symbol = $symbols[$currency] ?? $currency;
    return number_format($amount, 0, ',', ' ') . ' ' . $symbol;
}

function formatDate(?string $date, string $format = 'd/m/Y H:i'): string {
    if ($date === null || $date === '' || $date === '0000-00-00 00:00:00' || $date === '0000-00-00') return '-';
    try {
        $d = new DateTime($date);
        return $d->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

function formatDateShort(string $date): string {
    return formatDate($date, 'd/m/Y');
}

function generateReference(string $prefix = 'TRX'): string {
    return $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

function paginate(PDO $db, string $query, array $params = [], int $page = 1, int $perPage = ITEMS_PER_PAGE): array {
    // Compter le total sans ORDER BY (inutile pour un COUNT)
    $countQuery = preg_replace('/\s+ORDER\s+BY\s+.+$/i', '', $query);
    $countQuery = "SELECT COUNT(*) FROM ($countQuery) as total";
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $offset = ($page - 1) * $perPage;
    $totalPages = max(1, ceil($total / $perPage));

    // Ajouter LIMIT/OFFSET à la requête (qui a déjà son ORDER BY)
    $dataQuery = "$query LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($dataQuery);
    $stmt->execute($params);
    $data = $stmt->fetchAll();

    return ['data' => $data, 'total' => $total, 'page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages];
}

function renderPagination(int $currentPage, int $totalPages, string $baseUrl = ''): string {
    if ($totalPages <= 1) return '';
    $sep = (strpos($baseUrl, '?') !== false) ? '&' : '?';
    $html = '<nav><ul class="pagination justify-content-center mb-0">';
    $prevDisabled = $currentPage <= 1 ? 'disabled' : '';
    $html .= '<li class="page-item ' . $prevDisabled . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($currentPage - 1) . '">&laquo;</a></li>';

    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $sep . 'page=1">1</a></li>';
        if ($start > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? 'active' : '';
        $html .= '<li class="page-item ' . $active . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }

    $nextDisabled = $currentPage >= $totalPages ? 'disabled' : '';
    $html .= '<li class="page-item ' . $nextDisabled . '"><a class="page-link" href="' . $baseUrl . $sep . 'page=' . ($currentPage + 1) . '">&raquo;</a></li>';
    $html .= '</ul></nav>';
    return $html;
}

function cleanInput(string $input): string {
    return trim(htmlspecialchars($input, ENT_QUOTES, 'UTF-8'));
}

function validateEmail(string $email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function redirectWithMessage(string $url, string $type, string $message): void {
    setFlash($type, $message);
    header('Location: ' . $url);
    exit;
}

function getStatusBadge(string $status): string {
    $labels = STATUSES;
    $classes = STATUS_BADGES;
    $label = $labels[$status] ?? $status;
    $class = $classes[$status] ?? 'bg-secondary';
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function getTypeBadge(string $type): string {
    $types = ['revenu' => 'bg-success', 'depense' => 'bg-danger', 'transfert' => 'bg-info'];
    $class = $types[$type] ?? 'bg-secondary';
    $labels = TRANSACTION_TYPES;
    $label = $labels[$type] ?? $type;
    return '<span class="badge ' . $class . '">' . e($label) . '</span>';
}

function uploadJustificatif(array $file, string $entityType, int $entityId): ?string {
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > UPLOAD_MAX_SIZE) return null;
    if (!in_array($file['type'], UPLOAD_ALLOWED_TYPES)) return null;

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $entityType . '_' . $entityId . '_' . time() . '.' . $ext;
    $filepath = UPLOAD_DIR . $filename;

    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return $filename;
    }
    return null;
}

function getAgences(PDO $db): array {
    $stmt = $db->query("SELECT id, codeagence, nomagence FROM agence WHERE statut = 'actif' ORDER BY nomagence");
    return $stmt->fetchAll();
}

function getBanks(PDO $db): array {
    $stmt = $db->query("SELECT id, codeBank, nombank FROM bank WHERE statut = 'actif' ORDER BY nombank");
    return $stmt->fetchAll();
}

function getProprietaires(PDO $db): array {
    $stmt = $db->query("SELECT id, codeproprio, nomProprio, groupe FROM proprietaire WHERE statut = 'actif' ORDER BY nomProprio");
    return $stmt->fetchAll();
}

function getCategories(PDO $db, ?string $type = null): array {
    if ($type) {
        $stmt = $db->prepare("SELECT id, code, nom FROM categories WHERE type = ? AND statut = 'actif' ORDER BY nom");
        $stmt->execute([$type]);
    } else {
        $stmt = $db->query("SELECT id, code, nom, type FROM categories WHERE statut = 'actif' ORDER BY type, nom");
    }
    return $stmt->fetchAll();
}

function getVehicules(PDO $db): array {
    $stmt = $db->query("SELECT v.id, v.immatriculation, v.marque, v.modele, a.nomagence as agence_nom FROM vehicules v LEFT JOIN agence a ON v.agence_id = a.id WHERE v.statut = 'actif' ORDER BY v.immatriculation");
    return $stmt->fetchAll();
}

function exportCSV(string $filename, array $headers, array $data): void {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    fputcsv($output, $headers, ';');
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    fclose($output);
    exit;
}

function getSystemParam(PDO $db, string $key, string $default = ''): string {
    $stmt = $db->prepare("SELECT valeur FROM parametres WHERE cle = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetchColumn();
    return $result ?: $default;
}

function setSystemParam(PDO $db, string $key, string $value, string $description = ''): void {
    $stmt = $db->prepare("INSERT INTO parametres (cle, valeur, description, updated_by) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE valeur = ?, updated_by = ?");
    $userId = $_SESSION['user_id'] ?? null;
    $stmt->execute([$key, $value, $description, $userId, $value, $userId]);
}

function getDashboardStats(PDO $db): array {
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    // Recettes du jour
    $stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement), 0) FROM recette WHERE date = ? AND statut = 'validee'");
    $stmt->execute([$today]);
    $recettesJour = (float) $stmt->fetchColumn();

    // Dépenses du jour
    $stmt = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM depenses WHERE date_depense = ? AND statut = 'validee'");
    $stmt->execute([$today]);
    $depensesJour = (float) $stmt->fetchColumn();

    // Recettes camions du jour
    $stmt = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM recettes_camions WHERE date_recette = ? AND statut = 'validee'");
    $stmt->execute([$today]);
    $recettesCamionsJour = (float) $stmt->fetchColumn();

    // Recettes du mois
    $stmt = $db->prepare("SELECT COALESCE(SUM(montantexpedition + montantAccompagnement), 0) FROM recette WHERE date BETWEEN ? AND ? AND statut = 'validee'");
    $stmt->execute([$monthStart, $monthEnd]);
    $recettesMois = (float) $stmt->fetchColumn();

    // Dépenses du mois
    $stmt = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM depenses WHERE date_depense BETWEEN ? AND ? AND statut = 'validee'");
    $stmt->execute([$monthStart, $monthEnd]);
    $depensesMois = (float) $stmt->fetchColumn();

    // Versements du mois
    $stmt = $db->prepare("SELECT COALESCE(SUM(sommeverse), 0) FROM versement WHERE dateversement BETWEEN ? AND ? AND statut = 'validee'");
    $stmt->execute([$monthStart, $monthEnd]);
    $versementsMois = (float) $stmt->fetchColumn();

    // Transactions en attente
    $stmt = $db->query("SELECT
        (SELECT COUNT(*) FROM recette WHERE statut = 'en_attente') +
        (SELECT COUNT(*) FROM depenses WHERE statut = 'en_attente') +
        (SELECT COUNT(*) FROM recettes_camions WHERE statut = 'en_attente') +
        (SELECT COUNT(*) FROM versement WHERE statut = 'en_attente') as total");
    $enAttente = (int) $stmt->fetchColumn();

    return [
        'recettes_jour'       => $recettesJour,
        'depenses_jour'       => $depensesJour,
        'recettes_camions_jour' => $recettesCamionsJour,
        'solde_jour'          => $recettesJour + $recettesCamionsJour - $depensesJour,
        'recettes_mois'       => $recettesMois,
        'depenses_mois'       => $depensesMois,
        'versements_mois'     => $versementsMois,
        'solde_mois'          => $recettesMois - $depensesMois,
        'en_attente'          => $enAttente,
    ];
}