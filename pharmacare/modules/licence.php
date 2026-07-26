<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/licence.php';
requirePermission('parametres.gerer');
$db = getDB();

// ── Appliquer un code d'activation ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    verifyCsrf();
    $res = licence_apply_code((string)($_POST['code'] ?? ''));
    flash($res['msg'], $res['ok'] ? 'success' : 'error');
    header('Location: ' . url('licence'));
    exit;
}

$inst      = licence_instance_id();
$cur       = licence_current();
$cap       = $cur['cap'];
$usage     = licence_usage();
$remaining = max(0, $cap - $usage);
$pct       = $cap > 0 ? min(100, (int)round($usage / $cap * 100)) : 0;
$pubConfig = defined('LICENCE_PUBKEY') && LICENCE_PUBKEY !== '';

// ── Vérification d'intégrité des fichiers licence ───────────
$integrity = licence_integrity_check();
if (!$integrity['ok']) {
    // Journaliser une seule fois par session pour éviter de spammer audit_log.
    if (empty($_SESSION['lic_integrity_logged'])) {
        auditLog('licence.integrity', sprintf(
            'Intégrité licence compromise : %s',
            implode(', ', $integrity['tampered'])
        ));
        $_SESSION['lic_integrity_logged'] = true;
    }
} else {
    unset($_SESSION['lic_integrity_logged']);
}

layout_head('Licence', 'licence');
showFlash();
?>

<div style="max-width:760px;margin:0 auto;">

  <!-- ── État de la licence ─────────────────────────────── -->
  <div class="card" style="margin-bottom:20px;">
    <div class="card-header">
      <div class="card-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;color:var(--gold)"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        État de la licence
      </div>
    </div>
    <div style="padding:22px;">
      <div class="form-grid">
        <div class="form-group">
          <label>Identifiant d'instance</label>
          <input type="text" value="<?= e($inst) ?>" readonly
                 style="font-family:'DM Mono',monospace;font-size:13px;"
                 onclick="this.select()">
          <div class="form-hint">Communiquez cet identifiant à votre fournisseur pour obtenir un code d'activation.</div>
        </div>
        <div class="form-group">
          <label>Plafond de lignes</label>
          <input type="text" value="<?= number_format($cap, 0, ',', ' ') ?> lignes" readonly>
          <div class="form-hint">
            <?php if ($cur['valid'] && $cur['source'] === 'short'): ?>
              Licence active (code court).
            <?php elseif ($cur['valid']): ?>
              Licence active (contre n°<?= (int)$cur['counter'] ?>).
            <?php else: ?>
              Palier gratuit — enregistrement de licence non actif.
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Barre d'utilisation -->
      <div style="margin-top:18px;">
        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px;">
          <span>Lignes utilisées : <strong><?= number_format($usage, 0, ',', ' ') ?></strong> / <?= number_format($cap, 0, ',', ' ') ?></span>
          <span style="color:var(--text2);">Restantes : <strong style="color:<?= $remaining > 0 ? 'var(--teal2)' : 'var(--red)' ?>"><?= number_format($remaining, 0, ',', ' ') ?></strong></span>
        </div>
        <div style="height:14px;background:var(--bg3);border-radius:8px;overflow:hidden;">
          <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--teal2),var(--gold));transition:width .3s;"></div>
        </div>
      </div>

      <?php if ($remaining === 0): ?>
      <div style="margin-top:18px;padding:14px 16px;border-radius:10px;background:var(--red-dim);border:1px solid var(--red);color:var(--red);font-size:13px;">
        <strong>Plafond atteint.</strong> Les nouvelles ventes sont bloquées. Pour continuer, saisissez un code d'activation ci-dessous (fourni par votre fournisseur).
      </div>
      <?php endif; ?>

      <?php if (!$pubConfig): ?>
      <div style="margin-top:18px;padding:14px 16px;border-radius:10px;background:var(--gold-glow);border:1px solid var(--gold);color:var(--gold);font-size:13px;">
        Clé publique de licence non configurée sur ce serveur — aucun code ne peut être vérifié. Contactez votre fournisseur.
      </div>
      <?php endif; ?>

      <?php if ($integrity['status'] === 'tampered'): ?>
      <div style="margin-top:18px;padding:14px 16px;border-radius:10px;background:var(--red-dim);border:1px solid var(--red);color:var(--red);font-size:13px;">
        <strong>⚠ Intégrité des fichiers licence compromise.</strong>
        Les fichiers suivants diffèrent de la version livrée par votre fournisseur :
        <code><?= e(implode(', ', $integrity['tampered'])) ?></code>.
        Toute modification du système de licence est tracée. Contactez votre fournisseur.
      </div>
      <?php elseif ($integrity['status'] === 'bad'): ?>
      <div style="margin-top:18px;padding:14px 16px;border-radius:10px;background:var(--red-dim);border:1px solid var(--red);color:var(--red);font-size:13px;">
        <strong>⚠ Manifeste d'intégrité invalide.</strong> Le manifeste d'intégrité des fichiers licence est corrompu ou a été falsifié. Contactez votre fournisseur.
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Saisie du code d'activation ───────────────────── -->
  <div class="card">
    <div class="card-header">
      <div class="card-title">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;color:var(--teal2)"><path d="M3 6l9 6 9-6"/><rect x="3" y="6" width="18" height="12" rx="2"/></svg>
        Activer un pack de lignes
      </div>
    </div>
    <div style="padding:22px;">
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= csrf() ?>">
        <div class="form-group full">
          <label>Code d'activation</label>
          <textarea name="code" rows="4" required
                placeholder="Collez ici le code d'activation transmis par votre fournisseur…"
                style="font-family:'DM Mono',monospace;font-size:12px;width:100%;"></textarea>
          <div class="form-hint">Collez le code fourni : code court (ex : <code>733D-15000-KEKABDBU</code>) ou code long. Il est lié à cet identifiant d'instance.</div>
        </div>
        <div style="margin-top:14px;display:flex;gap:10px;">
          <button type="submit" class="btn btn-primary"><?= icon('check',16) ?> Activer la licence</button>
        </div>
      </form>
    </div>
  </div>

</div>

<?php layout_foot();