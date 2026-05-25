<?php
// modules/bordereaux/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.view');
$pageTitle='Bordereaux de voyage'; $aid=getUserAgenceId();
if(isset($_GET['del'])&&can('bordereaux.delete')){$pdo->prepare("DELETE FROM bordereaux WHERE id=?")->execute([$_GET['del']]);flash('Bordereau supprimé.','warning');redirect(BASE_URL.'modules/bordereaux/');}
$search=trim($_GET['q']??''); $veh=trim($_GET['veh']??''); $agf=(int)($_GET['ag']??0);
$date_d=$_GET['date_d']??date('Y-m-01'); $date_f=$_GET['date_f']??date('Y-m-d');
$page=max(1,(int)($_GET['page']??1)); $pp=30;
$where=["b.date BETWEEN ? AND ?"]; $params=[$date_d,$date_f];
if($aid){$where[]="b.agence_depart_id=?";$params[]=$aid;}
if($search){$where[]="(b.num_bordereau LIKE ? OR b.code_bordereau LIKE ? OR b.libelle LIKE ?)";$params=array_merge($params,["%$search%","%$search%","%$search%"]);}
if($veh){$where[]="v.immatriculation LIKE ?";$params[]="%$veh%";}
if($agf){$where[]="b.agence_depart_id=?";$params[]=$agf;}
$ws=implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id WHERE $ws");$total->execute($params);$totalRows=$total->fetchColumn();$totalPages=ceil($totalRows/$pp);
$stmt=$pdo->prepare("SELECT b.*,v.immatriculation,g.nom as groupe,ad.nom as ag_dep,ad.code as ag_dep_code,aa.nom as ag_arr,aa.code as ag_arr_code FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id LEFT JOIN groupes g ON v.groupe_id=g.id LEFT JOIN agences ad ON b.agence_depart_id=ad.id LEFT JOIN agences aa ON b.agence_arrivee_id=aa.id WHERE $ws ORDER BY b.date DESC,b.num_bordereau DESC LIMIT $pp OFFSET ".(($page-1)*$pp));
$stmt->execute($params);$rows=$stmt->fetchAll();
$tots=$pdo->prepare("SELECT SUM(recette_totale) as r,SUM(recette_nette) as n,SUM(nb_passagers) as p,COUNT(*) as nb FROM bordereaux b LEFT JOIN vehicules v ON b.vehicule_id=v.id WHERE $ws");$tots->execute($params);$tots=$tots->fetch();
$agences=$pdo->query("SELECT id,nom,code FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Bordereaux</div>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <h3><i class="fas fa-file-invoice"></i> Bordereaux (<?= $totalRows ?>)</h3>
    <div style="display:flex;gap:8px;">
      <?php if(can('bordereaux.create')): ?><a href="ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouveau</a><?php endif; ?>
      <?php if(can('export.access')): ?><a href="../../modules/export/bordereaux.php?date_d=<?= $date_d ?>&date_f=<?= $date_f ?>" class="btn btn-success btn-sm"><i class="fas fa-file-excel"></i> Excel</a><?php endif; ?>
    </div>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="fc" placeholder="N°, code, libellé..." value="<?= h($search) ?>" style="max-width:200px;">
      <input type="text" name="veh" class="fc" placeholder="Immatriculation..." value="<?= h($veh) ?>" style="max-width:150px;">
      <select name="ag" class="fc" style="width:auto;"><option value="">Toutes agences</option><?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $agf==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option><?php endforeach; ?></select>
      <input type="date" name="date_d" class="fc" value="<?= $date_d ?>" style="width:auto;">
      <input type="date" name="date_f" class="fc" value="<?= $date_f ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <!-- Totaux -->
    <div style="display:flex;gap:20px;flex-wrap:wrap;font-size:13px;background:var(--bg);border-radius:var(--radius);padding:10px 14px;">
      <span>📋 <strong><?= $tots['nb'] ?></strong> bordereaux</span>
      <span>👥 <strong><?= number_format($tots['p']) ?></strong> passagers</span>
      <span>💰 Recette brute : <strong><?= moneyRaw($tots['r']??0) ?> FCFA</strong></span>
      <span>✅ Recette nette : <strong style="color:var(--success);"><?= moneyRaw($tots['n']??0) ?> FCFA</strong></span>
    </div>
  </div>
</div>
<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Code</th><th>Date</th><th>Véhicule</th><th>Groupe</th><th>Départ</th><th>Arrivée</th><th>Pass.</th><th>Gratuits</th><th>Recette brute</th><th>Carburant</th><th>Péages</th><th>Retenue</th><th>Ration Chauf.</th><th>Autres</th><th style="background:#f0fdf4;">Recette nette</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($rows as $b): ?>
        <tr>
          <td><strong><?= $b['num_bordereau'] ?></strong></td>
          <td style="font-size:11px;color:var(--text3);"><?= h($b['code_bordereau']??'—') ?></td>
          <td><?= fdate($b['date']) ?></td>
          <td><code style="font-size:11px;"><?= h($b['immatriculation']??'—') ?></code></td>
          <td style="font-size:12px;"><?= h($b['groupe']??'—') ?></td>
          <td><span class="badge b-blue"><?= h($b['ag_dep_code']??'—') ?></span></td>
          <td><span class="badge b-teal"><?= h($b['ag_arr_code']??'—') ?></span></td>
          <td style="text-align:center;font-weight:600;"><?= $b['nb_passagers'] ?></td>
          <td style="text-align:center;"><?= $b['nb_billets_gratuits'] ?></td>
          <td style="text-align:right;"><?= moneyRaw($b['recette_totale']) ?></td>
          <td style="text-align:right;color:var(--danger);"><?= moneyRaw($b['carburant']) ?></td>
          <td style="text-align:right;color:var(--danger);"><?= moneyRaw($b['peage_total']) ?></td>
          <td style="text-align:right;color:var(--danger);"><?= moneyRaw($b['retenue_agence']) ?></td>
          <td style="text-align:right;color:var(--danger);"><?= moneyRaw($b['ration_chauffeur']) ?></td>
          <td style="text-align:right;color:var(--danger);"><?= moneyRaw($b['autres_depenses']) ?></td>
          <td style="text-align:right;font-weight:800;color:var(--success);background:#f0fdf4;"><?= moneyRaw($b['recette_nette']) ?></td>
          <td>
            <div style="display:flex;gap:2px;">
              <a href="voir.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-primary"><i class="fas fa-eye"></i></a>
              <?php if(can('bordereaux.edit')): ?><a href="ajouter.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-warning"><i class="fas fa-edit"></i></a><?php endif; ?>
              <a href="imprimer.php?id=<?= $b['id'] ?>" class="btn btn-xs btn-info"><i class="fas fa-print"></i></a>
              <?php if(can('bordereaux.delete')): ?><a href="?del=<?= $b['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ce bordereau ?')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($rows)): ?><tr><td colspan="17" class="t-empty"><i class="fas fa-file-invoice"></i>Aucun bordereau trouvé</td></tr><?php endif; ?>
      </tbody>
      <?php if(!empty($rows)): ?>
      <tfoot>
        <tr style="background:var(--primary);color:#fff;font-weight:700;">
          <td colspan="7" style="padding:8px 12px;text-align:right;">TOTAUX :</td>
          <td style="padding:8px 12px;text-align:center;"><?= number_format($tots['p']) ?></td>
          <td></td>
          <td style="padding:8px 12px;text-align:right;"><?= moneyRaw($tots['r']??0) ?></td>
          <td colspan="5"></td>
          <td style="padding:8px 12px;text-align:right;font-size:14px;"><?= moneyRaw($tots['n']??0) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
    </div>
    <?php if($totalPages>1): ?><div class="pagination"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="?page=<?= $i ?>&date_d=<?= $date_d ?>&date_f=<?= $date_f ?>&q=<?= urlencode($search) ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
