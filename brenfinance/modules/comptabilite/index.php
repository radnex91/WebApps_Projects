<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('comptabilite');
$pageTitle = 'Comptabilité & Finance';

$db = getDB();
$userId = $_SESSION['user_id'];
$exercice = (int)($_GET['exercice'] ?? date('Y'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'saisir_ecriture') {
        if (!hasPermission('comptabilite', 'saisir')) { flash('danger','Vous n\'avez pas le droit de saisir des écritures.'); header('Location: index.php'); exit; }
        $journalId = (int)$_POST['journal_id'];
        $date      = $_POST['date_ecriture'];
        $libelle   = trim($_POST['libelle']);
        $numPiece  = trim($_POST['numero_piece'] ?? generateNumero('EC'));
        $periode   = (int)date('n', strtotime($date));
        $lines     = $_POST['lignes'] ?? [];

        // Check debit = credit (equilibre)
        $totalDebit  = 0;
        $totalCredit = 0;
        foreach($lines as $l) {
            $totalDebit  += (float)($l['debit']??0);
            $totalCredit += (float)($l['credit']??0);
        }
        if (abs($totalDebit - $totalCredit) > 0.01) {
            flash('danger', 'Écriture déséquilibrée : débit (' . formatMontant($totalDebit) . ') ≠ crédit (' . formatMontant($totalCredit) . ').');
            header('Location: index.php'); exit;
        }

        foreach($lines as $l) {
            if (empty($l['compte']) || (empty($l['debit']) && empty($l['credit']))) continue;
            $db->prepare("INSERT INTO ecritures_comptables (journal_id,exercice,periode,date_ecriture,numero_piece,libelle,compte,debit,credit,saisi_par,source_type) VALUES (?,?,?,?,?,?,?,?,?,?,'manuel')")
               ->execute([$journalId,$exercice,$periode,$date,$numPiece,$libelle,$l['compte'],(float)($l['debit']??0),(float)($l['credit']??0),$userId]);
        }
        auditLog('saisie_ecriture','comptabilite');
        flash('success','Écriture comptable enregistrée. Pièce N° '.$numPiece);
        header('Location: index.php'); exit;
    }
}

$journaux = $db->query("SELECT * FROM journaux WHERE statut='actif' ORDER BY libelle")->fetchAll();
$activeTab = $_GET['tab'] ?? 'journal';

// Journal entries
$journalId_filter = $_GET['journal'] ?? '';
$whereJ = $journalId_filter ? "AND ec.journal_id=$journalId_filter" : '';
$ecritures = $db->prepare("SELECT ec.*, j.libelle as journal_nom, j.code as journal_code FROM ecritures_comptables ec JOIN journaux j ON ec.journal_id=j.id WHERE ec.exercice=? $whereJ ORDER BY ec.date_ecriture DESC, ec.numero_piece LIMIT 200");
$ecritures->execute([$exercice]);
$ecritures = $ecritures->fetchAll();

// Balance
$balance = $db->prepare("SELECT compte, SUM(debit) as total_debit, SUM(credit) as total_credit, SUM(debit)-SUM(credit) as solde FROM ecritures_comptables WHERE exercice=? GROUP BY compte ORDER BY compte");
$balance->execute([$exercice]);
$balance = $balance->fetchAll();

// Plan comptable for autocomplete
$comptes = $db->query("SELECT compte, libelle FROM plan_comptable WHERE statut='actif' ORDER BY compte")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>Comptabilité &amp; Finance</h1>
    <p>Journaux, grand livre et balance — Exercice <?= $exercice ?></p>
  </div>
  <div class="btn-group">
    <?php for($y=date('Y');$y>=date('Y')-3;$y--): ?>
    <a href="?exercice=<?= $y ?>" class="btn btn-sm <?= $exercice==$y?'btn-primary':'btn-outline' ?>"><?= $y ?></a>
    <?php endfor; ?>
    <button class="btn btn-accent" onclick="openModal('modal-ecriture')">+ Saisir écriture</button>
  </div>
</div>

<div class="tab-wrapper">
  <div class="tabs">
    <button class="tab <?= $activeTab==='journal'?'active':'' ?>" data-tab="tab-journal">Journal</button>
    <button class="tab <?= $activeTab==='balance'?'active':'' ?>" data-tab="tab-balance">Balance</button>
    <button class="tab <?= $activeTab==='grandlivre'?'active':'' ?>" data-tab="tab-gl">Grand livre</button>
  </div>

  <!-- JOURNAL -->
  <div class="tab-content <?= $activeTab==='journal'?'active':'' ?>" id="tab-journal">
    <div class="d-flex gap-8 mb-16" style="flex-wrap:wrap">
      <a href="?tab=journal&exercice=<?= $exercice ?>" class="btn btn-sm <?= !$journalId_filter?'btn-primary':'btn-outline' ?>">Tous les journaux</a>
      <?php foreach($journaux as $j): ?>
      <a href="?tab=journal&journal=<?= $j['id'] ?>&exercice=<?= $exercice ?>" class="btn btn-sm <?= $journalId_filter==$j['id']?'btn-primary':'btn-outline' ?>"><?= sanitize($j['libelle']) ?></a>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="card-header">
        <span class="card-title"><?= count($ecritures) ?> écriture(s)</span>
        <input type="text" id="search-ec" class="form-control" placeholder="Rechercher..." style="width:200px;margin-left:auto">
      </div>
      <div class="table-wrap">
        <table id="tbl-ec">
          <thead>
            <tr><th>Date</th><th>Pièce</th><th>Journal</th><th>Libellé</th><th>Compte</th><th>Débit</th><th>Crédit</th></tr>
          </thead>
          <tbody>
            <?php if(empty($ecritures)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:32px">Aucune écriture comptable pour l'exercice <?= $exercice ?></td></tr>
            <?php else: foreach($ecritures as $e): ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($e['date_ecriture'])) ?></td>
              <td><code><?= sanitize($e['numero_piece']) ?></code></td>
              <td><span class="badge badge-info" style="font-size:11px"><?= sanitize($e['journal_code']) ?></span></td>
              <td><?= sanitize($e['libelle']) ?></td>
              <td><code style="font-weight:700"><?= sanitize($e['compte']) ?></code></td>
              <td class="amount amount-debit"><?= $e['debit']>0 ? number_format($e['debit'],0,',',' ') : '' ?></td>
              <td class="amount amount-credit"><?= $e['credit']>0 ? number_format($e['credit'],0,',',' ') : '' ?></td>
            </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- BALANCE -->
  <div class="tab-content <?= $activeTab==='balance'?'active':'' ?>" id="tab-balance">
    <div class="card">
      <div class="card-header"><span class="card-title">Balance générale — Exercice <?= $exercice ?></span></div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Compte</th><th>Libellé</th>
              <th>Total débit</th><th>Total crédit</th>
              <th>Solde débiteur</th><th>Solde créditeur</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $totD=0;$totC=0;$totSD=0;$totSC=0;
            $comptesMap = array_column($comptes,null,'compte');
            if(empty($balance)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:32px">Aucune écriture comptabilisée</td></tr>
            <?php else: foreach($balance as $b):
              $libelle = $comptesMap[$b['compte']]['libelle'] ?? '—';
              $sd = $b['solde'] > 0 ? $b['solde'] : 0;
              $sc = $b['solde'] < 0 ? abs($b['solde']) : 0;
              $totD+=$b['total_debit'];$totC+=$b['total_credit'];$totSD+=$sd;$totSC+=$sc;
            ?>
            <tr>
              <td><code style="font-weight:700"><?= sanitize($b['compte']) ?></code></td>
              <td><?= sanitize($libelle) ?></td>
              <td class="amount"><?= number_format($b['total_debit'],0,',',' ') ?></td>
              <td class="amount"><?= number_format($b['total_credit'],0,',',' ') ?></td>
              <td class="amount amount-debit"><?= $sd>0?number_format($sd,0,',',' '):'' ?></td>
              <td class="amount amount-credit"><?= $sc>0?number_format($sc,0,',',' '):'' ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:var(--surface2);font-weight:700;border-top:2px solid var(--border)">
              <td colspan="2">TOTAUX</td>
              <td class="amount"><?= number_format($totD,0,',',' ') ?></td>
              <td class="amount"><?= number_format($totC,0,',',' ') ?></td>
              <td class="amount"><?= number_format($totSD,0,',',' ') ?></td>
              <td class="amount"><?= number_format($totSC,0,',',' ') ?></td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- GRAND LIVRE -->
  <div class="tab-content <?= $activeTab==='grandlivre'?'active':'' ?>" id="tab-gl">
    <?php
    $compteGL = $_GET['compte_gl'] ?? '';
    $glData = [];
    if ($compteGL) {
        $glQ = $db->prepare("SELECT ec.*, j.code as jcode FROM ecritures_comptables ec JOIN journaux j ON ec.journal_id=j.id WHERE ec.compte=? AND ec.exercice=? ORDER BY ec.date_ecriture,ec.id");
        $glQ->execute([$compteGL,$exercice]);
        $glData = $glQ->fetchAll();
    }
    ?>
    <div class="d-flex gap-12 mb-16 align-center">
      <form method="get" class="d-flex gap-8 align-center">
        <input type="hidden" name="tab" value="grandlivre">
        <input type="hidden" name="exercice" value="<?= $exercice ?>">
        <label class="form-label mb-0">Compte :</label>
        <select name="compte_gl" class="form-control" style="width:300px" onchange="this.form.submit()">
          <option value="">-- Sélectionner un compte --</option>
          <?php foreach($comptes as $c): ?>
          <option value="<?= $c['compte'] ?>" <?= $compteGL===$c['compte']?'selected':'' ?>><?= sanitize($c['compte'].' — '.$c['libelle']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>
    <?php if ($compteGL && !empty($glData)): ?>
    <div class="card">
      <div class="card-header">
        <span class="card-title">Grand livre — Compte <?= sanitize($compteGL) ?></span>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Date</th><th>Journal</th><th>Pièce</th><th>Libellé</th><th>Débit</th><th>Crédit</th><th>Solde cumulé</th></tr></thead>
          <tbody>
            <?php $solde=0; foreach($glData as $g):
              $solde += $g['debit'] - $g['credit'];
            ?>
            <tr>
              <td><?= date('d/m/Y', strtotime($g['date_ecriture'])) ?></td>
              <td><span class="badge badge-info" style="font-size:11px"><?= sanitize($g['jcode']) ?></span></td>
              <td><code><?= sanitize($g['numero_piece']) ?></code></td>
              <td><?= sanitize($g['libelle']) ?></td>
              <td class="amount amount-debit"><?= $g['debit']>0?number_format($g['debit'],0,',',' '):'' ?></td>
              <td class="amount amount-credit"><?= $g['credit']>0?number_format($g['credit'],0,',',' '):'' ?></td>
              <td class="amount <?= $solde>=0?'amount-debit':'amount-credit' ?>"><?= number_format(abs($solde),0,',',' ') ?> <?= $solde>=0?'D':'C' ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php elseif($compteGL): ?>
    <div class="card"><div class="empty-state" style="padding:48px"><p>Aucune écriture sur ce compte pour l'exercice <?= $exercice ?>.</p></div></div>
    <?php else: ?>
    <div class="card"><div class="empty-state" style="padding:48px"><div class="empty-icon"><i class="fa-solid fa-book-open"></i></div><p>Sélectionnez un compte pour afficher son grand livre.</p></div></div>
    <?php endif; ?>
  </div>
</div>

<!-- Modal: Saisie écriture comptable -->
<div class="modal-overlay" id="modal-ecriture">
  <div class="modal" style="max-width:800px">
    <div class="modal-header">
      <div class="modal-title">Saisir une écriture comptable</div>
      <button class="modal-close" onclick="closeModal('modal-ecriture')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="saisir_ecriture">
      <div class="modal-body">
        <div class="form-row-3">
          <div class="form-group">
            <label class="form-label">Journal <span class="req">*</span></label>
            <select name="journal_id" class="form-control" required>
              <?php foreach($journaux as $j): ?>
              <option value="<?= $j['id'] ?>"><?= sanitize($j['libelle']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Date <span class="req">*</span></label>
            <input type="date" name="date_ecriture" class="form-control" value="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">N° Pièce</label>
            <input type="text" name="numero_piece" class="form-control" placeholder="Auto-généré si vide">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé de l'opération <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" required>
        </div>

        <div style="background:var(--surface2);border-radius:var(--radius);padding:16px;margin-top:8px">
          <div style="display:grid;grid-template-columns:200px 1fr 120px 120px 30px;gap:8px;margin-bottom:8px;font-size:12px;font-weight:600;color:var(--text3)">
            <span>Compte</span><span>Libellé de ligne</span><span>Débit</span><span>Crédit</span><span></span>
          </div>
          <div id="lignes-container">
            <?php for($i=0;$i<4;$i++): ?>
            <div class="ligne-row" style="display:grid;grid-template-columns:200px 1fr 120px 120px 30px;gap:8px;margin-bottom:8px">
              <input type="text" name="lignes[<?= $i ?>][compte]" class="form-control" placeholder="6xxxxx" list="comptes-list">
              <input type="text" name="lignes[<?= $i ?>][libelle_ligne]" class="form-control" placeholder="Détail...">
              <input type="number" name="lignes[<?= $i ?>][debit]"  class="form-control" placeholder="0" step="1" min="0" oninput="recalcEq()">
              <input type="number" name="lignes[<?= $i ?>][credit]" class="form-control" placeholder="0" step="1" min="0" oninput="recalcEq()">
              <button type="button" onclick="removeLigne(this)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px;padding:4px"><i class="fa-solid fa-trash-can"></i></button>
            </div>
            <?php endfor; ?>
          </div>
          <datalist id="comptes-list">
            <?php foreach($comptes as $c): ?>
            <option value="<?= $c['compte'] ?>"><?= sanitize($c['libelle']) ?></option>
            <?php endforeach; ?>
          </datalist>
          <div class="d-flex justify-between align-center" style="margin-top:8px">
            <button type="button" class="btn btn-outline btn-sm" onclick="addLigne()">+ Ajouter une ligne</button>
            <div style="font-size:13px;color:var(--text2)">
              Total débit : <strong id="tot-d" class="amount">0</strong> &nbsp;|&nbsp;
              Total crédit : <strong id="tot-c" class="amount">0</strong> &nbsp;|&nbsp;
              Écart : <strong id="ecart" class="amount" style="color:var(--danger)">0</strong>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-ecriture')">Annuler</button>
        <button type="submit" class="btn btn-primary">Valider l'écriture</button>
      </div>
    </form>
  </div>
</div>

<script>
tableSearch('search-ec','tbl-ec');

let ligneCount = 4;
function addLigne() {
  const i = ligneCount++;
  const div = document.createElement('div');
  div.className = 'ligne-row';
  div.style.cssText = 'display:grid;grid-template-columns:200px 1fr 120px 120px 30px;gap:8px;margin-bottom:8px';
  div.innerHTML = `
    <input type="text" name="lignes[${i}][compte]" class="form-control" placeholder="6xxxxx" list="comptes-list">
    <input type="text" name="lignes[${i}][libelle_ligne]" class="form-control" placeholder="Détail...">
    <input type="number" name="lignes[${i}][debit]"  class="form-control" placeholder="0" step="1" min="0" oninput="recalcEq()">
    <input type="number" name="lignes[${i}][credit]" class="form-control" placeholder="0" step="1" min="0" oninput="recalcEq()">
    <button type="button" onclick="removeLigne(this)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:18px;padding:4px"><i class="fa-solid fa-trash-can"></i></button>`;
  document.getElementById('lignes-container').appendChild(div);
}
function removeLigne(btn) {
  const rows = document.querySelectorAll('.ligne-row');
  if(rows.length > 2) { btn.parentElement.remove(); recalcEq(); }
}
function recalcEq() {
  let d=0,c=0;
  document.querySelectorAll('[name*="[debit]"]').forEach(i=>d+=parseFloat(i.value||0));
  document.querySelectorAll('[name*="[credit]"]').forEach(i=>c+=parseFloat(i.value||0));
  const fmt = v => new Intl.NumberFormat('fr-CM').format(Math.round(v));
  document.getElementById('tot-d').textContent = fmt(d);
  document.getElementById('tot-c').textContent = fmt(c);
  const ecart = Math.abs(d-c);
  const el = document.getElementById('ecart');
  el.textContent = fmt(ecart);
  el.style.color = ecart < 0.01 ? 'var(--success)' : 'var(--danger)';
}

// Grand livre tab URL sync
const urlTabComp = new URLSearchParams(location.search).get('tab');
if(urlTabComp){
  const tabMap = {journal:'tab-journal',balance:'tab-balance',grandlivre:'tab-gl'};
  document.querySelectorAll('.tabs .tab').forEach(t=>{
    t.classList.toggle('active', t.dataset.tab===tabMap[urlTabComp]);
  });
  document.querySelectorAll('.tab-content').forEach(c=>{
    c.classList.toggle('active', c.id===tabMap[urlTabComp]);
  });
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
