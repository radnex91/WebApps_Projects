<?php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.view');
$id=(int)($_GET['id']??0); if(!$id) redirect(BASE_URL.'modules/bordereaux/');
$stmt=$pdo->prepare("SELECT b.*,v.immatriculation,v.marque,v.modele,g.nom as groupe,ad.nom as ag_dep,ad.code as ag_dep_code,aa.nom as ag_arr,aa.code as ag_arr_code,CONCAT(u.prenom,' ',u.nom) as saisie_par FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN agences ad ON b.agence_depart_id=ad.id LEFT JOIN agences aa ON b.agence_arrivee_id=aa.id LEFT JOIN users u ON b.saisie_par=u.id WHERE b.id=?");
$stmt->execute([$id]); $b=$stmt->fetch(); if(!$b){flash('Bordereau introuvable.','danger');redirect(BASE_URL.'modules/bordereaux/');}
$pageTitle='Bordereau N°'.$b['num_bordereau'];
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Bordereaux</a><span class="breadcrumb-sep">/</span>N°<?= $b['num_bordereau'] ?></div>
<div style="display:flex;gap:8px;margin-bottom:14px;" class="no-print">
  <a href="imprimer.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer</a>
  <?php if(can('bordereaux.edit')): ?><a href="ajouter.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-edit"></i> Modifier</a><?php endif; ?>
  <a href="." class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>
<div class="grid-2" style="margin-bottom:18px;">
<div class="card">
  <div class="card-header"><h3><i class="fas fa-info-circle"></i> Informations du bordereau</h3></div>
  <div class="card-body">
    <?php $rows=[['N° Bordereau',$b['num_bordereau']],['Code',$b['code_bordereau']??'—'],['Date',fdate($b['date'])],['Véhicule',($b['immatriculation']??'—').' '.($b['marque']??'').' '.($b['modele']??'')],['Groupe',$b['groupe']??'—'],['Agence départ',$b['ag_dep']??'—'],['Agence arrivée',$b['ag_arr']??'—'],['Passagers',$b['nb_passagers']],['Billets gratuits',$b['nb_billets_gratuits']],['Saisi par',$b['saisie_par']??'—'],['Libellé',$b['libelle']??'—']];
    ?><table style="width:100%;font-size:13px;border-collapse:collapse;"><?php foreach($rows as [$k,$v]): ?><tr><td style="padding:6px 10px;border-bottom:1px solid var(--border);color:var(--text2);font-weight:500;width:140px;"><?= $k ?></td><td style="padding:6px 10px;border-bottom:1px solid var(--border);"><?= h((string)$v) ?></td></tr><?php endforeach; ?></table>
  </div>
</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-coins"></i> Détail financier</h3></div>
  <div class="card-body">
    <?php $fin=[['Recette totale',moneyRaw($b['recette_totale']),'var(--success)',true],['(−) Carburant','('.moneyRaw($b['carburant']).')','var(--danger)',false],['(−) Péages','('.moneyRaw($b['peage_total']).')','var(--danger)',false],['(−) Retenue agence','('.moneyRaw($b['retenue_agence']).')','var(--danger)',false],['(−) Ration chauffeur','('.moneyRaw($b['ration_chauffeur']).')','var(--danger)',false],['(−) Autres dépenses','('.moneyRaw($b['autres_depenses']).')','var(--danger)',false]];
    foreach($fin as [$k,$v,$c,$b2]): ?><div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed var(--border);font-size:13px;"><span><?= $k ?></span><span style="font-weight:<?= $b2?'600':'400' ?>;color:<?= $c ?>;"><?= $v ?> FCFA</span></div><?php endforeach; ?>
    <div style="background:var(--primary);color:#fff;border-radius:var(--radius);padding:14px;margin-top:10px;display:flex;justify-content:space-between;"><strong>RECETTE NETTE</strong><strong style="font-size:18px;"><?= moneyRaw($b['recette_nette']) ?> FCFA</strong></div>
  </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
