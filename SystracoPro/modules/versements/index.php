<?php
// modules/versements/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('versements.create');
$pageTitle = 'Versements Bancaires / Mobile Money';
$aid = getUserAgenceId();

if (isset($_GET['confirm']) && can('versements.confirm')) {
    $pdo->prepare("UPDATE versements SET statut='confirme',confirme_par=?,date_confirmation=NOW() WHERE id=?")->execute([$_SESSION['user_id'],(int)$_GET['confirm']]);
    flash('Versement confirmé.');
    redirect(BASE_URL.'modules/versements/index.php');
}
if (isset($_GET['reject']) && can('versements.confirm')) {
    $pdo->prepare("UPDATE versements SET statut='rejete' WHERE id=?")->execute([(int)$_GET['reject']]);
    flash('Versement rejeté.','warning');
    redirect(BASE_URL.'modules/versements/index.php');
}

$date_d=$_GET['date_d']??date('Y-m-d',strtotime('-30 days')); $date_f=$_GET['date_f']??date('Y-m-d');
$type_f=$_GET['type']??''; $statut_f=$_GET['statut']??'';
$where=['1=1']; $params=[];
if($aid){$where[]="v.agence_id=?";$params[]=$aid;}
if($type_f){$where[]="v.type=?";$params[]=$type_f;}
if($statut_f){$where[]="v.statut=?";$params[]=$statut_f;}
$where[]="DATE(v.date_versement)>=?";$params[]=$date_d;
$where[]="DATE(v.date_versement)<=?";$params[]=$date_f;
$ws=implode(' AND ',$where);
$stmt=$pdo->prepare("SELECT v.*,a.ville as agence_ville,CONCAT(u.prenom,' ',u.nom) as saisi_nom,CONCAT(uc.prenom,' ',uc.nom) as confirme_nom FROM versements v JOIN agences a ON v.agence_id=a.id LEFT JOIN utilisateurs u ON v.saisi_par=u.id LEFT JOIN utilisateurs uc ON v.confirme_par=uc.id WHERE $ws ORDER BY v.created_at DESC");
$stmt->execute($params);$versements=$stmt->fetchAll();
$tot=$pdo->prepare("SELECT SUM(montant) FROM versements v WHERE $ws");$tot->execute($params);$totalMontant=$tot->fetchColumn();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Versements</div>
<div class="card" style="margin-bottom:14px;">
  <div class="card-header">
    <h3><i class="fas fa-university"></i> Versements (<?= count($versements) ?>)</h3>
    <a href="ajouter.php" class="btn btn-orange btn-sm"><i class="fas fa-plus"></i> Nouveau versement</a>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <select name="type" class="fc" style="width:auto;"><option value="">Tous types</option><?php foreach(['banque'=>'🏦 Banque','om'=>'📱 Orange Money','momo'=>'📱 MTN MoMo','autre'=>'Autre'] as $k=>$v): ?><option value="<?= $k ?>" <?= $type_f===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select>
      <select name="statut" class="fc" style="width:auto;"><option value="">Tous statuts</option><?php foreach(['en_attente','confirme','rejete'] as $s): ?><option value="<?= $s ?>" <?= $statut_f===$s?'selected':'' ?>><?= statutLabel($s) ?></option><?php endforeach; ?></select>
      <input type="date" name="date_d" class="fc" value="<?= $date_d ?>" style="width:auto;">
      <input type="date" name="date_f" class="fc" value="<?= $date_f ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
    </form>
    <div style="font-size:13px;margin-top:6px;">Total période : <strong style="color:var(--success);"><?= number_format($totalMontant??0,0,',',' ') ?> FCFA</strong></div>
  </div>
</div>
<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table>
      <thead><tr><th>Numéro</th><th>Date</th><th>Type</th><th>Montant</th><th>Référence</th><th>Banque/Opérateur</th><th>Saisi par</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($versements as $v): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($v['numero']??'—') ?></code></td>
          <td style="font-size:12px;"><?= fdate($v['date_versement']) ?></td>
          <td><?php $ti=['banque'=>'🏦 Banque','om'=>'📱 OM','momo'=>'📱 MOMO','autre'=>'📝 Autre']; echo $ti[$v['type']]??sanitize($v['type']); ?></td>
          <td style="font-weight:700;color:var(--success);font-size:14px;"><?= number_format($v['montant'],0,',',' ') ?></td>
          <td style="font-size:12px;"><?= sanitize($v['reference']??'—') ?></td>
          <td style="font-size:12px;"><?= sanitize($v['banque']??'—') ?></td>
          <td style="font-size:12px;"><?= sanitize($v['saisi_nom']??'—') ?></td>
          <td><span class="tag-statut <?= $v['statut']==='confirme'?'st-confirme':($v['statut']==='en_attente'?'st-en_attente':'st-annule') ?>"><?= statutLabel($v['statut']) ?></span></td>
          <td>
            <div style="display:flex;gap:3px;">
              <?php if($v['statut']==='en_attente' && can('versements.confirm')): ?>
              <a href="?confirm=<?= $v['id'] ?>" class="btn btn-xs btn-success" onclick="return confirm('Confirmer ce versement ?')"><i class="fas fa-check"></i></a>
              <a href="?reject=<?= $v['id'] ?>"  class="btn btn-xs btn-danger"  onclick="return confirm('Rejeter ce versement ?')"><i class="fas fa-times"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($versements)): ?><tr><td colspan="9" class="t-empty"><i class="fas fa-university"></i>Aucun versement</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
