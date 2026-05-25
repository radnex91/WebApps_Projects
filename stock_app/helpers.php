<?php
declare(strict_types=1);

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function user_permissions(): array
{
    $user = current_user();

    if (!$user) {
        return [];
    }

    return $user['permissions'] ?? [];
}

function user_can(string $permission): bool
{
    $permissions = user_permissions();

    return in_array($permission, $permissions, true);
}

function require_permission(string $permission): void
{
    if (!user_can($permission)) {
        set_flash('danger', 'Vous n avez pas les droits pour cette page.');
        redirect('index.php');
    }
}

function available_permissions(): array
{
    return [
        'view_dashboard' => 'Voir le tableau de bord',
        'manage_categories' => 'Gerer les categories',
        'manage_products' => 'Gerer les produits',
        'manage_movements' => 'Gerer les mouvements de stock',
        'manage_sales' => 'Gerer les ventes et imprimer les tickets',
        'view_reports' => 'Voir les rapports',
        'manage_users' => 'Gerer les utilisateurs',
        'manage_roles' => 'Gerer les roles et permissions',
    ];
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_number(float $value): string
{
    return number_format($value, 2, ',', ' ');
}
