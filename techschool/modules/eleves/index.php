<?php
// modules/eleves/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('eleves.view');
$pageTitle='Gestion des Élèves';
$annee=getAnneeActive($pdo); $aid=$annee['id']??1;
$search=trim($_GET['q']??''); $sexe=$_GET['sexe']??''; $classe_f=(int)($_GET['classe_id']??0); $statut=$_GET['statut']??'actif';
$page=max(1,(int)($_GET['page']??1)); $perPage=25;
$where=['1=1']; $params=[];
if($search){$where[]="(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)";$params=array_merge($params,["%$search%","%$search%","%$search%"]);}
if($sexe){$where[]="e.sexe=?";$params[]=$sexe;}
if($statut){$where[]="e.statut=?";$params[]=$statut;}
$joinClass=''; if($classe_f){$joinClass="JOIN inscriptions ins ON ins.eleve_id=e.id AND ins.annee_id=$aid AND ins.classe_id=$classe_f";}
$ws=implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM eleves e $joinClass WHERE $ws");$total->execute($params);
$totalRows=$total->fetchColumn();$totalPages=ceil($totalRows/$perPage);$offset=($page-1)*$perPage;
$stmt=$pdo->prepare("SELECT DISTINCT e.*,COALESCE(cl.nom,'—') as classe_nom,COALESCE(f.nom,'—') as filiere_nom,COALESCE(f.couleur,'#888') as filiere_color FROM eleves e LEFT JOIN inscriptions i2 ON i2.eleve_id=e.id AND i2.annee_id=$aid LEFT JOIN classes cl ON i2.classe_id=cl.id LEFT JOIN filieres f ON cl.filiere_id=f.id $joinClass WHERE $ws ORDER BY e.nom,e.prenom LIMIT $perPage OFFSET $offset");
$stmt->execute($params);$eleves=$stmt->fetchAll();
$classes=$pdo->query("SELECT c.*,n.nom as niveau_nom FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$aid ORDER BY n.ordre")->fetchAll();
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Élèves</div>
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-user-graduate"></i> Élèves — <?= $totalRows ?> trouvé(s)</h3>
    <?php if(can('eleves.create')): ?>
    <a href="ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Ajouter</a>
    <?php endif; ?>
  </div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="🔍 Nom, prénom, matricule..." value="<?= sanitize($search) ?>" style="max-width:260px;">
      <select name="sexe" class="form-control" style="width:auto;"><option value="">Tous sexes</option><option value="M" <?= $sexe==='M'?'selected':'' ?>>Masculin</option><option value="F" <?= $sexe==='F'?'selected':'' ?>>Féminin</option></select>
      <select name="classe_id" class="form-control" style="width:auto;"><option value="">Toutes classes</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $classe_f==$c['id']?'selected':'' ?>><?= sanitize($c['nom']) ?></option><?php endforeach; ?></select>
      <select name="statut" class="form-control" style="width:auto;"><option value="">Tous statuts</option><option value="actif" <?= $statut==='actif'?'selected':'' ?>>Actif</option><option value="inactif" <?= $statut==='inactif'?'selected':'' ?>>Inactif</option><option value="transfere">Transféré</option></select>
      <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div class="table-wrap">
    <table>
      <thead><tr><th>#</th><th>Matricule</th><th>Nom & Prénom</th><th>Sexe</th><th>Naissance</th><th>Classe</th><th>Filière</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($eleves as $i=>$e): ?>
        <tr>
          <td><?= ($page-1)*$perPage+$i+1 ?></td>
          <td><code style="font-size:11px;"><?= sanitize($e['matricule']) ?></code></td>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div class="avatar avatar-sm" style="background:<?= $e['sexe']==='F'?'#ec4899':'var(--primary)' ?>;"><?= initials($e['prenom'].' '.$e['nom']) ?></div>
              <strong><?= sanitize($e['nom'].' '.$e['prenom']) ?></strong>
            </div>
          </td>
          <td><?= $e['sexe']==='M'?'<span class="badge badge-info">M</span>':'<span class="badge" style="background:#fce7f3;color:#be185d;">F</span>' ?></td>
          <td><?= formatDate($e['date_naissance']??'') ?></td>
          <td><?= sanitize($e['classe_nom']) ?></td>
          <td><span class="filiere-badge" style="background:<?= sanitize($e['filiere_color']) ?>20;color:<?= sanitize($e['filiere_color']) ?>"><?= sanitize($e['filiere_nom']) ?></span></td>
          <td><?php $sc=['actif'=>'badge-success','inactif'=>'badge-secondary','transfere'=>'badge-warning','diplome'=>'badge-info']; ?><span class="badge <?= $sc[$e['statut']]??'badge-secondary' ?>"><?= sanitize($e['statut']) ?></span></td>
          <td>
            <div style="display:flex;gap:3px;">
              <a href="voir.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-ghost" title="Voir"><i class="fas fa-eye"></i></a>
              <?php if(can('eleves.edit')): ?><a href="ajouter.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-warning btn-icon" title="Modifier"><i class="fas fa-edit"></i></a><?php endif; ?>
              <a href="<?= BASE_URL ?>modules/bulletins/?eleve_id=<?= $e['id'] ?>" class="btn btn-sm btn-info btn-icon" title="Bulletin"><i class="fas fa-file-alt"></i></a>
              <?php if(can('eleves.delete')): ?><a href="supprimer.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-danger btn-icon" title="Supprimer" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($eleves)): ?><tr><td colspan="9"><div class="table-empty"><i class="fas fa-user-graduate"></i>Aucun élève trouvé</div></td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php if($totalPages>1): ?>
    <div class="pagination">
      <?php for($p=1;$p<=$totalPages;$p++): ?>
      <a href="?page=<?= $p ?>&q=<?= urlencode($search) ?>&sexe=<?= urlencode($sexe) ?>&classe_id=<?= $classe_f ?>&statut=<?= urlencode($statut) ?>" class="page-btn <?= $p==$page?'active':'' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
