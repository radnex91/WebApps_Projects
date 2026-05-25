<?php
// modules/acomptes/index.php — Avances sur Salaires
require_once '../../includes/config.php';
requireLogin(); requirePerm('acomptes.manage');
$pageTitle = 'Acomptes / Avances sur Salaires';

if (isset($_GET['rembourse']) && can('acomptes.manage')) {
    $pdo->prepare("UPDATE acomptes SET rembourse=1,date_remboursement=NOW() WHERE id=?")->execute([(int)$_GET['rembourse']]);
    flash('Acompte marqué remboursé.');
    redirect(BASE_URL.'modules/acomptes/');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emp_id  = (int)($_POST['employe_id'] ?? 0);
    $ag_id   = (int)($_POST['agence_id'] ?? 0) ?: null;
    $montant = (float)($_POST['montant'] ?? 0);
    $date    = $_POST['date_acompte'] ?? date('Y-m-d');
    $motif   = trim($_POST['motif'] ?? '');
    if ($emp_id && $montant > 0) {
        $pdo->prepare("INSERT INTO acomptes (employe_id,agence_id,montant,date_acompte,motif,saisie_par) VALUES (?,?,?,?,?,?)")
            ->execute([$emp_id, $ag_id, $montant, $date, $motif, $_SESSION['user_id']]);
        logAction($pdo,'create_acompte','acomptes',"Emp $emp_id — ".money($montant));
        flash('Acompte enregistré.');
    } else { flash('Employé et montant obligatoires.','danger'); }
    redirect(BASE_URL.'modules/acomptes/');
}

$aid = getUserAgenceId();
$wA  = $aid ? "AND (a.agence_id=$aid OR ac.agence_id=$aid)" : "";
$acomptes = $pdo->query("SELECT ac.*,CONCAT(p.nom,' ',COALESCE(p.prenom,'')) as employe_nom,p.titre,ag.nom as agence_nom FROM acomptes ac JOIN personnel p ON ac.employe_id=p.id LEFT JOIN agences ag ON ac.agence_id=ag.id LEFT JOIN personnel a ON ac.employe_id=a.id ORDER BY ac.date_acompte DESC")->fetchAll();
$personnels = $pdo->query("SELECT id,CONCAT(nom,' ',COALESCE(prenom,'')) as nom,titre FROM personnel WHERE actif=1 ORDER BY nom")->fetchAll();
$agences    = $pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();

$tot_non_rem = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM acomptes WHERE rembourse=0")->fetchColumn();
$tot_all     = $pdo->query("SELECT COALESCE(SUM(montant),0) FROM acomptes")->fetchColumn();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Acomptes</div>

<div class="grid-2" style="margin-bottom:16px;">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-money-bill-wave"></i></div>
    <div><div class="stat-val" style="font-size:15px;"><?= moneyRaw($tot_non_rem) ?></div><div class="stat-lbl">FCFA non remboursés</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-check-circle"></i></div>
    <div><div class="stat-val" style="font-size:15px;"><?= moneyRaw($tot_all - $tot_non_rem) ?></div><div class="stat-lbl">FCFA remboursés</div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:18px;">
<div class="card">
  <div class="card-header"><h3><i class="fas fa-user-clock"></i> Historique des acomptes (<?= count($acomptes) ?>)</h3></div>
  <div class="card-body" style="padding:0;"><div class="table-wrap">
  <table>
    <thead><tr><th>Employé</th><th>Poste</th><th>Agence</th><th>Date</th><th>Montant</th><th>Motif</th><th>Statut</th><th>Actions</th></tr></thead>
    <tbody>
      <?php foreach($acomptes as $ac): ?>
      <tr>
        <td><strong><?= h($ac['employe_nom']) ?></strong></td>
        <td style="font-size:12px;"><?= h($ac['titre']??'—') ?></td>
        <td style="font-size:12px;"><?= h($ac['agence_nom']??'—') ?></td>
        <td><?= fdate($ac['date_acompte']) ?></td>
        <td style="font-weight:700;color:<?= $ac['rembourse']?'var(--success)':'var(--danger)' ?>;"><?= moneyRaw($ac['montant']) ?></td>
        <td style="font-size:12px;"><?= h($ac['motif']??'—') ?></td>
        <td>
          <?php if($ac['rembourse']): ?>
          <span class="badge b-green">✅ Remboursé<br><small><?= fdate($ac['date_remboursement']) ?></small></span>
          <?php else: ?>
          <span class="badge b-red">⏳ Non remboursé</span>
          <?php endif; ?>
        </td>
        <td>
          <?php if(!$ac['rembourse']): ?>
          <a href="?rembourse=<?= $ac['id'] ?>" class="btn btn-xs btn-success" onclick="return confirm('Marquer comme remboursé ?')"><i class="fas fa-check"></i> Remboursé</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($acomptes)): ?><tr><td colspan="8" class="t-empty"><i class="fas fa-user-clock"></i>Aucun acompte</td></tr><?php endif; ?>
    </tbody>
  </table>
  </div></div>
</div>

<!-- FORM NOUVEAU ACOMPTE -->
<div class="card" style="height:fit-content;">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Nouvel acompte</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="fg" style="margin-bottom:10px;">
        <label class="flbl">Employé <span class="freq">*</span></label>
        <select name="employe_id" class="fc" required>
          <option value="">— Sélectionner —</option>
          <?php foreach($personnels as $p): ?><option value="<?= $p['id'] ?>"><?= h($p['nom']) ?> (<?= h($p['titre']??'—') ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <div class="fg" style="margin-bottom:10px;">
        <label class="flbl">Agence</label>
        <select name="agence_id" class="fc">
          <option value="">— Aucune —</option>
          <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>"><?= h($a['nom']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="fg" style="margin-bottom:10px;">
        <label class="flbl">Montant (FCFA) <span class="freq">*</span></label>
        <input type="number" name="montant" class="fc" required min="1" step="1000">
      </div>
      <div class="fg" style="margin-bottom:10px;">
        <label class="flbl">Date</label>
        <input type="date" name="date_acompte" class="fc" value="<?= date('Y-m-d') ?>">
      </div>
      <div class="fg" style="margin-bottom:14px;">
        <label class="flbl">Motif</label>
        <textarea name="motif" class="fc" rows="2" placeholder="Raison de l'avance..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary btn-block"><i class="fas fa-save"></i> Enregistrer l'acompte</button>
    </form>
  </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>
