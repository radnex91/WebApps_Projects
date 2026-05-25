<?php
require_once '../../includes/config.php'; requireLogin(); requirePerm('versements.view');
$pageTitle='Versements Bancaires'; $aid=getUserAgenceId();
if(isset($_GET['confirm'])&&can('versements.confirm')){$pdo->prepare("UPDATE versements SET statut='confirme',confirme_par=? WHERE id=?")->execute([$_SESSION['user_id'],(int)$_GET['confirm']]);flash('Versement confirmé.');redirect(BASE_URL.'modules/versements/');}
if(isset($_GET['del'])&&isSuperAdmin()){$pdo->prepare("DELETE FROM versements WHERE id=?")->execute([$_GET['del']]);flash('Supprimé.','warning');redirect(BASE_URL.'modules/versements/');}
$date_d=$_GET['date_d']??date('Y-m-01'); $date_f=$_GET['date_f']??date('Y-m-d'); $ag=(int)($_GET['ag']??0);
$where=["v.date BETWEEN ? AND ?"]; $params=[$date_d,$date_f];
if($aid){$where[]="v.agence_id=?";$params[]=$aid;} if($ag&&!$aid){$where[]="v.agence_id=?";$params[]=$ag;}
$ws=implode(' AND ',$where);
$stmt=$pdo->prepare("SELECT v.*,a.nom as agence_nom,CONCAT(u.prenom,' ',u.nom) as saisi FROM versements v JOIN agences a ON v.agence_id=a.id LEFT JOIN users u ON v.saisie_par=u.id WHERE $ws ORDER BY v.date DESC,v.created_at DESC");
$stmt->execute($params); $vers=$stmt->fetchAll();
$tots=$pdo->prepare("SELECT SUM(versement_agence) as tv,SUM(decaissement_agence) as td,SUM(recette_agence) as tr FROM versements v WHERE $ws"); $tots->execute($params); $tots=$tots->fetch();
$agences=$pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Versements</div>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header"><h3><i class="fas fa-university"></i> Versements (<?= count($vers) ?>)</h3><?php if(can('versements.create')): ?><a href="ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouveau</a><?php endif; ?></div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <?php if(!$aid&&isAdmin()): ?><select name="ag" class="fc" style="width:auto;"><option value="">Toutes agences</option><?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $ag==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option><?php endforeach; ?></select><?php endif; ?>
      <input type="date" name="date_d" class="fc" value="<?= $date_d ?>" style="width:auto;">
      <input type="date" name="date_f" class="fc" value="<?= $date_f ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    </form>
    <div style="display:flex;gap:16px;font-size:13px;background:var(--bg);padding:10px 14px;border-radius:var(--radius);">
      <span>💳 Versements : <strong><?= moneyRaw($tots['tv']??0) ?> FCFA</strong></span>
      <span>📤 Décaissements : <strong><?= moneyRaw($tots['td']??0) ?> FCFA</strong></span>
      <span>📈 Recette agence : <strong><?= moneyRaw($tots['tr']??0) ?> FCFA</strong></span>
    </div>
  </div>
</div>
<div class="card"><div class="card-body" style="padding:0;"><div class="table-wrap">
<table>
  <thead><tr><th>Réf.</th><th>Agence</th><th>Date</th><th>Versement</th><th>Décaissement</th><th>Recette agence</th><th>Quittance</th><th>Statut</th><th>Saisi par</th><th>Actions</th></tr></thead>
  <tbody>
    <?php foreach($vers as $v): ?>
    <tr>
      <td><code style="font-size:11px;"><?= h($v['ref_versement']??'—') ?></code></td>
      <td><?= h($v['agence_nom']) ?></td>
      <td><?= fdate($v['date']) ?></td>
      <td style="font-weight:700;color:var(--success);"><?= moneyRaw($v['versement_agence']) ?></td>
      <td><?= moneyRaw($v['decaissement_agence']) ?></td>
      <td><?= moneyRaw($v['recette_agence']) ?></td>
      <td style="font-size:12px;"><?= h($v['quittance']??'—') ?></td>
      <td><span class="st-<?= $v['statut'] ?>"><?= h($v['statut']) ?></span></td>
      <td style="font-size:12px;"><?= h($v['saisi']??'—') ?></td>
      <td>
        <?php if($v['statut']==='en_attente'&&can('versements.confirm')): ?>
        <a href="?confirm=<?= $v['id'] ?>" class="btn btn-xs btn-success" onclick="return confirm('Confirmer ce versement ?')"><i class="fas fa-check"></i></a>
        <?php endif; ?>
        <?php if(isSuperAdmin()): ?><a href="?del=<?= $v['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a><?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
    <?php if(empty($vers)): ?><tr><td colspan="10" class="t-empty"><i class="fas fa-university"></i>Aucun versement</td></tr><?php endif; ?>
  </tbody>
</table></div></div></div>
<?php include '../../includes/footer.php'; ?>
