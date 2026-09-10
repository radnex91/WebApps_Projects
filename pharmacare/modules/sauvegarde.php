<?php
declare(strict_types=1);
/**
 * Module Sauvegarde / Restauration de la base de données — admin only.
 *
 * Permission requise : parametres.gerer.
 * Toute action destructrice (restauration, suppression) exige POST + CSRF.
 * La restauration exige en plus la confirmation tapée « RESTAURER » et réalise
 * automatiquement une sauvegarde pré-restauration (pour pouvoir annuler).
 *
 * Logique métier : includes/sauvegarde.php.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/sauvegarde.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('parametres.gerer');

// ════════════════════════════════════════════════════════════
//  Traitement des actions
// ════════════════════════════════════════════════════════════

// ── Téléchargement (GET, protégé par token CSRF) ────────────
if (isset($_GET['download']) && isset($_GET['token'])) {
    if (!hash_equals(csrf(), (string)$_GET['token'])) { http_response_code(403); exit('Token invalide.'); }
    $path = sauv_resolve((string)$_GET['download']);
    if (!$path) { http_response_code(404); exit('Fichier introuvable.'); }
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . basename($path) . '"');
    header('Content-Length: ' . filesize($path));
    header('Cache-Control: private, no-store');
    readfile($path);
    auditLog('sauvegarde.download', 'Téléchargement sauvegarde : ' . basename($path));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'backup') {
        $r = sauv_make_backup();
        flash($r['msg'], $r['ok'] ? 'success' : 'error');
        if ($r['ok']) auditLog('sauvegarde.create', 'Sauvegarde BDD : ' . ($r['file'] ?? ''));
        header('Location: ' . url('sauvegarde')); exit;

    } elseif ($action === 'restore') {
        $file = sauv_resolve(trim((string)($_POST['file'] ?? '')));
        $confirm = trim((string)($_POST['confirm'] ?? ''));
        if (!$file) {
            flash('Sauvegarde introuvable.', 'error');
        } elseif ($confirm !== 'RESTAURER') {
            flash('Confirmation manquante : tapez exactement « RESTAURER » pour confirmer.', 'error');
        } else {
            // Sauvegarde de sécurité avant restauration (pour annuler si besoin).
            $pre = sauv_make_backup();
            $preName = '(échec pré-sauvegarde)';
            if ($pre['ok'] && !empty($pre['file'])) {
                $oldPath = sauv_dir() . '/' . $pre['file'];
                $newName = 'pharmacare_prerestore_' . date('Ymd_His') . '.sql';
                @rename($oldPath, sauv_dir() . '/' . $newName);
                $preName = $newName;
            }
            $r = sauv_restore($file);
            flash($r['msg'] . ($pre['ok'] ? ' — pré-sauvegarde : ' . $preName : ' — ⚠ pré-sauvegarde échouée'), $r['ok'] ? 'success' : 'error');
            auditLog('sauvegarde.restore', 'Restauration BDD depuis ' . basename($file) . ' → ' . ($r['ok'] ? 'OK' : 'ÉCHEC') . ' (pré-sauvegarde: ' . $preName . ')');
        }
        header('Location: ' . url('sauvegarde')); exit;

    } elseif ($action === 'delete') {
        $file = sauv_resolve(trim((string)($_POST['file'] ?? '')));
        if (!$file) {
            flash('Sauvegarde introuvable.', 'error');
        } else {
            $r = sauv_delete($file);
            flash($r['msg'], $r['ok'] ? 'success' : 'error');
            if ($r['ok']) auditLog('sauvegarde.delete', 'Suppression sauvegarde : ' . basename($file));
        }
        header('Location: ' . url('sauvegarde')); exit;
    }
}

// ════════════════════════════════════════════════════════════
//  Vue
// ════════════════════════════════════════════════════════════
$dbName  = defined('DB_NAME') ? DB_NAME : 'pharmacare';
$dbInfo  = sauv_db_info();
$backups = sauv_list();
$binOk   = (bool)sauv_bin();

layout_head('Sauvegarde / Restauration', 'sauvegarde');
showFlash();
?>

<div style="max-width:880px;margin:0 auto;">

  <!-- ── État de la base ─────────────────────────────────── -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header"><div class="card-title"><?= icon('save',16) ?> Sauvegarde / Restauration</div></div>
    <div style="padding:22px;">
      <div class="form-grid">
        <div class="form-group">
          <label>Base de données</label>
          <input type="text" value="<?= e($dbName) ?>" readonly>
        </div>
        <div class="form-group">
          <label>Tables</label>
          <input type="text" value="<?= e((string)$dbInfo['nb_tables']) ?>" readonly>
        </div>
        <div class="form-group">
          <label>Taille (Mo)</label>
          <input type="text" value="<?= e((string)$dbInfo['size_mb']) ?>" readonly>
        </div>
        <div class="form-group">
          <label>Outil mysqldump</label>
          <input type="text" value="<?= $binOk ? 'Détecté' : 'Introuvable' ?>" readonly
                 style="color:<?= $binOk ? 'var(--teal2)' : 'var(--red)' ?>;">
          <?php if (!$binOk): ?>
            <div class="form-hint" style="color:var(--red);"> mysqldump/mysql introuvable. Définissez la variable d'env <code>MYSQL_BIN</code> (ex : <code>C:\xampp\mysql\bin</code>) ou ajoutez-la au PATH.</div>
          <?php endif; ?>
        </div>
      </div>

      <form method="post" style="margin-top:14px;">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="backup">
        <button type="submit" class="btn btn-primary" <?= $binOk ? '' : 'disabled' ?>>
          <?= icon('save',16) ?> Créer une sauvegarde maintenant
        </button>
      </form>
    </div>
  </div>

  <!-- ── Liste des sauvegardes ───────────────────────────── -->
  <div class="card">
    <div class="card-header"><div class="card-title">Sauvegardes disponibles <span class="muted" style="font-weight:400;font-size:13px;">(<?= count($backups) ?>)</span></div></div>
    <div style="padding:22px;">

      <?php if (!$backups): ?>
        <p class="muted">Aucune sauvegarde pour l'instant. Cliquez sur « Créer une sauvegarde ».</p>
      <?php else: ?>
        <table style="width:100%;border-collapse:collapse;">
          <thead>
            <tr>
              <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border2);font-size:12px;color:var(--text2);">Fichier</th>
              <th style="text-align:right;padding:8px 10px;border-bottom:1px solid var(--border2);font-size:12px;color:var(--text2);">Taille</th>
              <th style="text-align:left;padding:8px 10px;border-bottom:1px solid var(--border2);font-size:12px;color:var(--text2);">Date</th>
              <th style="text-align:right;padding:8px 10px;border-bottom:1px solid var(--border2);font-size:12px;color:var(--text2);">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($backups as $b):
            $dlUrl = url('sauvegarde', ['download' => $b['name'], 'token' => csrf()]);
          ?>
            <tr>
              <td style="padding:10px;border-bottom:1px solid var(--border);font-family:'DM Mono',monospace;font-size:12px;">
                <?= e($b['name']) ?>
                <?php if ($b['prerestore']): ?>
                  <span class="badge badge-gold" style="font-size:9px;margin-left:4px;">avant restauration</span>
                <?php endif; ?>
              </td>
              <td style="padding:10px;border-bottom:1px solid var(--border);text-align:right;font-family:'DM Mono',monospace;font-size:12px;">
                <?= $b['size'] >= 1048576 ? round($b['size']/1048576,2).' Mo' : round($b['size']/1024).' Ko' ?>
              </td>
              <td style="padding:10px;border-bottom:1px solid var(--border);font-size:12px;color:var(--text2);">
                <?= date('d/m/Y H:i', (int)$b['mtime']) ?>
              </td>
              <td style="padding:10px;border-bottom:1px solid var(--border);text-align:right;white-space:nowrap;">
                <a href="<?= e($dlUrl) ?>" class="btn btn-ghost btn-xs" title="Télécharger">↓</a>
                <button type="button" class="btn btn-ghost btn-xs" title="Restaurer"
                        onclick="openRestore('<?= e($b['name']) ?>')">↺</button>
                <form method="post" style="display:inline">
                  <input type="hidden" name="csrf" value="<?= csrf() ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="file" value="<?= e($b['name']) ?>">
                  <button type="submit" class="btn btn-ghost btn-xs" title="Supprimer"
                          onclick="return confirm('Supprimer cette sauvegarde ?')"
                          style="color:var(--red);">🗑</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>

      <div class="form-hint" style="margin-top:14px;">
        Les sauvegardes sont stockées dans <code>backups/</code> (non accessibles depuis le web).
        La restauration <strong>remplace toute la base actuelle</strong> — une pré-sauvegarde est créée automatiquement.
      </div>
    </div>
  </div>

</div>

<!-- ── Modal de confirmation restauration ─────────────────── -->
<div class="modal-overlay" id="modal-restore">
  <div class="modal" style="width:520px;max-width:92vw;">
    <div class="modal-header">
      <div class="modal-title" style="color:var(--red);"><?= icon('alert',16) ?> Confirmer la restauration</div>
      <button class="modal-close" onclick="closeModal('modal-restore')">✕</button>
    </div>
    <div class="card-pad">
      <p style="font-size:14px;margin:0 0 10px;">
        Restaurer <strong id="restore-file-name" style="font-family:'DM Mono',monospace;"></strong> ?
      </p>
      <div style="padding:12px;border-radius:8px;background:var(--red-dim);border:1px solid var(--red);color:var(--red);font-size:13px;margin-bottom:14px;">
        ⚠ Toute la base actuelle sera <strong>remplacée</strong> par le contenu de cette sauvegarde.
        Une pré-sauvegarde sera créée automatiquement pour pouvoir annuler.
      </div>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <input type="hidden" name="action" value="restore">
        <input type="hidden" name="file" id="restore-file-input">
        <div class="form-group">
          <label>Pour confirmer, tapez <code>RESTAURER</code></label>
          <input type="text" name="confirm" autocomplete="off" placeholder="RESTAURER"
                 style="font-family:'DM Mono',monospace;text-transform:uppercase;">
        </div>
        <div style="display:flex;gap:10px;justify-content:flex-end;">
          <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-restore')">Annuler</button>
          <button type="submit" class="btn btn-sm" style="background:var(--red);color:#fff;">Restaurer la base</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function openRestore(name) {
  document.getElementById('restore-file-name').textContent = name;
  document.getElementById('restore-file-input').value = name;
  openModal('modal-restore');
}
</script>

<?php layout_foot();