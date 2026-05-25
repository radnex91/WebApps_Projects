<?php
// ============================================================
//  MediCore ERP - Configuration base de données
//  Modifiez ces paramètres selon votre configuration XAMPP
// ============================================================

// Gestionnaire d'erreurs global (doit être chargé avant tout)
define('APP_ENV', 'dev'); // dev = affiche les erreurs, prod = log seulement
require_once __DIR__ . '/error_handler.php';

// Icônes centralisées
if (!defined('ICON_NAV')) require_once __DIR__ . '/icons.php';

define('DB_HOST',    'localhost');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_NAME',    'medicore');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',    'MediCore ERP');
define('APP_VERSION', '2.4');
// Détection automatique du protocole et de l'hôte
// Permet l'accès depuis n'importe quel poste du réseau LAN
$_appProtocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_appHost     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$_appPath     = str_replace('\\', '/', dirname(__DIR__));
$_appDocRoot  = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
$_appBase     = $_appDocRoot ? str_replace($_appDocRoot, '', $_appPath) : '/medicore';
define('APP_URL', $_appProtocol . '://' . $_appHost . $_appBase);

// Clé secrète HMAC — changez en production
define('APP_SECRET', 'mc_s3cr3t_k3y_ch4ng3_m01_3n_pr0duct10n_2026!xZqP9mLwR2vK8nT');

// Durée de session (secondes) — 8 heures
define('SESSION_DURATION', 28800);

// Connexion PDO singleton
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
                $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            _log_error('DB_CONNECTION', 'Impossible de se connecter à la BDD', __FILE__, __LINE__, $e);
            throw new RuntimeException('Connexion base de données impossible');
        }
    }
    return $pdo;
}

// Paramètres application (police, monnaie...)
function invalidate_settings_cache(): void {
    // Les static vars se réinitialisent entre requêtes HTTP automatiquement
}

function get_settings(): array {
    static $s = null;
    if ($s === null) {
        try {
            $rows = getDB()->query("SELECT cle, valeur FROM app_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
            $s = $rows ?: [];
        } catch (Exception $e) {
            // Table absente : la créer silencieusement
            try {
                getDB()->exec("CREATE TABLE IF NOT EXISTS `app_settings` (
                    `cle`    VARCHAR(60)  NOT NULL,
                    `valeur` VARCHAR(500) NOT NULL DEFAULT '',
                    `label`  VARCHAR(120) NOT NULL DEFAULT '',
                    PRIMARY KEY (`cle`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (Exception $e2) { _log_error('SETTINGS', 'Echec création table app_settings', __FILE__, __LINE__, $e2); }
            $s = [];
        }
    }
    return $s;
}

// Roles dynamiques depuis la BDD (cache session)
function invalidate_roles_cache(): void {
    unset($_SESSION['_all_roles'], $_SESSION['_roles_map']);
}

function get_roles_map(): array {
    if (isset($_SESSION['_roles_map']) && !empty($_SESSION['_roles_map'])) {
        return $_SESSION['_roles_map'];
    }
    try {
        $rows = getDB()->query(
            "SELECT role, label, couleur, description FROM roles_config WHERE actif=1 ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
        $map = [];
        foreach ($rows as $r) {
            $map[$r['role']] = $r;
        }
        if (!empty($map)) {
            $_SESSION['_roles_map'] = $map;
        }
        return $map;
    } catch (Exception $e) {
        _log_error('ROLES', 'Echec lecture roles_config', __FILE__, __LINE__, $e);
        return [];
    }
}

function get_all_roles(): array {
    $map = get_roles_map();
    return !empty($map) ? array_keys($map) : ['admin','medecin','infirmier','pharmacien','comptable'];
}

function role_label(string $role): string {
    $map = get_roles_map();
    return $map[$role]['label'] ?? ucfirst($role);
}

function role_couleur(string $role): string {
    $map = get_roles_map();
    return $map[$role]['couleur'] ?? '#3b82f6';
}

function setting(string $key, string $default = ''): string {
    return get_settings()[$key] ?? $default;
}

// --- Préférences par utilisateur (surcharge des paramètres globaux) ---

function get_user_preferences(): array {
    static $up = null;
    if ($up === null) {
        $uid = $_SESSION['user_id'] ?? null;
        if ($uid === null) { $up = []; }
        else {
            try {
                $stmt = getDB()->prepare("SELECT cle, valeur FROM user_preferences WHERE user_id = ?");
                $stmt->execute([$uid]);
                $up = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
            } catch (Exception $e) {
                try {
                    getDB()->exec("CREATE TABLE IF NOT EXISTS `user_preferences` (
                        `user_id` INT(11) NOT NULL,
                        `cle`     VARCHAR(60) NOT NULL,
                        `valeur`  VARCHAR(500) NOT NULL DEFAULT '',
                        PRIMARY KEY (`user_id`, `cle`),
                        INDEX `idx_user` (`user_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                } catch (Exception $e2) { _log_error('USER_PREFS', 'Echec création table user_preferences', __FILE__, __LINE__, $e2); }
                $up = [];
            }
        }
    }
    return $up;
}

function user_pref(string $key, string $default = ''): string {
    $up = get_user_preferences();
    if (isset($up[$key]) && $up[$key] !== '') return $up[$key];
    return setting($key, $default);
}

function save_user_pref(string $key, string $value): void {
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid === null) return;
    $stmt = getDB()->prepare(
        "INSERT INTO user_preferences (user_id, cle, valeur) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE valeur = VALUES(valeur)"
    );
    $stmt->execute([$uid, $key, $value]);
}

function delete_user_prefs(): void {
    $uid = $_SESSION['user_id'] ?? null;
    if ($uid === null) return;
    getDB()->prepare("DELETE FROM user_preferences WHERE user_id = ?")->execute([$uid]);
}

function fmt_money(float $amount, bool $showSymbol = true): string {
    $s       = get_settings();
    $dec     = (int)($s['currency_decimals']  ?? 0);
    $decSep  = $s['currency_dec_sep']  ?? ',';
    $thouSep = $s['currency_thou_sep'] ?? ' ';
    $symbol  = $s['currency_symbol']   ?? 'FCFA';
    $pos     = $s['currency_position'] ?? 'after';

    $formatted = number_format($amount, $dec, $decSep, $thouSep);
    if (!$showSymbol) return $formatted;
    return $pos === 'before' ? $symbol . $formatted : $formatted . $symbol;
}

function fmt_date(string $dateStr, bool $withTime = false): string {
    if (!$dateStr || $dateStr === '0000-00-00') return '-';
    $fmt = setting('date_format', 'd/m/Y');
    if ($withTime) $fmt .= ' H:i';
    return date($fmt, strtotime($dateStr));
}
