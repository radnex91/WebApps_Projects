<?php
// modules/depenses/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('depenses.create');
$pageTitle = 'Dépenses';
$aid = getUserAgenceId();

// Approbation
if (isset($_GET['approve']) && can('depenses.approve')) {
    $pdo->prepare("UPDATE depenses SET statut='approuve',approuve_par=?,date_approbation=NOW() WHERE id=?")->execute([$_SESSION['user_id'],(int)$_GET['approve']]);
    flash('Dépense approuvée.');
    redirect(BASE_URL.'modules/depenses/index.php');
}
if (isset($_GET['del']) && isSuperAdmin()) {
    $pdo->prepare("DELETE FROM depenses WHERE id=?")->execute([(int)$_GET['del']]);
    flash('Dépense supprimée.','warning');
    redirect(BASE_URL.'modules/depenses/index.php');
}

$search = trim($_GET['q']??''); $statut=$_GET['statut']??''; $cat=$_GET['cat']??'';
$date_d=$_GET['date_d']??date('Y-m-d',strtotime('-30 days')); $date_f=$_GET['date_f']??date('Y-m-d');
$page=max(1,(int)($_GET['page']??1)); $perPage=25;

$where=['1=1']; $params=[];
if($aid){$where[]="d.agence_id=?";$params[]=$aid;}
if($search){$where[]="(d.libelle LIKE ? OR d.beneficiaire LIKE ?)";$params=array_merge($params,["%$search%","%$search%"]);}
if($statut){$where[]="d.statut=?";$params[]=$statut;}
if($cat){$where[]="d.categorie=?";$params[]=$cat;}
$where[]="DATE(d.date_depense)>=?";$params[]=$date_d;
$where[]="DATE(d.date_depense)<=?";$params[]=$date_f;
$ws=implode(' AND ',$where);

$total=$pdo->prepare("SELECT COUNT(*) FROM depenses d WHERE $ws");$total->execute($params);
$totalRows=$total->fetchColumn();$totalPages=ceil($totalRows/$perPage);
$stmt=$pdo->prepare("SELECT d.*,a.ville as agence_ville,CONCAT(u.prenom,' ',u.nom) as impute_nom,CONCAT(ua.prenom,' ',ua.nom) as approuve_nom FROM depenses d JOIN agences a ON d.agence_id=a.id LEFT JOIN utilisateurs u ON d.impute_par=u.id LEFT JOIN utilisateurs ua ON d.approuve_par=ua.id WHERE $ws ORDER BY d.created_at DESC LIMIT $perPage OFFSET ".(($page-1)*$perPage));
$stmt->execute($params);$depenses=$stmt->fetchAll();

// Total
$tot=$pdo->prepare("SELECT SUM(montant) FROM depenses d WHERE $ws AND d.statut IN ('approuve','paye')");$tot->execute($params);$totalMontant=$tot->fetchColumn();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Dépenses</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header">
    <h3><i class="fas fa-money-bill-wave"></i> Dépenses (<?= $totalRows ?>)</h3>
    <a href="ajouter.php" class="btn btn-warning btn-sm"><i class="fas fa-plus"></i> Nouvelle dépense</a>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="fc" placeholder="🔍 Libellé, bénéficiaire..." value="<?= sanitize($search) ?>" style="max-width:220px;">
      <select name="cat" class="fc" style="width:auto;">
        <option value="">Toutes catégories</option>
        <?php foreach(['reparation','carburant','salaire','loyer','fourniture','peage','autre'] as $c): ?>
        <option value="<?= $c ?>" <?= $cat===$c?'selected':'' ?>><?= categorieDepLabel($c) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="statut" class="fc" style="width:auto;">
        <option value="">Tous statuts</option>
        <?php foreach(['en_attente','approuve','paye','rejete'] as $s): ?>
        <option value="<?= $s ?>" <?= $statut===$s?'selected':'' ?>><?= statutLabel($s) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date_d" class="fc" value="<?= $date_d ?>" style="width:auto;">
      <input type="date" name="date_f" class="fc" value="<?= $date_f ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div style="font-size:13px;color:var(--text2);margin-top:6px;">
      Total approuvé : <strong style="color:var(--danger);"><?= number_format($totalMontant??0,0,',',' ') ?> FCFA</strong>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
    <table>
      <thead><tr><th>N°</th><th>Date</th><th>Libellé</th><th>Catégorie</th><th>Bénéficiaire</th><th>Montant</th><th>Imputé par</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($depenses as $d): ?>
        <tr>
          <td><code style="font-size:10px;"><?= sanitize($d['numero']??'—') ?></code></td>
          <td style="font-size:12px;"><?= fdate($d['date_depense']) ?></td>
          <td><strong><?= sanitize($d['libelle']) ?></strong></td>
          <td>
            <?php $catColors=['reparation'=>'badge-red','carburant'=>'badge-amber','salaire'=>'badge-blue','loyer'=>'badge-purple','fourniture'=>'badge-gray','peage'=>'badge-teal','autre'=>'badge-gray']; ?>
            <span class="badge <?= $catColors[$d['categorie']]??'badge-gray' ?>"><?= categorieDepLabel($d['categorie']) ?></span>
          </td>
          <td><?= sanitize($d['beneficiaire']??'—') ?></td>
          <td style="font-weight:700;color:var(--danger);"><?= number_format($d['montant'],0,',',' ') ?></td>
          <td style="font-size:12px;"><?= sanitize($d['impute_nom']??'—') ?></td>
          <td>
            <span class="tag-statut <?= $d['statut']==='approuve'||$d['statut']==='paye'?'st-confirme':($d['statut']==='en_attente'?'st-en_attente':'st-annule') ?>">
              <?= statutLabel($d['statut']) ?>
            </span>
          </td>
          <td>
            <div style="display:flex;gap:3px;">
              <?php if($d['statut']==='en_attente' && can('depenses.approve')): ?>
              <a href="?approve=<?= $d['id'] ?>" class="btn btn-xs btn-success" title="Approuver" onclick="return confirm('Approuver cette dépense ?')"><i class="fas fa-check"></i></a>
              <?php endif; ?>
              <?php if(isSuperAdmin()): ?><a href="?del=<?= $d['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer cette dépense ?')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($depenses)): ?><tr><td colspan="9" class="t-empty"><i class="fas fa-money-bill-wave"></i>Aucune dépense</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php if($totalPages>1): ?><div class="pagination"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&date_d=<?= $date_d ?>&date_f=<?= $date_f ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
