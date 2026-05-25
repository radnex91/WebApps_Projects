<?php
// modules/bulletins/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bulletins.view');
$pageTitle = 'Bulletins de Notes';
$annee = getAnneeActive($pdo);
$aid = $annee['id'] ?? 1;

$classes  = $pdo->query("SELECT c.*,n.nom as niveau_nom,f.nom as filiere_nom,f.couleur FROM classes c JOIN niveaux n ON c.niveau_id=n.id JOIN filieres f ON c.filiere_id=f.id WHERE c.annee_id=$aid ORDER BY n.ordre,c.nom")->fetchAll();
$periodes = $pdo->query("SELECT * FROM periodes WHERE annee_id=$aid ORDER BY ordre")->fetchAll();

$sel_classe  = (int)($_GET['classe_id'] ?? 0);
$sel_periode = (int)($_GET['periode_id'] ?? 0);
$sel_eleve   = (int)($_GET['eleve_id'] ?? 0);
$mode        = $_GET['mode'] ?? 'select';

$eleves = [];
if($sel_classe){
    $st = $pdo->prepare("SELECT e.*,i.id as insc_id FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id WHERE i.classe_id=? AND i.annee_id=? AND i.statut='actif' ORDER BY e.nom,e.prenom");
    $st->execute([$sel_classe,$aid]);
    $eleves = $st->fetchAll();
}

// Config bulletin
$config = $pdo->prepare("SELECT * FROM bulletin_config WHERE annee_id=?");
$config->execute([$aid]);
$cfg = $config->fetch() ?: [];

// Données bulletin
$bulletin = null;
if($sel_eleve && $sel_periode && $mode==='view'){
    // Élève
    $es = $pdo->prepare("SELECT e.*,CONCAT(p.prenom,' ',p.nom) as parent_nom,p.telephone as parent_tel FROM eleves e LEFT JOIN parents p ON e.parent_id=p.id WHERE e.id=?");
    $es->execute([$sel_eleve]); $eleveInfo = $es->fetch();

    // Classe & inscription
    $cs = $pdo->prepare("SELECT c.*,n.nom as niveau_nom,f.nom as filiere_nom,f.couleur FROM inscriptions i JOIN classes c ON i.classe_id=c.id JOIN niveaux n ON c.niveau_id=n.id JOIN filieres f ON c.filiere_id=f.id WHERE i.eleve_id=? AND i.annee_id=?");
    $cs->execute([$sel_eleve,$aid]); $classeInfo = $cs->fetch();

    // Notes
    $data = calculerBulletin($pdo,$sel_eleve,$sel_periode,$aid);

    // Absences
    $abs = $pdo->prepare("SELECT COALESCE(SUM(nb_heures),0) as total, COALESCE(SUM(CASE WHEN justifie=1 THEN nb_heures ELSE 0 END),0) as justif FROM absences WHERE eleve_id=? AND annee_id=?");
    $abs->execute([$sel_eleve,$aid]); $absInfo = $abs->fetch();

    // Rang dans la classe
    $rankQ = $pdo->prepare("SELECT i.eleve_id, ROUND(SUM(n.note*mc.coefficient)/NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN mc.coefficient ELSE 0 END),0),2) as moy FROM inscriptions i LEFT JOIN notes n ON n.eleve_id=i.eleve_id AND n.periode_id=? AND n.annee_id=? JOIN matiere_classe mc ON mc.matiere_id=n.matiere_id AND mc.classe_id=i.classe_id WHERE i.classe_id=? AND i.annee_id=? GROUP BY i.eleve_id ORDER BY moy DESC");
    $rankQ->execute([$sel_periode,$aid,$sel_classe,$aid]);
    $classement = $rankQ->fetchAll();
    $total_cl = count($classement); $rang = 1;
    foreach($classement as $cr){ if($cr['eleve_id']==$sel_eleve) break; $rang++; }

    // Conseil
    $cons = $pdo->prepare("SELECT * FROM conseils_classe WHERE eleve_id=? AND periode_id=?");
    $cons->execute([$sel_eleve,$sel_periode]); $conseil = $cons->fetch();

    // Période info
    $perInfo = $pdo->prepare("SELECT * FROM periodes WHERE id=?");
    $perInfo->execute([$sel_periode]); $perInfo = $perInfo->fetch();

    $mention = getMention((float)($data['moyenne']??0),$cfg);
    $bulletin = compact('eleveInfo','classeInfo','data','absInfo','rang','total_cl','conseil','perInfo','mention');
}

include '../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a>
  <span class="breadcrumb-sep">/</span> Bulletins
</div>

<!-- SÉLECTEUR -->
<div class="card no-print" style="margin-bottom:20px;">
  <div class="card-header">
    <h3><i class="fas fa-file-alt"></i> Génération des bulletins</h3>
    <div style="display:flex;gap:8px;">
      <?php if($bulletin && can('bulletins.print')): ?>
      <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Imprimer</button>
      <a href="?classe_id=<?= $sel_classe ?>&periode_id=<?= $sel_periode ?>&mode=batch" class="btn btn-secondary btn-sm"><i class="fas fa-layer-group"></i> Tous les bulletins</a>
      <?php endif; ?>
      <?php if(can('bulletins.config')): ?>
      <a href="<?= BASE_URL ?>modules/parametres/bulletin.php" class="btn btn-outline btn-sm"><i class="fas fa-paint-brush"></i> Personnaliser</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="form-grid">
      <div class="form-group">
        <label class="form-label">Classe</label>
        <select name="classe_id" class="form-control" onchange="this.form.submit()">
          <option value="">— Sélectionner —</option>
          <?php foreach($classes as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $sel_classe==$c['id']?'selected':'' ?>><?= sanitize($c['nom'].' ('.$c['niveau_nom'].')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Période / Séquence</label>
        <select name="periode_id" class="form-control">
          <option value="">— Sélectionner —</option>
          <?php foreach($periodes as $p): ?>
          <option value="<?= $p['id'] ?>" <?= $sel_periode==$p['id']?'selected':'' ?>><?= sanitize($p['nom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if(!empty($eleves)): ?>
      <div class="form-group">
        <label class="form-label">Élève</label>
        <select name="eleve_id" class="form-control">
          <option value="">— Tous les élèves —</option>
          <?php foreach($eleves as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $sel_eleve==$e['id']?'selected':'' ?>><?= sanitize($e['nom'].' '.$e['prenom']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group" style="justify-content:flex-end;padding-top:18px;">
        <button type="submit" name="mode" value="view" class="btn btn-primary"><i class="fas fa-eye"></i> Afficher</button>
      </div>
    </form>
  </div>
</div>

<?php if($mode==='select' && !$bulletin): ?>
<div class="empty-state card" style="padding:60px;">
  <i class="fas fa-file-alt"></i>
  <h3>Sélectionnez les paramètres</h3>
  <p>Choisissez une classe, une période et un élève pour générer le bulletin</p>
</div>

<?php elseif($bulletin): ?>
<?php $b=$bulletin; $c=$cfg; ?>

<!-- ══════════════════════════════════════════════════════════
     BULLETIN IMPRIMABLE
     ══════════════════════════════════════════════════════════ -->
<div id="bulletin-sheet" style="background:#fff;max-width:800px;margin:0 auto;font-family:<?= sanitize($c['police']??'Times New Roman') ?>,serif;font-size:12px;padding:20px;">

  <!-- EN-TÊTE -->
  <table style="width:100%;border-collapse:collapse;border:2px solid <?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;margin-bottom:8px;">
    <tr>
      <!-- LOGO GAUCHE -->
      <td style="width:90px;text-align:center;padding:10px;border-right:1px solid <?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;">
        <?php if(!empty($c['logo_path']) && file_exists(UPLOAD_DIR.'logos/'.$c['logo_path'])): ?>
        <img src="<?= BASE_URL ?>uploads/logos/<?= sanitize($c['logo_path']) ?>" style="max-width:75px;max-height:75px;object-fit:contain;">
        <?php else: ?>
        <div style="width:70px;height:70px;border:2px solid <?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:22px;color:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>">🎓</div>
        <?php endif; ?>
      </td>
      <!-- INFO ÉTABLISSEMENT -->
      <td style="text-align:center;padding:10px 16px;">
        <div style="font-size:7.5px;font-weight:700;color:var(--text2);text-transform:uppercase;letter-spacing:1px;">République du <?= sanitize($c['pays']??'Cameroun') ?></div>
        <div style="font-size:7px;color:var(--text3);margin-bottom:6px;">Paix – Travail – Patrie</div>
        <div style="font-size:16px;font-weight:900;color:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;text-transform:uppercase;line-height:1.2;"><?= sanitize($c['nom_etablissement']??'LYCÉE TECHNIQUE') ?></div>
        <div style="font-size:10px;color:#374151;margin:3px 0;"><?= sanitize($c['sous_titre']??'') ?></div>
        <div style="font-size:9px;color:#6b7280;"><?= sanitize($c['adresse_etab']??'') ?></div>
        <div style="font-size:9px;color:#6b7280;">Tél: <?= sanitize($c['telephone_etab']??'') ?> | <?= sanitize($c['email_etab']??'') ?></div>
      </td>
      <!-- LOGO DROIT -->
      <td style="width:90px;text-align:center;padding:10px;border-left:1px solid <?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;">
        <?php if(!empty($c['logo2_path']) && file_exists(UPLOAD_DIR.'logos/'.$c['logo2_path'])): ?>
        <img src="<?= BASE_URL ?>uploads/logos/<?= sanitize($c['logo2_path']) ?>" style="max-width:75px;max-height:75px;object-fit:contain;">
        <?php else: ?>
        <div style="text-align:center;">
          <div style="font-size:8px;color:#6b7280;">Année scolaire</div>
          <div style="font-size:13px;font-weight:800;color:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;"><?= sanitize($annee['libelle']??'') ?></div>
        </div>
        <?php endif; ?>
      </td>
    </tr>
    <tr>
      <td colspan="3" style="background:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;color:#fff;text-align:center;padding:7px;font-size:15px;font-weight:800;letter-spacing:2px;">
        <?= sanitize($c['titre_bulletin']??'BULLETIN DE NOTES') ?>
        — <?= sanitize($b['perInfo']['nom']??'') ?>
      </td>
    </tr>
  </table>

  <!-- INFOS ÉLÈVE -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #999;margin-bottom:8px;font-size:11px;">
    <tr style="background:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>20;">
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Nom :</strong> <?= sanitize(strtoupper($b['eleveInfo']['nom'])) ?></td>
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Prénom :</strong> <?= sanitize($b['eleveInfo']['prenom']) ?></td>
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Matricule :</strong> <?= sanitize($b['eleveInfo']['matricule']) ?></td>
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Sexe :</strong> <?= $b['eleveInfo']['sexe']==='M'?'Masculin':'Féminin' ?></td>
    </tr>
    <tr>
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Né(e) le :</strong> <?= formatDate($b['eleveInfo']['date_naissance']??'') ?></td>
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Classe :</strong> <?= sanitize($b['classeInfo']['nom']??'') ?></td>
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Filière :</strong> <?= sanitize($b['classeInfo']['filiere_nom']??'') ?></td>
      <td style="padding:5px 10px;border:1px solid #999;"><strong>Niveau :</strong> <?= sanitize($b['classeInfo']['niveau_nom']??'') ?></td>
    </tr>
    <?php if(!empty($b['eleveInfo']['parent_nom'])): ?>
    <tr>
      <td colspan="2" style="padding:5px 10px;border:1px solid #999;"><strong>Parent/Tuteur :</strong> <?= sanitize($b['eleveInfo']['parent_nom']) ?></td>
      <td colspan="2" style="padding:5px 10px;border:1px solid #999;"><strong>Tél. :</strong> <?= sanitize($b['eleveInfo']['parent_tel']??'—') ?></td>
    </tr>
    <?php endif; ?>
  </table>

  <!-- TABLEAU DES NOTES -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #999;margin-bottom:8px;font-size:11px;">
    <thead>
      <tr style="background:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;color:#fff;text-align:center;">
        <th style="padding:6px 8px;border:1px solid #666;text-align:left;">Matière</th>
        <th style="padding:6px 8px;border:1px solid #666;width:40px;">Coeff.</th>
        <th style="padding:6px 8px;border:1px solid #666;width:60px;">Note /20</th>
        <th style="padding:6px 8px;border:1px solid #666;width:70px;">Nte×Coeff</th>
        <?php if(!empty($c['afficher_appreciations'])): ?>
        <th style="padding:6px 8px;border:1px solid #666;width:90px;">Appréciation</th>
        <?php endif; ?>
        <th style="padding:6px 8px;border:1px solid #666;">Enseignant</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $lastType='';
      foreach($b['data']['notes'] as $i => $n):
        $rowBg = ($i%2===0) ? ($c['couleur_ligne_pair']??'#f0f7ff') : '#ffffff';
        $noteColor = $n['note']>=10?'#166534':'#991b1b';
        $apprec = $n['note']>=16?'Très Bien':($n['note']>=14?'Bien':($n['note']>=12?'Assez Bien':($n['note']>=10?'Passable':'Insuffisant')));
        // Séparateur par type
        if($lastType !== $n['matiere_type']):
          $typeLabels=['generale'=>'MATIÈRES GÉNÉRALES','technique'=>'MATIÈRES TECHNIQUES','pratique'=>'TRAVAUX PRATIQUES','option'=>'OPTIONS'];
          $lastType=$n['matiere_type'];
      ?>
      <tr><td colspan="6" style="background:<?= sanitize($c['couleur_accent']??'#2563eb') ?>15;padding:4px 8px;font-size:10px;font-weight:700;color:<?= sanitize($c['couleur_accent']??'#2563eb') ?>;text-transform:uppercase;letter-spacing:.5px;border:1px solid #bbb;"><?= $typeLabels[$n['matiere_type']]??'' ?></td></tr>
      <?php endif; ?>
      <tr style="background:<?= $rowBg ?>;">
        <td style="padding:5px 8px;border:1px solid #ccc;font-weight:500;"><?= sanitize($n['matiere_nom']) ?></td>
        <td style="padding:5px 8px;border:1px solid #ccc;text-align:center;"><?= $n['coefficient'] ?></td>
        <td style="padding:5px 8px;border:1px solid #ccc;text-align:center;font-weight:700;font-size:13px;color:<?= $noteColor ?>;"><?= number_format($n['note'],2) ?></td>
        <td style="padding:5px 8px;border:1px solid #ccc;text-align:center;"><?= number_format($n['note']*$n['coefficient'],2) ?></td>
        <?php if(!empty($c['afficher_appreciations'])): ?>
        <td style="padding:5px 8px;border:1px solid #ccc;text-align:center;font-size:10px;"><?= $apprec ?></td>
        <?php endif; ?>
        <td style="padding:5px 8px;border:1px solid #ccc;font-size:10px;color:#6b7280;"><?= sanitize($n['enseignant_nom']??'—') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($b['data']['notes'])): ?>
      <tr><td colspan="6" style="text-align:center;padding:20px;color:#9ca3af;font-style:italic;">Aucune note enregistrée pour cette période.</td></tr>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr style="background:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>;color:#fff;font-weight:700;font-size:13px;">
        <td colspan="3" style="padding:7px 8px;border:1px solid #666;text-align:right;">MOYENNE GÉNÉRALE :</td>
        <td colspan="<?= !empty($c['afficher_appreciations'])?3:2 ?>" style="padding:7px 8px;border:1px solid #666;font-size:16px;text-align:center;">
          <?= $b['data']['moyenne']?number_format($b['data']['moyenne'],2).'/20':'—' ?>
          &nbsp;&nbsp;
          <span style="font-size:12px;font-weight:600;"><?= $b['mention']['label'] ?></span>
        </td>
      </tr>
    </tfoot>
  </table>

  <!-- RÉSUMÉ & STATISTIQUES -->
  <table style="width:100%;border-collapse:collapse;border:1px solid #999;margin-bottom:8px;font-size:11px;">
    <tr style="background:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>15;font-weight:600;">
      <td style="padding:6px 10px;border:1px solid #999;">
        Moyenne : <span style="font-size:14px;font-weight:800;color:<?= $b['mention']['color'] ?>"><?= $b['data']['moyenne']?number_format($b['data']['moyenne'],2).'/20':'—' ?></span>
      </td>
      <?php if(!empty($c['afficher_rang'])): ?>
      <td style="padding:6px 10px;border:1px solid #999;">
        Rang : <strong><?= $b['rang'] ?><?= $b['rang']==1?'er':'ème' ?> / <?= $b['total_cl'] ?> élèves</strong>
      </td>
      <?php endif; ?>
      <?php if(!empty($c['afficher_absences'])): ?>
      <td style="padding:6px 10px;border:1px solid #999;">
        Absences : <strong><?= $b['absInfo']['total'] ?>h</strong> (dont <?= $b['absInfo']['justif'] ?>h justif.)
      </td>
      <?php endif; ?>
      <td style="padding:6px 10px;border:1px solid #999;">
        Mention : <strong><?= sanitize($b['mention']['label']) ?></strong>
      </td>
    </tr>
  </table>

  <!-- APPRÉCIATION DU CONSEIL -->
  <?php if(!empty($c['afficher_conseil']) && $b['conseil']): ?>
  <table style="width:100%;border-collapse:collapse;border:1px solid #999;margin-bottom:8px;font-size:11px;">
    <tr>
      <td style="padding:6px 10px;border:1px solid #999;background:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>15;font-weight:700;width:160px;">Appréciation du conseil :</td>
      <td style="padding:6px 10px;border:1px solid #999;font-style:italic;"><?= sanitize($b['conseil']['appreciation']??'—') ?></td>
    </tr>
    <?php if(!empty($c['afficher_decisions']) && !empty($b['conseil']['decision'])): ?>
    <tr>
      <td style="padding:6px 10px;border:1px solid #999;font-weight:700;background:<?= sanitize($c['couleur_entete']??'#1e3a5f') ?>15;">Décision du conseil :</td>
      <td style="padding:6px 10px;border:1px solid #999;font-weight:600;text-transform:capitalize;">
        <?php
        $decLabels=['tableauhonneur'=>'🏆 Tableau d\'Honneur','felicitations'=>'🌟 Félicitations','encouragements'=>'👍 Encouragements','avertissement'=>'⚠️ Avertissement','passage'=>'✅ Admis au passage','redoublement'=>'🔄 Redoublement'];
        echo $decLabels[$b['conseil']['decision']] ?? sanitize($b['conseil']['decision']);
        ?>
      </td>
    </tr>
    <?php endif; ?>
  </table>
  <?php endif; ?>

  <!-- SIGNATURES -->
  <table style="width:100%;border-collapse:collapse;margin-bottom:8px;font-size:11px;">
    <tr>
      <td style="text-align:center;padding:10px;width:33%;">
        <div style="border-bottom:1px solid #999;padding-bottom:4px;font-weight:600;"><?= sanitize($c['titre_sign1']??'Le Directeur') ?></div>
        <div style="height:50px;"></div>
        <div style="border-top:1px solid #ccc;font-size:10px;color:#6b7280;">Nom & Cachet</div>
      </td>
      <td style="text-align:center;padding:10px;width:33%;">
        <div style="border-bottom:1px solid #999;padding-bottom:4px;font-weight:600;"><?= sanitize($c['titre_sign2']??'Le Prof. Principal') ?></div>
        <div style="height:50px;"></div>
        <div style="border-top:1px solid #ccc;font-size:10px;color:#6b7280;">Signature</div>
      </td>
      <td style="text-align:center;padding:10px;width:33%;">
        <div style="border-bottom:1px solid #999;padding-bottom:4px;font-weight:600;"><?= sanitize($c['titre_sign3']??'Parent / Tuteur') ?></div>
        <div style="height:50px;"></div>
        <div style="border-top:1px solid #ccc;font-size:10px;color:#6b7280;">Lu et approuvé</div>
      </td>
    </tr>
  </table>

  <!-- PIED DE PAGE -->
  <?php if(!empty($c['pied_page'])): ?>
  <div style="text-align:center;font-size:9px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:6px;">
    <?= sanitize($c['pied_page']) ?>
  </div>
  <?php endif; ?>

  <!-- WATERMARK -->
  <?php if(!empty($c['watermark_text'])): ?>
  <div style="position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-size:72px;font-weight:900;color:rgba(0,0,0,.04);pointer-events:none;white-space:nowrap;z-index:0;">
    <?= sanitize($c['watermark_text']) ?>
  </div>
  <?php endif; ?>

</div><!-- #bulletin-sheet -->

<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
