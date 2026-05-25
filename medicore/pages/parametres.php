<?php
$currentPage = 'parametres';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

// Protection: créer app_settings si la table n'existe pas encore
try { getDB()->query("SELECT 1 FROM app_settings LIMIT 1"); }
catch (Exception $__e) {
    try {
        getDB()->exec("CREATE TABLE IF NOT EXISTS `app_settings` (`cle` VARCHAR(60) NOT NULL, `valeur` VARCHAR(500) NOT NULL DEFAULT '', `label` VARCHAR(120) NOT NULL DEFAULT '', PRIMARY KEY (`cle`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $__s2 = getDB()->prepare("INSERT IGNORE INTO app_settings (cle,valeur) VALUES (?,?)");
        foreach ([['app_name','MediCore ERP'],['etablissement',''],['currency_code','XAF'],['currency_symbol','FCFA'],['currency_decimals','0'],['currency_position','after'],['currency_dec_sep',','],['currency_thou_sep',' '],['font_body','DM Sans'],['font_heading','Playfair Display'],['font_size','14'],['date_format','d/m/Y'],['theme_primary','1E3A5F'],['theme_accent','3b82f6'],['theme_preset','ocean'],['theme_bg','0a0e1a'],['theme_surface','111827'],['logo_base64',''],['logo_nom',''],['entete_rapport','']] as $__kv) $__s2->execute($__kv);
    } catch (Exception $__e2) { _log_error('PARAMETRES', 'Echec création app_settings', __FILE__, __LINE__, $__e2); }
}

if (!hasRole(['admin'])) {
    require_once __DIR__ . '/../includes/layout.php';
    echo '<div class="alert alert-red">Accès réservé aux administrateurs.</div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

//  REINITIALISER
if (isset($_GET['reset'])) {
    $defaults = [
        'font_body'         => 'DM Sans',
        'font_heading'      => 'Playfair Display',
        'font_size'         => '14',
        'currency_code'     => 'XAF',
        'currency_symbol'   => 'FCFA',
        'currency_position' => 'after',
        'currency_dec_sep'  => ',',
        'currency_thou_sep' => ' ',
        'currency_decimals' => '0',
        'date_format'       => 'd/m/Y',
        'app_name'          => 'MediCore ERP',
        'etablissement'     => 'Hôpital Universitaire Saint-Charles',
        'adresse'           => '',
        'telephone'         => '',
        'email_contact'     => '',
        'theme_primary'     => '1E3A5F',
        'theme_accent'      => '3b82f6',
        'theme_preset'      => 'ocean',
        'theme_bg'          => '0a0e1a',
        'theme_surface'     => '111827',
    ];
    $stmt = getDB()->prepare("INSERT INTO app_settings (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)");
    foreach ($defaults as $k => $v) $stmt->execute([$k, $v]);
    logActivity('Paramètres réinitialisés', 'yellow', 'settings');
    header('Location: ' . APP_URL . '/parametres.php?saved=1');
    exit;
}

//  UPLOAD LOGO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'upload_logo') {
    csrf_verify();
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg','image/png','image/gif','image/webp','image/svg+xml'];
        $finfo   = finfo_open(FILEINFO_MIME_TYPE);
        $mime    = finfo_file($finfo, $_FILES['logo']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $allowed)) {
            $flash = ['red', 'Format non autorisé. Utilisez JPG, PNG, GIF, WEBP ou SVG.'];
        } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
            $flash = ['red', 'Fichier trop volumineux (max 2 Mo).'];
        } else {
            $data   = file_get_contents($_FILES['logo']['tmp_name']);
            $b64    = 'data:' . $mime . ';base64,' . base64_encode($data);
            $nom    = basename($_FILES['logo']['name']);
            $stmt   = getDB()->prepare("INSERT INTO app_settings (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)");
            $stmt->execute(['logo_base64', $b64]);
            $stmt->execute(['logo_nom', $nom]);
            logActivity("Logo mis à jour: $nom", 'blue', 'settings');
            $flash = ['green', "Logo \"$nom\" importé avec succès."];
        }
    } else {
        $flash = ['red', 'Erreur lors du téléchargement du fichier.'];
    }
    header('Location: ' . APP_URL . '/parametres.php?saved=1');
    exit;
}

//  SUPPRIMER LOGO
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'delete_logo') {
    csrf_verify();
    $stmt = getDB()->prepare("INSERT INTO app_settings (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)");
    $stmt->execute(['logo_base64', '']);
    $stmt->execute(['logo_nom', '']);
    logActivity("Logo supprimé", 'yellow', 'settings');
    header('Location: ' . APP_URL . '/parametres.php?saved=1');
    exit;
}

//  EN-TÊTE RAPPORT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'save_entete') {
    csrf_verify();
    $entete = substr(trim(post_str('entete_rapport')), 0, 500);
    $stmt   = getDB()->prepare("INSERT INTO app_settings (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)");
    $stmt->execute(['entete_rapport', $entete]);
    logActivity("En-tête rapport mis à jour", 'blue', 'settings');
    header('Location: ' . APP_URL . '/parametres.php?saved=1');
    exit;
}

//  SAUVEGARDER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'save_settings') {
    csrf_verify();

    $FONTS_BODY    = ['DM Sans','Inter','Roboto','Nunito','Poppins','Source Sans 3','Space Grotesk','Syne'];
    $FONTS_HEADING = ['Playfair Display','Merriweather','Lora','Crimson Text','DM Sans','Inter','Poppins','Syne'];
    $CURRENCIES    = ['XOF','XAF','CDF','STN','CVE','EUR','USD'];
    $DATE_FMTS     = ['d/m/Y','m/d/Y','Y-m-d','d.m.Y'];

    // currency_thou_sep : lecture directe depuis $_POST car post_str() fait trim()
    // qui supprime l'espace (valeur valide pour "Espace")
    $_thou = $_POST['currency_thou_sep'] ?? ' ';
    if (!in_array($_thou, [' ',',','.',''], true)) $_thou = ' ';

    $updates = [
        'font_body'         => in_whitelist(post_str('font_body'), $FONTS_BODY, 'DM Sans'),
        'font_heading'      => in_whitelist(post_str('font_heading'), $FONTS_HEADING, 'Playfair Display'),
        'font_size'         => (string)max(12, min(20, post_int('font_size', 14))),
        'currency_code'     => in_whitelist(post_str('currency_code'), $CURRENCIES, 'XAF'),
        'currency_symbol'   => substr(trim(post_str('currency_symbol')), 0, 10) ?: 'FCFA',
        'currency_position' => in_whitelist(post_str('currency_position'), ['before','after'], 'after'),
        'currency_dec_sep'  => in_whitelist(post_str('currency_dec_sep'), [',','.'], ','),
        'currency_thou_sep' => $_thou,
        'currency_decimals' => (string)max(0, min(4, post_int('currency_decimals', 2))),
        'date_format'       => in_whitelist(post_str('date_format'), $DATE_FMTS, 'd/m/Y'),
        'app_name'          => post_str('app_name') ?: 'MediCore ERP',
        'etablissement'     => post_str('etablissement'),
        'adresse'           => post_str('adresse'),
        'telephone'         => post_str('telephone'),
        'email_contact'     => post_email('email_contact') ?? '',
        'theme_preset'      => in_whitelist(post_str('theme_preset'), ['ocean','foret','crepuscule','nuit','bordeaux','sarcelle','ambre','magnetique','custom'], 'ocean'),
        'theme_primary'     => preg_match('/^[0-9a-fA-F]{6}$/', post_str('theme_primary')) ? post_str('theme_primary') : '1E3A5F',
        'theme_accent'      => preg_match('/^[0-9a-fA-F]{6}$/', post_str('theme_accent')) ? post_str('theme_accent') : '3b82f6',
        'theme_bg'          => preg_match('/^[0-9a-fA-F]{6}$/', post_str('theme_bg')) ? post_str('theme_bg') : '0a0e1a',
        'theme_surface'     => preg_match('/^[0-9a-fA-F]{6}$/', post_str('theme_surface')) ? post_str('theme_surface') : '111827',
    ];

    try {
        $stmt = getDB()->prepare("INSERT INTO app_settings (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=VALUES(valeur)");
        foreach ($updates as $k => $v) $stmt->execute([$k, $v]);
        logActivity('Paramètres mis à jour — police: '.$updates['font_body'].', monnaie: '.$updates['currency_code'], 'blue', 'settings');
        header('Location: ' . APP_URL . '/parametres.php?saved=1');
    } catch (Exception $e) {
        logActivity('Erreur sauvegarde paramètres: '.$e->getMessage(), 'red', 'settings');
        header('Location: ' . APP_URL . '/parametres.php?error=1');
    }
    exit;
}

//  Charger les paramètres
$s = get_settings();
$cur = [
    'font_body'         => $s['font_body']         ?? 'DM Sans',
    'font_heading'      => $s['font_heading']      ?? 'Playfair Display',
    'font_size'         => (int)($s['font_size']    ?? 14),
    'currency_code'     => $s['currency_code']      ?? 'XAF',
    'currency_symbol'   => $s['currency_symbol']    ?? 'FCFA',
    'currency_position' => $s['currency_position']  ?? 'after',
    'currency_dec_sep'  => $s['currency_dec_sep']   ?? ',',
    'currency_thou_sep' => $s['currency_thou_sep']  ?? ' ',
    'currency_decimals' => (int)($s['currency_decimals'] ?? 0),
    'date_format'       => $s['date_format']        ?? 'd/m/Y',
    'app_name'          => $s['app_name']           ?? 'MediCore ERP',
    'etablissement'     => $s['etablissement']      ?? '',
    'adresse'           => $s['adresse']            ?? '',
    'telephone'         => $s['telephone']          ?? '',
    'email_contact'     => $s['email_contact']      ?? '',
    'logo_base64'       => $s['logo_base64']        ?? '',
    'logo_nom'          => $s['logo_nom']           ?? '',
    'entete_rapport'    => $s['entete_rapport']     ?? '',
    'theme_primary'     => $s['theme_primary']      ?? '1E3A5F',
    'theme_accent'      => $s['theme_accent']       ?? '3b82f6',
    'theme_preset'      => $s['theme_preset']       ?? 'ocean',
    'theme_bg'          => $s['theme_bg']           ?? '0a0e1a',
    'theme_surface'     => $s['theme_surface']       ?? '111827',
];

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('parametres');

$FONTS_BODY    = ['DM Sans'=>'DM Sans — Moderne, lisible','Inter'=>'Inter — Technologique','Roboto'=>'Roboto — Standard Google','Nunito'=>'Nunito — Arrondi','Poppins'=>'Poppins — Géométrique','Source Sans 3'=>'Source Sans 3 — Professionnel','Space Grotesk'=>'Space Grotesk — Futuriste','Syne'=>'Syne — Éditorial'];
$FONTS_HEADING = ['Playfair Display'=>'Playfair Display — Élégant','Merriweather'=>'Merriweather — Académique','Lora'=>'Lora — Chaleureux','Crimson Text'=>'Crimson Text — Classique','DM Sans'=>'DM Sans — Uniforme','Inter'=>'Inter — Moderne','Poppins'=>'Poppins — Géométrique','Syne'=>'Syne — Fort'];
$CURRENCIES    = [
    'XAF' => ['FCFA', 'Franc CFA BEAC (Cameroun, Congo, Gabon, RCA, Tchad, Guinée Eq.)'],
    'XOF' => ['FCFA', 'Franc CFA BCEAO (Bénin, Burkina, Côte Ivoire, Mali, Niger, Sénégal, Togo)'],
    'CDF' => ['FC',   'Franc Congolais (RD Congo)'],
    'STN' => ['Db',   'Dobra (Sao Tomé-et-Príncipe)'],
    'CVE' => ['Esc',  'Escudo (Cap-Vert)'],
    'EUR' => ['EUR',  'Euro (référence internationale)'],
    'USD' => ['USD',  'Dollar US (référence internationale)'],
];
$DATE_FMTS     = ['d/m/Y'=>date('d/m/Y').' — Français','m/d/Y'=>date('m/d/Y').' — Américain','Y-m-d'=>date('Y-m-d').' — ISO 8601','d.m.Y'=>date('d.m.Y').' — Allemand'];
$COMBOS = [
    ['DM Sans','Playfair Display',14,'Médical classique'],
    ['Inter','Inter',14,'Tech épuré'],
    ['Nunito','Lora',15,'Chaleureux'],
    ['Poppins','Merriweather',13,'Professionnel'],
    ['Space Grotesk','Syne',14,'Futuriste'],
];
$THEMES_PRIMARY = [
    '1E3A5F' => 'Bleu marine (défaut)',
    '1a2e1a' => 'Vert forêt',
    '3d1a3a' => 'Pourpre',
    '1a1a2e' => 'Bleu nuit',
    '2e1a1a' => 'Bordeaux',
    '1a2e2e' => 'Sarcelle',
];
$THEMES_ACCENT = [
    '3b82f6' => 'Bleu (défaut)',
    '10b981' => 'Vert émeraude',
    '8b5cf6' => 'Violet',
    'f59e0b' => 'Ambre',
    'ef4444' => 'Rouge',
    '06b6d4' => 'Cyan',
    'ec4899' => 'Rose',
    'f97316' => 'Orange',
];
?>

<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-green alert-auto">Paramètres sauvegardés avec succès. Rechargez pour voir les changements.</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="alert alert-red alert-auto">Erreur lors de la sauvegarde. Vérifiez les données et réessayez.</div>
<?php endif; ?>

<div class="page-header-row" style="margin-bottom:24px">
  <div>
    <h2>Paramètres</h2>
    <p>Personnalisation globale de l'application</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
    <span style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:5px 11px;font-size:11px;color:var(--text2)">Police: <strong style="color:var(--text)"><?= h($cur['font_body']) ?></strong></span>
    <span style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:5px 11px;font-size:11px;color:var(--text2)">Monnaie: <strong style="color:var(--text)"><?= h($cur['currency_code']) ?></strong></span>
    <span style="background:var(--surface2);border:1px solid var(--border2);border-radius:8px;padding:5px 11px;font-size:11px;color:var(--text2)">Date: <strong style="color:var(--text)"><?= h($cur['date_format']) ?></strong></span>
    <a href="parametres.php?reset=1" class="btn btn-sm btn-ghost" onclick="return confirm('Réinitialiser tous les paramètres aux valeurs par défaut ?')">Réinitialiser</a>
  </div>
</div>

<!-- FORMULAIRE PRINCIPAL (pas de formulaires imbriqués) -->
<form method="POST" id="settings-form">
  <input type="hidden" name="action" value="save_settings">
  <?= csrf_field() ?>
  <input type="hidden" name="theme_preset" id="theme_preset_id" value="<?= h($cur['theme_preset']) ?>">
  <input type="hidden" name="theme_primary" id="theme_primary_hex" value="<?= h($cur['theme_primary']) ?>">
  <input type="hidden" name="theme_accent" id="theme_accent_hex" value="<?= h($cur['theme_accent']) ?>">
  <input type="hidden" name="theme_bg" id="theme_bg_hex" value="<?= h($cur['theme_bg']) ?>">
  <input type="hidden" name="theme_surface" id="theme_surface_hex" value="<?= h($cur['theme_surface']) ?>">

  <!-- 1. ÉTABLISSEMENT -->
  <div class="card mb-24">
    <div class="card-header"><h3>Établissement</h3></div>
    <div style="padding:24px">
      <div class="form-grid">
        <div class="form-group form-full"><label>Nom de l'application</label>
          <input type="text" name="app_name" value="<?= h($cur['app_name']) ?>" maxlength="100"></div>
        <div class="form-group form-full"><label>Nom de l'établissement</label>
          <input type="text" name="etablissement" value="<?= h($cur['etablissement']) ?>" maxlength="200" placeholder="ex: Hôpital Universitaire Saint-Charles"></div>
        <div class="form-group form-full"><label>Adresse</label>
          <input type="text" name="adresse" value="<?= h($cur['adresse']) ?>" maxlength="300" placeholder="1 Avenue du Général Leclerc, 75014 Paris"></div>
        <div class="form-group"><label>Téléphone</label>
          <input type="tel" name="telephone" value="<?= h($cur['telephone']) ?>" maxlength="30" placeholder="01 23 45 67 89"></div>
        <div class="form-group"><label>Email de contact</label>
          <input type="email" name="email_contact" value="<?= h($cur['email_contact']) ?>" maxlength="150" placeholder="contact@hopital.fr"></div>
      </div>
    </div>
  </div>

  <!-- 2. THÈME & COULEURS -->
  <div class="card mb-24">
    <div class="card-header"><h3>Thème &amp; Couleurs</h3></div>
    <div style="padding:24px">
      <?php
      $_ADMIN_PRESETS = [
        'ocean'      => ['name'=>'Océan',       'primary'=>'1E3A5F','accent'=>'3b82f6','bg'=>'0a0e1a','surface'=>'111827'],
        'foret'      => ['name'=>'Forêt',       'primary'=>'1a2e1a','accent'=>'10b981','bg'=>'0a120a','surface'=>'111f11'],
        'crepuscule' => ['name'=>'Crépuscule', 'primary'=>'3d1a3a','accent'=>'ec4899','bg'=>'120a14','surface'=>'1f1127'],
        'nuit'       => ['name'=>'Nuit',        'primary'=>'1a1a2e','accent'=>'8b5cf6','bg'=>'0a0a18','surface'=>'111127'],
        'bordeaux'   => ['name'=>'Bordeaux',    'primary'=>'2e1a1a','accent'=>'ef4444','bg'=>'140a0a','surface'=>'1f1111'],
        'sarcelle'   => ['name'=>'Sarcelle',    'primary'=>'1a2e2e','accent'=>'06b6d4','bg'=>'0a1414','surface'=>'111f1f'],
        'ambre'      => ['name'=>'Ambre',       'primary'=>'2e2a1a','accent'=>'f59e0b','bg'=>'14120a','surface'=>'1f1d11'],
        'magnetique' => ['name'=>'Magnétique',  'primary'=>'1a1a2e','accent'=>'f97316','bg'=>'0e0a18','surface'=>'16111f'],
      ];
      $_darken = function($hex, $amt=25) { $r=max(0,hexdec(substr($hex,0,2))-$amt); $g=max(0,hexdec(substr($hex,2,2))-$amt); $b=max(0,hexdec(substr($hex,4,2))-$amt); return sprintf('%02x%02x%02x',$r,$g,$b); };
      ?>
      <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;display:block">Thème par défaut</label>
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:16px">
        <?php foreach ($_ADMIN_PRESETS as $pid => $p): ?>
        <div class="theme-preset-card<?= $cur['theme_preset']===$pid?' active':'' ?>" data-preset="<?= $pid ?>"
             onclick="adminSelectPreset('<?= $pid ?>')"
             style="cursor:pointer">
          <div class="theme-preset-preview">
            <div class="theme-preset-sidebar" style="background:linear-gradient(180deg,#<?= $p['primary'] ?>,#<?= $_darken($p['primary']) ?>)"></div>
            <div class="theme-preset-body" style="background:#<?= $p['bg'] ?>">
              <div class="theme-preset-dot" style="background:#<?= $p['surface'] ?>"></div>
              <div class="theme-preset-dot" style="background:#<?= $p['accent'] ?>;width:20px"></div>
            </div>
          </div>
          <div class="theme-preset-name"><?= h($p['name']) ?></div>
          <?php if ($cur['theme_preset'] === $pid): ?><div class="theme-preset-check">&#10003;</div><?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>

      <div style="background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.2);border-radius:8px;padding:10px 14px;margin-top:4px;font-size:12px;color:var(--text2)">
        Ces paramètres servent de <strong>valeurs par défaut</strong> pour tous les utilisateurs. Chaque utilisateur peut personnaliser son propre thème via son profil dans la barre latérale.
      </div>

      <!-- Aperçu couleurs -->
      <div id="color-preview" style="margin-top:20px;border-radius:10px;overflow:hidden;border:1px solid var(--border2)">
        <div id="prev-sidebar" style="background:#<?= h($cur['theme_primary']) ?>;padding:12px 16px;display:flex;align-items:center;gap:10px">
          <div style="width:28px;height:28px;border-radius:7px;background:#<?= h($cur['theme_accent']) ?>;display:flex;align-items:center;justify-content:center;font-size:14px"></div>
          <span style="color:#fff;font-weight:700;font-size:14px"><?= h($cur['app_name']) ?></span>
        </div>
        <div id="prev-body" style="background:#<?= h($cur['theme_bg']) ?>;padding:12px 16px;display:flex;gap:10px;flex-wrap:wrap">
          <div id="prev-btn" style="background:#<?= h($cur['theme_accent']) ?>;color:#fff;padding:7px 14px;border-radius:7px;font-size:13px;font-weight:600">+ Nouveau patient</div>
          <div style="background:#<?= h($cur['theme_surface']) ?>;border:1px solid var(--border2);padding:7px 14px;border-radius:7px;font-size:13px;color:var(--text2)">Exporter</div>
          <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:rgba(16,185,129,.15);color:var(--green)">Actif</span>
          <span style="padding:3px 10px;border-radius:12px;font-size:11px;font-weight:600;background:rgba(239,68,68,.15);color:var(--red)">Critique</span>
        </div>
      </div>
    </div>
  </div>

  <!-- 3. POLICES -->
  <div class="card mb-24">
    <div class="card-header"><h3>Polices</h3></div>
    <div style="padding:24px">
      <!-- Aperçu polices -->
      <div id="font-preview" style="background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:18px;margin-bottom:24px">
        <div id="prev-heading" style="font-size:22px;font-weight:700;margin-bottom:6px;color:var(--text)">Hôpital Saint-Charles &mdash; Tableau de bord</div>
        <div id="prev-body" style="font-size:14px;color:var(--text2);margin-bottom:10px">Patient Martin Lefebvre &middot; Cardiologie &middot; Chambre 12A &middot; Admis le <?= date($cur['date_format']) ?></div>
        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
          <span class="badge badge-red">Critique</span><span class="badge badge-green">Stable</span>
          <span style="font-weight:700;color:var(--green)" id="prev-money"><?= fmt_money(2480.00) ?></span>
        </div>
      </div>

      <div class="form-grid">
        <div class="form-group">
          <label>Police principale (texte)</label>
          <select name="font_body" id="sel-font-body" onchange="previewFonts()">
            <?php foreach ($FONTS_BODY as $val => $desc): ?>
            <option value="<?= h($val) ?>" <?= $cur['font_body']===$val?'selected':'' ?>><?= h($desc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Police des titres</label>
          <select name="font_heading" id="sel-font-heading" onchange="previewFonts()">
            <?php foreach ($FONTS_HEADING as $val => $desc): ?>
            <option value="<?= h($val) ?>" <?= $cur['font_heading']===$val?'selected':'' ?>><?= h($desc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Taille de base : <strong id="font-size-label"><?= $cur['font_size'] ?>px</strong></label>
          <input type="range" name="font_size" id="font-size-range" min="12" max="20" step="1" value="<?= $cur['font_size'] ?>"
            oninput="document.getElementById('font-size-label').textContent=this.value+'px'; previewFonts()"
            style="width:100%;accent-color:var(--accent);margin-top:8px;cursor:pointer">
          <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--text3);margin-top:4px"><span>12px</span><span>16px</span><span>20px</span></div>
        </div>
        <div class="form-group">
          <label>Combinaisons rapides</label>
          <div style="display:flex;flex-direction:column;gap:5px;margin-top:4px">
            <?php foreach ($COMBOS as [$fb,$fh,$fs,$name]): ?>
            <button type="button" class="btn btn-sm btn-ghost" onclick="applyCombo('<?= h($fb) ?>','<?= h($fh) ?>',<?= $fs ?>)">
              <?= h($name) ?> <span style="color:var(--text3);font-size:10px;margin-left:4px"><?= h($fb) ?> + <?= h($fh) ?></span>
            </button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- 4. MONNAIE & FORMATS -->
  <div class="card mb-24">
    <div class="card-header">
      <h3>Monnaie &amp; Formats</h3>
      <span style="font-size:12px;color:var(--text2)">Aperçu : <strong id="money-preview" style="color:var(--green)"><?= fmt_money(12345.67) ?></strong></span>
    </div>
    <div style="padding:24px">
      <div class="form-grid">
        <div class="form-group">
          <label>Devise</label>
          <select name="currency_code" id="sel-currency" onchange="updateCurrencyPreview(); syncSymbol()">
            <?php foreach ($CURRENCIES as $code => [$sym,$desc]): ?>
            <option value="<?= h($code) ?>" data-symbol="<?= h($sym) ?>" <?= $cur['currency_code']===$code?'selected':'' ?>><?= h($sym) ?> &mdash; <?= h($desc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Symbole affiché</label>
          <input type="text" name="currency_symbol" id="inp-symbol" value="<?= h($cur['currency_symbol']) ?>"
            maxlength="10" oninput="updateCurrencyPreview()"
            style="font-size:18px;font-weight:700;text-align:center">
        </div>
        <div class="form-group">
          <label>Position du symbole</label>
          <div style="display:flex;gap:8px;margin-top:4px">
            <label style="flex:1;display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;cursor:pointer">
              <input type="radio" name="currency_position" value="before" <?= $cur['currency_position']==='before'?'checked':'' ?> onchange="updateCurrencyPreview()" style="accent-color:var(--accent)">
              <span style="font-size:12px">$ 1 234 <small style="color:var(--text3)">Avant</small></span>
            </label>
            <label style="flex:1;display:flex;align-items:center;gap:8px;padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;cursor:pointer">
              <input type="radio" name="currency_position" value="after" <?= $cur['currency_position']==='after'?'checked':'' ?> onchange="updateCurrencyPreview()" style="accent-color:var(--accent)">
              <span style="font-size:12px">1 234 EUR <small style="color:var(--text3)">Après</small></span>
            </label>
          </div>
        </div>
        <div class="form-group">
          <label>Décimales</label>
          <select name="currency_decimals" id="sel-decimals" onchange="updateCurrencyPreview()">
            <option value="0" <?= $cur['currency_decimals']===0?'selected':'' ?>>0 — Entier (1 234)</option>
            <option value="2" <?= $cur['currency_decimals']===2?'selected':'' ?>>2 — Standard (1 234,56)</option>
            <option value="3" <?= $cur['currency_decimals']===3?'selected':'' ?>>3 — Précis (1 234,567)</option>
          </select>
        </div>
        <div class="form-group">
          <label>Séparateur décimal</label>
          <select name="currency_dec_sep" id="sel-dec-sep" onchange="updateCurrencyPreview()">
            <option value="," <?= $cur['currency_dec_sep']===','?'selected':'' ?>>, — Virgule</option>
            <option value="." <?= $cur['currency_dec_sep']==='.'?'selected':'' ?>>. — Point</option>
          </select>
        </div>
        <div class="form-group">
          <label>Séparateur milliers</label>
          <select name="currency_thou_sep" id="sel-thou-sep" onchange="updateCurrencyPreview()">
            <option value=" "  <?= $cur['currency_thou_sep']===' '?'selected':'' ?>>Espace (1 234 567)</option>
            <option value=","  <?= $cur['currency_thou_sep']===','?'selected':'' ?>>, — Virgule</option>
            <option value="."  <?= $cur['currency_thou_sep']==='.'?'selected':'' ?>>. — Point</option>
            <option value=""   <?= $cur['currency_thou_sep']===''?'selected':'' ?>>Aucun</option>
          </select>
        </div>
        <div class="form-group">
          <label>Format des dates</label>
          <select name="date_format">
            <?php foreach ($DATE_FMTS as $fmt => $desc): ?>
            <option value="<?= h($fmt) ?>" <?= $cur['date_format']===$fmt?'selected':'' ?>><?= h($desc) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Aperçu monnaie en contexte -->
      <div style="background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:16px;margin-top:16px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Aperçu en contexte &mdash; Facture</div>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px">
          <div style="background:var(--surface);border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:11px;color:var(--text3)">Total</div>
            <div style="font-size:18px;font-weight:700;color:var(--green)" id="prev-total">--</div>
          </div>
          <div style="background:var(--surface);border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:11px;color:var(--text3)">Assurance</div>
            <div style="font-size:18px;font-weight:700;color:var(--accent2)" id="prev-assurance">--</div>
          </div>
          <div style="background:var(--surface);border-radius:8px;padding:12px;text-align:center">
            <div style="font-size:11px;color:var(--text3)">Patient</div>
            <div style="font-size:18px;font-weight:700;color:var(--yellow)" id="prev-patient">--</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bouton sauvegarder -->
  <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:8px;padding-bottom:32px">
    <a href="parametres.php?reset=1" class="btn btn-ghost" onclick="return confirm('Réinitialiser tous les paramètres ?')">Réinitialiser</a>
    <button type="submit" class="btn btn-blue" style="padding:12px 28px;font-size:14px">Sauvegarder tous les paramètres</button>
  </div>

</form>

<!-- 5. LOGO (formulaire indépendant, en dehors du formulaire principal) -->
<div class="card mb-24">
  <div class="card-header"><h3>Logo &amp; En-tête</h3></div>
  <div style="padding:24px">
    <label style="font-size:13px;font-weight:600;margin-bottom:10px;display:block">Logo de l'établissement</label>
    <?php if (!empty($cur['logo_base64'])): ?>
    <div style="display:flex;align-items:center;gap:14px;background:var(--surface2);border:1px solid var(--border2);border-radius:10px;padding:12px 16px;margin-bottom:12px">
      <img src="<?= h($cur['logo_base64']) ?>" alt="Logo" style="max-height:56px;max-width:180px;object-fit:contain;border-radius:4px">
      <div style="flex:1">
        <div style="font-size:13px;font-weight:600"><?= h($cur['logo_nom']) ?></div>
        <div style="font-size:11px;color:var(--text3)">Logo actuel — affiché sur les rapports et documents</div>
      </div>
      <form method="POST" style="margin:0" onsubmit="return confirm('Supprimer le logo ?')">
        <input type="hidden" name="action" value="delete_logo"><?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-red">Supprimer</button>
      </form>
    </div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <input type="hidden" name="action" value="upload_logo"><?= csrf_field() ?>
      <input type="file" name="logo" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" required
        style="padding:7px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-size:12px;flex:1;min-width:200px;outline:none">
      <button type="submit" class="btn btn-blue btn-sm">Importer</button>
    </form>
    <div style="font-size:11px;color:var(--text3);margin-top:6px">JPG, PNG, SVG — Max 2 Mo</div>

    <div style="border-top:1px solid var(--border);margin:24px 0 20px"></div>

    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:4px">En-tête des rapports imprimables</label>
    <div style="font-size:11px;color:var(--text3);margin-bottom:10px">Texte affiché sous le logo sur chaque document imprimé</div>
    <form method="POST" style="display:flex;flex-direction:column;gap:10px">
      <input type="hidden" name="action" value="save_entete"><?= csrf_field() ?>
      <textarea name="entete_rapport" rows="3" maxlength="500"
        style="width:100%;padding:10px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;resize:vertical;box-sizing:border-box"
        placeholder="Ex: Service de Médecine Interne — Dr. Martin&#10;Tél: +237 6XX XXX XXX"><?= h($cur['entete_rapport'] ?? '') ?></textarea>
      <div><button type="submit" class="btn btn-blue btn-sm">Sauvegarder l'en-tête</button></div>
    </form>
  </div>
</div>

<script>
// Polices Google Fonts
const GF = {
  'DM Sans':'DM+Sans:wght@300;400;500;600;700','Inter':'Inter:wght@300;400;500;600;700',
  'Roboto':'Roboto:wght@300;400;500;700','Nunito':'Nunito:wght@300;400;600;700',
  'Poppins':'Poppins:wght@300;400;500;600;700','Source Sans 3':'Source+Sans+3:wght@300;400;600;700',
  'Playfair Display':'Playfair+Display:wght@600;700','Merriweather':'Merriweather:wght@400;700',
  'Lora':'Lora:wght@400;600;700','Crimson Text':'Crimson+Text:wght@400;600',
  'Space Grotesk':'Space+Grotesk:wght@300;400;500;600;700','Syne':'Syne:wght@400;600;700;800',
};
const loaded = new Set();
function loadFont(name) {
  if (loaded.has(name)||!GF[name]) return;
  const l=document.createElement('link'); l.rel='stylesheet';
  l.href='https://fonts.googleapis.com/css2?family='+GF[name]+'&display=swap';
  document.head.appendChild(l); loaded.add(name);
}

function previewFonts() {
  const body    = document.getElementById('sel-font-body').value;
  const heading = document.getElementById('sel-font-heading').value;
  const size    = parseInt(document.getElementById('font-size-range').value)||14;
  [body,heading].forEach(loadFont);
  setTimeout(() => {
    const ph = document.getElementById('prev-heading');
    const pb = document.getElementById('prev-body');
    if(ph){ ph.style.fontFamily="'"+heading+"',serif";   ph.style.fontSize=(size+8)+'px'; }
    if(pb){ pb.style.fontFamily="'"+body+"',sans-serif"; pb.style.fontSize=size+'px'; }
  }, 250);
}

function applyCombo(body, heading, size) {
  document.getElementById('sel-font-body').value    = body;
  document.getElementById('sel-font-heading').value = heading;
  const r = document.getElementById('font-size-range');
  r.value = size;
  document.getElementById('font-size-label').textContent = size + 'px';
  previewFonts();
}

// Monnaie
function fmt(amount) {
  const sym = document.getElementById('inp-symbol').value || 'FCFA';
  const pos = document.querySelector('input[name="currency_position"]:checked')?.value || 'after';
  const dec = parseInt(document.getElementById('sel-decimals').value)||2;
  const ds  = document.getElementById('sel-dec-sep').value||',';
  const ts  = document.getElementById('sel-thou-sep').value;
  let [i,d] = Math.abs(amount).toFixed(dec).split('.');
  if(ts) i = i.replace(/\B(?=(\d{3})+(?!\d))/g, ts);
  let s = dec>0 ? i+ds+d : i;
  return pos==='before' ? sym+s : s+sym;
}

function updateCurrencyPreview() {
  const f = fmt;
  const el = id => document.getElementById(id);
  if(el('money-preview'))  el('money-preview').textContent  = f(12345.67);
  if(el('prev-money'))     el('prev-money').textContent     = f(2480.00);
  if(el('prev-total'))     el('prev-total').textContent     = f(2480.00);
  if(el('prev-assurance')) el('prev-assurance').textContent = f(1984.00);
  if(el('prev-patient'))   el('prev-patient').textContent   = f(496.00);
}

function syncSymbol() {
  const sel = document.getElementById('sel-currency');
  const sym = sel.options[sel.selectedIndex]?.getAttribute('data-symbol') || 'EUR';
  document.getElementById('inp-symbol').value = sym;
  updateCurrencyPreview();
}

// Couleurs : sync preset cards avec hidden inputs
const ADMIN_PRESETS = {
  ocean:      { name:'Océan',       primary:'1E3A5F',accent:'3b82f6',bg:'0a0e1a',surface:'111827' },
  foret:      { name:'Forêt',       primary:'1a2e1a',accent:'10b981',bg:'0a120a',surface:'111f11' },
  crepuscule: { name:'Crépuscule', primary:'3d1a3a',accent:'ec4899',bg:'120a14',surface:'1f1127' },
  nuit:       { name:'Nuit',        primary:'1a1a2e',accent:'8b5cf6',bg:'0a0a18',surface:'111127' },
  bordeaux:   { name:'Bordeaux',    primary:'2e1a1a',accent:'ef4444',bg:'140a0a',surface:'1f1111' },
  sarcelle:   { name:'Sarcelle',    primary:'1a2e2e',accent:'06b6d4',bg:'0a1414',surface:'111f1f' },
  ambre:      { name:'Ambre',       primary:'2e2a1a',accent:'f59e0b',bg:'14120a',surface:'1f1d11' },
  magnetique: { name:'Magnétique',  primary:'1a1a2e',accent:'f97316',bg:'0e0a18',surface:'16111f' },
};

function adminSelectPreset(presetId) {
  const preset = ADMIN_PRESETS[presetId];
  if (!preset) return;
  document.getElementById('theme_preset_id').value = presetId;
  document.getElementById('theme_primary_hex').value = preset.primary;
  document.getElementById('theme_accent_hex').value = preset.accent;
  document.getElementById('theme_bg_hex').value = preset.bg;
  document.getElementById('theme_surface_hex').value = preset.surface;

  // Update active card
  document.querySelectorAll('.theme-preset-card').forEach(card => {
    const isActive = card.dataset.preset === presetId;
    card.classList.toggle('active', isActive);
    const check = card.querySelector('.theme-preset-check');
    if (isActive && !check) {
      const ck = document.createElement('div');
      ck.className = 'theme-preset-check';
      ck.innerHTML = '&#10003;';
      card.appendChild(ck);
    } else if (!isActive && check) {
      check.remove();
    }
  });

  updateColorPreview();
}

function updateColorPreview() {
  const p = document.getElementById('theme_primary_hex').value;
  const a = document.getElementById('theme_accent_hex').value;
  const bg = document.getElementById('theme_bg_hex').value;
  const sidebar = document.getElementById('prev-sidebar');
  const btn     = document.getElementById('prev-btn');
  const body    = document.getElementById('prev-body');
  if(sidebar) sidebar.style.background = '#'+p;
  if(btn)     btn.style.background     = '#'+a;
  if(body)    body.style.background    = '#'+bg;
}

// Init
document.addEventListener('DOMContentLoaded', () => {
  loadFont(document.getElementById('sel-font-body').value);
  loadFont(document.getElementById('sel-font-heading').value);
  previewFonts();
  updateCurrencyPreview();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';