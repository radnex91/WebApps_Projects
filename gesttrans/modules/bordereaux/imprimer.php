<?php
// modules/bordereaux/imprimer.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.view');
$id=(int)($_GET['id']??0);
if(!$id) redirect(BASE_URL.'modules/bordereaux/');
$stmt=$pdo->prepare("SELECT b.*,v.immatriculation,v.marque,v.modele,g.nom as groupe,g.num_compte_bancaire,g.banque,g.nom_contact as groupe_contact,ad.nom as ag_dep,ad.code as ag_dep_code,ad.ville as ville_dep,ad.telephone as tel_dep,aa.nom as ag_arr,aa.code as ag_arr_code,aa.ville as ville_arr,CONCAT(u.prenom,' ',u.nom) as saisie_par FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN agences ad ON b.agence_depart_id=ad.id LEFT JOIN agences aa ON b.agence_arrivee_id=aa.id LEFT JOIN users u ON b.saisie_par=u.id WHERE b.id=?");
$stmt->execute([$id]); $b=$stmt->fetch();
if(!$b){flash('Bordereau introuvable.','danger');redirect(BASE_URL.'modules/bordereaux/');}
$appName=getParam('nom_entreprise',APP_NAME);
$pageTitle='Impression Bordereau N°'.$b['num_bordereau'];
include '../../includes/header.php';
?>
<div class="no-print" style="display:flex;gap:8px;margin-bottom:14px;">
  <button onclick="window.print()" class="btn btn-primary btn-lg"><i class="fas fa-print"></i> Imprimer</button>
  <a href="." class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
  <?php if(can('bordereaux.edit')): ?><a href="ajouter.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Modifier</a><?php endif; ?>
</div>

<div id="print-zone" style="background:#fff;padding:24px;max-width:900px;margin:0 auto;font-family:'Times New Roman',serif;">

  <!-- EN-TÊTE -->
  <div style="text-align:center;border-bottom:3px double #1e3a8a;padding-bottom:12px;margin-bottom:14px;">
    <div style="font-size:18px;font-weight:900;text-transform:uppercase;color:#1e3a8a;"><?= h($appName) ?></div>
    <div style="font-size:10px;color:#6b7280;">Système de Gestion des Transports en Commun</div>
    <div style="font-size:20px;font-weight:900;margin:8px 0;text-transform:uppercase;border:2px solid #1e3a8a;display:inline-block;padding:4px 20px;">
      BORDEREAU N° <?= $b['num_bordereau'] ?>
    </div>
    <?php if($b['code_bordereau']): ?>
    <div style="font-size:12px;color:#374151;margin-top:4px;">Code : <strong><?= h($b['code_bordereau']) ?></strong></div>
    <?php endif; ?>
  </div>

  <!-- INFOS VOYAGE -->
  <table style="width:100%;border-collapse:collapse;font-size:12px;margin-bottom:12px;">
    <tr style="background:#f0f7ff;">
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;width:150px;">Date</td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><strong><?= fdate($b['date']) ?></strong></td>
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;width:150px;">Véhicule</td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><strong><?= h($b['immatriculation']??'—') ?></strong> <?= h($b['marque']??'') ?> <?= h($b['modele']??'') ?></td>
    </tr>
    <tr>
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Agence départ</td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><?= h($b['ag_dep']??'—') ?> (<?= h($b['ag_dep_code']??'') ?>)</td>
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Agence arrivée</td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><?= h($b['ag_arr']??'—') ?> (<?= h($b['ag_arr_code']??'') ?>)</td>
    </tr>
    <tr style="background:#f0f7ff;">
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Groupe</td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><?= h($b['groupe']??'—') ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Contact groupe</td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><?= h($b['groupe_contact']??'—') ?></td>
    </tr>
    <tr>
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Nb passagers</td>
      <td style="padding:6px 10px;border:1px solid #ccc;font-size:16px;font-weight:900;"><?= $b['nb_passagers'] ?></td>
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Billets gratuits</td>
      <td style="padding:6px 10px;border:1px solid #ccc;"><?= $b['nb_billets_gratuits'] ?></td>
    </tr>
    <?php if($b['libelle']): ?>
    <tr style="background:#f0f7ff;">
      <td style="padding:6px 10px;border:1px solid #ccc;font-weight:700;">Libellé</td>
      <td colspan="3" style="padding:6px 10px;border:1px solid #ccc;font-style:italic;"><?= h($b['libelle']) ?></td>
    </tr>
    <?php endif; ?>
  </table>

  <!-- FINANCIER -->
  <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:#1e3a8a;border-bottom:1px solid #ccc;padding-bottom:3px;margin-bottom:8px;">Détail financier</div>
  <table style="width:60%;border-collapse:collapse;font-size:13px;margin-bottom:12px;">
    <?php $rows2=[
      ['Recette totale encaissée', $b['recette_totale'], true, 'var(--success)'],
      ['(−) Carburant', $b['carburant'], false, '#dc2626'],
      ['(−) Péages', $b['peage_total'], false, '#dc2626'],
      ['(−) Retenue agence', $b['retenue_agence'], false, '#dc2626'],
      ['(−) Ration chauffeur', $b['ration_chauffeur'], false, '#dc2626'],
      ['(−) Autres dépenses', $b['autres_depenses'], false, '#dc2626'],
    ]; foreach($rows2 as [$lbl,$val,$bold,$col]): ?>
    <tr style="<?= $bold?'background:#f0f7ff;':'' ?>">
      <td style="padding:6px 12px;border:1px solid #ccc;font-weight:<?= $bold?'700':'400' ?>"><?= $lbl ?></td>
      <td style="padding:6px 12px;border:1px solid #ccc;text-align:right;font-weight:700;color:<?= $col ?>;"><?= number_format($val,0,',','') ?> FCFA</td>
    </tr>
    <?php endforeach; ?>
    <tr style="background:#1e3a8a;color:#fff;">
      <td style="padding:10px 12px;border:1px solid #0f2060;font-weight:900;font-size:14px;">RECETTE NETTE</td>
      <td style="padding:10px 12px;border:1px solid #0f2060;text-align:right;font-weight:900;font-size:18px;"><?= number_format($b['recette_nette'],0,',','') ?> FCFA</td>
    </tr>
  </table>

  <!-- INFOS BANQUE -->
  <?php if($b['banque']||$b['num_compte_bancaire']): ?>
  <div style="margin-bottom:12px;padding:10px;background:#fafafa;border:1px dashed #ccc;font-size:11px;">
    <strong>Coordonnées bancaires du groupe :</strong>
    <?= h($b['banque']??'') ?> — Compte : <?= h($b['num_compte_bancaire']??'—') ?>
  </div>
  <?php endif; ?>

  <!-- SIGNATURES -->
  <div class="rpt-sign" style="margin-top:24px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;">
    <div class="rpt-sign-box" style="text-align:center;">
      <div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Le Saisisseur</div>
      <div class="rpt-sign-line" style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div>
      <div style="font-size:11px;color:#6b7280;"><?= h($b['saisie_par']??'—') ?></div>
    </div>
    <div class="rpt-sign-box" style="text-align:center;">
      <div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Chef d'Agence</div>
      <div class="rpt-sign-line" style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div>
      <div style="font-size:11px;color:#6b7280;">Nom & Cachet</div>
    </div>
    <div class="rpt-sign-box" style="text-align:center;">
      <div style="font-weight:700;font-size:12px;border-bottom:1px solid #333;padding-bottom:4px;">Représentant du Groupe</div>
      <div class="rpt-sign-line" style="height:40px;border-bottom:1px solid #999;margin:8px 0;"></div>
      <div style="font-size:11px;color:#6b7280;"><?= h($b['groupe_contact']??'Signature') ?></div>
    </div>
  </div>

  <div style="text-align:center;font-size:10px;color:#9ca3af;border-top:1px solid #e5e7eb;padding-top:8px;margin-top:16px;">
    Imprimé le <?= date('d/m/Y à H:i') ?> — <?= h($appName) ?> — Confidentiel
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
