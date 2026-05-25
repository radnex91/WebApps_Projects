<?php

function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function getSetting(string $key, string $default = ''): string {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT valeur FROM settings WHERE cle = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['valeur'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function formatDate(string $date, string $format = 'd/m/Y'): string {
    if (empty($date)) return '';
    $dt = new DateTime($date);
    return $dt->format($format);
}

function formatDatetime(string $datetime, string $format = 'd/m/Y H:i'): string {
    if (empty($datetime)) return '';
    $dt = new DateTime($datetime);
    return $dt->format($format);
}

function formatMoney(float $amount): string {
    return number_format($amount, 0, ',', ' ') . ' FCFA';
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || $token !== ($_SESSION['csrf_token'] ?? '')) {
            die('CSRF validation failed');
        }
    }
}

function flash(string $key, ?string $value = null): ?string {
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    $val = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $val;
}

function has_flash(string $key): bool {
    return isset($_SESSION['flash'][$key]);
}

function old(string $key, mixed $default = ''): mixed {
    return $_SESSION['old'][$key] ?? $default;
}

function getStatusBadge(string $status): string {
    $map = [
        'actif' => 'success',
        'inactif' => 'secondary',
        'credit' => 'success',
        'debit' => 'danger',
    ];
    $class = $map[$status] ?? 'primary';
    return "<span class=\"badge badge-$class\">$status</span>";
}
