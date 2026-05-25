<?php
function asset(string $path): string
{
    return BASE_URL . '/public/' . ltrim($path, '/');
}

function url(string $path): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function formatDate(string $date, string $format = 'd/m/Y'): string
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d ? $d->format($format) : $date;
}

function formatDateTime(string $datetime, string $format = 'd/m/Y H:i'): string
{
    $d = new DateTime($datetime);
    return $d->format($format);
}

function formatMoney(float|string $amount): string
{
    $symbole = Setting::get('devise_symbole', 'FCFA');
    $code    = Setting::get('devise_code', 'XAF');
    return number_format((float)$amount, 0, ',', ' ') . ' ' . $symbole;
}

function formatNumber(float|string $amount, int $decimals = 2): string
{
    return number_format((float)$amount, $decimals, ',', ' ');
}

function activeLink(string $path): string
{
    $current = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? '', '/');
    $match   = trim($path, '/');

    if ($match === '') {
        return $current === '' ? 'active' : '';
    }

    return str_starts_with($current, $match) ? 'active' : '';
}

function redirect(string $url): void
{
    header('Location: ' . BASE_URL . $url);
    exit;
}

function slugify(string $text): string
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text) ?: 'n-a';
}

function theme(): string
{
    $theme = Session::get('theme');
    if (!$theme) {
        $theme = Setting::get('theme_defaut', 'dark');
    }
    $allowed = ['dark', 'corporate', 'minimal'];
    return in_array($theme, $allowed) ? $theme : 'dark';
}

function themeLabel(): string
{
    $labels = ['dark' => 'Dark Pro', 'corporate' => 'Corporate Luxe', 'minimal' => 'Minimal'];
    return $labels[theme()] ?? 'Dark Pro';
}

function generateReference(string $prefix, int $id): string
{
    return strtoupper($prefix) . '-' . date('Y') . '-' . str_pad($id, 5, '0', STR_PAD_LEFT);
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_flash']['_old_input'][$key] ?? $default;
}

function formError(string $key): ?string
{
    $errors = $_SESSION['_flash']['_errors'] ?? [];
    return $errors[$key] ?? null;
}
