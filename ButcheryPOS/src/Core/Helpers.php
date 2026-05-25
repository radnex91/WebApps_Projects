<?php
// ButcheryPOS - Global Helper Functions

/**
 * HTML-escape a string
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Translate a key using the loaded language files
 * @param string $key Translation key
 * @param array|null $replacements Placeholder replacements (e.g., ['days' => 3])
 */
function t(string $key, ?array $replacements = null): string
{
    global $translations, $lang;
    $value = $translations[$lang][$key] ?? $translations['en'][$key] ?? $key;
    if ($replacements !== null) {
        foreach ($replacements as $search => $replace) {
            $value = str_replace(':' . $search, (string)$replace, $value);
        }
    }
    return $value;
}

/**
 * Format money value with currency
 */
function money(float $amount, string $currency = 'XAF'): string
{
    return number_format($amount, 0, ',', ' ') . ' ' . $currency;
}

/**
 * Set a flash message in session
 */
function flash(string $type, string $message): void
{
    if (!isset($_SESSION['flashes'])) {
        $_SESSION['flashes'] = [];
    }
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

/**
 * Pull and clear all flash messages
 */
function pull_flashes(): array
{
    $flashes = $_SESSION['flashes'] ?? [];
    $_SESSION['flashes'] = [];
    return $flashes;
}

/**
 * HTTP redirect
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Get asset URL
 */
function asset_url(string $path): string
{
    global $config;
    return ($config['app']['base_path'] ?? '') . '/public/assets/' . $path;
}

/**
 * Get page URL
 */
function url(string $path): string
{
    global $config;
    return ($config['app']['base_path'] ?? '') . '/' . ltrim($path, '/');
}

/**
 * Check if current page matches a route
 */
function is_current_page(string $page): bool
{
    return ($_GET['page'] ?? '') === $page;
}

/**
 * Generate a random reference string
 */
function generate_reference(string $prefix = ''): string
{
    return $prefix . date('YmdHis') . '-' . bin2hex(random_bytes(3));
}

/**
 * Format a date for display
 */
function format_date(?string $date, string $format = 'd/m/Y H:i'): string
{
    if ($date === null) return '-';
    return date($format, strtotime($date));
}

/**
 * Format a date as short date only
 */
function format_date_short(?string $date): string
{
    return format_date($date, 'd/m/Y');
}

/**
 * Get days difference between two dates
 */
function days_between(string $date1, string $date2 = 'now'): int
{
    $d1 = new DateTime($date1);
    $d2 = new DateTime($date2);
    return (int)$d2->diff($d1)->format('%r%a');
}