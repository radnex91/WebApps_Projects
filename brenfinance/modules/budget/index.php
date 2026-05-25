<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('budget');
$pageTitle = 'Gestion Budgétaire';

$db = getDB();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_budget') {
        if (!hasPermission('budget', 'creer')) { flash('danger','Vous n\'avez pas le droit de créer un budget.'); header('Location: index.php'); exit; }
        $db->prepare("INSERT INTO budgets (agence_id,service_id,exercice,libelle,type) VALUES (?,?,?,?,?)")
           ->execute([currentUser()['agence_id']??1,$_POST['service_id']??null,$_POST['exercice'],$_POST['libelle'],$_POST['type']??'previsionnel']);
        flash('success','Budget créé.');
        header('Location: index.php'); exit;
    }
    if ($action === 'add_ligne') {
        if (!hasPermission('budget', 'creer')) { flash('danger','Vous n\'avez pas le droit de modifier ce budget.'); header('Location: index.php?budget='.$_POST['budget_id']); exit; }
        $db->prepare("INSERT INTO lignes_budget (budget_id,compte_comptable,libelle,montant_prevu,seuil_alerte) VALUES (?,?,?,?,?)")
           ->execute([$_POST['budget_id'],$_POST['compte']??null,$_POST['libelle'],(float)$_POST['montant'],(float)($_POST['seuil']??80)]);
        flash('success','Ligne budgétaire ajoutée.');
        header('Location: index.php?budget='.$_POST['budget_id']); exit;
    }
}

$budgets = $db->query("SELECT b.*, COUNT(lb.id) as nb_lignes, SUM(lb.montant_prevu) as total_prevu, SUM(lb.montant_realise) as total_realise FROM budgets b LEFT JOIN lignes_budget lb ON lb.budget_id=b.id GROUP BY b.id ORDER BY b.exercice DESC, b.id DESC")->fetchAll();

$selectedBudget = null;
$lignes = [];
if (!empty($_GET['budget'])) {
    $bR = $db->prepare("SELECT * FROM budgets WHERE id=?");
    $bR->execute([(int)$_GET['budget']]);
    $selectedBudget = $bR->fetch();
    if ($selectedBudget) {
        $lR = $db->prepare("SELECT * FROM lignes_budget WHERE budget_id=? ORDER BY libelle");
        $lR->execute([$selectedBudget['id']]);
        $lignes = $lR->fetchAll();
    }
}

$services = $db->query("SELECT * FROM services WHERE statut='actif'")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div><h1>Gestion Budgétaire</h1><p>Définition, suivi et contrôle des budgets</p></div>
  <button class="btn btn-primary" onclick="openModal('modal-new-budget')">+ Nouveau budget</button>
</div>

<div class="budget-layout" style="display:grid;grid-template-columns:320px 1fr;gap:16px;align-items:start">
  <!-- Budgets list -->
  <div class="card">
    <div class="card-header"><span class="card-title">Budgets</span></div>
    <div style="padding:8px">
      <?php if (empty($budgets)): ?>
      <div class="empty-state" style="padding:24px"><p>Aucun budget défini</p></div>
      <?php else: foreach($budgets as $b):
        $pct = $b['total_prevu'] > 0 ? round(($b['total_realise']/$b['total_prevu'])*100) : 0;
        $statusBadge = ['brouillon'=>'badge-gray','valide'=>'badge-success','cloture'=>'badge-danger'];
      ?>
      <a href="?budget=<?= $b['id'] ?>" style="display:block;padding:12px;border-radius:6px;text-decoration:none;margin-bottom:4px;border:1px solid <?= isset($_GET['budget']) && $_GET['budget']==$b['id'] ? 'var(--primary)' : 'var(--border)' ?>;background:<?= isset($_GET['budget']) && $_GET['budget']==$b['id'] ? '#f0f5ff' : 'var(--surface)' ?>">
        <div class="d-flex justify-between align-center">
          <span style="font-weight:600;font-size:13.5px;color:var(--text)"><?= sanitize($b['libelle']) ?></span>
          <span class="badge <?= $statusBadge[$b['statut']]??'badge-gray' ?>"><?= ucfirst($b['statut']) ?></span>
        </div>
        <div style="font-size:12px;color:var(--text3);margin-top:3px">Exercice <?= $b['exercice'] ?> · <?= $b['nb_lignes'] ?> ligne(s)</div>
        <div class="progress" style="margin-top:6px">
          <div class="progress-bar <?= $pct>=100?'danger':($pct>=80?'warning':'') ?>" style="width:<?= min($pct,100) ?>%"></div>
        </div>
        <div style="font-size:11px;color:var(--text3);margin-top:3px"><?= $pct ?>% consommé</div>
      </a>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Budget detail -->
  <div>
    <?php if ($selectedBudget): ?>
    <div class="card mb-16">
      <div class="card-header">
        <div>
          <div class="card-title"><?= sanitize($selectedBudget['libelle']) ?></div>
          <div style="font-size:12px;color:var(--text3)">Exercice <?= $selectedBudget['exercice'] ?> · <?= ucfirst($selectedBudget['type']) ?></div>
        </div>
        <button class="btn btn-accent btn-sm" style="margin-left:auto" onclick="openModal('modal-add-ligne')">+ Ligne budgétaire</button>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Libellé</th><th>Compte</th><th>Prévu</th><th>Réalisé</th><th>Écart</th><th>%</th><th>Alerte</th></tr>
          </thead>
          <tbody>
            <?php if (empty($lignes)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Aucune ligne. Ajoutez des lignes budgétaires.</td></tr>
            <?php else:
              $totPrevu = 0; $totRealise = 0;
              foreach($lignes as $l):
                $pct = $l['montant_prevu'] > 0 ? round(($l['montant_realise']/$l['montant_prevu'])*100) : 0;
                $ecart = $l['montant_prevu'] - $l['montant_realise'];
                $alert = $pct >= 100 ? 'danger' : ($pct >= $l['seuil_alerte'] ? 'warning' : 'success');
                $totPrevu += $l['montant_prevu']; $totRealise += $l['montant_realise'];
            ?>
            <tr>
              <td><?= sanitize($l['libelle']) ?></td>
              <td><?= sanitize($l['compte_comptable']??'—') ?></td>
              <td class="amount"><?= number_format($l['montant_prevu'],0,',',' ') ?></td>
              <td class="amount"><?= number_format($l['montant_realise'],0,',',' ') ?></td>
              <td class="amount <?= $ecart < 0 ? 'amount-debit' : 'amount-credit' ?>"><?= number_format(abs($ecart),0,',',' ') ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:6px">
                  <div class="progress" style="width:60px"><div class="progress-bar <?= $alert==='danger'?'danger':($alert==='warning'?'warning':'') ?>" style="width:<?= min($pct,100) ?>%"></div></div>
                  <span style="font-size:12px"><?= $pct ?>%</span>
                </div>
              </td>
              <td><span class="badge badge-<?= $alert ?>"><?= $pct >= $l['seuil_alerte'] ? '<i class="fa-solid fa-triangle-exclamation"></i> Alerte' : 'OK' ?></span></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:var(--surface2);font-weight:700">
              <td colspan="2">TOTAL</td>
              <td class="amount"><?= number_format($totPrevu,0,',',' ') ?></td>
              <td class="amount"><?= number_format($totRealise,0,',',' ') ?></td>
              <td class="amount"><?= number_format($totPrevu-$totRealise,0,',',' ') ?></td>
              <td colspan="2"></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="empty-state" style="padding:60px"><div class="empty-icon"><i class="fa-solid fa-gem"></i></div><p>Sélectionnez un budget dans la liste pour voir le détail.</p></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: Nouveau budget -->
<div class="modal-overlay" id="modal-new-budget">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Créer un budget</div>
      <button class="modal-close" onclick="closeModal('modal-new-budget')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="create_budget">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" placeholder="Budget de fonctionnement 2025" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Exercice <span class="req">*</span></label>
            <input type="number" name="exercice" class="form-control" value="<?= date('Y') ?>" min="2000" max="2100" required>
          </div>
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-control">
              <option value="previsionnel">Prévisionnel</option>
              <option value="revise">Révisé</option>
              <option value="supplementaire">Supplémentaire</option>
            </select>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Service concerné</label>
          <select name="service_id" class="form-control">
            <option value="">Tous les services</option>
            <?php foreach($services as $s): ?>
            <option value="<?= $s['id'] ?>"><?= sanitize($s['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-budget')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Ligne budgétaire -->
<div class="modal-overlay" id="modal-add-ligne">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Ajouter une ligne budgétaire</div>
      <button class="modal-close" onclick="closeModal('modal-add-ligne')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_ligne">
      <input type="hidden" name="budget_id" value="<?= $selectedBudget['id']??'' ?>">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Montant prévu (FCFA) <span class="req">*</span></label>
            <input type="number" name="montant" class="form-control" step="1" min="0" required>
          </div>
          <div class="form-group">
            <label class="form-label">Seuil d'alerte (%)</label>
            <input type="number" name="seuil" class="form-control" value="80" min="0" max="100">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Compte comptable</label>
          <input type="text" name="compte" class="form-control" placeholder="6xxxxx">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-add-ligne')">Annuler</button>
        <button type="submit" class="btn btn-primary">Ajouter</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
