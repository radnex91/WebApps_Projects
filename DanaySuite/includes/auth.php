<?php

declare(strict_types=1);

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;

    if ($user !== null && (int) $user['id'] === (int) $_SESSION['user_id']) {
        return $user;
    }

    $stmt = db()->prepare('SELECT id, full_name, email, role FROM users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    return $user;
}

function requireAuth(): array
{
    $user = currentUser();

    if ($user === null) {
        flash('error', 'Connectez-vous pour acceder a votre messagerie.');
        redirect('login.php');
    }

    return $user;
}

function loginUser(array $user): void
{
    $_SESSION['user_id'] = (int) $user['id'];
}

function logoutUser(): void
{
    unset($_SESSION['user_id']);
}
