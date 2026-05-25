<?php
// modules/enseignants/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Gestion des Enseignants';

$search = trim($_GET['search'] ?? '');
$statut = $_GET['statut'] ?? 'actif';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = ['1=1'];
$params = [];
if ($search) { $where[] = "(nom LIKE ? OR prenom LIKE ? OR matricule LIKE ? OR specialite LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]); }
if ($statut) { $where[] = "statut=?"; $params[] = $statut; }
$whereStr = implode(' AND ', $where);

$total = $pdo->prepare("SELECT COUNT(*) FROM enseignants WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);
$offset = ($page-1)*$perPage;

$stmt = $pdo->prepare("SELECT * FROM enseignants WHERE $whereStr ORDER BY nom LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$enseignants = $stmt->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span> Enseignants</div>
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-chalkboard-teacher"></i> Enseignants (<?= $totalRows ?>)</h2>
    <a href="ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajouter</a>
  </div>
  <div class="card-body">
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control" placeholder="🔍 Nom, prénom, spécialité..." value="<?= sanitize($search) ?>">
      <select name="statut" class="form-control" style="max-width:130px;">
        <option value="">Tous</option>
        <option value="actif" <?= $statut=='actif'?'selected':'' ?>>Actifs</option>
        <option value="inactif" <?= $statut=='inactif'?'selected':'' ?>>Inactifs</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-responsive">
    <table>
      <thead><tr><th>#</th><th>Matricule</th><th>Nom & Prénom</th><th>Spécialité</th><th>Téléphone</th><th>Email</th><th>Embauche</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($enseignants as $i => $e): ?>
        <tr>
          <td><?= $offset+$i+1 ?></td>
          <td><code><?= sanitize($e['matricule']) ?></code></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="avatar" style="width:32px;height:32px;font-size:11px;background:var(--secondary);">
                <?= strtoupper(substr($e['prenom'],0,1).substr($e['nom'],0,1)) ?>
              </div>
              <strong><?= sanitize($e['nom'].' '.$e['prenom']) ?></strong>
            </div>
          </td>
          <td><?= sanitize($e['specialite']??'—') ?></td>
          <td><?= sanitize($e['telephone']??'—') ?></td>
          <td><?= sanitize($e['email']??'—') ?></td>
          <td><?= $e['date_embauche'] ? date('d/m/Y',strtotime($e['date_embauche'])) : '—' ?></td>
          <td><span class="badge <?= $e['statut']=='actif'?'badge-success':'badge-secondary' ?>"><?= $e['statut'] ?></span></td>
          <td>
            <div style="display:flex;gap:4px;">
              <a href="modifier.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
              <a href="supprimer.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($enseignants)): ?><tr><td colspan="9"><div class="empty-state"><i class="fas fa-chalkboard-teacher"></i><p>Aucun enseignant</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
