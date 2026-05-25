<?php
// ============================================================
//  MediCore ERP — Gestionnaire d'erreurs global
//  Log toutes les erreurs, affiche une page propre en prod
// ============================================================

// ── Mode dev vs prod ──────────────────────────────────────
// Définir APP_ENV = 'dev' dans config.php pour voir les erreurs à l'écran
if (!defined('APP_ENV')) {
    define('APP_ENV', 'prod');
}

// ── Dossier de logs ──────────────────────────────────────
define('ERROR_LOG_DIR', dirname(__DIR__) . '/logs');
define('ERROR_LOG_FILE', ERROR_LOG_DIR . '/errors.log');

if (!is_dir(ERROR_LOG_DIR)) {
    @mkdir(ERROR_LOG_DIR, 0755, true);
}

// ── Helper : écrire dans le log ──────────────────────────
function _log_error(string $level, string $message, string $file = '', int $line = 0, ?Throwable $e = null): void {
    $ts = date('Y-m-d H:i:s');
    $ctx = '';
    if ($file) $ctx .= " | Fichier: $file";
    if ($line) $ctx .= ":$line";
    if ($e) {
        $ctx .= " | Exception: " . get_class($e);
        $trace = $e->getTraceAsString();
        if (strlen($trace) > 500) $trace = substr($trace, 0, 500) . '...';
        $ctx .= "\nTrace:\n$trace";
    }
    $entry = "[$ts] [$level] $message$ctx\n" . str_repeat('-', 80) . "\n";

    // Rotation : max 5 Mo par fichier
    if (file_exists(ERROR_LOG_FILE) && filesize(ERROR_LOG_FILE) > 5_000_000) {
        $old = ERROR_LOG_DIR . '/errors-' . date('Y-m-d-His') . '.log';
        @rename(ERROR_LOG_FILE, $old);
    }

    @file_put_contents(ERROR_LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

// ── Détecter si la requête attend du JSON ────────────────
function _is_api_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/api/') !== false) return true;
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/json') !== false && stripos($accept, 'text/html') === false) return true;
    return false;
}

// ── Afficher l'erreur selon le contexte ──────────────────
function _respond_error(int $code, string $message, bool $showDetail = false, ?Throwable $e = null): void {
    // Vider le buffer pour garantir l'affichage
    while (ob_get_level() > 0) ob_end_clean();
    if (!headers_sent()) http_response_code($code);

    if (_is_api_request()) {
        header('Content-Type: application/json');
        $out = ['error' => $message];
        if ($showDetail && $e) {
            $out['detail'] = $e->getMessage();
            $out['file'] = $e->getFile();
            $out['line'] = $e->getLine();
        }
        echo json_encode($out, JSON_UNESCAPED_UNICODE);
    } else {
        if ($showDetail && $e) {
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur — MediCore</title>';
            echo '<style>body{font-family:system-ui,sans-serif;background:#0a0e1a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
            echo '.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:2rem;max-width:600px;width:90%}';
            echo 'h1{color:#ef4444;margin:0 0 1rem}code{background:#0f172a;padding:2px 6px;border-radius:4px;font-size:0.9em}';
            echo 'a{color:#3b82f6}</style></head><body><div class="box">';
            echo '<h1>⚠️ Erreur serveur</h1>';
            echo '<p>' . _h($message) . '</p>';
            echo '<details><summary>Détails techniques</summary>';
            echo '<p><code>' . _h($e->getFile()) . ':' . $e->getLine() . '</code></p>';
            echo '<pre style="background:#0f172a;padding:1rem;border-radius:8px;overflow-x:auto;font-size:0.85em;color:#f87171">' . _h($e->getMessage()) . '</pre>';
            echo '</details>';
            echo '<p><a href="javascript:history.back()">← Retour</a></p>';
            echo '</div></body></html>';
        } else {
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur — MediCore</title>';
            echo '<style>body{font-family:system-ui,sans-serif;background:#0a0e1a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
            echo '.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:2rem;max-width:500px;width:90%;text-align:center}';
            echo 'h1{color:#ef4444;margin:0 0 1rem}a{color:#3b82f6}</style></head><body><div class="box">';
            echo '<h1>⚠️ Erreur serveur</h1>';
            echo '<p>' . _h($message) . '</p>';
            echo '<p><a href="javascript:history.back()">← Retour</a> · <a href="' . (defined('APP_URL') ? APP_URL : '/') . '">Accueil</a></p>';
            echo '</div></body></html>';
        }
    }
    exit;
}

function _h(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Handler : exceptions non catchées ────────────────────
set_exception_handler(function (Throwable $e): void {
    $isDev = (defined('APP_ENV') && APP_ENV === 'dev');
    _log_error('FATAL', 'Exception non gérée', $e->getFile(), $e->getLine(), $e);

    $public = $isDev ? $e->getMessage() : 'Une erreur inattendue s\'est produite.';
    _respond_error(500, $public, $isDev, $e);
});

// ── Handler : erreurs PHP (warnings, notices, etc.) ──────
set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): bool {
    // Respecte error_reporting (ignore les @ et les niveaux désactivés)
    if (!(error_reporting() & $errno)) return false;

    $levels = [
        E_ERROR             => 'ERROR',
        E_WARNING           => 'WARNING',
        E_PARSE             => 'PARSE',
        E_NOTICE            => 'NOTICE',
        E_CORE_ERROR        => 'CORE_ERROR',
        E_CORE_WARNING      => 'CORE_WARNING',
        E_COMPILE_ERROR     => 'COMPILE_ERROR',
        E_COMPILE_WARNING   => 'COMPILE_WARNING',
        E_USER_ERROR        => 'USER_ERROR',
        E_USER_WARNING      => 'USER_WARNING',
        E_USER_NOTICE       => 'USER_NOTICE',
        E_STRICT            => 'STRICT',
        E_DEPRECATED        => 'DEPRECATED',
        E_USER_DEPRECATED   => 'USER_DEPRECATED',
    ];
    $level = $levels[$errno] ?? 'UNKNOWN';
    _log_error($level, $errstr, $errfile, $errline);

    // En dev, afficher seulement les fatales ; les warnings sont logués uniquement
    $isDev = (defined('APP_ENV') && APP_ENV === 'dev');
    $fatal = in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);

    if ($isDev && $fatal) {
        while (ob_get_level() > 0) ob_end_clean();
        echo "<pre style='background:#0f172a;color:#f87171;padding:1rem;font-size:0.85em'>";
        echo "<strong>[$level]</strong> $errstr\n<em>$errfile:$errline</em></pre>";
        exit;
    }

    if ($fatal) {
        _respond_error(500, 'Une erreur critique s\'est produite.');
    }
    return true; // Empêche l'affichage PHP par défaut
});

// ── Handler : shutdown (erreurs fatales non catchables) ──
register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        _log_error('SHUTDOWN', $error['message'], $error['file'], $error['line']);
        // Vider le buffer pour afficher l'erreur
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) http_response_code(500);
        if (defined('APP_ENV') && APP_ENV === 'dev') {
            echo "<pre style='background:#0f172a;color:#f87171;padding:1rem;font-size:0.85em'>" . _h($error['message']) . "\n" . _h($error['file']) . ':' . $error['line'] . '</pre>';
        } else {
            if (!headers_sent()) {
                echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>Erreur — MediCore</title>';
                echo '<style>body{font-family:system-ui,sans-serif;background:#0a0e1a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}';
                echo '.box{background:#1e293b;border:1px solid #334155;border-radius:12px;padding:2rem;max-width:500px;width:90%;text-align:center}';
                echo 'h1{color:#ef4444;margin:0 0 1rem}a{color:#3b82f6}</style></head><body><div class="box">';
                echo '<h1>⚠️ Erreur critique</h1><p>Le serveur a rencontré un problème.</p>';
                echo '<p><a href="javascript:history.back()">← Retour</a></p></div></body></html>';
            }
        }
    }
});
