<?php
// modules/eleves/voir.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('eleves.view');

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . 'modules/eleves/');

$stmt = $pdo->prepare("SELECT e.*, CONCAT(p.prenom,' ',p.nom) as parent_nom, p.telephone as parent_tel, p.email as parent_email, p.profession as parent_prof FROM eleves e LEFT JOIN parents p ON e.parent_id=p.id WHERE e.id=?");
$stmt->execute([$id]);
$eleve = $stmt->fetch();
if (!$eleve) { flash('Élève introuvable.', 'danger'); redirect(BASE_URL . 'modules/eleves/'); }

$annee = getAnneeActive($pdo);
$aid   = $annee['id'] ?? 1;

// Inscription actuelle
$insc = $pdo->prepare("SELECT i.*, cl.nom as classe_nom, f.nom as filiere_nom, f.couleur, n.nom as niveau_nom FROM inscriptions i JOIN classes cl ON i.classe_id=cl.id JOIN filieres f ON cl.filiere_id=f.id JOIN niveaux n ON cl.niveau_id=n.id WHERE i.eleve_id=? AND i.annee_id=?");
$insc->execute([$id, $aid]);
$inscription = $insc->fetch();

// Paiements
$paie = $pdo->prepare("SELECT p.* FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.eleve_id=? AND i.annee_id=? ORDER BY p.date_paiement DESC");
$paie->execute([$id, $aid]);
$paiements = $paie->fetchAll();
$totalPaye = array_sum(array_column($paiements, 'montant'));

// Notes par période
$notes_stmt = $pdo->prepare("
    SELECT n.*, m.nom as matiere_nom, m.type as mat_type, pr.nom as periode_nom, mc.coefficient
    FROM notes n
    JOIN matieres m ON n.matiere_id=m.id
    JOIN periodes pr ON n.periode_id=pr.id
    JOIN matiere_classe mc ON mc.matiere_id=n.matiere_id AND mc.classe_id=n.classe_id
    WHERE n.eleve_id=? AND n.annee_id=?
    ORDER BY pr.ordre, m.type, m.nom
");
$notes_stmt->execute([$id, $aid]);
$notesAll = $notes_stmt->fetchAll();

// Grouper par période
$notesByPeriode = [];
foreach ($notesAll as $n) {
    $notesByPeriode[$n['periode_nom']][] = $n;
}

// Absences
$abs = $pdo->prepare("SELECT COALESCE(SUM(nb_heures),0) as total, COALESCE(SUM(CASE WHEN justifie=1 THEN nb_heures ELSE 0 END),0) as justif FROM absences WHERE eleve_id=? AND annee_id=?");
$abs->execute([$id, $aid]);
$absences = $abs->fetch();

$pageTitle = $eleve['prenom'] . ' ' . $eleve['nom'];
include '../../includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a>
  <span class="breadcrumb-sep">/</span>
  <a href=".">Élèves</a>
  <span class="breadcrumb-sep">/</span>
  <?= sanitize($eleve['prenom'].' '.$eleve['nom']) ?>
</div>

<!-- PROFIL HEADER -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-body" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
    <div class="avatar" style="width:64px;height:64px;font-size:22px;background:<?= $eleve['sexe']==='F'?'#ec4899':'var(--primary)' ?>;">
      <?= initials($eleve['prenom'].' '.$eleve['nom']) ?>
    </div>
    <div style="flex:1;">
      <h2 style="font-size:20px;font-weight:800;"><?= sanitize(strtoupper($eleve['nom']).' '.$eleve['prenom']) ?></h2>
      <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:6px;">
        <span class="badge badge-secondary"><i class="fas fa-id-card"></i> <?= sanitize($eleve['matricule']) ?></span>
        <span class="badge <?= $eleve['sexe']==='M'?'badge-info':'badge' ?>" style="<?= $eleve['sexe']==='F'?'background:#fce7f3;color:#be185d;':'' ?>"><?= $eleve['sexe']==='M'?'Masculin':'Féminin' ?></span>
        <?php $sc=['actif'=>'badge-success','inactif'=>'badge-secondary','transfere'=>'badge-warning','diplome'=>'badge-info']; ?>
        <span class="badge <?= $sc[$eleve['statut']]??'badge-secondary' ?>"><?= ucfirst($eleve['statut']) ?></span>
        <?php if ($inscription): ?>
        <span class="filiere-badge" style="background:<?= sanitize($inscription['couleur']) ?>20;color:<?= sanitize($inscription['couleur']) ?>"><i class="fas fa-school"></i> <?= sanitize($inscription['classe_nom']) ?></span>
        <span class="badge badge-primary"><?= sanitize($inscription['filiere_nom']) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
      <?php if(can('eleves.edit')): ?>
      <a href="ajouter.php?id=<?= $id ?>" class="btn btn-warning btn-sm"><i class="fas fa-edit"></i> Modifier</a>
      <?php endif; ?>
      <?php if(can('bulletins.print')): ?>
      <?php if($inscription): ?>
      <a href="<?= BASE_URL ?>modules/bulletins/?classe_id=<?= $inscription['classe_id'] ?>&eleve_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="fas fa-file-alt"></i> Bulletin</a>
      <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="grid-2" style="gap:20px;margin-bottom:20px;">
  <!-- INFOS PERSONNELLES -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-user"></i> Informations personnelles</h3></div>
    <div class="card-body">
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <?php $rows = [
          ['Date de naissance', formatDate($eleve['date_naissance']??'')],
          ['Lieu de naissance', $eleve['lieu_naissance']??'—'],
          ['Nationalité', $eleve['nationalite']??'—'],
          ['Adresse', $eleve['adresse']??'—'],
          ['Téléphone', $eleve['telephone']??'—'],
          ['Email', $eleve['email']??'—'],
        ]; foreach($rows as [$k,$v]): ?>
        <tr>
          <td style="padding:7px 10px;border-bottom:1px solid var(--border);color:var(--text2);font-weight:500;width:150px;"><?= $k ?></td>
          <td style="padding:7px 10px;border-bottom:1px solid var(--border);"><?= sanitize($v) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>

  <!-- PARENT & SCOLARITÉ -->
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-users"></i> Parent & Scolarité</h3></div>
    <div class="card-body">
      <table style="width:100%;font-size:13px;border-collapse:collapse;">
        <?php $rows2 = [
          ['Parent/Tuteur', $eleve['parent_nom']??'—'],
          ['Tél. parent', $eleve['parent_tel']??'—'],
          ['Email parent', $eleve['parent_email']??'—'],
          ['Profession', $eleve['parent_prof']??'—'],
          ['Classe', $inscription['classe_nom']??'Non inscrit'],
          ['Niveau', $inscription['niveau_nom']??'—'],
          ['Filière', $inscription['filiere_nom']??'—'],
          ['Frais scolarité', $inscription?formatMoney($inscription['frais_scolarite']):'—'],
        ]; foreach($rows2 as [$k,$v]): ?>
        <tr>
          <td style="padding:7px 10px;border-bottom:1px solid var(--border);color:var(--text2);font-weight:500;width:150px;"><?= $k ?></td>
          <td style="padding:7px 10px;border-bottom:1px solid var(--border);"><?= sanitize($v) ?></td>
        </tr>
        <?php endforeach; ?>
      </table>
    </div>
  </div>
</div>

<!-- STATS RAPIDES -->
<div class="stats-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:20px;">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-star-half-alt"></i></div>
    <div>
      <div class="stat-value">
        <?php
        if ($notesAll) {
            $tp=0; $tc=0;
            foreach ($notesAll as $n) { $tp+=$n['note']*$n['coefficient']; $tc+=$n['coefficient']; }
            echo $tc>0 ? number_format($tp/$tc,2).'/20' : '—';
        } else echo '—';
        ?>
      </div>
      <div class="stat-label">Moyenne générale</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-money-bill-wave"></i></div>
    <div>
      <div class="stat-value" style="font-size:15px;"><?= number_format($totalPaye,0,',',' ') ?></div>
      <div class="stat-label">FCFA payés</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-user-clock"></i></div>
    <div>
      <div class="stat-value"><?= $absences['total'] ?>h</div>
      <div class="stat-label">Absences (<?= $absences['justif'] ?>h just.)</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-clipboard-list"></i></div>
    <div>
      <div class="stat-value"><?= count($notesAll) ?></div>
      <div class="stat-label">Notes enregistrées</div>
    </div>
  </div>
</div>

<!-- NOTES PAR PÉRIODE -->
<?php if (!empty($notesByPeriode)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h3><i class="fas fa-star-half-alt"></i> Notes par période</h3>
    <?php if($inscription): ?>
    <a href="<?= BASE_URL ?>modules/bulletins/?classe_id=<?= $inscription['classe_id'] ?>&eleve_id=<?= $id ?>" class="btn btn-primary btn-sm"><i class="fas fa-file-alt"></i> Générer bulletin</a>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0;">
    <div class="tabs" style="margin:0;padding:0 20px;background:#fafbfc;">
      <?php $first=true; foreach(array_keys($notesByPeriode) as $per): ?>
      <div class="tab <?= $first?'active':'' ?>" onclick="showPer('<?= md5($per) ?>',this)"><?= sanitize($per) ?></div>
      <?php $first=false; endforeach; ?>
    </div>
    <?php $first=true; foreach($notesByPeriode as $per=>$notes): ?>
    <div id="per-<?= md5($per) ?>" class="per-tab" style="display:<?= $first?'':'none' ?>;">
      <table>
        <thead><tr><th>Matière</th><th>Type</th><th>Coeff.</th><th>Note /20</th><th>Pondérée</th><th>Appréciation</th></tr></thead>
        <tbody>
          <?php
          $tp=0; $tc=0; $lastType='';
          foreach ($notes as $n):
            if ($lastType !== $n['mat_type']) {
                $lastType = $n['mat_type'];
                $tl = ['generale'=>'Matières générales','technique'=>'Matières techniques','pratique'=>'Travaux pratiques'];
          ?>
          <tr><td colspan="6" style="background:var(--bg2);font-size:10px;font-weight:700;color:var(--text2);text-transform:uppercase;padding:4px 14px;letter-spacing:.5px;"><?= $tl[$n['mat_type']]??$n['mat_type'] ?></td></tr>
          <?php } $tp+=$n['note']*$n['coefficient']; $tc+=$n['coefficient'];
            $ap = $n['note']>=16?'Très Bien':($n['note']>=14?'Bien':($n['note']>=12?'Assez Bien':($n['note']>=10?'Passable':'Insuffisant')));
            $nc = $n['note']>=10?'note-high':'note-low';
          ?>
          <tr>
            <td><strong><?= sanitize($n['matiere_nom']) ?></strong></td>
            <td><span class="badge badge-secondary"><?= sanitize($n['type_eval']) ?></span></td>
            <td style="text-align:center;"><?= $n['coefficient'] ?></td>
            <td style="text-align:center;"><span class="<?= $nc ?>"><?= number_format($n['note'],2) ?></span></td>
            <td style="text-align:center;"><?= number_format($n['note']*$n['coefficient'],2) ?></td>
            <td><?= $ap ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--primary);color:#fff;font-weight:700;">
            <td colspan="3" style="padding:8px 14px;text-align:right;">Moyenne :</td>
            <td colspan="3" style="padding:8px 14px;font-size:16px;">
              <?= $tc>0 ? number_format($tp/$tc,2).'/20' : '—' ?>
              <?php if($tc>0): $m=getMention($tp/$tc); ?>
              &nbsp;<span style="font-size:12px;font-weight:600;"><?= $m['label'] ?></span>
              <?php endif; ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
    <?php $first=false; endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- PAIEMENTS -->
<?php if (can('paiements.view')): ?>
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-money-bill-wave"></i> Historique des paiements</h3>
    <?php if($inscription && can('paiements.manage')): ?>
    <button class="btn btn-success btn-sm" onclick="openModal('pay-modal')"><i class="fas fa-plus"></i> Ajouter paiement</button>
    <?php endif; ?>
  </div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Date</th><th>Montant</th><th>Mode</th><th>Référence</th></tr></thead>
      <tbody>
        <?php foreach($paiements as $p): ?>
        <tr>
          <td><?= formatDate($p['date_paiement']) ?></td>
          <td style="font-weight:600;color:var(--success);"><?= formatMoney($p['montant']) ?></td>
          <td><?= sanitize($p['mode']) ?></td>
          <td style="font-size:11px;color:var(--text3);"><?= sanitize($p['reference']??'—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($paiements)): ?><tr><td colspan="4" class="table-empty">Aucun paiement enregistré</td></tr><?php endif; ?>
      </tbody>
    </table>
    <?php if($inscription): ?>
    <div style="padding:12px 16px;background:#fafbfc;border-top:1px solid var(--border);display:flex;justify-content:space-between;font-size:13px;">
      <span>Frais totaux : <strong><?= formatMoney($inscription['frais_scolarite']) ?></strong></span>
      <span>Payé : <strong style="color:var(--success);"><?= formatMoney($totalPaye) ?></strong></span>
      <span>Reste : <strong style="color:<?= ($inscription['frais_scolarite']-$totalPaye)>0?'var(--danger)':'var(--success)' ?>;"><?= formatMoney($inscription['frais_scolarite']-$totalPaye) ?></strong></span>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- MODAL PAIEMENT -->
<?php if($inscription && can('paiements.manage')): ?>
<div class="modal-overlay" id="pay-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-plus"></i> Nouveau paiement</h3><button class="modal-close" onclick="closeModal('pay-modal')">✕</button></div>
    <form method="POST" action="<?= BASE_URL ?>modules/paiements.php">
      <input type="hidden" name="inscription_id" value="<?= $inscription['id'] ?>">
      <input type="hidden" name="redirect" value="<?= BASE_URL ?>modules/eleves/voir.php?id=<?= $id ?>">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Montant (FCFA)</label><input type="number" name="montant" class="form-control" required min="1"></div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Date</label><input type="date" name="date_paiement" class="form-control" value="<?= date('Y-m-d') ?>"></div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Mode</label><select name="mode" class="form-control"><option value="especes">Espèces</option><option value="mobile_money">Mobile Money</option><option value="cheque">Chèque</option><option value="virement">Virement</option></select></div>
        <div class="form-group"><label class="form-label">Référence</label><input type="text" name="reference" class="form-control" placeholder="N° reçu..."></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('pay-modal')">Annuler</button><button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function showPer(id, el) {
    document.querySelectorAll('.per-tab').forEach(t => t.style.display='none');
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('per-'+id).style.display='';
    el.classList.add('active');
}
</script>

<?php include '../../includes/footer.php'; ?>
