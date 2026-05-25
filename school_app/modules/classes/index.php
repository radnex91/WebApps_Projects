<?php
// modules/classes/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Gestion des Classes';
$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

// ADD/EDIT
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = trim($_POST['nom']??'');
    $niveau_id = (int)($_POST['niveau_id']??0);
    $capacite = (int)($_POST['capacite']??30);
    $id = (int)($_POST['id']??0);
    if (!$nom || !$niveau_id) { flash('Nom et niveau obligatoires.','danger'); }
    elseif ($id) {
        $pdo->prepare("UPDATE classes SET nom=?,niveau_id=?,capacite=? WHERE id=?")->execute([$nom,$niveau_id,$capacite,$id]);
        flash('Classe modifiée.');
    } else {
        $pdo->prepare("INSERT INTO classes (nom,niveau_id,annee_id,capacite) VALUES (?,?,?,?)")->execute([$nom,$niveau_id,$annee_id,$capacite]);
        flash('Classe ajoutée.');
    }
    redirect(BASE_URL.'modules/classes/');
}
if (isset($_GET['del'])) {
    $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$_GET['del']]);
    flash('Classe supprimée.','warning');
    redirect(BASE_URL.'modules/classes/');
}

$classes = $pdo->query("SELECT c.*, n.nom as niveau_nom, n.cycle, (SELECT COUNT(*) FROM inscriptions i WHERE i.classe_id=c.id AND i.annee_id=$annee_id) as nb_eleves FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$annee_id ORDER BY n.ordre, c.nom")->fetchAll();
$niveaux = $pdo->query("SELECT * FROM niveaux ORDER BY ordre")->fetchAll();

$edit = null;
if (isset($_GET['edit'])) { foreach($classes as $c) if($c['id']==$_GET['edit']) { $edit = $c; break; } }

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Classes</div>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-chalkboard"></i> Classes — <?= sanitize($annee['libelle']??'') ?></h2></div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>#</th><th>Classe</th><th>Niveau</th><th>Cycle</th><th>Capacité</th><th>Élèves</th><th>Occupation</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($classes as $i => $c):
            $pct = $c['capacite'] > 0 ? min(100, round($c['nb_eleves']/$c['capacite']*100)) : 0;
            $pct_color = $pct>=90?'var(--danger)':($pct>=70?'var(--warning)':'var(--success)');
            $cycle_class = strtolower(str_replace(['é','è','ê'],'e',$c['cycle']));
          ?>
          <tr>
            <td><?= $i+1 ?></td>
            <td><strong><?= sanitize($c['nom']) ?></strong></td>
            <td><?= sanitize($c['niveau_nom']) ?></td>
            <td><span class="badge cycle-<?= $cycle_class ?>"><?= sanitize($c['cycle']) ?></span></td>
            <td><?= $c['capacite'] ?></td>
            <td><strong><?= $c['nb_eleves'] ?></strong></td>
            <td>
              <div style="display:flex;align-items:center;gap:6px;">
                <div style="flex:1;background:#f1f5f9;border-radius:10px;height:8px;overflow:hidden;">
                  <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct_color ?>;border-radius:10px;"></div>
                </div>
                <span style="font-size:11px;"><?= $pct ?>%</span>
              </div>
            </td>
            <td>
              <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></a>
              <a href="?del=<?= $c['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer cette classe ?')"><i class="fas fa-trash"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($classes)): ?><tr><td colspan="8"><div class="empty-state"><i class="fas fa-chalkboard"></i><p>Aucune classe</p></div></td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- FORM -->
  <div class="card" style="height:fit-content;">
    <div class="card-header"><h2><?= $edit?'<i class="fas fa-edit"></i> Modifier':'<i class="fas fa-plus"></i> Ajouter' ?> une classe</h2></div>
    <div class="card-body">
      <form method="POST">
        <?php if($edit): ?><input type="hidden" name="id" value="<?= $edit['id'] ?>"><?php endif; ?>
        <div class="form-group"><label>Nom de la classe *</label>
          <input type="text" name="nom" class="form-control" value="<?= sanitize($edit['nom']??'') ?>" required placeholder="Ex: 6ème A, CM1-B">
        </div>
        <div class="form-group"><label>Niveau *</label>
          <select name="niveau_id" class="form-control" required>
            <option value="">-- Choisir --</option>
            <?php foreach($niveaux as $n): ?>
            <option value="<?= $n['id'] ?>" <?= ($edit['niveau_id']??'')==$n['id']?'selected':'' ?>><?= sanitize($n['nom'].' ('.$n['cycle'].')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label>Capacité max.</label>
          <input type="number" name="capacite" class="form-control" value="<?= $edit['capacite']??30 ?>" min="1" max="100">
        </div>
        <div style="display:flex;gap:8px;margin-top:16px;">
          <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> <?= $edit?'Modifier':'Ajouter' ?></button>
          <?php if($edit): ?><a href="?" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Annuler</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
