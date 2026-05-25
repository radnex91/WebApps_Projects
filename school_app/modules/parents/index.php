<?php
// modules/parents/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Gestion des Parents';

$search = trim($_GET['search'] ?? '');
$page = max(1,(int)($_GET['page']??1)); $perPage=20;
$where = ['1=1']; $params=[];
if ($search) { $where[]="(nom LIKE ? OR prenom LIKE ? OR telephone LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
$ws = implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM parents WHERE $ws"); $total->execute($params);
$totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$perPage); $offset=($page-1)*$perPage;
$stmt=$pdo->prepare("SELECT p.*, COUNT(e.id) as nb_enfants FROM parents p LEFT JOIN eleves e ON e.parent_id=p.id WHERE $ws GROUP BY p.id ORDER BY p.nom LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $parents=$stmt->fetchAll();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Parents</div>
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-users"></i> Parents / Tuteurs (<?= $totalRows ?>)</h2>
    <a href="ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajouter</a>
  </div>
  <div class="card-body">
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control" placeholder="🔍 Nom, prénom, téléphone..." value="<?= sanitize($search) ?>">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-responsive">
    <table>
      <thead><tr><th>#</th><th>Nom & Prénom</th><th>Téléphone</th><th>Email</th><th>Profession</th><th>Enfants</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($parents as $i => $p): ?>
        <tr>
          <td><?= $offset+$i+1 ?></td>
          <td><strong><?= sanitize($p['prenom'].' '.$p['nom']) ?></strong></td>
          <td><?= sanitize($p['telephone']??'—') ?></td>
          <td><?= sanitize($p['email']??'—') ?></td>
          <td><?= sanitize($p['profession']??'—') ?></td>
          <td><span class="badge badge-primary"><?= $p['nb_enfants'] ?> enfant(s)</span></td>
          <td>
            <a href="ajouter.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
            <a href="supprimer.php?id=<?= $p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($parents)): ?><tr><td colspan="7"><div class="empty-state"><i class="fas fa-users"></i><p>Aucun parent</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
