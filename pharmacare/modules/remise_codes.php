<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requireLogin();
$db = getDB();

// Accès réservé aux approbateurs de remise (liste gérée par l'admin)
$_stAcces = $db->prepare("SELECT 1 FROM remise_approbateurs WHERE utilisateur_id=? AND actif=1");
$_stAcces->execute([currentUser()['id']]);
if (!$_stAcces->fetch()) {
    flash('Accès refusé : vous n\'êtes pas approbateur de remise.', 'error');
    header('Location: ' . APP_URL . '/dashboard.php'); exit;
}

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$ttl    = (int)getParam('remise_code_ttl_min', '15');
if ($ttl < 1) $ttl = 15;

/**
 * Génère un code aléatoire à 6 caractères (majuscules + chiffres), sans collision.
 */
function genererCodeRemise(PDO $db): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sans I,O,0,1 (ambigus)
    for ($try = 0; $try < 10; $try++) {
        $code = '';
        for ($i = 0; $i < 6; $i++) $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        $stmt = $db->prepare("SELECT id FROM codes_remise WHERE code = ?");
        $stmt->execute([$code]);
        if (!$stmt->fetch()) return $code;
    }
    throw new Exception('Impossible de générer un code unique.');
}

// ── POST : générer un nouveau code ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'generer') {
    verifyCsrf();
    $remisePct = (float)($_POST['remise_pct'] ?? 0);
    $maxPct    = (float)getParam('remise_max_pct', '100');
    if ($remisePct < 0)      $remisePct = 0;
    if ($remisePct > $maxPct) $remisePct = $maxPct;
    if ($remisePct <= 0) {
        flash('Le taux de remise doit être supérieur à 0.', 'error');
        header('Location: ' . url('remise_codes')); exit;
    }
    try {
        $db->beginTransaction();
        $code = genererCodeRemise($db);
        $stmt = $db->prepare("INSERT INTO codes_remise (code, created_by, expires_at, remise_pct)
                              VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)");
        $stmt->execute([$code, currentUser()['id'], $ttl, $remisePct]);
        $cid = (int)$db->lastInsertId();
        $db->commit();
        auditLog('remise.code', 'Génération code remise ' . $code . ' (' . $remisePct . '%, validité ' . $ttl . ' min)', null, $code);
        flash('Code ' . $code . ' généré — remise ' . $remisePct . '%, valide ' . $ttl . ' min.', 'success');
        header('Location: ' . url('remise_codes', ['action'=>'show','id'=>$cid])); exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('Erreur génération code : ' . $e->getMessage(), 'error');
        header('Location: ' . url('remise_codes')); exit;
    }
}

// ── Données pour les vues ────────────────────────────────────
$title = 'Codes d\'autorisation de remise';
if ($action === 'show' && $id) {
    $stmt = $db->prepare("SELECT c.*, u.prenom, u.nom AS u_nom,
                                v.reference AS vente_ref
                         FROM codes_remise c
                         LEFT JOIN utilisateurs u ON c.created_by = u.id
                         LEFT JOIN ventes v ON c.used_vente_id = v.id
                         WHERE c.id = ?");
    $stmt->execute([$id]);
    $code = $stmt->fetch();
    if ($code) $title = 'Code ' . $code['code'];
}

layout_head($title, 'remise_codes');
showFlash();
?>

<div style="max-width:1100px;margin:0 auto;">

<?php if ($action === 'show' && !empty($code)): ?>
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title"><?= icon('key',18) ?> Code d'autorisation de remise</div>
      <a href="<?= url('remise_codes') ?>" class="btn btn-ghost btn-sm"><?= icon('chevron-left',14) ?> Liste</a>
    </div>
    <div class="card-pad" style="text-align:center;padding:30px;">
      <?php
        $now   = time();
        $exp   = strtotime($code['expires_at']);
        $reste = max(0, $exp - $now);
        $resteMin = (int)ceil($reste / 60);
        $expirate = $code['used'] || $reste <= 0;
      ?>
      <div style="font-family:'DM Mono',monospace;font-size:54px;font-weight:700;letter-spacing:8px;
                  color:<?= $expirate ? 'var(--text2)' : 'var(--teal2)' ?>;background:var(--surface2);
                  padding:24px 40px;border-radius:12px;display:inline-block;margin-bottom:16px;">
        <?= e($code['code']) ?>
      </div>
      <div style="font-size:20px;font-weight:700;color:var(--teal2);margin-bottom:8px;">
        Remise : <?= (float)$code['remise_pct'] ?>%
      </div>
      <div style="font-size:14px;color:var(--text2);">
        <?php if ($code['used']): ?>
          ✅ <strong>Utilisé</strong> sur la vente <?= e($code['vente_ref'] ?? '—') ?> (remise <?= (float)$code['used_remise_pct'] ?>%)
        <?php elseif ($reste <= 0): ?>
          ⌛ <strong>Expiré</strong> (non utilisé)
        <?php else: ?>
          ⏱ Valide encore <strong><?= $resteMin ?> min</strong> (jusqu'à <?= e(date('H:i', $exp)) ?>)
        <?php endif; ?>
      </div>
      <div style="margin-top:8px;font-size:12px;color:var(--text2);">
        Généré par <?= e(trim($code['prenom'].' '.$code['u_nom'])) ?>
        le <?= e(date('d/m/Y H:i', strtotime($code['created_at']))) ?>
      </div>
      <div style="margin-top:18px;color:var(--text2);font-size:13px;max-width:520px;margin-left:auto;margin-right:auto;">
        Communiquez ce code <strong>et le taux de <?= (float)$code['remise_pct'] ?>%</strong> à la caisse.
        Le caissier saisit le code : le taux et l'autorité sont appliqués automatiquement.
      </div>
    </div>
  </div>

<?php else: ?>
  <!-- ── Liste des codes ── -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><?= icon('key',18) ?> Codes d'autorisation de remise</div>
      <form method="POST" action="?action=generer" style="margin:0;display:flex;gap:8px;align-items:center;">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="number" name="remise_pct" min="0.01" max="<?= (float)getParam('remise_max_pct','100') ?>"
               step="0.01" value="5" placeholder="%" required
               style="width:70px;padding:6px 8px;border:1px solid var(--border);border-radius:6px;
                      background:var(--surface);color:var(--text);font-family:'DM Mono',monospace;text-align:center;">
        <span style="font-size:13px;color:var(--text2);">%</span>
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('plus',14) ?> Générer</button>
      </form>
    </div>
    <div class="table-wrap" style="padding:22px;">
      <table>
        <thead><tr><th>Code</th><th>Remise</th><th>Généré par</th><th>Créé le</th><th>Expire</th><th>Statut</th><th>Vente</th><th></th></tr></thead>
        <tbody>
        <?php
          $codes = $db->query("SELECT c.*, u.prenom, u.nom AS u_nom,
                                      v.reference AS vente_ref
                               FROM codes_remise c
                               LEFT JOIN utilisateurs u ON c.created_by = u.id
                               LEFT JOIN ventes v ON c.used_vente_id = v.id
                               ORDER BY c.created_at DESC LIMIT 100")->fetchAll();
          $now = time();
          foreach ($codes as $c):
            $reste = strtotime($c['expires_at']) - $now;
            if ($c['used']) { $statut = 'Utilisé'; $cls = 'var(--text2)'; }
            elseif ($reste <= 0) { $statut = 'Expiré'; $cls = 'var(--text2)'; }
            else { $statut = 'Valide'; $cls = 'var(--teal2)'; }
        ?>
          <tr>
            <td class="fw-mono" style="font-weight:700;letter-spacing:2px;"><?= e($c['code']) ?></td>
            <td style="font-weight:600;color:var(--teal2);"><?= (float)$c['remise_pct'] ?>%</td>
            <td><?= e(trim($c['prenom'].' '.$c['u_nom'])) ?></td>
            <td><?= e(date('d/m/Y H:i', strtotime($c['created_at']))) ?></td>
            <td><?= e(date('d/m/Y H:i', strtotime($c['expires_at']))) ?></td>
            <td style="font-weight:600;color:<?= $cls ?>"><?= $statut ?></td>
            <td class="fw-mono"><?= e($c['vente_ref'] ?: '—') ?></td>
            <td><a href="<?= url('remise_codes', ['action'=>'show','id'=>$c['id']]) ?>" class="btn btn-ghost btn-sm"><?= icon('eye',14) ?></a></td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$codes): ?>
           <tr><td colspan="8"><div class="empty">Aucun code généré. Cliquez sur « Générer ».</div></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      <div style="margin-top:12px;color:var(--text2);font-size:12px;">
        Les codes sont à usage unique, valides <?= $ttl ?> min. Seuls les admins peuvent en générer.
      </div>
    </div>
  </div>
<?php endif; ?>

</div>
<?php layout_foot(); ?>