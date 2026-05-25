<?php
// modules/inscription/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Inscriptions';
$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

// Inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $eleve_id = (int)($_POST['eleve_id']??0);
    $classe_id = (int)($_POST['classe_id']??0);
    $frais = (float)($_POST['frais_scolarite']??0);
    if (!$eleve_id || !$classe_id) { flash('Élève et classe obligatoires.','danger'); }
    else {
        // Vérifier si déjà inscrit
        $exists = $pdo->prepare("SELECT id FROM inscriptions WHERE eleve_id=? AND annee_id=?");
        $exists->execute([$eleve_id,$annee_id]);
        if ($exists->fetchColumn()) { flash("Cet élève est déjà inscrit pour l'année {$annee['libelle']}.","warning"); }
        else {
            $pdo->prepare("INSERT INTO inscriptions (eleve_id,classe_id,annee_id,frais_scolarite,date_inscription) VALUES (?,?,?,?,CURDATE())")
                ->execute([$eleve_id,$classe_id,$annee_id,$frais]);
            flash('Inscription réalisée avec succès.');
        }
    }
    redirect(BASE_URL.'modules/inscription/');
}

// Delete
if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM inscriptions WHERE id=?")->execute([$_GET['del']]);
    flash('Inscription supprimée.','warning');
    redirect(BASE_URL.'modules/inscription/');
}

$search = trim($_GET['search'] ?? '');
$classe_f = $_GET['classe_id'] ?? '';
$page = max(1,(int)($_GET['page']??1)); $perPage=25;

$where = ["i.annee_id=$annee_id"]; $params=[];
if($search){$where[]="(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]);}
if($classe_f){$where[]="i.classe_id=?"; $params[]=$classe_f;}
$ws=implode(' AND ',$where);

$total=$pdo->prepare("SELECT COUNT(*) FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id WHERE $ws"); $total->execute($params);
$totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$perPage); $offset=($page-1)*$perPage;

$stmt=$pdo->prepare("SELECT i.*, e.nom, e.prenom, e.matricule, e.sexe, cl.nom as classe_nom, n.nom as niveau_nom, n.cycle,
    COALESCE(SUM(p.montant),0) as total_paye
    FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id JOIN classes cl ON i.classe_id=cl.id JOIN niveaux n ON cl.niveau_id=n.id
    LEFT JOIN paiements p ON p.inscription_id=i.id
    WHERE $ws GROUP BY i.id ORDER BY n.ordre, cl.nom, e.nom LIMIT $perPage OFFSET $offset");
$stmt->execute($params); $inscriptions=$stmt->fetchAll();

$eleves_list = $pdo->query("SELECT id, CONCAT(matricule,' - ',prenom,' ',nom) as label FROM eleves WHERE statut='actif' ORDER BY nom")->fetchAll();
$classes_list = $pdo->query("SELECT c.id, CONCAT(c.nom,' (',n.nom,')') as label FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$annee_id ORDER BY n.ordre")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Inscriptions</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">
<div>
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-file-signature"></i> Inscriptions — <?= sanitize($annee['libelle']??'') ?> (<?= $totalRows ?>)</h2>
    <a href="<?= BASE_URL ?>modules/rapports/?type=inscriptions" class="btn btn-secondary btn-sm"><i class="fas fa-chart-bar"></i> Stats</a>
  </div>
  <div class="card-body">
    <form method="GET" class="search-bar">
      <input type="text" name="search" class="form-control" placeholder="🔍 Nom, prénom, matricule..." value="<?= sanitize($search) ?>">
      <select name="classe_id" class="form-control" style="max-width:200px;">
        <option value="">Toutes classes</option>
        <?php foreach($classes_list as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $classe_f==$c['id']?'selected':'' ?>><?= sanitize($c['label']) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-responsive">
    <table>
      <thead><tr><th>#</th><th>Matricule</th><th>Élève</th><th>Classe</th><th>Date</th><th>Frais</th><th>Payé</th><th>Reste</th><th>Statut</th><th></th></tr></thead>
      <tbody>
        <?php foreach($inscriptions as $i => $insc):
          $reste = $insc['frais_scolarite'] - $insc['total_paye'];
          $cycle = strtolower(str_replace(['é','è','ê'],'e',$insc['cycle']));
        ?>
        <tr>
          <td><?= $offset+$i+1 ?></td>
          <td><code><?= sanitize($insc['matricule']) ?></code></td>
          <td><strong><?= sanitize($insc['nom'].' '.$insc['prenom']) ?></strong></td>
          <td><span class="badge cycle-<?= $cycle ?>"><?= sanitize($insc['classe_nom']) ?></span></td>
          <td><?= date('d/m/Y',strtotime($insc['date_inscription'])) ?></td>
          <td><?= number_format($insc['frais_scolarite'],0,',',' ') ?></td>
          <td style="color:var(--success)"><strong><?= number_format($insc['total_paye'],0,',',' ') ?></strong></td>
          <td style="color:<?= $reste>0?'var(--danger)':'var(--success)' ?>"><strong><?= number_format($reste,0,',',' ') ?></strong></td>
          <td><span class="badge <?= $insc['statut']=='actif'?'badge-success':($insc['statut']=='inscrit'?'badge-info':'badge-danger') ?>"><?= $insc['statut'] ?></span></td>
          <td>
            <a href="<?= BASE_URL ?>modules/paiements.php?inscription_id=<?= $insc['id'] ?>" class="btn btn-sm btn-success" title="Paiement"><i class="fas fa-money-bill"></i></a>
            <a href="?del=<?= $insc['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($inscriptions)): ?><tr><td colspan="10"><div class="empty-state"><i class="fas fa-file-signature"></i><p>Aucune inscription</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
</div>

<!-- FORMULAIRE INSCRIPTION -->
<div class="card" style="height:fit-content;">
  <div class="card-header"><h2><i class="fas fa-plus"></i> Nouvelle inscription</h2></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group"><label>Élève *</label>
        <select name="eleve_id" class="form-control" required>
          <option value="">-- Chercher un élève --</option>
          <?php foreach($eleves_list as $e): ?>
          <option value="<?= $e['id'] ?>"><?= sanitize($e['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Classe *</label>
        <select name="classe_id" class="form-control" required>
          <option value="">-- Sélectionner --</option>
          <?php foreach($classes_list as $c): ?>
          <option value="<?= $c['id'] ?>"><?= sanitize($c['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group"><label>Frais de scolarité (FCFA)</label>
        <input type="number" name="frais_scolarite" class="form-control" value="0" min="0" step="500">
      </div>
      <div style="margin-top:16px;">
        <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;"><i class="fas fa-file-signature"></i> Inscrire</button>
      </div>
      <div style="margin-top:8px;text-align:center;">
        <a href="<?= BASE_URL ?>modules/eleves/ajouter.php" style="font-size:12px;color:var(--primary);">+ Créer un nouvel élève</a>
      </div>
    </form>
  </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>
