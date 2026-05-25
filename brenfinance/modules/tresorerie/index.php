<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('tresorerie');
$pageTitle = 'Gestion de Trésorerie';

$db = getDB();
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_compte') {
        if (!hasPermission('tresorerie', 'saisir')) { flash('danger','Vous n\'avez pas le droit de créer des comptes bancaires.'); header('Location: index.php'); exit; }
        $db->prepare("INSERT INTO comptes_bancaires (agence_id,banque,agence_banque,numero_compte,rib,libelle,devise,solde_initial,solde_actuel) VALUES (?,?,?,?,?,?,?,?,?)")
           ->execute([currentUser()['agence_id']??1,$_POST['banque'],$_POST['agence_banque']??'',$_POST['numero_compte'],$_POST['rib']??'',$_POST['libelle'],$_POST['devise']??'FCFA',(float)$_POST['solde_initial'],(float)$_POST['solde_initial']]);
        flash('success','Compte bancaire créé.');
        header('Location: index.php'); exit;
    }

    if ($action === 'add_operation') {
        if (!hasPermission('tresorerie', 'saisir')) { flash('danger','Vous n\'avez pas le droit de saisir des opérations bancaires.'); header('Location: index.php'); exit; }
        $compteId = (int)$_POST['compte_id'];
        $montant  = abs((float)$_POST['montant']);
        $sens     = $_POST['sens'];
        $dateOp   = $_POST['date_operation'];
        $libelle  = trim($_POST['libelle']);
        $ref      = trim($_POST['reference']??'');
        $dateVal  = $_POST['date_valeur']??null;

        $compteR = $db->prepare("SELECT solde_actuel FROM comptes_bancaires WHERE id=?");
        $compteR->execute([$compteId]);
        $compte = $compteR->fetch();
        $soldeCourant = $compte['solde_actuel'];
        $soldeApres = $sens === 'credit' ? $soldeCourant + $montant : $soldeCourant - $montant;

        $db->prepare("INSERT INTO operations_bancaires (compte_id,date_operation,date_valeur,libelle,reference,montant,sens,solde_apres,saisi_par,cree_par,statut) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
           ->execute([$compteId,$dateOp,$dateVal??null,$libelle,$ref,$montant,$sens,$soldeApres,$userId,$userId,"valide"]);
        $db->prepare("UPDATE comptes_bancaires SET solde_actuel=? WHERE id=?")->execute([$soldeApres,$compteId]);
        auditLog('saisie_operation_bancaire','tresorerie');
        flash('success','Opération bancaire enregistrée.');
        header('Location: index.php'); exit;
    }
}

$comptes = $db->query("SELECT * FROM comptes_bancaires WHERE statut='actif' ORDER BY libelle")->fetchAll();
$totalBanque = array_sum(array_column($comptes,'solde_actuel'));

$selectedCompte = null;
$ops = [];
if (!empty($_GET['compte'])) {
    $cR = $db->prepare("SELECT * FROM comptes_bancaires WHERE id=?");
    $cR->execute([(int)$_GET['compte']]);
    $selectedCompte = $cR->fetch();
    if ($selectedCompte) {
        $opsR = $db->prepare("SELECT * FROM operations_bancaires WHERE compte_id=? ORDER BY date_operation DESC, created_at DESC LIMIT 50");
        $opsR->execute([$selectedCompte['id']]);
        $ops = $opsR->fetchAll();
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div><h1>Gestion de Trésorerie</h1><p>Comptes bancaires, flux et rapprochement</p></div>
  <div class="btn-group">
    <button class="btn btn-primary" onclick="openModal('modal-new-compte')">+ Nouveau compte</button>
    <?php if ($selectedCompte): ?>
    <button class="btn btn-accent" onclick="openModal('modal-operation-bnq')">+ Opération</button>
    <?php endif; ?>
  </div>
</div>

<!-- Summary -->
<div class="stats-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));margin-bottom:20px">
  <div class="stat-card info">
    <div class="stat-label">Solde bancaire global</div>
    <div class="stat-value" style="font-size:18px"><?= formatMontant($totalBanque) ?></div>
    <div class="stat-sub"><?= count($comptes) ?> compte(s) actif(s)</div>
  </div>
  <?php foreach($comptes as $c): ?>
  <div class="stat-card">
    <div class="stat-label"><?= sanitize($c['banque']) ?></div>
    <div class="stat-value" style="font-size:16px"><?= formatMontant($c['solde_actuel']) ?></div>
    <div class="stat-sub"><?= sanitize($c['libelle']) ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Comptes list -->
<div class="card mb-20">
  <div class="card-header"><span class="card-title">Comptes bancaires</span></div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Libellé</th><th>Banque</th><th>N° Compte</th><th>Devise</th><th>Solde actuel</th><th class="hide-mobile">Dernier relevé</th><th>Actions</th></tr>
      </thead>
      <tbody>
        <?php if (empty($comptes)): ?>
        <tr><td colspan="7" class="text-center text-muted" style="padding:24px">Aucun compte bancaire</td></tr>
        <?php else: foreach($comptes as $c): ?>
        <tr>
          <td><?= sanitize($c['libelle']) ?></td>
          <td><?= sanitize($c['banque']) ?></td>
          <td><code><?= sanitize($c['numero_compte']) ?></code></td>
          <td><?= sanitize($c['devise']) ?></td>
          <td class="amount fw-bold <?= $c['solde_actuel'] < 0 ? 'amount-debit' : '' ?>"><?= formatMontant($c['solde_actuel']) ?></td>
          <td><?= $c['date_derniere_releve'] ? date('d/m/Y', strtotime($c['date_derniere_releve'])) : '—' ?></td>
          <td><a href="?compte=<?= $c['id'] ?>" class="btn btn-outline btn-sm">Voir les flux</a></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Operations for selected compte -->
<?php if ($selectedCompte): ?>
<div class="card">
  <div class="card-header">
    <div>
      <div class="card-title">Flux — <?= sanitize($selectedCompte['libelle']) ?> (<?= sanitize($selectedCompte['banque']) ?>)</div>
      <div style="font-size:12px;color:var(--text3)">Solde: <strong><?= formatMontant($selectedCompte['solde_actuel']) ?></strong></div>
    </div>
    <button class="btn btn-primary btn-sm" style="margin-left:auto" onclick="openModal('modal-operation-bnq')">+ Opération</button>
  </div>
  <div class="table-wrap">
    <table>
      <thead>
        <tr><th>Date op.</th><th>Date valeur</th><th>Libellé</th><th>Référence</th><th>Débit</th><th>Crédit</th><th>Solde</th><th>Statut</th><th>Rappr.</th></tr>
      </thead>
      <tbody>
        <?php if (empty($ops)): ?>
        <tr><td colspan="9" class="text-center text-muted" style="padding:24px">Aucune opération</td></tr>
        <?php else: foreach($ops as $op): ?>
        <tr>
          <td><?= date('d/m/Y', strtotime($op['date_operation'])) ?></td>
          <td><?= $op['date_valeur'] ? date('d/m/Y', strtotime($op['date_valeur'])) : '—' ?></td>
          <td><?= sanitize($op['libelle']) ?></td>
          <td><?= sanitize($op['reference']??'—') ?></td>
          <td class="amount amount-debit"><?= $op['sens']==='debit' ? number_format($op['montant'],0,',',' ') : '' ?></td>
          <td class="amount amount-credit"><?= $op['sens']==='credit' ? number_format($op['montant'],0,',',' ') : '' ?></td>
          <td class="amount"><?= number_format($op['solde_apres'],0,',',' ') ?></td>
          <td><?php
            $sB = ['brouillon'=>'gray','soumis'=>'info','valide_comptable'=>'purple','valide_daf'=>'teal','valide'=>'success','rejete'=>'danger'];
            $statut = $op['statut'] ?? 'valide';
            echo '<span class="badge badge-' . ($sB[$statut] ?? 'gray') . '">' . htmlspecialchars(str_replace('_',' ',ucfirst($statut))) . '</span>';
          ?></td>
          <td><?= $op['rapproche'] ? '<span class="badge badge-success"><i class="fa-solid fa-check"></i></span>' : '<span class="badge badge-gray">—</span>' ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Modal: Nouveau compte -->
<div class="modal-overlay" id="modal-new-compte">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <div class="modal-title">Nouveau compte bancaire</div>
      <button class="modal-close" onclick="closeModal('modal-new-compte')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_compte">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Banque <span class="req">*</span></label>
            <input type="text" name="banque" class="form-control" placeholder="Afriland First Bank" required>
          </div>
          <div class="form-group">
            <label class="form-label">Agence bancaire</label>
            <input type="text" name="agence_banque" class="form-control" placeholder="Agence centrale">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé du compte <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Numéro de compte <span class="req">*</span></label>
            <input type="text" name="numero_compte" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">RIB</label>
            <input type="text" name="rib" class="form-control">
          </div>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Devise</label>
            <input type="text" name="devise" class="form-control" value="FCFA">
          </div>
          <div class="form-group">
            <label class="form-label">Solde initial</label>
            <input type="number" name="solde_initial" class="form-control" value="0" step="1">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-compte')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer le compte</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Opération bancaire -->
<div class="modal-overlay" id="modal-operation-bnq">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Saisir une opération bancaire</div>
      <button class="modal-close" onclick="closeModal('modal-operation-bnq')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="add_operation">
      <input type="hidden" name="compte_id" value="<?= $selectedCompte['id']??'' ?>">
      <div class="modal-body">
        <div class="form-row-2">
          <?php if (!$selectedCompte): ?>
          <div class="form-group" style="grid-column:1/-1">
            <label class="form-label">Compte <span class="req">*</span></label>
            <select name="compte_id" class="form-control" required>
              <?php foreach($comptes as $c): ?>
              <option value="<?= $c['id'] ?>"><?= sanitize($c['libelle']) ?> (<?= sanitize($c['banque']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
          <div class="form-group">
            <label class="form-label">Sens <span class="req">*</span></label>
            <select name="sens" class="form-control" required>
              <option value="credit">Crédit (encaissement)</option>
              <option value="debit">Débit (décaissement)</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date opération <span class="req">*</span></label>
            <input type="date" name="date_operation" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" required>
        </div>
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Montant <span class="req">*</span></label>
            <input type="number" name="montant" class="form-control" step="1" min="1" required>
          </div>
          <div class="form-group">
            <label class="form-label">Référence</label>
            <input type="text" name="reference" class="form-control" placeholder="N° virement, chèque...">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Date de valeur</label>
          <input type="date" name="date_valeur" class="form-control">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-operation-bnq')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
