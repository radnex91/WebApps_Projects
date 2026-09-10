<?php
declare(strict_types=1);

/**
 * Pages d'erreur personnalisées PharmaCare.
 *
 * Rendu autonome (CSS inline) : aucune dépendance à la base de données ni aux
 * assets — la page s'affiche même si MySQL est tombé (503/500) ou si le CSS
 * ne charge pas. Aucun appel à getParam()/getDB() (qui mourraient en cours de
 * route) : on retombe sur les constantes APP_NAME / APP_URL de env.php, avec
 * des valeurs de secours si env.php lui-même n'a pas pu être chargé.
 *
 * Utilisé par : 404.php, 403.php, 500.php, et le 503 de config/database.php.
 */

// Tenter de charger env.php (constantes APP_URL / APP_NAME / IS_PROD) sans planter
// si l'environnement est déjà cassé.
if (!defined('APP_NAME')) {
    $envFile = __DIR__ . '/../config/env.php';
    if (is_file($envFile)) { require_once $envFile; }
}

function _erreur_app_url(): string {
    if (defined('APP_URL')) return APP_URL;
    // Reconstruire depuis le chemin du script (sous-dossier ou racine).
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $dir = str_replace('\\', '/', dirname($script));
    $dir = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . $dir;
}

function _erreur_app_nom(): string {
    return defined('APP_NAME') ? APP_NAME : 'PharmaCare';
}

function _erreur_connecte(): bool {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return !empty($_SESSION['user_id']);
    }
    if (!headers_sent()) { @session_start(); }
    return !empty($_SESSION['user_id']);
}

/**
 * Rend une page d'erreur brandée PharmaCare.
 *
 * @param int    $code       Code HTTP (404, 403, 500, 503…).
 * @param string $titre      Titre court affiché (ex : « Page introuvable »).
 * @param string $message    Explication affichée à l'utilisateur.
 * @param string $sousTitre  Petite ligne sous le titre (optionnel).
 * @param string $icone      Clé d'icône : '404' | '403' | '500' | '503' | 'alert'.
 */
function afficher_erreur(int $code, string $titre, string $message, string $sousTitre = '', string $icone = 'alert'): void {
    if (!headers_sent()) {
        http_response_code($code);
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
    }

    $appUrl  = _erreur_app_url();
    $appNom  = htmlspecialchars(_erreur_app_nom(), ENT_QUOTES, 'UTF-8');
    $version = defined('APP_VERSION') ? APP_VERSION : '';
    $titre   = htmlspecialchars($titre, ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $sous    = htmlspecialchars($sousTitre, ENT_QUOTES, 'UTF-8');

    $connecte   = _erreur_connecte();
    $btnLabel   = $connecte ? 'Retour au tableau de bord' : 'Retour à la connexion';
    $btnHref    = $connecte ? $appUrl . '/dashboard.php' : $appUrl . '/index.php';
    $secondLabel = 'Accueil';
    $secondHref  = $appUrl . '/index.php';

    // Accent par code (vert teal pour 404, ambre pour 403, rouge pour 5xx).
    $accents = [
        404 => ['#0D9488', '#14B8A6', '#5EEAD4'],
        403 => ['#D97706', '#F59E0B', '#FCD34D'],
        500 => ['#DC2626', '#EF4444', '#FCA5A5'],
        503 => ['#DC2626', '#EF4444', '#FCA5A5'],
    ];
    [$c1, $c2, $cLight] = $accents[$code] ?? ['#0D9488', '#14B8A6', '#5EEAD4'];

    // Icônes (stroke, currentColor — même langage que includes/layout.php).
    $icons = [
        '404'  => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/><line x1="8" y1="11" x2="14" y2="11"/>',
        '403'  => '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        '500'  => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        '503'  => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'alert'=> '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    ];
    $iconPath = $icons[$icone] ?? $icons['alert'];

    // Croix pharmaceutique (logo) inline.
    $logo = '<svg viewBox="0 0 512 512" width="40" height="40" aria-hidden="true">'
          . '<defs><linearGradient id="errbg" x1="0%" y1="0%" x2="100%" y2="100%">'
          . '<stop offset="0%" stop-color="' . $c1 . '"/><stop offset="100%" stop-color="' . $c2 . '"/>'
          . '</linearGradient></defs>'
          . '<rect x="24" y="24" width="464" height="464" rx="96" fill="url(#errbg)"/>'
          . '<rect x="194" y="124" width="124" height="264" rx="24" fill="#fff"/>'
          . '<rect x="124" y="194" width="264" height="124" rx="24" fill="#fff"/>'
          . '</svg>';

    $annee = (int)date('Y');

    echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
       . '<meta name="robots" content="noindex">'
       . '<title>' . $code . ' — ' . $titre . ' | ' . $appNom . '</title>'
       . '<style>'
       . '*{margin:0;padding:0;box-sizing:border-box}'
       . 'body{font-family:\'Manrope\',\'Segoe UI\',system-ui,-apple-system,sans-serif;'
       . 'min-height:100vh;display:flex;align-items:center;justify-content:center;'
       . 'padding:24px;color:#E2E8F0;position:relative;overflow-x:hidden;'
       . 'background:linear-gradient(135deg,#0F172A 0%,#1E293B 35%,#0F172A 70%,#134E4A 100%)}'
       // Cercles décoratifs animés (fonds)
       . '.bg{position:fixed;inset:0;pointer-events:none;z-index:0;overflow:hidden}'
       . '.c1,.c2,.c3{position:absolute;border-radius:50%}'
       . '.c1{width:600px;height:600px;border:2px solid ' . $c1 . ';top:-180px;right:-180px;opacity:.08;animation:float 20s ease-in-out infinite}'
       . '.c2{width:420px;height:420px;background:radial-gradient(circle,' . $c1 . ' 0,transparent 70%);bottom:-140px;left:-140px;opacity:.10;animation:pulse 8s ease-in-out infinite}'
       . '.c3{width:300px;height:300px;border:1px solid ' . $c2 . ';top:55%;left:28%;opacity:.10;animation:float 15s ease-in-out infinite reverse}'
       . '@keyframes float{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-30px) rotate(5deg)}}'
       . '@keyframes pulse{0%,100%{transform:scale(1);opacity:.08}50%{transform:scale(1.1);opacity:.12}}'
       . '@keyframes fadeUp{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:translateY(0)}}'
       // Carte glassmorphism
       . '.card{position:relative;z-index:10;max-width:560px;width:100%;text-align:center;'
       . 'background:rgba(15,23,42,.85);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);'
       . 'border:1px solid ' . $c1 . '33;border-radius:24px;padding:56px 40px;'
       . 'box-shadow:0 25px 50px -12px rgba(0,0,0,.5),0 0 60px ' . $c1 . '1a,inset 0 1px 0 rgba(255,255,255,.05);'
       . 'animation:fadeUp .5s ease-out}'
       . '.logo{width:64px;height:64px;margin:0 auto 24px;display:flex;align-items:center;justify-content:center;'
       . 'border-radius:18px;overflow:hidden;box-shadow:0 8px 24px ' . $c1 . '40}'
       . '.logo svg{width:40px;height:40px}'
       . '.code{font-size:96px;font-weight:800;line-height:1;letter-spacing:-2px;margin-bottom:8px;'
       . 'background:linear-gradient(135deg,' . $c1 . ' 0%,' . $c2 . ' 100%);'
       . '-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;color:transparent}'
       . '.ic{display:inline-flex;margin-bottom:20px;color:' . $cLight . '}'
       . '.ic svg{width:56px;height:56px;stroke:currentColor;fill:none;stroke-width:1.6;stroke-linecap:round;stroke-linejoin:round}'
       . 'h1{font-size:24px;font-weight:700;color:#F1F5F9;margin-bottom:12px}'
       . '.msg{font-size:15px;line-height:1.6;color:#94A3B8;margin-bottom:8px;max-width:420px;margin-left:auto;margin-right:auto}'
       . '.sub{font-size:13px;color:#64748B;margin-bottom:32px}'
       . '.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap}'
       . '.btn{display:inline-flex;align-items:center;gap:8px;padding:12px 24px;border-radius:12px;'
       . 'font-size:14px;font-weight:600;text-decoration:none;transition:all .2s;cursor:pointer;border:none}'
       . '.btn-primary{background:linear-gradient(135deg,' . $c1 . ' 0%,' . $c2 . ' 100%);color:#fff;box-shadow:0 8px 20px ' . $c1 . '33}'
       . '.btn-primary:hover{transform:translateY(-2px);box-shadow:0 12px 28px ' . $c1 . '55}'
       . '.btn-ghost{background:rgba(255,255,255,.05);color:#CBD5E1;border:1px solid rgba(255,255,255,.1)}'
       . '.btn-ghost:hover{background:rgba(255,255,255,.1);color:#F1F5F9}'
       . '.foot{margin-top:32px;padding-top:20px;border-top:1px solid rgba(255,255,255,.06);'
       . 'font-size:12px;color:#475569}'
       . '.foot strong{color:#64748B}'
       . '@media(max-width:480px){.card{padding:40px 24px}.code{font-size:72px}h1{font-size:20px}}'
       . '</style></head><body>'
       . '<div class="bg"><span class="c1"></span><span class="c2"></span><span class="c3"></span></div>'
       . '<main class="card">'
       . '<div class="logo">' . $logo . '</div>'
       . '<div class="code">' . $code . '</div>'
       . '<div class="ic"><svg viewBox="0 0 24 24">' . $iconPath . '</svg></div>'
       . '<h1>' . $titre . '</h1>'
       . '<p class="msg">' . $message . '</p>'
       . ($sous !== '' ? '<p class="sub">' . $sous . '</p>' : '')
       . '<div class="actions">'
       . '<a class="btn btn-primary" href="' . htmlspecialchars($btnHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($btnLabel, ENT_QUOTES, 'UTF-8') . '</a>'
       . '<a class="btn btn-ghost" href="' . htmlspecialchars($secondHref, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($secondLabel, ENT_QUOTES, 'UTF-8') . '</a>'
       . '</div>'
       . '<div class="foot"><strong>' . $appNom . '</strong>'
       . ($version !== '' ? ' · v' . htmlspecialchars($version, ENT_QUOTES, 'UTF-8') : '')
       . ' · © ' . $annee . '</div>'
       . '</main></body></html>';
}