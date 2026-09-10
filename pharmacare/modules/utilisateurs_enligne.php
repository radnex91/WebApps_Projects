<?php
declare(strict_types=1);
/**
 * Utilisateurs en ligne — surveillance temps réel des connexions.
 *
 * « En ligne » = activité de moins de 5 minutes (heartbeat géré par
 * includes/auth.php :: touchUserActivity(), throttlé 60 s par session).
 * Entre 5 et 30 minutes d'inactivité : « Absent » (session probablement
 * ouverte mais poste quitté). Page auto-actualisée toutes les 30 s.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
$db = getDB();

// Permission auto-seedée (même audience que le suivi des caissiers)
try {
    $db->exec("INSERT IGNORE INTO permissions (code, libelle, module)
               VALUES ('enligne.voir', 'Voir les utilisateurs en ligne', 'en_ligne')");
    $db->exec("INSERT IGNORE INTO role_permissions (role_id, permission_id)
               SELECT r.id, p.id FROM roles r
               JOIN permissions p ON p.code = 'enligne.voir'
               WHERE r.code IN ('admin', 'directeur', 'manager', 'superviseur', 'informaticien', 'regisseur')");
    $row = $db->query("SELECT COUNT(*) FROM menus WHERE code = 'en_ligne'")->fetchColumn();
    if (!$row) {
        $db->exec("UPDATE menus SET position = position + 1 WHERE position >= 18");
        $db->prepare("INSERT INTO menus (code, libelle, actif, position) VALUES ('en_ligne', 'Utilisateurs en ligne', 1, 18)")
           ->execute();
    }
} catch (Throwable $e) { /* non bloquant */ }

requirePermission('enligne.voir');

function enligne_il_y_a(?string $dt): string
{
    if (!$dt) return '—';
    $s = time() - strtotime($dt);
    if ($s < 60)    return "à l'instant";
    if ($s < 3600)  return 'il y a ' . floor($s / 60) . ' min';
    if ($s < 86400) return 'il y a ' . floor($s / 3600) . ' h';
    return 'il y a ' . floor($s / 86400) . ' j';
}

$users = $db->query("
    SELECT u.id, u.prenom, u.nom, u.login, u.derniere_activite, u.derniere_connexion,
           r.libelle AS role_libelle, r.code AS role_code
    FROM utilisateurs u
    JOIN roles r ON u.role_id = r.id
    WHERE u.actif = 1 AND u.derniere_activite IS NOT NULL
      AND u.derniere_activite > DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ORDER BY u.derniere_activite DESC
")->fetchAll();

$enLigne = []; $absents = [];
foreach ($users as $u) {
    $s = time() - strtotime($u['derniere_activite']);
    if ($s < 150) $enLigne[] = $u; else $absents[] = $u;
}

$nbActifs = (int)$db->query("SELECT COUNT(*) FROM utilisateurs WHERE actif = 1")->fetchColumn();

layout_head('Utilisateurs en ligne', 'utilisateurs_enligne');
showFlash();
?>
<div class="page-header">
  <h1>🟢 Utilisateurs en ligne</h1>
  <div class="flex gap-8">
    <span class="text-sm" style="color:var(--text3);">Actualisation auto toutes les 30 s</span>
    <button type="button" class="btn btn-ghost btn-sm" onclick="location.reload()"><?= icon('refresh', 13) ?> Actualiser</button>
  </div>
</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card s-teal">
    <div class="stat-icon" style="color:var(--teal2);opacity:.25;"><?= icon('users', 28) ?></div>
    <div class="stat-label">En ligne maintenant (&lt; 2 min 30)</div>
    <div class="stat-value c-teal"><?= count($enLigne) ?></div>
  </div>
  <div class="stat-card s-gold">
    <div class="stat-icon" style="color:var(--gold);opacity:.25;"><?= icon('clock', 28) ?></div>
    <div class="stat-label">Inactifs récents (2 min 30 – 30 min)</div>
    <div class="stat-value c-gold"><?= count($absents) ?></div>
  </div>
  <div class="stat-card s-blue">
    <div class="stat-icon" style="color:var(--blue);opacity:.25;"><?= icon('users', 28) ?></div>
    <div class="stat-label">Comptes actifs</div>
    <div class="stat-value c-blue"><?= $nbActifs ?></div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">🟢 En ligne maintenant</div>
    <span class="text-sm" style="color:var(--text3);">activité &lt; 2 min 30 — vérifiée par ping navigateur</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th style="width:60px;"></th><th>Utilisateur</th><th>Rôle</th><th>Dernière activité</th><th>Dernière connexion</th></tr></thead>
      <tbody>
      <?php if (!$enLigne): ?>
        <tr><td colspan="5" class="empty">Aucun utilisateur en ligne actuellement.</td></tr>
      <?php else: foreach ($enLigne as $u):
            $init = strtoupper(mb_substr(($u['prenom'] ?: $u['login']), 0, 1) . mb_substr($u['nom'], 0, 1)); ?>
        <tr>
          <td><span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:var(--teal-dim);color:var(--teal2);font-weight:700;font-size:12px;"><?= e($init) ?></span></td>
          <td><strong><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?></strong>
              <div class="text-sm" style="color:var(--text3);"><?= e($u['login']) ?></div></td>
          <td><span class="badge badge-blue"><?= e($u['role_libelle']) ?></span></td>
          <td class="text-sm" style="color:var(--teal2);font-weight:600;">🟢 <?= e(enligne_il_y_a($u['derniere_activite'])) ?></td>
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($u['derniere_connexion'])) ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($absents): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Absent récemment</div>
    <span class="text-sm" style="color:var(--text3);">inactivité entre 2 min 30 et 30 minutes — poste probablement quitté</span>
  </div>
  <div class="table-wrap">
    <table>
      <thead><tr><th style="width:60px;"></th><th>Utilisateur</th><th>Rôle</th><th>Dernière activité</th><th>Dernière connexion</th></tr></thead>
      <tbody>
      <?php foreach ($absents as $u):
            $init = strtoupper(mb_substr(($u['prenom'] ?: $u['login']), 0, 1) . mb_substr($u['nom'], 0, 1)); ?>
        <tr>
          <td><span style="display:inline-flex;align-items:center;justify-content:center;width:34px;height:34px;border-radius:50%;background:var(--gold-dim);color:var(--gold);font-weight:700;font-size:12px;"><?= e($init) ?></span></td>
          <td><strong><?= e(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?></strong>
              <div class="text-sm" style="color:var(--text3);"><?= e($u['login']) ?></div></td>
          <td><span class="badge badge-gray"><?= e($u['role_libelle']) ?></span></td>
          <td class="text-sm" style="color:var(--gold);">🟡 <?= e(enligne_il_y_a($u['derniere_activite'])) ?></td>
          <td class="text-sm"><?= date('d/m/Y H:i', strtotime($u['derniere_connexion'])) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
// Auto-rafraîchissement du mur SEULEMENT si l'utilisateur est réellement actif
// (interaction de moins de 45 s). Sinon on arrête de rafraîchir : sans aucune
// réaction de l'utilisateur, la session expirera normalement après le délai
// d'inactivité (10-15 min) → déconnexion automatique.
setTimeout(function(){
  var idle = Infinity;
  try {
    var v = parseInt(localStorage.getItem('pc_last_user_activity') || '0', 10);
    if (v > 0) idle = Date.now() - v;
  } catch (e) {}
  if (idle < 45000) {
    var u = location.href;
    if (!/[?&]autor=1(?=&|$)/.test(u)) u += (u.indexOf('?') === -1 ? '?' : '&') + 'autor=1';
    location.replace(u);
  }
}, 30000);
</script>
<?php layout_foot();