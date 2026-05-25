<?php
// modules/eleves/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Gestion des Élèves';

$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

// Filtres
$search  = trim($_GET['search'] ?? '');
$sexe    = $_GET['sexe'] ?? '';
$niveau  = $_GET['niveau'] ?? '';
$statut  = $_GET['statut'] ?? 'actif';
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = ['1=1'];
$params = [];
if ($search) { $where[] = "(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)"; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($sexe) { $where[] = "e.sexe=?"; $params[] = $sexe; }
if ($statut) { $where[] = "e.statut=?"; $params[] = $statut; }

$joinNiveau = '';
$whereNiveau = '';
if ($niveau) {
    $joinNiveau = "LEFT JOIN inscriptions i ON i.eleve_id=e.id AND i.annee_id=$annee_id LEFT JOIN classes c ON i.classe_id=c.id";
    $where[] = "c.niveau_id=?"; $params[] = $niveau;
}

$whereStr = implode(' AND ', $where);
$total = $pdo->prepare("SELECT COUNT(DISTINCT e.id) FROM eleves e $joinNiveau WHERE $whereStr");
$total->execute($params);
$totalRows = $total->fetchColumn();
$totalPages = ceil($totalRows / $perPage);
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT DISTINCT e.*, 
    COALESCE(n.nom,'Non inscrit') as niveau_nom, COALESCE(n.cycle,'') as cycle,
    COALESCE(cl.nom,'') as classe_nom,
    CONCAT(p.prenom,' ',p.nom) as parent_nom, p.telephone as parent_tel
    FROM eleves e
    LEFT JOIN inscriptions i2 ON i2.eleve_id=e.id AND i2.annee_id=$annee_id
    LEFT JOIN classes cl ON i2.classe_id=cl.id
    LEFT JOIN niveaux n ON cl.niveau_id=n.id
    LEFT JOIN parents p ON e.parent_id=p.id
    $joinNiveau
    WHERE $whereStr
    ORDER BY e.nom, e.prenom LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$eleves = $stmt->fetchAll();

$niveaux_list = $pdo->query("SELECT * FROM niveaux ORDER BY ordre")->fetchAll();
include '../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i> Accueil</a>
  <span class="breadcrumb-sep"></span> Élèves
</div>

<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-user-graduate"></i> Liste des élèves</h2>
    <div style="display:flex;gap:8px;">
      <a href="ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajouter</a>
    </div>
  </div>
  <div class="card-body">
    <!-- FILTRES -->
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control" placeholder="🔍 Rechercher nom, prénom, matricule..." value="<?= sanitize($search) ?>">
      <select name="sexe" class="form-control" style="max-width:130px;">
        <option value="">Tous sexes</option>
        <option value="M" <?= $sexe=='M'?'selected':'' ?>>Masculin</option>
        <option value="F" <?= $sexe=='F'?'selected':'' ?>>Féminin</option>
      </select>
      <select name="niveau" class="form-control" style="max-width:160px;">
        <option value="">Tous niveaux</option>
        <?php foreach($niveaux_list as $n): ?>
        <option value="<?= $n['id'] ?>" <?= $niveau==$n['id']?'selected':'' ?>><?= sanitize($n['nom']) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="statut" class="form-control" style="max-width:130px;">
        <option value="">Tous statuts</option>
        <option value="actif" <?= $statut=='actif'?'selected':'' ?>>Actif</option>
        <option value="inactif" <?= $statut=='inactif'?'selected':'' ?>>Inactif</option>
        <option value="transfere" <?= $statut=='transfere'?'selected':'' ?>>Transféré</option>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Filtrer</button>
      <a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
    </form>

    <div style="color:var(--text-muted);font-size:13px;margin-bottom:10px;">
      <strong><?= $totalRows ?></strong> élève(s) trouvé(s)
    </div>

    <div class="table-responsive">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Matricule</th>
          <th>Nom & Prénom</th>
          <th>Sexe</th>
          <th>Naissance</th>
          <th>Classe</th>
          <th>Parent</th>
          <th>Statut</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($eleves as $i => $e):
          $cycle_class = strtolower(str_replace(['é','è','ê'],'e', $e['cycle']));
        ?>
        <tr>
          <td><?= $offset+$i+1 ?></td>
          <td><code><?= sanitize($e['matricule']) ?></code></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="avatar" style="width:32px;height:32px;font-size:12px;background:<?= $e['sexe']=='F'?'#ec4899':'#2563eb' ?>;">
                <?= strtoupper(substr($e['prenom'],0,1).substr($e['nom'],0,1)) ?>
              </div>
              <div>
                <strong><?= sanitize($e['nom'].' '.$e['prenom']) ?></strong>
              </div>
            </div>
          </td>
          <td><?= $e['sexe']=='M' ? '<span class="badge badge-info">Masc.</span>' : '<span class="badge" style="background:#fce7f3;color:#be185d;">Fém.</span>' ?></td>
          <td><?= $e['date_naissance'] ? date('d/m/Y', strtotime($e['date_naissance'])) : '—' ?></td>
          <td>
            <?php if($e['classe_nom']): ?>
            <span class="badge cycle-<?= $cycle_class ?>"><?= sanitize($e['classe_nom']) ?></span>
            <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
          </td>
          <td>
            <?php if($e['parent_nom']): ?>
            <span title="<?= sanitize($e['parent_tel']??'') ?>"><?= sanitize($e['parent_nom']) ?></span>
            <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?>
          </td>
          <td>
            <?php
            $badges = ['actif'=>'badge-success','inactif'=>'badge-secondary','transfere'=>'badge-warning','diplome'=>'badge-info'];
            echo '<span class="badge '.($badges[$e['statut']]??'badge-secondary').'">'.sanitize($e['statut']).'</span>';
            ?>
          </td>
          <td>
            <div style="display:flex;gap:4px;">
              <a href="voir.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-secondary" title="Voir"><i class="fas fa-eye"></i></a>
              <a href="modifier.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-warning" title="Modifier"><i class="fas fa-edit"></i></a>
              <a href="../../modules/bulletins/?eleve_id=<?= $e['id'] ?>" class="btn btn-sm btn-info" title="Bulletin" style="background:#0891b2;color:#fff;"><i class="fas fa-file-alt"></i></a>
              <a href="supprimer.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-danger" title="Supprimer" onclick="return confirm('Supprimer cet élève ?')"><i class="fas fa-trash"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($eleves)): ?>
        <tr><td colspan="9">
          <div class="empty-state"><i class="fas fa-user-graduate"></i><p>Aucun élève trouvé</p></div>
        </td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>

    <!-- PAGINATION -->
    <?php if($totalPages > 1): ?>
    <div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?>
      <a href="?page=<?= $p ?>&search=<?= urlencode($search) ?>&sexe=<?= urlencode($sexe) ?>&niveau=<?= urlencode($niveau) ?>&statut=<?= urlencode($statut) ?>"
         class="page-btn <?= $p==$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include '../../includes/footer.php'; ?>
