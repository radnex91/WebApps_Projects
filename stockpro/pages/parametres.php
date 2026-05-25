<?php
$page_title = 'Paramètres';
$page_id = 'parametres';
require_once '../includes/header.php';
requireAuth(['super_admin','admin']);
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'entreprise') {
        $nom    = trim($_POST['nom']);
        $slogan = trim($_POST['slogan']);
        $coul1  = $_POST['couleur_primaire'];
        $coul2  = $_POST['couleur_secondaire'];
        $adresse= trim($_POST['adresse']);
        $tel    = trim($_POST['telephone']);
        $email  = trim($_POST['email']);
        $site   = trim($_POST['site_web']);
        $devise = trim($_POST['devise']);
        $police = trim($_POST['police']);
        $police_titres = trim($_POST['police_titres']);
        $taille = (int)$_POST['taille_texte'];

        if (!validateString($nom, 255, true)) {
            $msg = '<div class="alert alert-danger">Le nom de l\'entreprise est obligatoire.</div>';
        } elseif (!validateColor($coul1) || !validateColor($coul2)) {
            $msg = '<div class="alert alert-danger">Couleurs invalides.</div>';
        } elseif ($email && !validateEmail($email)) {
            $msg = '<div class="alert alert-danger">Email invalide.</div>';
        } elseif (!validateInt($taille, 12, 18)) {
            $msg = '<div class="alert alert-danger">Taille de texte invalide.</div>';
        } else {

        // Upload logo
        $logo_val = null;
        if (!empty($_FILES['logo']['name']) && $_FILES['logo']['error']===0) {
            $ext = strtolower(pathinfo($_FILES['logo']['name'],PATHINFO_EXTENSION));
            $allowed_ext = ['png','jpg','jpeg','webp'];
            $allowed_mime = ['image/png','image/jpeg','image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
            finfo_close($finfo);
            $max_size = 2 * 1024 * 1024; // 2 Mo
            if (in_array($ext, $allowed_ext) && in_array($mime, $allowed_mime) && $_FILES['logo']['size'] <= $max_size) {
                $filename = 'logo_'.time().'.'.$ext;
                $dest = dirname(__DIR__).'/uploads/logos/'.$filename;
                if (move_uploaded_file($_FILES['logo']['tmp_name'], $dest)) $logo_val=$filename;
            }
        }

        // Check if police_titres column exists (migration might not be run yet)
        $hasPoliceTitres = $db->query("SHOW COLUMNS FROM entreprise LIKE 'police_titres'")->rowCount() > 0;
        if ($hasPoliceTitres) {
            $sql = "UPDATE entreprise SET nom=?,slogan=?,couleur_primaire=?,couleur_secondaire=?,adresse=?,telephone=?,email=?,site_web=?,devise=?,police=?,police_titres=?,taille_texte=?";
            $p = [$nom,$slogan,$coul1,$coul2,$adresse,$tel,$email,$site,$devise,$police,$police_titres,$taille];
        } else {
            $sql = "UPDATE entreprise SET nom=?,slogan=?,couleur_primaire=?,couleur_secondaire=?,adresse=?,telephone=?,email=?,site_web=?,devise=?,police=?,taille_texte=?";
            $p = [$nom,$slogan,$coul1,$coul2,$adresse,$tel,$email,$site,$devise,$police,$taille];
        }
        if ($logo_val) { $sql .= ',logo=?'; $p[] = $logo_val; }
        $db->prepare($sql)->execute($p);
        logAudit($db, 'update', 'entreprise', 1, ['nom'=>$nom]);
        $msg = '<div class="alert alert-success">Paramètres sauvegardés. <a href="dashboard.php" style="color:inherit;text-decoration:underline">Rafraîchir la page</a> pour voir les changements.</div>';
        } // end else (validation passed)

        // Reload ent (force refresh on next page load)
        $_SESSION['ent'] = null;
    }
}

$ent = $db->query("SELECT * FROM entreprise LIMIT 1")->fetch();
?>

<?= $msg ?>

<div style="max-width:720px">
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="entreprise">
    <?= csrf_field() ?>

    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><div class="card-title">Identité de l'entreprise</div></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Nom de l'entreprise *</label>
          <input class="form-control" name="nom" required value="<?= htmlspecialchars($ent['nom']) ?>" placeholder="Nom de votre société">
        </div>
        <div class="form-group">
          <label class="form-label">Slogan / Activité</label>
          <input class="form-control" name="slogan" value="<?= htmlspecialchars($ent['slogan']??'') ?>" placeholder="Votre slogan ou secteur d'activité">
        </div>
        <div class="form-group">
          <label class="form-label">Logo (PNG, JPG, SVG — recommandé 200×60px)</label>
          <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px">
            <?php if (!empty($ent['logo']) && file_exists(dirname(__DIR__).'/uploads/logos/'.$ent['logo'])): ?>
            <img src="<?= BASE_URL ?>/uploads/logos/<?= $ent['logo'] ?>" style="height:50px;object-fit:contain;border:1px solid var(--border);border-radius:8px;padding:6px;background:var(--bg)">
            <?php else: ?>
            <div style="width:100px;height:50px;border:1px dashed var(--border);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px;color:var(--muted)">Aucun logo</div>
            <?php endif; ?>
            <input class="form-control" type="file" name="logo" accept="image/*" style="width:auto;flex:1">
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><div class="card-title">Apparence & Thème</div></div>
      <div class="card-body">
        <label class="form-label" style="margin-bottom:14px">Thème</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px" id="theme-grid">
          <?php
          $themes = [
            ['light',           'Clair',          '#6366f1', '#f1f5f9', '#ffffff'],
            ['dark',            'Sombre',         '#6366f1', '#0f172a', '#1e293b'],
            ['nuit-indigo',     'Nuit Indigo',    '#818cf8', '#0c0a1d', '#1e1b3a'],
            ['ocean-profond',   'Océan Profond',  '#38bdf8', '#021a2b', '#063047'],
            ['foret-emeraude',  'Forêt Émeraude', '#34d399', '#021a0f', '#064e3b'],
            ['flamme-noire',    'Flamme Noire',   '#f87171', '#1a0505', '#3d0a0a'],
            ['ambre-nuit',      'Ambre Nuit',     '#fbbf24', '#1a0f00', '#3d1e00'],
            ['ardoise',         'Ardoise',        '#94a3b8', '#0f172a', '#1e293b'],
          ];
          $currentTheme = $_COOKIE['sp_theme'] ?? ($_SESSION['user']['theme'] ?? 'light');
          foreach ($themes as $t): ?>
          <div class="theme-card" data-theme="<?= $t[0] ?>" onclick="selectTheme('<?= $t[0] ?>')" style="display:flex;align-items:center;gap:10px;padding:12px 14px;border-radius:14px;border:2px solid <?= $currentTheme===$t[0] ? $t[2] : 'var(--border)' ?>;cursor:pointer;transition:all .2s;background:<?= $currentTheme===$t[0] ? 'var(--primary-light)' : 'var(--bg)' ?>;position:relative;overflow:hidden">
            <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg, <?= $t[3] ?>, <?= $t[4] ?>);display:flex;align-items:center;justify-content:center;flex-shrink:0;border:1px solid rgba(255,255,255,.1)">
              <div style="width:12px;height:12px;border-radius:50%;background:<?= $t[2] ?>"></div>
            </div>
            <div style="min-width:0">
              <div style="font-weight:700;font-size:13px;color:var(--text);line-height:1.2"><?= $t[1] ?></div>
              <div style="font-size:11px;color:var(--muted);margin-top:1px"><?= $t[0] === 'light' ? 'Classique' : ($t[0] === 'dark' ? 'Standard' : 'Dégradé') ?></div>
            </div>
            <?php if ($currentTheme===$t[0]): ?>
            <div style="position:absolute;top:6px;right:6px;width:18px;height:18px;border-radius:50%;background:<?= $t[2] ?>;display:flex;align-items:center;justify-content:center">
              <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <input type="hidden" name="couleur_primaire" id="cp-input" value="<?= htmlspecialchars($ent['couleur_primaire']) ?>">
        <input type="hidden" name="couleur_secondaire" id="cs-input" value="<?= htmlspecialchars($ent['couleur_secondaire']) ?>">

        <div style="padding:16px;border-radius:10px;background:var(--bg);border:1px solid var(--border)">
          <div style="font-size:12px;color:var(--muted);margin-bottom:8px">Couleur principale (pour les thèmes Clair/Sombre)</div>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php
            $presets=[
              ['Indigo','#6366f1','#0f172a'],
              ['Océan','#0284c7','#0c2d48'],
              ['Émeraude','#059669','#064e3b'],
              ['Flamme','#dc2626','#450a0a'],
              ['Ambre','#d97706','#451a03'],
              ['Ardoise','#475569','#0f172a'],
            ];
            foreach ($presets as $pr): ?>
            <div onclick="applyPreset('<?= $pr[1] ?>','<?= $pr[2] ?>')" style="display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;border:1px solid var(--border);cursor:pointer;font-size:13px;transition:all .15s" onmouseover="this.style.borderColor='<?= $pr[1] ?>'" onmouseout="this.style.borderColor='var(--border)'">
              <div style="width:12px;height:12px;border-radius:50%;background:<?= $pr[1] ?>"></div>
              <?= $pr[0] ?>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><div class="card-title">✨ Polices & Taille du texte</div></div>
      <div class="card-body">
        <input type="hidden" name="police" id="font-input" value="manrope">
        <input type="hidden" name="police_titres" id="heading-input" value="manrope">
        <label class="form-label" style="margin-bottom:14px">Police du texte (corps)</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px" id="font-grid">
          <?php
          $fonts = [
            ['manrope',    'Manrope',       'Moderne · géométrique', '#6366f1', "'Manrope', sans-serif"],
          ];
          $currentFont = 'manrope';
          foreach ($fonts as $f): ?>
          <div class="font-card" data-font="<?= $f[0] ?>" onclick="selectFont('<?= $f[0] ?>')" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:14px;border:2px solid <?= $currentFont===$f[0] ? $f[3] : 'var(--border)' ?>;cursor:pointer;transition:all .2s;background:<?= $currentFont===$f[0] ? 'var(--primary-light)' : 'var(--bg)' ?>;position:relative;overflow:hidden">
            <div style="min-width:0">
              <div style="font-family:<?= $f[4] ?>;font-weight:700;font-size:15px;color:var(--text);line-height:1.2"><?= $f[1] ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= $f[2] ?></div>
            </div>
            <?php if ($currentFont===$f[0]): ?>
            <div style="position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:50%;background:<?= $f[3] ?>;display:flex;align-items:center;justify-content:center">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <label class="form-label" style="margin-bottom:14px">Police des titres</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:24px" id="heading-grid">
          <?php
          $headings = [
            ['manrope',    'Manrope',       'Audacieux · moderne', '#6366f1', "'Manrope', sans-serif"],
          ];
          $currentHeading = 'manrope';
          foreach ($headings as $h): ?>
          <div class="heading-card" data-heading="<?= $h[0] ?>" onclick="selectHeading('<?= $h[0] ?>')" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border-radius:14px;border:2px solid <?= $currentHeading===$h[0] ? $h[3] : 'var(--border)' ?>;cursor:pointer;transition:all .2s;background:<?= $currentHeading===$h[0] ? 'var(--primary-light)' : 'var(--bg)' ?>;position:relative;overflow:hidden">
            <div style="min-width:0">
              <div style="font-family:<?= $h[4] ?>;font-weight:800;font-size:17px;color:var(--text);line-height:1.2"><?= $h[1] ?></div>
              <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= $h[2] ?></div>
            </div>
            <?php if ($currentHeading===$h[0]): ?>
            <div style="position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:50%;background:<?= $h[3] ?>;display:flex;align-items:center;justify-content:center">
              <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>

        <label class="form-label" style="margin-bottom:14px">Taille du texte : <strong id="taille-label" style="color:var(--primary)"><?= $ent['taille_texte']??14 ?>px</strong></label>
        <input type="hidden" name="taille_texte" id="taille-input" value="<?= $ent['taille_texte']??14 ?>">
        <div style="background:var(--bg);border-radius:14px;padding:20px;border:1px solid var(--border)">
          <!-- Zone d'aperçu -->
          <div id="preview-zone" style="margin-bottom:18px;padding:16px 20px;border-radius:10px;background:var(--card);border:1px solid var(--border)">
            <div id="preview-title" style="font-weight:800;font-size:20px;color:var(--text);margin-bottom:6px">Aperçu en temps réel</div>
            <div id="preview-text" style="color:var(--text-2);line-height:1.6">Ceci est un exemple de texte avec la police et la taille sélectionnées. Vous pouvez voir le rendu ici.</div>
          </div>
          <!-- Slider premium -->
          <div style="display:flex;align-items:center;gap:14px">
            <span style="font-size:12px;color:var(--muted);font-weight:600;white-space:nowrap">Aa</span>
            <div style="flex:1;position:relative;height:8px;background:var(--border);border-radius:99px;overflow:hidden">
              <div id="taille-track" style="position:absolute;left:0;top:0;height:100%;background:var(--primary);border-radius:99px;transition:width .15s;width:<?= (($ent['taille_texte']??14) - 12) * 100 / 6 ?>%"></div>
            </div>
            <span style="font-size:18px;color:var(--muted);font-weight:700;white-space:nowrap">Aa</span>
            <span id="taille-value-badge" style="background:var(--primary);color:white;padding:4px 12px;border-radius:8px;font-size:13px;font-weight:700;min-width:46px;text-align:center"><?= $ent['taille_texte']??14 ?>px</span>
          </div>
          <!-- Steps -->
          <div style="display:flex;justify-content:space-between;margin-top:8px;padding:0 2px">
            <?php for ($i=12; $i<=18; $i++): ?>
            <button type="button" onclick="setTaille(<?= $i ?>)" class="taille-step" data-size="<?= $i ?>" style="width:32px;height:28px;border-radius:8px;border:1.5px solid <?= ($ent['taille_texte']??14)==$i ? 'var(--primary)' : 'var(--border)' ?>;background:<?= ($ent['taille_texte']??14)==$i ? 'var(--primary)' : 'var(--card)' ?>;color:<?= ($ent['taille_texte']??14)==$i ? 'white' : 'var(--text-2)' ?>;font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;font-family:var(--font-body)"><?= $i ?></button>
            <?php endfor; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-bottom:20px">
      <div class="card-header"><div class="card-title">Coordonnées & Devise</div></div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Devise</label>
          <select class="form-control form-select" name="devise">
            <?php foreach (['FCFA','EUR','USD','MAD','XOF','GHS','NGN','KES','ZAR'] as $d): ?>
            <option <?= $ent['devise']===$d?'selected':'' ?>><?= $d ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <textarea class="form-control" name="adresse" rows="2"><?= htmlspecialchars($ent['adresse']??'') ?></textarea>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input class="form-control" name="telephone" value="<?= htmlspecialchars($ent['telephone']??'') ?>" placeholder="+237 ...">
          </div>
          <div class="form-group">
            <label class="form-label">Email entreprise</label>
            <input class="form-control" type="email" name="email" value="<?= htmlspecialchars($ent['email']??'') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Site web</label>
          <input class="form-control" name="site_web" value="<?= htmlspecialchars($ent['site_web']??'') ?>" placeholder="https://...">
        </div>
      </div>
    </div>

    <div style="display:flex;gap:12px">
      <button class="btn btn-primary" type="submit">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>
        Sauvegarder les paramètres
      </button>
      <a href="dashboard.php" class="btn btn-secondary">Annuler</a>
    </div>
  </form>
</div>

<script>
// Theme selection
var themeData = {
  'light':          { primary: '#6366f1', secondary: '#0f172a' },
  'dark':           { primary: '#6366f1', secondary: '#0f172a' },
  'nuit-indigo':    { primary: '#818cf8', secondary: '#0c0a1d' },
  'ocean-profond':  { primary: '#38bdf8', secondary: '#021a2b' },
  'foret-emeraude': { primary: '#34d399', secondary: '#021a0f' },
  'flamme-noire':   { primary: '#f87171', secondary: '#1a0505' },
  'ambre-nuit':     { primary: '#fbbf24', secondary: '#1a0f00' },
  'ardoise':        { primary: '#94a3b8', secondary: '#0f172a' }
};

function selectTheme(theme) {
  var info = themeData[theme];
  document.getElementById('cp-input').value = info.primary;
  document.getElementById('cs-input').value = info.secondary;
  document.documentElement.setAttribute('data-theme', theme);
  localStorage.setItem('sp_theme', theme);
  // Update theme cards
  document.querySelectorAll('.theme-card').forEach(function(card) {
    var t = card.getAttribute('data-theme');
    var td = themeData[t];
    if (t === theme) {
      card.style.borderColor = td.primary;
      card.style.background = 'var(--primary-light)';
      if (!card.querySelector('.check-badge')) {
        var badge = document.createElement('div');
        badge.className = 'check-badge';
        badge.style.cssText = 'position:absolute;top:6px;right:6px;width:18px;height:18px;border-radius:50%;background:' + td.primary + ';display:flex;align-items:center;justify-content:center';
        badge.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        card.appendChild(badge);
      }
    } else {
      card.style.borderColor = 'var(--border)';
      card.style.background = 'var(--bg)';
      var existing = card.querySelector('.check-badge');
      if (existing) existing.remove();
    }
  });
}

function applyPreset(primary, secondary) {
  document.getElementById('cp-input').value = primary;
  document.getElementById('cs-input').value = secondary;
  // Switch to light theme when using color presets
  selectTheme('light');
}

// Font selection
var fontMap = {
  manrope:    { css: "'Manrope', sans-serif",        color: '#6366f1' }
};

var headingMap = {
  manrope:    { css: "'Manrope', sans-serif",        color: '#6366f1' }
};

function updateCardStates(container, selected, map) {
  document.querySelectorAll('.' + container + '-card').forEach(function(card) {
    var key = card.getAttribute('data-' + container);
    var info = map[key];
    if (key === selected) {
      card.style.borderColor = info.color;
      card.style.background = 'var(--primary-light)';
      if (!card.querySelector('.check-badge')) {
        var badge = document.createElement('div');
        badge.className = 'check-badge';
        badge.style.cssText = 'position:absolute;top:8px;right:8px;width:20px;height:20px;border-radius:50%;background:' + info.color + ';display:flex;align-items:center;justify-content:center';
        badge.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg>';
        card.appendChild(badge);
      }
    } else {
      card.style.borderColor = 'var(--border)';
      card.style.background = 'var(--bg)';
      var existing = card.querySelector('.check-badge');
      if (existing) existing.remove();
    }
  });
}

function selectFont(font) {
  document.getElementById('font-input').value = font;
  var info = fontMap[font];
  document.documentElement.setAttribute('data-font', font);
  var pz = document.getElementById('preview-zone');
  if (pz) pz.style.fontFamily = info.css;
  var pt = document.getElementById('preview-text');
  if (pt) pt.style.fontFamily = info.css;
  updateCardStates('font', font, fontMap);
}

function selectHeading(heading) {
  document.getElementById('heading-input').value = heading;
  var info = headingMap[heading];
  document.documentElement.setAttribute('data-heading', heading);
  var pt = document.getElementById('preview-title');
  if (pt) pt.style.fontFamily = info.css;
  updateCardStates('heading', heading, headingMap);
}

// Sync theme cards with localStorage on load
(function() {
  var current = localStorage.getItem('sp_theme') || 'light';
  if (current === 'auto') current = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
  selectTheme(current);
})();

// Taille selection
function setTaille(size) {
  document.getElementById('taille-input').value = size;
  document.getElementById('taille-label').textContent = size + 'px';
  document.getElementById('taille-value-badge').textContent = size + 'px';
  document.documentElement.style.fontSize = size + 'px';
  // Update track
  var pct = ((size - 12) * 100) / 6;
  document.getElementById('taille-track').style.width = pct + '%';
  // Update step buttons
  document.querySelectorAll('.taille-step').forEach(function(btn) {
    var s = parseInt(btn.getAttribute('data-size'));
    if (s === size) {
      btn.style.borderColor = 'var(--primary)';
      btn.style.background = 'var(--primary)';
      btn.style.color = 'white';
    } else {
      btn.style.borderColor = 'var(--border)';
      btn.style.background = 'var(--card)';
      btn.style.color = 'var(--text-2)';
    }
  });
  // Update preview
  var pt = document.getElementById('preview-title');
  if (pt) pt.style.fontSize = Math.round(size * 1.43) + 'px';
}
</script>

<?php require_once '../includes/footer.php'; ?>
