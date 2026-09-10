<?php
/**
 * modules/assistant.php
 *
 * Endpoint AJAX de l'assistant intégré (moteur déterministe — voir config/assistant.php).
 *
 * Route : /assistant  (RewriteRule dans .htaccess → modules/assistant.php)
 * Action : ?action=chat  (POST, CSRF dans le corps `csrf=` conformément à verifyCsrf())
 *
 * Sécurité :
 *  - requirePermission('assistant.utiliser') (auto-seeded par le moteur).
 *  - verifyCsrf() sur le POST.
 *  - Rate-limit PHP (rateLimitConsume) en complément du rate-limit JS.
 *  - auditLog tronqué (200 chars) — aucune donnée personnelle.
 *  - Désactivé si assistant_active=0 (assistantActive() garde le widget hors-ligne ;
 *    ici on renvoie un refus propre si jamais l'endpoint est appelé après désactivation).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/assistant.php';
requirePermission('assistant.utiliser');

// ── Branche AJAX : chat ──────────────────────────────────────
if (($_GET['action'] ?? '') === 'chat') {
    verifyCsrf(); // lit $_POST['csrf'] ; 403 « Session expirée » si invalide
    header('Content-Type: application/json; charset=utf-8');

    // Rate-limit : 30 messages / minute par utilisateur (complément du garde-fou JS)
    $uid = (int)currentUser()['id'];
    $rl = rateLimitConsume('assistant.chat:' . $uid, 30, 60);
    if (!$rl['allowed']) {
        echo json_encode([
            'reply' => "Trop de questions en peu de temps — patientez quelques secondes puis réessayez.",
            'quick' => [],
            'links' => [],
        ]);
        exit;
    }

    // Garde-fou : si l'admin a désactivé l'assistant entre-temps
    if (!assistantActive()) {
        echo json_encode([
            'reply' => "L'assistant a été désactivé par l'administrateur.",
            'quick' => [],
            'links' => [],
        ]);
        exit;
    }

    $msg = trim($_POST['message'] ?? '');
    $ctx = [
        'page' => trim($_POST['page'] ?? ''),
        'role' => currentUser()['role'] ?? '',
    ];

    // Audit : log tronqué (pas de données personnelles a priori, tronqué par sécurité)
    if (function_exists('auditLog')) {
        auditLog('assistant.chat', mb_substr($msg, 0, 200, 'UTF-8'), null);
    }

    $resp = assistant_handle(['message' => $msg], $ctx);
    // Sérialisation sûre (HTML-safe)
    echo json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    exit;
}

// ── Page pleine (optionnelle) : liste l'aide globale ─────────
// Le MVP utilise le panneau latéral injecté par layout_foot() ; cette page
// sert de point d'entrée accessible /assistant avec un récapitulatif d'aide.
layout_head('Assistant', 'assistant');
showFlash();
?>
<div class="card">
  <div class="card-header"><div class="card-title">Assistant intégré PharmaCare</div></div>
  <div class="card-pad">
    <p class="text-sm" style="margin-bottom:16px;color:var(--text2,#475569);">
      L'assistant est disponible via le bouton flottant en bas à droite de chaque page.
      Il peut rechercher un médicament, consulter le stock, donner le chiffre du jour
      et vous guider dans les menus. Il n'effectue aucune action sensible.
    </p>
    <?php foreach ($GLOBALS['ASSISTANT_HELP'] as $key => $h): ?>
      <div style="margin-bottom:14px;">
        <div style="font-weight:600;margin-bottom:4px;"><?= e($h['titre']) ?></div>
        <ul style="margin:0;padding-left:20px;color:var(--text2,#475569);">
          <?php foreach ($h['points'] as $p): ?>
            <li class="text-sm"><?= e($p) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php
layout_foot();