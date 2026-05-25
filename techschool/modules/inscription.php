<?php
// modules/inscription.php
require_once '../includes/config.php';
requireLogin(); requirePerm('eleves.view');
$pageTitle='Inscriptions';
$annee=getAnneeActive($pdo); $aid=$annee['id']??1;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $eid=(int)$_POST['eleve_id']; $cid=(int)$_POST['classe_id']; $frais=(float)$_POST['frais_scolarite'];
    if(!$eid||!$cid){flash('Élève et classe obligatoires.','danger');}
    else{
        $ex=$pdo->prepare("SELECT id FROM inscriptions WHERE eleve_id=? AND annee_id=?"); $ex->execute([$eid,$aid]);
        if($ex->fetch()){flash('Cet élève est déjà inscrit pour cette année.','warning');}
        else{$pdo->prepare("INSERT INTO inscriptions (eleve_id,classe_id,annee_id,frais_scolarite) VALUES (?,?,?,?)")->execute([$eid,$cid,$aid,$frais]);flash('Inscription effectuée.');}
    }
    redirect(BASE_URL.'modules/inscription.php');
}
if(isset($_GET['del'])){$pdo->prepare("DELETE FROM inscriptions WHERE id=?")->execute([$_GET['del']]);flash('Inscription supprimée.','warning');redirect(BASE_URL.'modules/inscription.php');}
$search=trim($_GET['q']??''); $cf=(int)($_GET['classe_id']??0);
$where=["i.annee_id=$aid"]; $params=[];
if($search){$where[]="(e.nom LIKE ? OR e.prenom LIKE ? OR e.matricule LIKE ?)";$params=array_merge($params,["%$search%","%$search%","%$search%"]);}
if($cf){$where[]="i.classe_id=?";$params[]=$cf;}
$ws=implode(' AND ',$where);
$stmt=$pdo->prepare("SELECT i.*,e.nom,e.prenom,e.matricule,e.sexe,cl.nom as classe_nom,f.nom as filiere_nom,f.couleur,COALESCE(SUM(p.montant),0) as total_paye FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id JOIN classes cl ON i.classe_id=cl.id JOIN filieres f ON cl.filiere_id=f.id LEFT JOIN paiements p ON p.inscription_id=i.id WHERE $ws GROUP BY i.id ORDER BY cl.id,e.nom");
$stmt->execute($params); $inscrits=$stmt->fetchAll();
$eleves=$pdo->query("SELECT id,CONCAT(matricule,' — ',prenom,' ',nom) as label FROM eleves WHERE statut='actif' ORDER BY nom")->fetchAll();
$classes=$pdo->query("SELECT c.*,n.nom as nv FROM classes c JOIN niveaux n ON c.niveau_id=n.id WHERE c.annee_id=$aid ORDER BY n.ordre")->fetchAll();
include '../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Inscriptions</div>
<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">
<div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-file-signature"></i> Inscriptions — <?= sanitize($annee['libelle']??'') ?></h3></div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="form-control" placeholder="🔍 Nom, matricule..." value="<?= sanitize($search) ?>" style="max-width:240px;">
      <select name="classe_id" class="form-control" style="width:auto;"><option value="">Toutes classes</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>" <?= $cf==$c['id']?'selected':'' ?>><?= sanitize($c['nom']) ?></option><?php endforeach; ?></select>
      <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-search"></i></button>
    </form>
    <div class="table-wrap">
    <table>
      <thead><tr><th>Élève</th><th>Classe</th><th>Date</th><th>Frais</th><th>Payé</th><th>Reste</th><th></th></tr></thead>
      <tbody>
        <?php foreach($inscrits as $i): $reste=$i['frais_scolarite']-$i['total_paye']; ?>
        <tr>
          <td><strong><?= sanitize($i['nom'].' '.$i['prenom']) ?></strong><br><code style="font-size:10px;"><?= sanitize($i['matricule']) ?></code></td>
          <td><span class="filiere-badge" style="background:<?= sanitize($i['couleur']) ?>20;color:<?= sanitize($i['couleur']) ?>"><?= sanitize($i['classe_nom']) ?></span></td>
          <td style="font-size:12px;"><?= formatDate($i['date_inscription']) ?></td>
          <td><?= number_format($i['frais_scolarite'],0,',',' ') ?></td>
          <td style="color:var(--success);font-weight:600;"><?= number_format($i['total_paye'],0,',',' ') ?></td>
          <td style="color:<?= $reste>0?'var(--danger)':'var(--success)' ?>;font-weight:600;"><?= number_format($reste,0,',',' ') ?></td>
          <td><a href="?del=<?= $i['id'] ?>" class="btn btn-danger btn-xs" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($inscrits)): ?><tr><td colspan="7" class="table-empty">Aucune inscription</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
  </div>
</div>
</div>
<div class="card" style="height:fit-content;">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Nouvelle inscription</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Élève <span class="form-required">*</span></label><select name="eleve_id" class="form-control" required><option value="">— Chercher —</option><?php foreach($eleves as $e): ?><option value="<?= $e['id'] ?>"><?= sanitize($e['label']) ?></option><?php endforeach; ?></select></div>
      <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Classe <span class="form-required">*</span></label><select name="classe_id" class="form-control" required><option value="">— Sélectionner —</option><?php foreach($classes as $c): ?><option value="<?= $c['id'] ?>"><?= sanitize($c['nom'].' ('.$c['nv'].')') ?></option><?php endforeach; ?></select></div>
      <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Frais scolarité (FCFA)</label><input type="number" name="frais_scolarite" class="form-control" value="0" min="0" step="1000"></div>
      <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-file-signature"></i> Inscrire</button>
    </form>
  </div>
</div>
</div>
<?php include '../includes/footer.php'; ?>
