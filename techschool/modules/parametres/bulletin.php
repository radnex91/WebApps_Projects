<?php
// modules/parametres/bulletin.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bulletins.config');
$pageTitle = 'Personnalisation du Bulletin';
$annee = getAnneeActive($pdo);
$aid = $annee['id'] ?? 1;

// Charger config existante
$stmt = $pdo->prepare("SELECT * FROM bulletin_config WHERE annee_id=?");
$stmt->execute([$aid]);
$cfg = $stmt->fetch();
if(!$cfg){ $cfg=['annee_id'=>$aid]; }

if($_SERVER['REQUEST_METHOD']==='POST'){
    $fields = ['nom_etablissement','sous_titre','adresse_etab','telephone_etab','email_etab','ville','pays',
               'titre_bulletin','mention_excellent','mention_tb','mention_b','mention_ab','mention_insuffisant',
               'seuil_excellent','seuil_tb','seuil_b','seuil_ab',
               'titre_sign1','titre_sign2','titre_sign3',
               'couleur_entete','couleur_accent','couleur_ligne_pair','police',
               'afficher_rang','afficher_absences','afficher_appreciations','afficher_conseil','afficher_decisions',
               'watermark_text','pied_page'];
    $data=[]; foreach($fields as $f) $data[$f]=trim($_POST[$f]??'') ?: null;
    $data['afficher_rang']         = isset($_POST['afficher_rang'])?1:0;
    $data['afficher_absences']     = isset($_POST['afficher_absences'])?1:0;
    $data['afficher_appreciations']= isset($_POST['afficher_appreciations'])?1:0;
    $data['afficher_conseil']      = isset($_POST['afficher_conseil'])?1:0;
    $data['afficher_decisions']    = isset($_POST['afficher_decisions'])?1:0;

    // Upload logos
    foreach(['logo_path'=>'logo','logo2_path'=>'logo2'] as $field=>$inputName){
        if(!empty($_FILES[$inputName]['name'])){
            $ext=strtolower(pathinfo($_FILES[$inputName]['name'],PATHINFO_EXTENSION));
            if(in_array($ext,['png','jpg','jpeg','gif','svg'])){
                $fname='logo_'.$aid.'_'.$inputName.'_'.time().'.'.$ext;
                $dest=UPLOAD_DIR.'logos/'.$fname;
                if(!is_dir(dirname($dest))) mkdir(dirname($dest),0755,true);
                if(move_uploaded_file($_FILES[$inputName]['tmp_name'],$dest)) $data[$field]=$fname;
            }
        } else { $data[$field]=$cfg[$field]??null; }
    }

    $existing=$pdo->prepare("SELECT id FROM bulletin_config WHERE annee_id=?");
    $existing->execute([$aid]);
    if($existing->fetch()){
        $sets=implode('=?,',array_keys($data)).'=?';
        $pdo->prepare("UPDATE bulletin_config SET $sets WHERE annee_id=?")->execute([...array_values($data),$aid]);
    } else {
        $data['annee_id']=$aid;
        $cols=implode(',',array_keys($data));
        $phs=implode(',',array_fill(0,count($data),'?'));
        $pdo->prepare("INSERT INTO bulletin_config ($cols) VALUES ($phs)")->execute(array_values($data));
    }
    logAction($pdo,'update','bulletins','Personnalisation bulletin mise à jour');
    flash('Configuration du bulletin sauvegardée avec succès.');
    redirect(BASE_URL.'modules/parametres/bulletin.php');
}

// Recharger
$stmt->execute([$aid]); $cfg=$stmt->fetch()?:$cfg;

include '../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a>
  <span class="breadcrumb-sep">/</span>
  <a href="<?= BASE_URL ?>modules/parametres/">Paramètres</a>
  <span class="breadcrumb-sep">/</span> Personnalisation bulletin
</div>

<div class="tabs no-print">
  <div class="tab active" onclick="showTab('entete',this)">🏫 En-tête</div>
  <div class="tab" onclick="showTab('mentions',this)">⭐ Mentions</div>
  <div class="tab" onclick="showTab('signatures',this)">✍️ Signatures</div>
  <div class="tab" onclick="showTab('style',this)">🎨 Style</div>
  <div class="tab" onclick="showTab('options',this)">⚙️ Options</div>
  <div class="tab" onclick="showTab('preview',this)">👁️ Prévisualisation</div>
</div>

<form method="POST" enctype="multipart/form-data" id="config-form">

<!-- ── ONGLET EN-TÊTE ─────────────────────────────────────────── -->
<div id="tab-entete" class="tab-content fade-in">
  <div class="grid-2" style="gap:20px;">
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-school"></i> Informations de l'établissement</h3></div>
      <div class="card-body">
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">Nom de l'établissement <span class="form-required">*</span></label>
          <input type="text" name="nom_etablissement" class="form-control" value="<?= sanitize($cfg['nom_etablissement']??'') ?>" placeholder="LYCÉE TECHNIQUE DE YAOUNDÉ">
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">Sous-titre</label>
          <input type="text" name="sous_titre" class="form-control" value="<?= sanitize($cfg['sous_titre']??'') ?>">
        </div>
        <div class="form-group" style="margin-bottom:14px;">
          <label class="form-label">Adresse complète</label>
          <textarea name="adresse_etab" class="form-control" rows="2"><?= sanitize($cfg['adresse_etab']??'') ?></textarea>
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input type="text" name="telephone_etab" class="form-control" value="<?= sanitize($cfg['telephone_etab']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email_etab" class="form-control" value="<?= sanitize($cfg['email_etab']??'') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Ville</label>
            <input type="text" name="ville" class="form-control" value="<?= sanitize($cfg['ville']??'Yaoundé') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Pays</label>
            <input type="text" name="pays" class="form-control" value="<?= sanitize($cfg['pays']??'Cameroun') ?>">
          </div>
        </div>
        <div class="form-group" style="margin-top:14px;">
          <label class="form-label">Titre du bulletin</label>
          <input type="text" name="titre_bulletin" class="form-control" value="<?= sanitize($cfg['titre_bulletin']??'BULLETIN DE NOTES') ?>">
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fas fa-image"></i> Logos</h3></div>
      <div class="card-body">
        <div class="form-grid" style="gap:20px;">
          <div class="form-group">
            <label class="form-label">Logo gauche (armoiries/emblème)</label>
            <?php if(!empty($cfg['logo_path']) && file_exists(UPLOAD_DIR.'logos/'.$cfg['logo_path'])): ?>
            <img src="<?= BASE_URL ?>uploads/logos/<?= sanitize($cfg['logo_path']) ?>" style="max-height:80px;margin-bottom:8px;border-radius:8px;border:1px solid var(--border);">
            <?php endif; ?>
            <input type="file" name="logo" class="form-control" accept="image/*">
            <div class="form-hint">PNG, JPG, SVG (max 2Mo) — recommandé: 200×200px</div>
          </div>
          <div class="form-group">
            <label class="form-label">Logo droit (établissement)</label>
            <?php if(!empty($cfg['logo2_path']) && file_exists(UPLOAD_DIR.'logos/'.$cfg['logo2_path'])): ?>
            <img src="<?= BASE_URL ?>uploads/logos/<?= sanitize($cfg['logo2_path']) ?>" style="max-height:80px;margin-bottom:8px;border-radius:8px;border:1px solid var(--border);">
            <?php endif; ?>
            <input type="file" name="logo2" class="form-control" accept="image/*">
            <div class="form-hint">PNG, JPG, SVG (max 2Mo)</div>
          </div>
        </div>
        <div style="background:var(--bg);border-radius:var(--radius);padding:12px;margin-top:16px;font-size:12px;color:var(--text2);">
          <i class="fas fa-info-circle" style="color:var(--info)"></i>
          <strong>Astuce :</strong> Le logo gauche est généralement l'emblème national ou les armoiries. Le logo droit est le logo de votre établissement.
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ── ONGLET MENTIONS ────────────────────────────────────────── -->
<div id="tab-mentions" class="tab-content fade-in" style="display:none;">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-star"></i> Configuration des mentions et seuils</h3></div>
    <div class="card-body">
      <table style="width:100%;border-collapse:collapse;">
        <thead><tr>
          <th style="padding:10px;background:var(--bg2);border:1px solid var(--border);">Couleur indicative</th>
          <th style="padding:10px;background:var(--bg2);border:1px solid var(--border);">Libellé de la mention</th>
          <th style="padding:10px;background:var(--bg2);border:1px solid var(--border);">Seuil (note ≥)</th>
          <th style="padding:10px;background:var(--bg2);border:1px solid var(--border);">Aperçu</th>
        </tr></thead>
        <tbody>
          <?php
          $mentionRows=[
            ['excellent','#16a34a','mention_excellent','seuil_excellent','Excellent/Très Bien'],
            ['tb','#2563eb','mention_tb','seuil_tb','Bien'],
            ['b','#0891b2','mention_b','seuil_b','Assez Bien'],
            ['ab','#d97706','mention_ab','seuil_ab','Passable'],
          ];
          foreach($mentionRows as [$key,$col,$mfield,$sfield,$default]): ?>
          <tr>
            <td style="padding:10px;border:1px solid var(--border);text-align:center;">
              <div style="width:20px;height:20px;border-radius:50%;background:<?= $col ?>;margin:0 auto;"></div>
            </td>
            <td style="padding:10px;border:1px solid var(--border);">
              <input type="text" name="<?= $mfield ?>" class="form-control" value="<?= sanitize($cfg[$mfield]??$default) ?>">
            </td>
            <td style="padding:10px;border:1px solid var(--border);">
              <input type="number" name="<?= $sfield ?>" class="form-control" value="<?= $cfg[$sfield]??'' ?>" min="0" max="20" step="0.5" style="width:80px;">
            </td>
            <td style="padding:10px;border:1px solid var(--border);">
              <span class="mention-<?= $key ?>"><?= sanitize($cfg[$mfield]??$default) ?></span>
            </td>
          </tr>
          <?php endforeach; ?>
          <tr>
            <td style="padding:10px;border:1px solid var(--border);text-align:center;">
              <div style="width:20px;height:20px;border-radius:50%;background:#dc2626;margin:0 auto;"></div>
            </td>
            <td style="padding:10px;border:1px solid var(--border);">
              <input type="text" name="mention_insuffisant" class="form-control" value="<?= sanitize($cfg['mention_insuffisant']??'Insuffisant') ?>">
            </td>
            <td style="padding:10px;border:1px solid var(--border);color:var(--text3);font-size:12px;">En dessous du seuil Passable</td>
            <td style="padding:10px;border:1px solid var(--border);"><span class="mention-insuf"><?= sanitize($cfg['mention_insuffisant']??'Insuffisant') ?></span></td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── ONGLET SIGNATURES ──────────────────────────────────────── -->
<div id="tab-signatures" class="tab-content fade-in" style="display:none;">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-pen"></i> Libellés des signatures</h3></div>
    <div class="card-body">
      <div class="form-grid">
        <div class="form-group">
          <label class="form-label">Titre Signature 1 (gauche)</label>
          <input type="text" name="titre_sign1" class="form-control" value="<?= sanitize($cfg['titre_sign1']??'Le Directeur') ?>">
          <div class="form-hint">Ex: Le Directeur, Le Proviseur, La Principale</div>
        </div>
        <div class="form-group">
          <label class="form-label">Titre Signature 2 (centre)</label>
          <input type="text" name="titre_sign2" class="form-control" value="<?= sanitize($cfg['titre_sign2']??'Le Professeur Principal') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Titre Signature 3 (droite)</label>
          <input type="text" name="titre_sign3" class="form-control" value="<?= sanitize($cfg['titre_sign3']??'Parent / Tuteur') ?>">
        </div>
      </div>
      <div class="form-group" style="margin-top:16px;">
        <label class="form-label">Pied de page</label>
        <textarea name="pied_page" class="form-control" rows="2" placeholder="Ex: Ce bulletin est un document officiel. Toute falsification est passible de sanctions."><?= sanitize($cfg['pied_page']??'') ?></textarea>
      </div>
    </div>
  </div>
</div>

<!-- ── ONGLET STYLE ────────────────────────────────────────────── -->
<div id="tab-style" class="tab-content fade-in" style="display:none;">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-paint-brush"></i> Style visuel du bulletin</h3></div>
    <div class="card-body">
      <div class="form-grid" style="gap:20px;">
        <div class="form-group">
          <label class="form-label">Couleur de l'en-tête / titres</label>
          <div style="display:flex;align-items:center;gap:10px;">
            <input type="color" name="couleur_entete" id="col_entete" value="<?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>" style="width:50px;height:40px;cursor:pointer;border-radius:6px;border:1px solid var(--border);">
            <input type="text" id="col_entete_txt" value="<?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>" class="form-control" style="width:120px;" oninput="document.getElementById('col_entete').value=this.value">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Couleur d'accent / catégories</label>
          <div style="display:flex;align-items:center;gap:10px;">
            <input type="color" name="couleur_accent" id="col_accent" value="<?= sanitize($cfg['couleur_accent']??'#2563eb') ?>" style="width:50px;height:40px;cursor:pointer;border-radius:6px;border:1px solid var(--border);">
            <input type="text" id="col_accent_txt" value="<?= sanitize($cfg['couleur_accent']??'#2563eb') ?>" class="form-control" style="width:120px;" oninput="document.getElementById('col_accent').value=this.value">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Couleur lignes paires</label>
          <div style="display:flex;align-items:center;gap:10px;">
            <input type="color" name="couleur_ligne_pair" id="col_ligne" value="<?= sanitize($cfg['couleur_ligne_pair']??'#f0f7ff') ?>" style="width:50px;height:40px;cursor:pointer;border-radius:6px;border:1px solid var(--border);">
            <input type="text" value="<?= sanitize($cfg['couleur_ligne_pair']??'#f0f7ff') ?>" class="form-control" style="width:120px;">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Police principale</label>
          <select name="police" class="form-control">
            <?php foreach(['Times New Roman','Arial','Helvetica','Georgia','Calibri','Verdana','Tahoma'] as $font): ?>
            <option value="<?= $font ?>" <?= ($cfg['police']??'')===$font?'selected':'' ?>><?= $font ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group full">
          <label class="form-label">Filigrane (watermark)</label>
          <input type="text" name="watermark_text" class="form-control" value="<?= sanitize($cfg['watermark_text']??'') ?>" placeholder="Ex: CONFIDENTIEL, ORIGINAL, COPIE... (laisser vide pour désactiver)">
        </div>
      </div>

      <!-- PRÉVISUALISATION DES COULEURS -->
      <div style="margin-top:20px;border:2px solid var(--border);border-radius:var(--radius);overflow:hidden;">
        <div style="background:<?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>;color:#fff;padding:12px 16px;font-weight:700;font-size:14px;">
          Aperçu en-tête — <?= sanitize($cfg['nom_etablissement']??'NOM ÉTABLISSEMENT') ?>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:12px;">
          <tr style="background:<?= sanitize($cfg['couleur_accent']??'#2563eb') ?>;color:#fff;">
            <th style="padding:6px 10px;border:1px solid #ccc;">Matière</th>
            <th style="padding:6px 10px;border:1px solid #ccc;width:50px;">Coeff.</th>
            <th style="padding:6px 10px;border:1px solid #ccc;width:60px;">Note</th>
          </tr>
          <tr style="background:#fff;"><td style="padding:5px 10px;border:1px solid #ddd;">Mathématiques</td><td style="padding:5px 10px;border:1px solid #ddd;text-align:center;">4</td><td style="padding:5px 10px;border:1px solid #ddd;text-align:center;font-weight:700;color:#166534;">15.50</td></tr>
          <tr style="background:<?= sanitize($cfg['couleur_ligne_pair']??'#f0f7ff') ?>;"><td style="padding:5px 10px;border:1px solid #ddd;">Algorithmique</td><td style="padding:5px 10px;border:1px solid #ddd;text-align:center;">4</td><td style="padding:5px 10px;border:1px solid #ddd;text-align:center;font-weight:700;color:#166534;">17.00</td></tr>
          <tr style="background:#fff;"><td style="padding:5px 10px;border:1px solid #ddd;">Anglais</td><td style="padding:5px 10px;border:1px solid #ddd;text-align:center;">2</td><td style="padding:5px 10px;border:1px solid #ddd;text-align:center;font-weight:700;color:#991b1b;">08.00</td></tr>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ── ONGLET OPTIONS ─────────────────────────────────────────── -->
<div id="tab-options" class="tab-content fade-in" style="display:none;">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-toggle-on"></i> Options d'affichage</h3></div>
    <div class="card-body">
      <?php
      $options=[
        ['afficher_rang','Afficher le rang de l\'élève','Affiche la position de l\'élève dans sa classe'],
        ['afficher_absences','Afficher les absences','Affiche le nombre d\'heures d\'absence'],
        ['afficher_appreciations','Afficher les appréciations par matière','Ajoute une colonne appréciation (Très Bien, Bien...)'],
        ['afficher_conseil','Afficher l\'appréciation du conseil de classe','Affiche les remarques du conseil'],
        ['afficher_decisions','Afficher la décision du conseil','Affiche: Passage, Redoublement, Félicitations...'],
      ];
      foreach($options as [$field,$label,$hint]):
        $checked = !empty($cfg[$field]) ? 'checked' : '';
      ?>
      <div style="display:flex;align-items:flex-start;gap:12px;padding:12px 0;border-bottom:1px solid var(--border);">
        <label class="toggle-switch" style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;margin-top:2px;">
          <input type="checkbox" name="<?= $field ?>" value="1" <?= $checked ?> style="opacity:0;width:0;height:0;" id="opt_<?= $field ?>">
          <span onclick="document.getElementById('opt_<?= $field ?>').click()" style="position:absolute;cursor:pointer;inset:0;background:<?= $checked?'var(--success)':'#cbd5e1' ?>;border-radius:34px;transition:.3s;" id="sw_<?= $field ?>">
            <span style="position:absolute;content:'';height:18px;width:18px;left:3px;bottom:3px;background:white;border-radius:50%;transition:.3s;transform:<?= $checked?'translateX(20px)':'' ?>"></span>
          </span>
        </label>
        <div>
          <div style="font-weight:500;font-size:13px;"><?= $label ?></div>
          <div style="font-size:12px;color:var(--text3);"><?= $hint ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ── ONGLET PRÉVISUALISATION ────────────────────────────────── -->
<div id="tab-preview" class="tab-content fade-in" style="display:none;">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-eye"></i> Aperçu de l'en-tête du bulletin</h3>
      <a href="<?= BASE_URL ?>modules/bulletins/" class="btn btn-primary btn-sm"><i class="fas fa-file-alt"></i> Générer un bulletin réel</a>
    </div>
    <div class="card-body">
      <div style="border:2px solid #e5e7eb;border-radius:var(--radius);overflow:hidden;font-family:<?= sanitize($cfg['police']??'Times New Roman') ?>,serif;">
        <table style="width:100%;border-collapse:collapse;border:2px solid <?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>;">
          <tr>
            <td style="width:90px;text-align:center;padding:10px;border-right:1px solid <?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>;">
              <?php if(!empty($cfg['logo_path'])): ?>
              <img src="<?= BASE_URL ?>uploads/logos/<?= sanitize($cfg['logo_path']) ?>" style="max-width:70px;max-height:70px;">
              <?php else: ?><div style="font-size:32px;">🎓</div><?php endif; ?>
            </td>
            <td style="text-align:center;padding:10px;">
              <div style="font-size:7px;color:#888;text-transform:uppercase;letter-spacing:1px;">République du <?= sanitize($cfg['pays']??'Cameroun') ?></div>
              <div style="font-size:16px;font-weight:900;color:<?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>;text-transform:uppercase;"><?= sanitize($cfg['nom_etablissement']??'LYCÉE TECHNIQUE') ?></div>
              <div style="font-size:10px;color:#555;"><?= sanitize($cfg['sous_titre']??'') ?></div>
              <div style="font-size:9px;color:#888;"><?= sanitize($cfg['adresse_etab']??'') ?></div>
            </td>
            <td style="width:90px;text-align:center;padding:10px;border-left:1px solid <?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>;">
              <div style="font-size:9px;color:#888;">Année scolaire</div>
              <div style="font-size:14px;font-weight:800;color:<?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>;"><?= sanitize($annee['libelle']??'2024-2025') ?></div>
            </td>
          </tr>
          <tr>
            <td colspan="3" style="background:<?= sanitize($cfg['couleur_entete']??'#1e3a5f') ?>;color:#fff;text-align:center;padding:7px;font-size:14px;font-weight:800;letter-spacing:2px;">
              <?= sanitize($cfg['titre_bulletin']??'BULLETIN DE NOTES') ?> — Séquence 1
            </td>
          </tr>
        </table>
        <div style="padding:12px;background:#f9f9f9;font-size:11px;color:#666;text-align:center;">
          ↑ Aperçu de l'en-tête — le contenu réel inclura les notes de l'élève
        </div>
      </div>
    </div>
  </div>
</div>

<!-- BOUTON SAUVEGARDER -->
<div class="card" style="margin-top:16px;">
  <div class="card-body" style="display:flex;justify-content:flex-end;gap:10px;padding:14px 20px;">
    <a href="<?= BASE_URL ?>index.php" class="btn btn-secondary">Annuler</a>
    <button type="submit" class="btn btn-primary btn-lg">
      <i class="fas fa-save"></i> Sauvegarder la configuration
    </button>
  </div>
</div>

</form>

<script>
function showTab(id, el) {
  document.querySelectorAll('.tab-content').forEach(t => t.style.display='none');
  document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
  document.getElementById('tab-'+id).style.display='';
  el.classList.add('active');
}
// Sync color pickers
document.getElementById('col_entete')?.addEventListener('input', e => {
  document.getElementById('col_entete_txt').value = e.target.value;
});
document.getElementById('col_accent')?.addEventListener('input', e => {
  document.getElementById('col_accent_txt').value = e.target.value;
});
</script>

<?php include '../../includes/footer.php'; ?>
