<?php
// modules/bordereaux/voir.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.view');

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL.'modules/bordereaux/index.php');

$stmt = $pdo->prepare("SELECT b.*,b.parent_id,b.segment_ordre,ag.nom as agence_nom,ag.ville as agence_ville,v.numero as voy_num,v.date_depart,CONCAT(u.prenom,' ',u.nom) as saisi_nom,CONCAT(uc.prenom,' ',uc.nom) as cree_nom FROM bordereaux b LEFT JOIN agences ag ON b.agence_id=ag.id LEFT JOIN voyages v ON b.voyage_id=v.id LEFT JOIN utilisateurs u ON b.saisi_par=u.id LEFT JOIN utilisateurs uc ON b.created_by=uc.id WHERE b.id=?");
$stmt->execute([$id]); $bordereau = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bordereau||!is_array($bordereau)) { flash('Bordereau introuvable.','danger'); redirect(BASE_URL.'modules/bordereaux/index.php'); }

$lignes = $pdo->prepare("SELECT bl.*,t.passager_tel,t.passager_cni,t.mode_paiement FROM bordereau_lignes bl LEFT JOIN tickets t ON bl.ticket_id=t.id WHERE bl.bordereau_id=? ORDER BY bl.siege"); $lignes->execute([$id]); $lignes=$lignes->fetchAll(PDO::FETCH_ASSOC);
$escales = $pdo->prepare("SELECT be.*,a.nom as agence_nom,a.ville as agence_ville,CONCAT(u.prenom,' ',u.nom) as confirme_nom FROM bordereau_escales be LEFT JOIN agences a ON be.agence_id=a.id LEFT JOIN utilisateurs u ON be.confirme_par=u.id WHERE be.bordereau_id=? ORDER BY be.ordre"); $escales->execute([$id]); $escales=$escales->fetchAll(PDO::FETCH_ASSOC);
$aid = getUserAgenceId();
$prochaineEscale = null;
foreach ($escales as $esc) { if ($esc['statut'] === 'en_attente') { $prochaineEscale = $esc; break; } }
$peutAjouterPassager = ($bordereau['statut'] === 'en_cours' && $prochaineEscale && $aid && $aid == $prochaineEscale['agence_id'] && can('tickets.create'));
$peutConfirmer = ($bordereau['statut'] === 'en_cours' && $prochaineEscale && $aid && $aid == $prochaineEscale['agence_id'] && can('bordereaux.validate'));
$pageTitle = 'Bordereau '.$bordereau['numero'];
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Bordereaux</a><span class="breadcrumb-sep">/</span><?= sanitize($bordereau['numero']) ?></div>

<?php
// Chaîne des bordereaux segments
$vParentId = $bordereau['parent_id'] ?? null;
$vChainChildren = $pdo->prepare("SELECT id, numero, segment_ordre, agence_depart, agence_arrivee, statut FROM bordereaux WHERE parent_id=? ORDER BY segment_ordre");
$vChainChildren->execute([$id]);
$vChainChildrenData = $vChainChildren->fetchAll(PDO::FETCH_ASSOC);
$vChainParent = null;
if ($vParentId) {
    $ps = $pdo->prepare("SELECT id, numero, segment_ordre, agence_depart, agence_arrivee, statut FROM bordereaux WHERE id=?");
    $ps->execute([$vParentId]);
    $vChainParent = $ps->fetch(PDO::FETCH_ASSOC);
}
if ($vChainParent || !empty($vChainChildrenData)):
?>
<div class="card" style="margin-bottom:16px;border-left:4px solid var(--purple);">
  <div class="card-header"><h3><i class="fas fa-link"></i> Segments du bordereau</h3></div>
  <div class="card-body" style="padding:8px 12px;">
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
      <?php if ($vChainParent): ?>
        <a href="voir.php?id=<?= $vChainParent['id'] ?>" class="btn btn-xs btn-secondary"><i class="fas fa-arrow-left"></i> <?= sanitize($vChainParent['numero']) ?> (<?= sanitize($vChainParent['agence_depart']) ?> → <?= sanitize($vChainParent['agence_arrivee']) ?>)</a>
        <i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>
      <?php endif; ?>
      <span class="btn btn-xs btn-primary"><strong><?= sanitize($bordereau['numero']) ?></strong> (<?= sanitize($bordereau['agence_depart']) ?> → <?= sanitize($bordereau['agence_arrivee']) ?>)</span>
      <?php foreach ($vChainChildrenData as $ch): ?>
        <i class="fas fa-long-arrow-alt-right" style="color:var(--text3);"></i>
        <a href="voir.php?id=<?= $ch['id'] ?>" class="btn btn-xs btn-secondary"><?= sanitize($ch['numero']) ?> (<?= sanitize($ch['agence_depart']) ?> → <?= sanitize($ch['agence_arrivee']) ?>)</a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="no-print" style="display:flex;gap:8px;margin-bottom:14px;">
  <a href="imprimer.php?id=<?= $id ?>" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer</a>
  <?php if(can('bordereaux.saisie') && $bordereau['statut']==='genere'): ?>
  <a href="saisie.php?id=<?= $id ?>" class="btn btn-success"><i class="fas fa-keyboard"></i> Modifier</a>
  <?php endif; ?>
  <?php if(can('bordereaux.validate') && $bordereau['statut']==='genere'): ?>
  <a href="valider.php?id=<?= $id ?>" class="btn btn-success"><i class="fas fa-check"></i> Valider</a>
  <?php endif; ?>
  <?php if(can('bordereaux.validate') && $bordereau['statut']==='en_cours'): ?>
  <a href="valider.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-check-circle"></i> Validation escale</a>
  <?php endif; ?>
  <?php if($peutAjouterPassager): ?>
  <a href="valider.php?id=<?= $id ?>" class="btn btn-warning"><i class="fas fa-user-plus"></i> Ajouter passager</a>
  <?php endif; ?>
  <?php if($peutConfirmer): ?>
  <a href="valider.php?id=<?= $id ?>" class="btn btn-success"><i class="fas fa-check"></i> Confirmer départ</a>
  <?php endif; ?>
  <a href="." class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px;">
<div class="card">
  <div class="card-header"><h3><i class="fas fa-file-invoice"></i> <?= sanitize($bordereau['numero']) ?></h3>
    <span class="tag-statut st-<?= $bordereau['statut'] ?>"><?= statutLabel($bordereau['statut']) ?></span>
  </div>
  <div class="card-body">
    <table data-no-filter style="width:100%;font-size:13px;border-collapse:collapse;">
      <?php $rows=[['Voyage',$bordereau['voy_num']],['Agence',$bordereau['agence_nom'].' ('.$bordereau['agence_ville'].')'],['Type',strtoupper($bordereau['type'])],['Véhicule',$bordereau['vehicule_immat']??'—'],['Chauffeur',$bordereau['chauffeur_nom']??'—'],['Permis',$bordereau['chauffeur_permis']??'—'],['Convoyeur',$bordereau['convoyeur_nom']??'—'],['Trajet',($bordereau['agence_depart']??'—').' → '.($bordereau['agence_arrivee']??'—')],['Date départ',fdatetime($bordereau['date_depart']??'')],['Créé par',$bordereau['cree_nom']??'—'],['Saisi par',$bordereau['saisi_nom']??'—'],['Date saisie',fdatetime($bordereau['date_saisie']??'')]];
      foreach($rows as [$k,$v]): ?>
      <tr><td style="padding:6px 10px;border-bottom:1px solid var(--border);color:var(--text2);font-weight:500;width:140px;"><?= $k ?></td><td style="padding:6px 10px;border-bottom:1px solid var(--border);"><?= sanitize($v) ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-calculator"></i> Synthèse financière</h3></div>
  <div class="card-body">
    <?php $rows2=[['Nb passagers',count($lignes),'',false],['Recette brute',number_format(array_sum(array_column($lignes,'montant')),0,',',' ').' FCFA','var(--success)',true],['(-) Carburant','('.number_format($bordereau['montant_carburant'],0,',',' ').') FCFA','',false],['(-) Péages','('.number_format($bordereau['montant_peage'],0,',',' ').') FCFA','',false],['(-) Avance chauffeur','('.number_format($bordereau['avance_chauffeur'],0,',',' ').') FCFA','',false],['(-) Autres','('.number_format($bordereau['autres_deductions'],0,',',' ').') FCFA','',false]];
    foreach($rows2 as [$k,$v,$col,$bold]): ?>
    <div style="display:flex;justify-content:space-between;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px;<?= $bold?'font-weight:600;':'' ?>">
      <span><?= $k ?></span><span style="color:<?= $col?:'' ?>"><?= $v ?></span>
    </div>
    <?php endforeach; ?>
    <div style="display:flex;justify-content:space-between;padding:12px 14px;background:var(--primary);color:#fff;border-radius:var(--radius);margin-top:8px;font-weight:800;font-size:16px;">
      <span>RECETTE NETTE</span>
      <span><?= number_format($bordereau['recette_nette'],0,',',' ') ?> FCFA</span>
    </div>
    <?php if($bordereau['observations']): ?>
    <div style="margin-top:12px;padding:10px;background:var(--warning-bg);border-radius:var(--radius);font-size:12px;"><strong>Observations :</strong> <?= sanitize($bordereau['observations']) ?></div>
    <?php endif; ?>
  </div>
</div>
</div>

<!-- PROGRESSION ESCALES -->
<?php if(!empty($escales)): ?>
<div class="card" style="margin-bottom:18px;">
  <div class="card-header"><h3><i class="fas fa-map-marked-alt"></i> Progression escales</h3></div>
  <div class="card-body">
    <div style="display:flex;align-items:flex-start;gap:0;flex-wrap:wrap;padding:8px 0;">
      <?php foreach($escales as $i=>$esc): ?>
      <div style="display:flex;flex-direction:column;align-items:center;min-width:80px;">
        <div style="width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;
          <?= $esc['statut']==='confirme'?'background:var(--success);color:#fff;':($esc['statut']==='refuse'?'background:var(--danger);color:#fff;':'background:var(--bg);border:2px solid var(--text3);color:var(--text3);') ?>">
          <?php if($esc['statut']==='confirme'): ?><i class="fas fa-check"></i>
          <?php elseif($esc['statut']==='refuse'): ?><i class="fas fa-times"></i>
          <?php else: ?><?= $esc['ordre'] ?><?php endif; ?>
        </div>
        <div style="margin-top:4px;font-size:11px;font-weight:600;text-align:center;"><?= sanitize($esc['agence_ville'] ?? $esc['agence_nom']) ?></div>
        <div style="font-size:9px;color:var(--text3);text-align:center;"><?= $esc['statut']==='confirme'?'Confirmé':'En attente' ?></div>
        <?php if($esc['statut']==='confirme' && $esc['date_confirmation']): ?><div style="font-size:9px;color:var(--success);"><?= date('d/m H:i',strtotime($esc['date_confirmation'])) ?></div><?php endif; ?>
      </div>
      <?php if($i<count($escales)-1): ?><div style="flex:1;min-width:20px;height:2px;background:<?= $esc['statut']==='confirme'?'var(--success)':'var(--border)' ?>;margin-top:16px;"></div><?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- LISTE PASSAGERS -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-users"></i> Passagers (<?= count($lignes) ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>#</th><th>Siège</th><th>Passager</th><th>Téléphone</th><th>CNI</th><th>Destination</th><th>Classe</th><th>Mode</th><th>Montant (FCFA)</th></tr></thead>
      <tbody>
        <?php foreach($lignes as $i=>$l): ?>
        <tr>
          <td><?= $i+1 ?></td>
          <td style="text-align:center;font-weight:700;"><?= sanitize($l['siege']??'—') ?></td>
          <td><?= sanitize($l['passager_nom']) ?></td>
          <td style="font-size:12px;"><?= sanitize($l['passager_tel']??'—') ?></td>
          <td style="font-size:12px;"><?= sanitize($l['passager_cni']??'—') ?></td>
          <td><?= sanitize($l['destination']??'—') ?></td>
          <td><span class="badge <?= $l['classe']==='vip'?'badge-purple':'badge-blue' ?>"><?= strtoupper($l['classe']??'normale') ?></span></td>
          <td style="font-size:11px;"><?= ['especes'=>'Espèces','om'=>'OM','momo'=>'MoMo','carte'=>'Carte','cheque'=>'Chèque'][$l['mode_paiement']]??$l['mode_paiement']??'—' ?></td>
          <td style="text-align:right;font-weight:600;"><?= number_format($l['montant']??0,0,',',' ') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($lignes)): ?><tr><td colspan="9" class="t-empty">Aucun passager enregistré</td></tr><?php endif; ?>
        <?php if(!empty($lignes)): ?>
        <tr style="background:var(--bg);font-weight:700;">
          <td colspan="8" style="padding:8px 12px;text-align:right;">TOTAL</td>
          <td style="padding:8px 12px;text-align:right;color:var(--success);"><?= number_format(array_sum(array_column($lignes,'montant')),0,',',' ') ?></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
