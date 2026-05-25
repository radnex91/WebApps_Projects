<?php
// modules/eleves/voir.php
require_once '../../includes/config.php';
requireLogin();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT e.*, CONCAT(p.prenom,' ',p.nom) as parent_nom, p.telephone as parent_tel, p.email as parent_email FROM eleves e LEFT JOIN parents p ON e.parent_id=p.id WHERE e.id=?");
$stmt->execute([$id]);
$eleve = $stmt->fetch();
if (!$eleve) { flash('Élève introuvable.','danger'); redirect(BASE_URL.'modules/eleves/'); }
$pageTitle = sanitize($eleve['prenom'].' '.$eleve['nom']);

$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

// Inscriptions
$inscrits = $pdo->prepare("SELECT i.*, cl.nom as classe_nom, n.nom as niveau_nom, n.cycle, a.libelle as annee_libelle FROM inscriptions i JOIN classes cl ON i.classe_id=cl.id JOIN niveaux n ON cl.niveau_id=n.id JOIN annees_scolaires a ON i.annee_id=a.id WHERE i.eleve_id=? ORDER BY a.libelle DESC");
$inscrits->execute([$id]); $inscrits = $inscrits->fetchAll();

// Notes
$notes = $pdo->prepare("SELECT n.*, m.nom as matiere, m.coefficient, per.nom as periode FROM notes n JOIN matieres m ON n.matiere_id=m.id JOIN periodes per ON n.periode_id=per.id WHERE n.eleve_id=? AND n.annee_id=? ORDER BY per.id, m.nom");
$notes->execute([$id,$annee_id]); $notes=$notes->fetchAll();

// Absences
$abs = $pdo->prepare("SELECT COUNT(*) as total, SUM(justifie) as justif FROM absences WHERE eleve_id=? AND annee_id=?");
$abs->execute([$id,$annee_id]); $abs=$abs->fetch();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span><a href=".">Élèves</a><span class="breadcrumb-sep"></span><?= sanitize($eleve['prenom'].' '.$eleve['nom']) ?></div>

<div style="display:grid;grid-template-columns:280px 1fr;gap:20px;">
<!-- PROFIL -->
<div>
  <div class="card">
    <div class="card-body" style="text-align:center;">
      <div style="width:80px;height:80px;border-radius:50%;background:<?= $eleve['sexe']=='F'?'#ec4899':'var(--primary)' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:800;margin:0 auto 12px;">
        <?= strtoupper(substr($eleve['prenom'],0,1).substr($eleve['nom'],0,1)) ?>
      </div>
      <h2 style="margin-bottom:4px;"><?= sanitize($eleve['prenom'].' '.$eleve['nom']) ?></h2>
      <code style="font-size:13px;"><?= sanitize($eleve['matricule']) ?></code>
      <div style="margin-top:12px;">
        <span class="badge <?= $eleve['statut']=='actif'?'badge-success':'badge-secondary' ?>"><?= sanitize($eleve['statut']) ?></span>
        <span class="badge <?= $eleve['sexe']=='F'?'':'badge-info' ?>" style="<?= $eleve['sexe']=='F'?'background:#fce7f3;color:#be185d;':'' ?>"><?= $eleve['sexe']=='M'?'Masculin':'Féminin' ?></span>
      </div>
    </div>
    <div class="card-body" style="border-top:1px solid var(--border);font-size:13px;">
      <div style="margin-bottom:8px;"><i class="fas fa-birthday-cake" style="width:18px;color:var(--primary);"></i> <?= $eleve['date_naissance'] ? date('d/m/Y',strtotime($eleve['date_naissance'])) : '—' ?></div>
      <div style="margin-bottom:8px;"><i class="fas fa-map-marker-alt" style="width:18px;color:var(--primary);"></i> <?= sanitize($eleve['lieu_naissance']??'—') ?></div>
      <div style="margin-bottom:8px;"><i class="fas fa-home" style="width:18px;color:var(--primary);"></i> <?= sanitize($eleve['adresse']??'—') ?></div>
      <?php if($eleve['parent_nom']): ?>
      <hr style="border-color:var(--border);margin:12px 0;">
      <div style="font-weight:700;margin-bottom:8px;"><i class="fas fa-user"></i> Parent</div>
      <div style="margin-bottom:4px;"><?= sanitize($eleve['parent_nom']) ?></div>
      <div style="margin-bottom:4px;"><i class="fas fa-phone" style="width:18px;"></i> <?= sanitize($eleve['parent_tel']??'—') ?></div>
      <div><i class="fas fa-envelope" style="width:18px;"></i> <?= sanitize($eleve['parent_email']??'—') ?></div>
      <?php endif; ?>
    </div>
    <div class="card-footer">
      <a href="modifier.php?id=<?= $eleve['id'] ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Modifier</a>
      <a href="../../modules/bulletins/?eleve_id=<?= $eleve['id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-file-alt"></i> Bulletin</a>
    </div>
  </div>

  <!-- ABSENCES -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-clock"></i> Absences</h2></div>
    <div class="card-body" style="text-align:center;">
      <div style="font-size:32px;font-weight:800;color:<?= ($abs['total']??0)>5?'var(--danger)':'var(--success)' ?>"><?= $abs['total']??0 ?></div>
      <div style="font-size:12px;color:var(--text-muted);">dont <?= $abs['justif']??0 ?> justifiée(s)</div>
    </div>
  </div>
</div>

<!-- CONTENU -->
<div>
  <!-- SCOLARITÉ -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-graduation-cap"></i> Historique scolaire</h2></div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>Année</th><th>Classe</th><th>Niveau</th><th>Statut</th></tr></thead>
        <tbody>
          <?php foreach($inscrits as $i):
            $cc = strtolower(str_replace(['é','è','ê'],'e',$i['cycle']));
          ?>
          <tr>
            <td><?= $i['annee_id']==$annee_id ? '⭐ '.sanitize($i['annee_libelle']) : sanitize($i['annee_libelle']) ?></td>
            <td><span class="badge cycle-<?= $cc ?>"><?= sanitize($i['classe_nom']) ?></span></td>
            <td><?= sanitize($i['niveau_nom']) ?></td>
            <td><span class="badge badge-<?= $i['statut']=='actif'?'success':($i['statut']=='inscrit'?'info':'secondary') ?>"><?= sanitize($i['statut']) ?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($inscrits)): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune inscription</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- NOTES -->
  <div class="card">
    <div class="card-header"><h2><i class="fas fa-star-half-alt"></i> Notes — <?= sanitize($annee['libelle']??'') ?></h2></div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>Matière</th><th>Période</th><th>Note</th><th>Type</th><th>Observation</th></tr></thead>
        <tbody>
          <?php foreach($notes as $n): ?>
          <tr>
            <td><?= sanitize($n['matiere']) ?></td>
            <td><?= sanitize($n['periode']) ?></td>
            <td><strong style="color:<?= $n['note']>=10?'var(--success)':'var(--danger)' ?>"><?= $n['note'] ?>/20</strong></td>
            <td><span class="badge badge-info"><?= sanitize($n['type_eval']) ?></span></td>
            <td><?= sanitize($n['observation']??'') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($notes)): ?><tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune note</td></tr><?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>
