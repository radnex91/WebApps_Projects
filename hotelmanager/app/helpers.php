<?php
/**
 * HotelPro Suite - Fonctions helpers globales
 */

/**
 * Échappe une chaîne pour l'affichage HTML
 */
function e(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Formate un montant en FCFA
 */
function format_money(float $amount): string
{
    return number_format($amount, 0, ',', ' ') . ' ' . HOTEL_DEVISE;
}

/**
 * Formate une date en français
 */
function format_date(string $date, string $format = 'd/m/Y'): string
{
    if (empty($date)) return '-';
    return (new DateTime($date))->format($format);
}

/**
 * Formate une date+heure
 */
function format_datetime(string $datetime): string
{
    if (empty($datetime)) return '-';
    return (new DateTime($datetime))->format('d/m/Y H:i');
}

/**
 * Génère une référence unique
 * Ex: RES-202504-00042
 */
function generate_reference(string $prefix, string $table, string $col = 'reference'): string
{
    $yymm    = date('Ym');
    $pattern = $prefix . '-' . $yymm . '-%';

    $stmt = Database::query(
        "SELECT MAX(CAST(SUBSTRING_INDEX($col, '-', -1) AS UNSIGNED)) AS max_n FROM $table WHERE $col LIKE ?",
        [$pattern]
    );
    $row = $stmt->fetch();
    $next = ($row['max_n'] ?? 0) + 1;

    return sprintf('%s-%s-%05d', $prefix, $yymm, $next);
}

/**
 * Hash bcrypt sécurisé
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
}

/**
 * Vérifie un mot de passe contre son hash
 */
function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Génère un token CSRF
 */
function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

/**
 * Champ CSRF hidden pour les formulaires
 */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . csrf_token() . '">';
}

/**
 * Valide le token CSRF
 */
function verify_csrf(): bool
{
    $token = $_POST[CSRF_TOKEN_NAME] ?? '';
    return hash_equals($_SESSION[CSRF_TOKEN_NAME] ?? '', $token);
}

/**
 * Redirige vers une URL
 */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/**
 * Définit un message flash
 */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $message];
}

/**
 * Récupère et vide les messages flash
 */
function get_flash(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/**
 * Vérifie si l'utilisateur a une permission
 */
function has_permission(string $permission): bool
{
    if (!isset($_SESSION['user_permissions'])) return false;
    $perms = $_SESSION['user_permissions'];
    return in_array('all', $perms) || in_array($permission, $perms);
}

/**
 * Journalise une action dans la table logs
 */
function log_action(string $action, string $module, ?int $objet_id = null, ?string $objet_type = null, ?string $details = null): void
{
    try {
        Database::query(
            "INSERT INTO logs (user_id, action, module, objet_type, objet_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $_SESSION['user_id'] ?? null,
                $action,
                $module,
                $objet_type,
                $objet_id,
                $details,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]
        );
    } catch (Exception $e) {
        error_log('Log failed: ' . $e->getMessage());
    }
}

/**
 * Sanitise et valide les données POST
 */
function sanitize(array $data): array
{
    return array_map(function ($v) {
        return is_string($v) ? trim(strip_tags($v)) : $v;
    }, $data);
}

/**
 * Badge HTML selon statut réservation
 */
function badge_statut_reservation(string $statut): string
{
    $map = [
        'en_attente' => ['warning',  'En attente'],
        'confirmee'  => ['info',     'Confirmée'],
        'checkin'    => ['success',  'Check-in'],
        'checkout'   => ['secondary','Check-out'],
        'annulee'    => ['danger',   'Annulée'],
        'no_show'    => ['dark',     'No-show'],
    ];
    [$cls, $label] = $map[$statut] ?? ['secondary', $statut];
    return "<span class='badge bg-{$cls}'>" . e($label) . "</span>";
}

/**
 * Badge HTML selon statut chambre
 */
function badge_statut_chambre(string $statut): string
{
    $map = [
        'disponible'  => ['success', 'Disponible'],
        'occupee'     => ['danger',  'Occupée'],
        'nettoyage'   => ['warning', 'Nettoyage'],
        'maintenance' => ['dark',    'Maintenance'],
    ];
    [$cls, $label] = $map[$statut] ?? ['secondary', $statut];
    return "<span class='badge bg-{$cls}'>" . e($label) . "</span>";
}
