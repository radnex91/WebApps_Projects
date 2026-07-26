<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('parametres.gerer');
$db = getDB();

// ── Sauvegarde ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $devises = getDevises();
    $dev = $_POST['devise'] ?? 'XAF';
    $devInfo = $devises[$dev] ?? ['FCFA','after',''];

    $toSave = [
        'app_nom'            => trim($_POST['app_nom'] ?? 'PharmaCare'),
        'devise'             => $dev,
        'devise_symbole'     => $devInfo[0],
        'devise_pos'         => $devInfo[1],
        'tva'                => number_format((float)str_replace(',','.',$_POST['tva'] ?? '19.25'), 2, '.', ''),
        'theme'              => $_POST['theme'] ?? 'dark-cyan',
        'police'             => $_POST['police'] ?? 'Manrope',
        'police_titre'       => $_POST['police_titre'] ?? 'Manrope',
        'pharmacie_adresse'  => trim($_POST['pharmacie_adresse'] ?? ''),
        'pharmacie_telephone'=> trim($_POST['pharmacie_telephone'] ?? ''),
        'pharmacie_nif'      => trim($_POST['pharmacie_nif'] ?? ''),
        'ticket_sous_titre'  => trim($_POST['ticket_sous_titre'] ?? 'Gestion Pharmacie'),
        'ticket_pied'       => trim($_POST['ticket_pied'] ?? 'Merci pour votre achat !'),
        'ticket_nb_copies'  => (string)max(1, min(5, (int)($_POST['ticket_nb_copies'] ?? 2))),
        'prefix_vente'       => strtoupper(trim($_POST['prefix_vente'] ?? 'VNT')),
        'caisse_fermeture_mode'   => ($_POST['caisse_fermeture_mode'] ?? 'manuel') === 'auto' ? 'auto' : 'manuel',
        'caisse_heure_fermeture'  => preg_match('/^\d{2}:\d{2}$/', $_POST['caisse_heure_fermeture'] ?? '') ? $_POST['caisse_heure_fermeture'] : '22:00',
        'assistant_active'        => ($_POST['assistant_active'] ?? '0') === '1' ? '1' : '0',
    ];

    $stmt = $db->prepare("INSERT INTO parametres (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=?");
    foreach ($toSave as $k => $v) $stmt->execute([$k, $v, $v]);

    // ── Logo de la pharmacie (upload, distinct du logo app PharmaCare) ──
    $logoErr = '';
    $allowedExt = ['png','jpg','jpeg','webp','svg'];
    if (!empty($_POST['pharmacie_logo_remove'])) {
        $old = pharmacieLogoPath();
        if ($old && is_file($old)) @unlink($old);
        $db->prepare("DELETE FROM parametres WHERE cle=?")->execute(['pharmacie_logo']);
        paramCacheClear();
    } elseif (!empty($_FILES['pharmacie_logo']['name'])) {
        $f = $_FILES['pharmacie_logo'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (($f['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $logoErr = 'Logo : transfert échoué (code ' . (int)($f['error'] ?? 0) . ').';
        } elseif (!in_array($ext, $allowedExt, true)) {
            $logoErr = 'Logo : format non autorisé (PNG, JPG, WebP, SVG).';
        } elseif ((int)$f['size'] > 1024 * 1024) {
            $logoErr = 'Logo : taille max 1 Mo.';
        } else {
            $extN = $ext === 'jpeg' ? 'jpg' : $ext;
            $target = __DIR__ . '/../assets/img/pharmacie_logo.' . $extN;
            foreach (['png','jpg','webp','svg'] as $oldExt) {  // purge anciens logos d'ext différente
                $oldFile = __DIR__ . '/../assets/img/pharmacie_logo.' . $oldExt;
                if ($oldFile !== $target && is_file($oldFile)) @unlink($oldFile);
            }
            if (move_uploaded_file($f['tmp_name'], $target)) {
                $rel = 'assets/img/pharmacie_logo.' . $extN;
                $db->prepare("INSERT INTO parametres (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=?")
                   ->execute(['pharmacie_logo', $rel, $rel]);
                paramCacheClear();
            } else {
                $logoErr = 'Logo : échec de l\'enregistrement du fichier.';
            }
        }
    }

    if ($logoErr !== '') flash($logoErr, 'error');
    else flash('Paramètres enregistrés avec succès.');
    header('Location: ' . url('parametres')); exit;
}

$p       = getAllParams();
$themes  = getThemes();
$polices = getPolices();
$policesTitres = getPolicesTitres();
$devises = getDevises();

layout_head('Paramètres', 'parametres');
showFlash();
?>

<div style="max-width:860px;margin:0 auto;">

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= csrf() ?>">

<!-- ── GÉNÉRAL ─────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;color:var(--teal2)"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/></svg>
      Informations générales
    </div>
  </div>
  <div class="form-grid" style="padding:22px;">
    <div class="form-group full">
      <label>Nom de la pharmacie</label>
      <input type="text" name="app_nom" value="<?= e($p['app_nom'] ?? 'PharmaCare') ?>" placeholder="PharmaCare">
      <div class="form-hint">Affiché sur les tickets et bons (sous le logo de la pharmacie). Le nom <strong>PharmaCare</strong> (logo de l'application) reste fixe et propriétaire.</div>
    </div>
    <div class="form-group full">
      <label>Logo de la pharmacie (tickets &amp; bons imprimés)</label>
      <?php $logoUrl = pharmacieLogoUrl(); ?>
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:8px;">
        <?php if ($logoUrl): ?>
          <img src="<?= e($logoUrl) ?>" alt="Logo pharmacie" style="max-height:64px;max-width:160px;border:1px solid var(--border2);border-radius:var(--radius-sm);padding:4px;background:#fff;">
        <?php else: ?>
          <div style="height:64px;width:120px;border:1px dashed var(--border2);border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:12px;">Aucun logo</div>
        <?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:6px;">
          <input type="file" name="pharmacie_logo" accept="image/png,image/jpeg,image/webp,image/svg+xml">
          <?php if ($logoUrl): ?>
          <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--text3);cursor:pointer;">
            <input type="checkbox" name="pharmacie_logo_remove" value="1"> Supprimer le logo actuel
          </label>
          <?php endif; ?>
        </div>
      </div>
      <div class="form-hint">PNG / JPG / WebP / SVG — 1 Mo max. Apparaît en en-tête des tickets de caisse et des bons de livraison.</div>
    </div>
    <div class="form-group">
      <label>Taux TVA (%)</label>
      <input type="number" name="tva" step="0.01" min="0" max="100" value="<?= e($p['tva'] ?? '19.25') ?>">
      <div class="form-hint">Ex : 19.25 pour le Cameroun (TVA standard)</div>
    </div>
    <div class="form-group">
      <label>Devise</label>
      <select name="devise" id="sel-devise" onchange="previewDevise(this.value)">
        <?php foreach ($devises as $code => $info): ?>
        <option value="<?= $code ?>" <?= ($p['devise']??'XAF')===$code ? 'selected' : '' ?>>
          <?= $code ?> — <?= e($info[2]) ?>
        </option>
        <?php endforeach; ?>
      </select>
      <div class="form-hint" id="devise-preview" style="color:var(--teal2);font-family:'DM Mono',monospace;margin-top:6px;">
        <?php
          $dv = $devises[$p['devise']??'XAF'];
          echo $dv[1]==='before' ? $dv[0].' 12 500' : '12 500 '.$dv[0];
        ?>
      </div>
    </div>
  </div>
</div>

<!-- ── TICKET DE CAISSE ───────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;color:var(--teal2)"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="16" y2="14"/></svg>
      Ticket de caisse
    </div>
  </div>
  <div class="form-grid" style="padding:22px;">
    <div class="form-group">
      <label>Sous-titre</label>
      <input type="text" name="ticket_sous_titre" value="<?= e($p['ticket_sous_titre'] ?? 'Gestion Pharmacie') ?>" placeholder="Gestion Pharmacie">
      <div class="form-hint">Sous le nom de la pharmacie sur le ticket</div>
    </div>
    <div class="form-group">
      <label>Message en pied de ticket</label>
      <input type="text" name="ticket_pied" value="<?= e($p['ticket_pied'] ?? 'Merci pour votre achat !') ?>" placeholder="Merci pour votre achat !">
    </div>
    <div class="form-group">
      <label>Nombre de copies par impression</label>
      <input type="number" name="ticket_nb_copies" value="<?= e($p['ticket_nb_copies'] ?? '2') ?>" min="1" max="5" step="1" style="max-width:120px;">
      <div class="form-hint">2 = un exemplaire Client + un exemplaire Caisse (séparés par une coupure).</div>
    </div>
    <div class="form-group full">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
        <input type="checkbox" name="assistant_active" value="1" <?= (($p['assistant_active'] ?? '1') === '1') ? 'checked' : '' ?> style="width:18px;height:18px;">
        Activer l'assistant intégré (aide + recherche, sans serveur IA)
      </label>
      <div class="form-hint">Affiche le bouton flottant de l'assistant sur toutes les pages. Aucune action sensible, aucune donnée personnelle.</div>
    </div>
    <div class="form-group">
      <label>Préfixe des références de vente</label>
      <input type="text" name="prefix_vente" value="<?= e($p['prefix_vente'] ?? 'VNT') ?>" placeholder="VNT" maxlength="6" style="text-transform:uppercase;">
      <div class="form-hint">Ex : VNT → VNT-2026-0001, FACT → FACT-2026-0001</div>
    </div>
    <div class="form-group full">
      <label>Adresse</label>
      <input type="text" name="pharmacie_adresse" value="<?= e($p['pharmacie_adresse'] ?? '') ?>" placeholder="123 Rue Exemple, Douala">
    </div>
    <div class="form-group">
      <label>Téléphone</label>
      <input type="text" name="pharmacie_telephone" value="<?= e($p['pharmacie_telephone'] ?? '') ?>" placeholder="+237 6 XX XX XX XX">
    </div>
    <div class="form-group">
      <label>NIF / N° registre</label>
      <input type="text" name="pharmacie_nif" value="<?= e($p['pharmacie_nif'] ?? '') ?>" placeholder="NIF : M0XXXXXXXXX">
    </div>
  </div>
</div>

<!-- ── CAISSE ──────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;color:var(--teal2)"><rect x="2" y="4" width="20" height="16" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/><circle cx="17" cy="15" r="1.5"/></svg>
      Paramètres de caisse
    </div>
  </div>
  <div class="form-grid" style="padding:22px;">
    <div class="form-group">
      <label>Mode de fermeture</label>
      <select name="caisse_fermeture_mode" id="sel-fermeture-mode" onchange="toggleHeureFermeture(this.value)">
        <option value="manuel" <?= ($p['caisse_fermeture_mode'] ?? 'manuel') === 'manuel' ? 'selected' : '' ?>>Fermeture manuelle</option>
        <option value="auto" <?= ($p['caisse_fermeture_mode'] ?? 'manuel') === 'auto' ? 'selected' : '' ?>>Fermeture automatique à heure fixe</option>
      </select>
      <div class="form-hint">Manuel : le caissier clôture lui-même. Auto : rappel de clôture à l'heure configurée.</div>
    </div>
    <div class="form-group" id="group-heure-fermeture" style="<?= ($p['caisse_fermeture_mode'] ?? 'manuel') === 'auto' ? '' : 'opacity:0.45;pointer-events:none;' ?>">
      <label>Heure de fermeture automatique</label>
      <input type="time" name="caisse_heure_fermeture" value="<?= e($p['caisse_heure_fermeture'] ?? '22:00') ?>" id="heure-fermeture">
      <div class="form-hint">À cette heure, les sessions ouvertes seront signalées pour clôture.</div>
    </div>
  </div>
</div>

<!-- ── THÈME ────────────────────────────────────────────── -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <div class="card-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;color:var(--teal2)"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 0 1 0 20"/><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/></svg>
      Thème & Couleurs
    </div>
  </div>
  <div style="padding:22px;">
    <div style="display:flex;flex-wrap:wrap;gap:12px;">
      <?php foreach ($themes as $tid => $tinfo): ?>
      <?php
        $isLight = str_starts_with($tid, 'light');
        $bg   = $tinfo[4];
        $acc  = $tinfo[1];
        $sel  = ($p['theme'] ?? 'dark-navy') === $tid;
        $unselBorder = $isLight ? 'rgba(0,0,0,.10)' : 'rgba(255,255,255,.08)';
      ?>
      <label style="cursor:pointer;">
        <input type="radio" name="theme" value="<?= $tid ?>" <?= $sel?'checked':'' ?> style="display:none;" onchange="previewTheme('<?= $tid ?>')">
        <div class="theme-tile <?= $sel?'selected':'' ?>" style="background:<?= $bg ?>;border:2px solid <?= $sel?$acc:$unselBorder ?>;border-radius:12px;padding:14px;transition:all .25s;position:relative;overflow:hidden;width:140px;" onclick="this.previousElementSibling.checked=true;document.querySelectorAll('.theme-tile').forEach(t=>t.classList.remove('selected'));this.classList.add('selected');this.style.borderColor='<?= $acc ?>';previewTheme('<?= $tid ?>')">
          <?php if (!$isLight): ?>
          <div style="position:absolute;top:-10px;right:-10px;width:50px;height:50px;background:<?= $acc ?>;border-radius:50%;filter:blur(18px);opacity:.2;"></div>
          <?php endif; ?>
          <div style="display:flex;gap:5px;margin-bottom:10px;position:relative;">
            <div style="width:16px;height:16px;border-radius:4px;background:<?= $tinfo[1] ?>;box-shadow:0 0 8px <?= $tinfo[1] ?>44;"></div>
            <div style="width:16px;height:16px;border-radius:4px;background:<?= $tinfo[2] ?>"></div>
            <div style="width:16px;height:16px;border-radius:4px;background:<?= $tinfo[3] ?>"></div>
            <div style="width:16px;height:16px;border-radius:4px;background:<?= $tinfo[4] ?>"></div>
          </div>
          <div style="font-size:12px;font-weight:600;color:<?= $isLight?'#1a1a2e':'#e8edf5' ?>;position:relative;"><?= $tinfo[0] ?></div>
        </div>
      </label>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── POLICES ───────────────────────────────────────────── -->
<div class="card" style="margin-bottom:24px;">
  <div class="card-header">
    <div class="card-title">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:-3px;margin-right:6px;color:var(--teal2)"><polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/></svg>
      Typographie
    </div>
  </div>
  <div class="form-grid" style="padding:22px;">
    <div class="form-group">
      <label>Police principale (corps de texte)</label>
      <select name="police" onchange="previewFont(this.value,'body')">
        <?php foreach ($polices as $fid => $flabel): ?>
        <option value="<?= e($fid) ?>" <?= ($p['police']??'Manrope')===$fid?'selected':'' ?>><?= e($flabel) ?></option>
        <?php endforeach; ?>
      </select>
      <div id="preview-body" style="margin-top:10px;padding:10px;background:var(--bg3);border-radius:6px;font-size:14px;color:var(--text2);">
        Aperçu — Stock médicaments, ventes, fournisseurs
      </div>
    </div>
    <div class="form-group">
      <label>Police des titres</label>
      <select name="police_titre" onchange="previewFont(this.value,'title')">
        <?php foreach ($policesTitres as $fid => $flabel): ?>
        <option value="<?= e($fid) ?>" <?= ($p['police_titre']??'Manrope')===$fid?'selected':'' ?>><?= e($flabel) ?></option>
        <?php endforeach; ?>
      </select>
      <div id="preview-title" style="margin-top:10px;padding:10px;background:var(--bg3);border-radius:6px;font-size:22px;font-weight:600;color:var(--text);">
        Tableau de bord PharmaCare
      </div>
    </div>
  </div>
</div>

<div style="display:flex;gap:12px;justify-content:flex-end;margin-bottom:40px;">
  <a href="<?= APP_URL ?>/dashboard.php" class="btn btn-ghost">Annuler</a>
  <button type="submit" class="btn btn-primary" style="padding:10px 28px;font-size:14px;">
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="vertical-align:-2px;margin-right:4px"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
    Enregistrer les paramètres
  </button>
</div>

</form>
</div>

<script>
const devises = <?= json_encode($devises, JSON_UNESCAPED_UNICODE) ?>;
const themes  = <?= json_encode($themes)  ?>;
const googleFontsCache = {};

function previewDevise(code) {
  const d = devises[code];
  if (!d) return;
  const preview = document.getElementById('devise-preview');
  preview.textContent = d[1]==='before' ? d[0]+' 12 500' : '12 500 '+d[0];
}

function previewTheme(tid) {
  // Aperçu immédiat : changer les CSS vars du document
  const t = themes[tid];
  if (!t) return;
  const root = document.documentElement;
  const isLight = tid.startsWith('light');
  root.style.setProperty('--teal',  t[1]);
  root.style.setProperty('--teal2', t[1]);
  root.style.setProperty('--gold',  t[2]);
  root.style.setProperty('--red',   t[3]);
  root.style.setProperty('--blue',  t[4]);
  if (isLight) {
    root.style.setProperty('--bg',    t[5]);
    root.style.setProperty('--bg2',   t[6] || '#faf5f3');
    root.style.setProperty('--bg3',   t[7] || '#f0e8e4');
    root.style.setProperty('--card',  t[8] || '#ffffff');
    root.style.setProperty('--text',  '#1e293b');
    root.style.setProperty('--text2', '#475569');
    root.style.setProperty('--text3', '#94a3b8');
    root.style.setProperty('--border','rgba(0,0,0,.08)');
    root.style.setProperty('--border2','rgba(0,0,0,.12)');
    root.style.setProperty('--glass', 'rgba(0,0,0,.04)');
    root.style.setProperty('--shadow', '0 8px 32px rgba(0,0,0,.10)');
    root.style.setProperty('--shadow-sm', '0 2px 12px rgba(0,0,0,.06)');
    root.style.setProperty('--btn-text', '#fff');
  } else {
    root.style.setProperty('--bg',    t[5]);
    root.style.setProperty('--bg2',   t[6] || shiftColor(t[5],8));
    root.style.setProperty('--bg3',   t[7] || shiftColor(t[5],20));
    root.style.setProperty('--card',  t[8] || shiftColor(t[5],6));
    root.style.setProperty('--text',  '#e8edf5');
    root.style.setProperty('--text2', '#8b97ab');
    root.style.setProperty('--text3', '#5a6678');
    root.style.setProperty('--border','rgba(255,255,255,.05)');
    root.style.setProperty('--border2','rgba(255,255,255,.08)');
    root.style.setProperty('--glass', 'rgba(255,255,255,.03)');
    root.style.setProperty('--shadow', '0 8px 32px rgba(0,0,0,.45)');
    root.style.setProperty('--shadow-sm', '0 2px 12px rgba(0,0,0,.35)');
    root.style.setProperty('--btn-text', '#000');
  }
  // dim colors
  root.style.setProperty('--teal-dim',   hexToRgba(t[1], .10));
  root.style.setProperty('--gold-dim',   hexToRgba(t[2], .10));
  root.style.setProperty('--red-dim',    hexToRgba(t[3], .10));
  root.style.setProperty('--blue-dim',   hexToRgba(t[4], .10));
  // glow colors
  root.style.setProperty('--teal-glow',  hexToRgba(t[1], .25));
  root.style.setProperty('--gold-glow',  hexToRgba(t[2], .25));
  root.style.setProperty('--red-glow',   hexToRgba(t[3], .25));
  root.style.setProperty('--blue-glow',  hexToRgba(t[4], .25));
}

function shiftColor(hex, amount) {
  const r = parseInt(hex.slice(1,3),16);
  const g = parseInt(hex.slice(3,5),16);
  const b = parseInt(hex.slice(5,7),16);
  return `rgb(${Math.min(255,r+amount)},${Math.min(255,g+amount)},${Math.min(255,b+amount)})`;
}
function hexToRgba(hex, alpha) {
  const r = parseInt(hex.slice(1,3),16);
  const g = parseInt(hex.slice(3,5),16);
  const b = parseInt(hex.slice(5,7),16);
  return `rgba(${r},${g},${b},${alpha})`;
}

function previewFont(font, target) {
  // Polices self-hostées (assets/fonts/fonts.css) — déjà chargées dans le head, rien à charger.
  const el = document.getElementById('preview-' + target);
  if (el) el.style.fontFamily = `'${font}', sans-serif`;
}

function toggleHeureFermeture(mode) {
  const group = document.getElementById('group-heure-fermeture');
  if (mode === 'auto') {
    group.style.opacity = '1';
    group.style.pointerEvents = 'auto';
  } else {
    group.style.opacity = '0.45';
    group.style.pointerEvents = 'none';
  }
}

// Appliquer le thème actuel au chargement
previewTheme('<?= e($p['theme'] ?? 'dark-navy') ?>');
previewFont('<?= e($p['police'] ?? 'Manrope') ?>', 'body');
previewFont('<?= e($p['police_titre'] ?? 'Manrope') ?>', 'title');
</script>
<?php layout_foot(); ?>
