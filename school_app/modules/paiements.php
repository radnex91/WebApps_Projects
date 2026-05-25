<?php
// modules/paiements.php
require_once '../includes/config.php';
requireLogin();
$pageTitle = 'Paiements de Scolarité';
$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

$inscription_id = (int)($_GET['inscription_id'] ?? 0);
$insc_info = null;

if ($inscription_id) {
    $stmt = $pdo->prepare("SELECT i.*, e.nom, e.prenom, e.matricule, cl.nom as classe_nom FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id JOIN classes cl ON i.classe_id=cl.id WHERE i.id=?");
    $stmt->execute([$inscription_id]);
    $insc_info = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ins_id = (int)($_POST['inscription_id']??0);
    $montant = (float)($_POST['montant']??0);
    $mode = $_POST['mode'] ?? 'especes';
    $ref = trim($_POST['reference']??'');
    $obs = trim($_POST['observation']??'');
    if (!$ins_id || $montant <= 0) { flash('Inscription et montant obligatoires.','danger'); }
    else {
        $pdo->prepare("INSERT INTO paiements (inscription_id,montant,date_paiement,mode,reference,observation) VALUES (?,?,CURDATE(),?,?,?)")
            ->execute([$ins_id,$montant,$mode,$ref?:null,$obs?:null]);
        flash('Paiement enregistré.');
        redirect(BASE_URL.'modules/paiements.php?inscription_id='.$ins_id);
    }
}

// Historique
$paiements = [];
if ($inscription_id) {
    $stmt = $pdo->prepare("SELECT * FROM paiements WHERE inscription_id=? ORDER BY date_paiement DESC");
    $stmt->execute([$inscription_id]);
    $paiements = $stmt->fetchAll();
}

// Stats générales
$stats_stmt = $pdo->prepare("SELECT COALESCE(SUM(p.montant),0) as total, COUNT(*) as nb FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=?");
$stats_stmt->execute([$annee_id]);
$stats = $stats_stmt->fetch();

include '../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Paiements</div>

<div class="stats-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-coins"></i></div><div><div class="stat-value"><?= number_format($stats['total'],0,',',' ') ?></div><div class="stat-label">Total encaissé (FCFA)</div></div></div>
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-receipt"></i></div><div><div class="stat-value"><?= $stats['nb'] ?></div><div class="stat-label">Transactions</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-calendar-alt"></i></div><div><div class="stat-value"><?= sanitize($annee['libelle']??'—') ?></div><div class="stat-label">Année active</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr <?= $inscription_id?'380px':'' ?>;gap:20px;">
<div class="card">
  <div class="card-header"><h2><i class="fas fa-history"></i> Historique des paiements</h2></div>
  <div class="card-body" style="padding:0;">
    <?php if($insc_info): ?>
    <div style="padding:12px 20px;background:#f0f7ff;border-bottom:1px solid var(--border);">
      Élève : <strong><?= sanitize($insc_info['prenom'].' '.$insc_info['nom']) ?></strong> |
      Classe : <?= sanitize($insc_info['classe_nom']) ?> |
      Frais : <?= number_format($insc_info['frais_scolarite'],0,',',' ') ?> FCFA
    </div>
    <?php endif; ?>
    <table>
      <thead><tr><th>Date</th><th>Montant</th><th>Mode</th><th>Référence</th><th>Élève</th><th>Observation</th></tr></thead>
      <tbody>
        <?php
        if ($inscription_id) {
            $all_pays = $paiements;
        } else {
            $stmt_all = $pdo->prepare("SELECT p.*, e.nom, e.prenom, cl.nom as classe FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id JOIN eleves e ON i.eleve_id=e.id JOIN classes cl ON i.classe_id=cl.id WHERE i.annee_id=? ORDER BY p.date_paiement DESC LIMIT 50");
            $stmt_all->execute([$annee_id]);
            $all_pays = $stmt_all->fetchAll();
        }
        foreach($all_pays as $pay):
        ?>
        <tr>
          <td><?= date('d/m/Y',strtotime($pay['date_paiement'])) ?></td>
          <td><strong style="color:var(--success)"><?= number_format($pay['montant'],0,',',' ') ?> FCFA</strong></td>
          <td><span class="badge badge-info"><?= sanitize($pay['mode']) ?></span></td>
          <td><?= sanitize($pay['reference']??'—') ?></td>
          <td><?= isset($pay['nom']) ? sanitize($pay['prenom'].' '.$pay['nom']) : '—' ?></td>
          <td><?= sanitize($pay['observation']??'') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($all_pays)): ?><tr><td colspan="6"><div class="empty-state"><i class="fas fa-receipt"></i><p>Aucun paiement</p></div></td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if($inscription_id && $insc_info): 
  $total_paye = array_sum(array_column($paiements,'montant'));
  $reste = $insc_info['frais_scolarite'] - $total_paye;
?>
<div class="card" style="height:fit-content;">
  <div class="card-header"><h2><i class="fas fa-plus"></i> Enregistrer un paiement</h2></div>
  <div class="card-body">
    <div style="background:#f0f7ff;border-radius:8px;padding:12px;margin-bottom:16px;font-size:13px;">
      <div>Frais total : <strong><?= number_format($insc_info['frais_scolarite'],0,',',' ') ?> FCFA</strong></div>
      <div>Déjà payé : <strong style="color:var(--success)"><?= number_format($total_paye,0,',',' ') ?> FCFA</strong></div>
      <div>Reste dû : <strong style="color:<?= $reste>0?'var(--danger)':'var(--success)' ?>"><?= number_format($reste,0,',',' ') ?> FCFA</strong></div>
    </div>
    <form method="POST">
      <input type="hidden" name="inscription_id" value="<?= $inscription_id ?>">
      <div class="form-group"><label>Montant (FCFA) *</label>
        <input type="number" name="montant" class="form-control" value="<?= max(0,$reste) ?>" min="0" step="500" required>
      </div>
      <div class="form-group"><label>Mode de paiement</label>
        <select name="mode" class="form-control">
          <option value="especes">Espèces</option>
          <option value="chèque">Chèque</option>
          <option value="virement">Virement</option>
          <option value="mobile_money">Mobile Money</option>
        </select>
      </div>
      <div class="form-group"><label>Référence / Reçu</label>
        <input type="text" name="reference" class="form-control" placeholder="N° de reçu...">
      </div>
      <div class="form-group"><label>Observation</label>
        <input type="text" name="observation" class="form-control" placeholder="Observation...">
      </div>
      <button type="submit" class="btn btn-success" style="width:100%;justify-content:center;margin-top:8px;"><i class="fas fa-money-bill"></i> Enregistrer</button>
    </form>
  </div>
</div>
<?php endif; ?>
</div>
<?php include '../includes/footer.php'; ?>
