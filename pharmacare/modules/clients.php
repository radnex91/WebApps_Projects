<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/pagination.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../config/comptabilite.php';
requirePermission('clients.voir');
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── POST : ajout / modification client ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'], true)) {
    $permNeeded = ($action === 'add') ? 'clients.ajouter' : 'clients.modifier';
    requirePermission($permNeeded);
    verifyCsrf();
    $nom       = mb_strtoupper(trim($_POST['nom'] ?? ''), 'UTF-8');
    $telephone = trim($_POST['telephone'] ?? '');

    if ($nom === '') {
        flash('Le nom du client est requis.', 'error');
        $redirect = ($action === 'edit' && $id) ? '?action=edit&id='.$id : '?action=add';
        header('Location: ' . url('clients') . $redirect); exit;
    }

    try {
        if ($action === 'edit' && $id) {
            $db->prepare("UPDATE clients SET nom=?, telephone=? WHERE id=?")
               ->execute([$nom, $telephone, $id]);
            flash('Client mis à jour.', 'success');
        } else {
            $db->prepare("INSERT INTO clients (nom, telephone) VALUES (?, ?)")
               ->execute([$nom, $telephone]);
            flash('Client créé.', 'success');
        }
        header('Location: ' . url('clients')); exit;
    } catch (Exception $e) {
        flashError($e, 'enregistrement client');
        $redirect = ($action === 'edit' && $id) ? '?action=edit&id='.$id : '?action=add';
        header('Location: ' . url('clients') . $redirect); exit;
    }
}

// ── POST : enregistrement d'un règlement ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'reglement') {
    requirePermission('clients.paiements');
    verifyCsrf();
    $clientId = (int)($_POST['client_id'] ?? 0);
    $montant  = (float)($_POST['montant'] ?? 0);
    $mode     = $_POST['mode'] ?? 'espèces';
    $note     = trim($_POST['note'] ?? '');
    $venteId  = !empty($_POST['vente_id']) ? (int)$_POST['vente_id'] : null;

    if ($clientId <= 0 || $montant <= 0) {
        flash('Client et montant requis.', 'error');
        header('Location: ' . url('clients', ['action'=>'detail','id'=>$clientId])); exit;
    }

    try {
        $db->beginTransaction();

        // 1. Enregistrer le règlement
        $db->prepare("INSERT INTO reglements (client_id, vente_id, montant, mode_paiement, note) VALUES (?,?,?,?,?)")
           ->execute([$clientId, $venteId, $montant, $mode, $note]);

        // 1.b. Écriture comptable du règlement (OHADA) :
        //     Débit du compte de trésorerie (selon le moyen) / Crédit 4112 (client).
        //     Solde la créance constatée à la vente à crédit.
        $stmtCli = $db->prepare("SELECT nom FROM clients WHERE id = ?");
        $stmtCli->execute([$clientId]);
        $nomClient = $stmtCli->fetchColumn() ?: ('Client #' . $clientId);

        $mapRegl = [
            'espèces' => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
            'carte'   => compteFindOrCreate($db, '512',  'Banque', 5, 'debit'),
            'chèque'  => compteFindOrCreate($db, '511',  'Chèques à encaisser', 5, 'debit'),
            'mobile'  => compteFindOrCreate($db, '512',  'Banque', 5, 'debit'),
        ];
        $compteEncaiss = $mapRegl[$mode] ?? $mapRegl['espèces'];
        $compteClient  = compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit');
        $refRegl = 'REG-' . date('Y') . '-' . str_pad((int)$db->lastInsertId(), 4, '0', STR_PAD_LEFT);
        ecritureCreate($db,
            'Règlement ' . $nomClient . ' (' . $mode . ')',
            date('Y-m-d'),
            [
                [$compteEncaiss, round($montant, 2), 0, 'Règlement ' . $nomClient],
                [$compteClient, 0, round($montant, 2), 'Règlement client ' . $nomClient],
            ],
            'caisse', $refRegl, currentUser()['id']
        );

        // 2. Mise à jour des statuts via FIFO
        // On récupère le total payé par le client
        $stmtTotalPaid = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM reglements WHERE client_id = ?");
        $stmtTotalPaid->execute([$clientId]);
        $totalPaid = (float)$stmtTotalPaid->fetchColumn();

        // On récupère toutes les ventes crédit du client par date croissante
        $stmtVentes = $db->prepare("SELECT id, total FROM ventes WHERE client_id = ? AND (mode_paiement = 'crédit' OR mode_paiement = 'credit' OR statut_paiement = 'en_attente' OR statut_paiement = 'partiel') ORDER BY created_at ASC");
        $stmtVentes->execute([$clientId]);
        $ventes = $stmtVentes->fetchAll();

        $cumulativeTotal = 0;
        foreach ($ventes as $v) {
            $cumulativeTotal += (float)$v['total'];
            $status = 'en_attente';

            if ($totalPaid >= $cumulativeTotal) {
                $status = 'payé';
            } elseif ($totalPaid > ($cumulativeTotal - (float)$v['total'])) {
                $status = 'partiel';
            }

            $db->prepare("UPDATE ventes SET statut_paiement = ? WHERE id = ?")->execute([$status, $v['id']]);
        }

        $db->commit();
        flash('Règlement enregistré et dettes mises à jour.', 'success');
        header('Location: ' . url('clients', ['action'=>'detail','id'=>$clientId], $nomClient ?? null)); exit;
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        flashError($e, 'règlement client');
        header('Location: ' . url('clients', ['action'=>'detail','id'=>$clientId], $nomClient ?? null)); exit;
    }
}

// ── GET : désactiver un client ────────────────────────────────
if ($action === 'disable' && $id && hasPermission('clients.supprimer')) {
    if (($_GET['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) die('Requête invalide (CSRF).');
    $db->prepare("UPDATE clients SET actif=0 WHERE id=?")->execute([$id]);
    flash('Client désactivé.', 'success');
    header('Location: ' . url('clients')); exit;
}

// ── GET : réactiver un client ─────────────────────────────────
if ($action === 'enable' && $id && hasPermission('clients.supprimer')) {
    if (($_GET['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) die('Requête invalide (CSRF).');
    $db->prepare("UPDATE clients SET actif=1 WHERE id=?")->execute([$id]);
    flash('Client réactivé.', 'success');
    header('Location: ' . url('clients')); exit;
}

// ── Rendu ────────────────────────────────────────────────────
$title = 'Clients';
if ($action === 'detail' && $id) $title = 'Détail client';
elseif ($action === 'add') $title = 'Nouveau client';
elseif ($action === 'edit' && $id) $title = 'Modifier client';

layout_head($title, 'clients');
?>

<?php if ($action === 'detail' && $id): ?>
<?php
$client = $db->prepare("SELECT * FROM clients WHERE id=?");
$client->execute([$id]);
$client = $client->fetch();

if (!$client) {
    echo '<div class="alert alert-error">Client introuvable.</div>';
    layout_foot(); exit;
}

// Ventes liees a ce client
$ventesClient = $db->prepare("
    SELECT v.*, u.prenom, u.nom AS u_nom
    FROM ventes v
    LEFT JOIN utilisateurs u ON v.caissier_id = u.id
    WHERE v.client_id = ? AND (v.mode_paiement = 'crédit' OR v.mode_paiement = 'credit' OR v.statut_paiement = 'en_attente' OR v.statut_paiement = 'partiel')
    ORDER BY v.created_at DESC
");
$ventesClient->execute([$id]);
$ventesClient = $ventesClient->fetchAll();

// Reglements
$reglements = $db->prepare("
    SELECT r.*, v.reference AS vente_ref
    FROM reglements r
    LEFT JOIN ventes v ON r.vente_id = v.id
    WHERE r.client_id = ?
    ORDER BY r.date_reglement DESC
");
$reglements->execute([$id]);
$reglements = $reglements->fetchAll();

// Calcul dette
$totalRegle = 0;
foreach ($reglements as $r) {
    $totalRegle += (float)$r['montant'];
}

$totalCreditVentes = 0;
$paidPool = (float)$totalRegle;
$cumulative = 0;
$ventesWithBalance = [];

usort($ventesClient, fn($a, $b) => strtotime($a['created_at']) <=> strtotime($b['created_at']));

foreach ($ventesClient as $v) {
    $total = (float)$v['total'];
    $totalCreditVentes += $total;
    $paidForThis = max(0, min($total, $paidPool - $cumulative));
    $balance = $total - $paidForThis;
    $ventesWithBalance[] = array_merge($v, ['paid_amount' => $paidForThis, 'balance' => $balance]);
    $cumulative += $total;
}
$detteRestante = max(0, $totalCreditVentes - $totalRegle);

if ($detteRestante <= 0 && $totalCreditVentes > 0) {
    $badgeDette = '<span class="badge badge-green">Payé</span>';
} elseif ($detteRestante > 0 && $totalRegle > 0) {
    $badgeDette = '<span class="badge badge-orange">Partiel</span>';
} elseif ($detteRestante > 0) {
    $badgeDette = '<span class="badge badge-red">En attente</span>';
} else {
    $badgeDette = '<span class="badge badge-gray">Aucune dette</span>';
}
?>

<div class="page-header">
  <div style="display:flex;align-items:center;gap:12px;">
    <a href="<?= url('clients') ?>" class="btn btn-ghost" style="padding:4px 8px;"><?= icon('chevron-left',16) ?></a>
    <h1 style="margin:0;"><?= e($client['nom']) ?></h1>
    <?= $badgeDette ?>
  </div>
  <div style="display:flex;gap:8px;">
    <?php if (hasPermission('clients.modifier')): ?>
    <a href="<?= url('clients', ['action'=>'edit','id'=>$client['id']], $client['nom'] ?? null) ?>" class="btn btn-outline"><?= icon('edit',14) ?> Modifier</a>
    <?php endif; ?>
  </div>
</div>

<div class="card-grid">
  <div class="card">
    <div class="card-header">
      <div class="card-title">Informations</div>
    </div>
    <div class="card-pad">
      <table class="info-table">
        <tr><td class="label">Téléphone</td><td><?= e($client['telephone'] ?: '—') ?></td></tr>
        <tr><td class="label">Client depuis</td><td><?= date('d/m/Y', strtotime($client['created_at'])) ?></td></tr>
      </table>
    </div>
  </div>
  <div class="card">
    <div class="card-header">
      <div class="card-title">Dette</div>
    </div>
    <div class="card-pad">
      <div style="font-size:28px;font-weight:700;color:<?= $detteRestante > 0 ? 'var(--red)' : 'var(--teal)' ?>;font-family:var(--font-title);">
        <?= fmtMoney($detteRestante) ?>
      </div>
      <div style="font-size:12px;color:var(--text2);">Total crédit : <?= fmtMoney($totalCreditVentes) ?> · Réglé : <?= fmtMoney($totalRegle) ?></div>
      <?php if ($detteRestante > 0): ?>
      <div style="margin-top:12px;">
        <button class="btn btn-primary btn-sm" onclick="payAll(<?= $detteRestante ?>)">
          <?= icon('money',12) ?> Solder la dette
        </button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (hasPermission('clients.paiements') && $detteRestante > 0): ?>
<div class="card">
  <div class="card-header">
    <div class="card-title">Enregistrer un règlement</div>
  </div>
  <div class="card-pad">
    <form method="POST" action="<?= APP_URL ?>/modules/clients.php?action=reglement" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
    <div class="form-group" style="margin-bottom:0;">
      <label>Montant</label>
      <input type="number" name="montant" step="0.01" min="0.01" required style="width:130px;">
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label>Mode</label>
      <select name="mode" style="width:130px;">
        <option value="espèces">Espèces</option>
        <option value="mobile">Mobile Money</option>
        <option value="carte">Carte bancaire</option>
        <option value="chèque">Chèque</option>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label>Vente (optionnel)</label>
      <select name="vente_id" style="width:180px;">
        <option value="">— Toutes —</option>
        <?php foreach ($ventesWithBalance as $v): ?>
          <?php if ($v['statut_paiement'] !== 'payé'): ?>
          <option value="<?= $v['id'] ?>"><?= e($v['reference']) ?> — <?= fmtMoney($v['total']) ?></option>
          <?php endif; ?>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin-bottom:0;">
      <label>Note</label>
      <input type="text" name="note" placeholder="Note..." style="width:150px;">
    </div>
    <button type="submit" class="btn btn-primary">Enregistrer</button>
  </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <div class="card-title">Ventes à crédit (Détail des balances)</div>
  </div>
  <?php if (count($ventesClient) === 0): ?>
  <div class="card-pad"><p style="color:var(--text3);">Aucune vente à crédit.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Réf</th><th>Date</th><th>Total</th><th>Payé</th><th>Reste</th><th style="text-align:right;">Action</th></tr></thead>
    <tbody style="font-size:13px;">
		    <?php foreach ($ventesWithBalance as $v): ?>
		      <?php
		      $sBadge = $v['statut_paiement'] === 'payé' ? '<span class="badge badge-green">Payé</span>'
		              : ($v['statut_paiement'] === 'partiel' ? '<span class="badge badge-orange">Partiel</span>'
		              : '<span class="badge badge-red">En attente</span>');
		      $balance = (float)$v['balance'];
		      ?>
		      <tr style="<?= $balance <= 0 ? 'opacity:0.6;' : '' ?>">
		        <td><span class="td-mono"><?= e($v['reference']) ?></span> <?= $sBadge ?></td>
		        <td><?= date('d/m/Y', strtotime($v['created_at'])) ?></td>
		        <td class="fw-mono"><?= fmtMoney((float)$v['total']) ?></td>
		        <td class="fw-mono" style="color:var(--teal2);"><?= fmtMoney($v['paid_amount'] ?? 0) ?></td>
		        <td class="fw-mono" style="font-weight:600;color:<?= $balance > 0 ? 'var(--red)' : 'var(--teal)' ?>;"><?= fmtMoney($balance) ?></td>
		        <td style="text-align:right;">
		          <?php if ($balance > 0): ?>
		          <button class="btn btn-ghost btn-xs" onclick="settleSale(<?= $id ?>, <?= $v['id'] ?>, <?= $balance ?>)">
		            <?= icon('money',12) ?> Régler
		          </button>
		          <?php endif; ?>
		        </td>
		      </tr>
		    <?php endforeach; ?>
		    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card">
  <div class="card-header">
    <div class="card-title">Historique des règlements</div>
  </div>
  <?php if (count($reglements) === 0): ?>
  <div class="card-pad"><p style="color:var(--text3);">Aucun règlement.</p></div>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Date</th><th>Montant</th><th>Mode</th><th>Vente</th><th>Note</th></tr></thead>
    <tbody>
    <?php foreach ($reglements as $r): ?>
      <tr>
        <td><?= date('d/m/Y H:i', strtotime($r['date_reglement'])) ?></td>
        <td><?= fmtMoney($r['montant']) ?></td>
        <td><?= e($r['mode_paiement']) ?></td>
        <td><?= e($r['vente_ref'] ?: '—') ?></td>
        <td style="color:var(--text2);"><?= e($r['note'] ?: '—') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php elseif ($action === 'add' || ($action === 'edit' && $id)): ?>
<?php
$editClient = null;
if ($action === 'edit' && $id) {
    $stmtE = $db->prepare("SELECT * FROM clients WHERE id=?");
    $stmtE->execute([$id]);
    $editClient = $stmtE->fetch();
    if (!$editClient) { echo '<div class="alert alert-error">Client introuvable.</div>'; layout_foot(); exit; }
}
$isEdit = ($editClient !== null);
?>

<div class="card" style="max-width:500px;margin:0 auto;">
  <div class="card-header">
    <div class="card-title"><?= $isEdit ? 'Modifier le client' : 'Nouveau client' ?></div>
  </div>
  <form method="POST" action="<?= APP_URL ?>/modules/clients.php?action=<?= $isEdit ? 'edit&id='.$editClient['id'] : 'add' ?>">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <div class="card-pad">
      <div class="form-group">
        <label>Nom <span style="color:var(--red);">*</span></label>
        <input type="text" name="nom" value="<?= $isEdit ? e($editClient['nom']) : '' ?>" required
               style="text-transform:uppercase;">
      </div>
      <div class="form-group" style="margin-bottom:0;">
        <label>Téléphone</label>
        <input type="text" name="telephone" value="<?= $isEdit ? e($editClient['telephone']) : '' ?>">
      </div>
    </div>
    <div class="modal-footer">
      <a href="<?= url('clients') ?>" class="btn btn-ghost">Annuler</a>
      <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Enregistrer' : 'Créer le client' ?></button>
    </div>
  </form>
</div>

<?php else: ?>
<?php
// Liste des clients avec dette calculee (pagination serveur 50/page).
// Les sous-requêtes agrégées (dette_credits / reglements_total) balayent
// ventes et reglements via des index sur client_id ; la pagination ne
// fait que borner le nombre de lignes retournées et rendues.
$perPage = 25; // section Gestion : pagination uniforme à 25/page
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = paginateOffset($page, $perPage);
$totalClients = (int)$db->query("SELECT COUNT(*) FROM clients")->fetchColumn();

// ── Export Excel de la liste des clients ────────────────────
if (($_GET['export'] ?? '') === '1') {
    require_once __DIR__ . '/../includes/export_xlsx.php';
    $stX = $db->query("
        SELECT c.nom, c.telephone, c.actif, c.created_at,
               COALESCE(dette_credits.total_credit, 0) AS dette_ventes,
               COALESCE(reglements_total.total_regle, 0) AS total_regle,
               COALESCE(dette_credits.total_credit, 0) - COALESCE(reglements_total.total_regle, 0) AS dette_restante
        FROM clients c
        LEFT JOIN (
            SELECT client_id, SUM(total) AS total_credit
            FROM ventes
            WHERE (mode_paiement = 'crédit' OR mode_paiement = 'credit' OR statut_paiement = 'en_attente' OR statut_paiement = 'partiel')
            GROUP BY client_id
        ) dette_credits ON c.id = dette_credits.client_id
        LEFT JOIN (
            SELECT client_id, SUM(montant) AS total_regle
            FROM reglements
            GROUP BY client_id
        ) reglements_total ON c.id = reglements_total.client_id
        ORDER BY c.nom ASC
    ");
    $rowsX = [];
    foreach ($stX->fetchAll() as $c) {
        $rowsX[] = [$c['nom'], $c['telephone'], (int)$c['actif'] ? 'Actif' : 'Inactif',
                    (float)$c['dette_ventes'], (float)$c['total_regle'], (float)$c['dette_restante'],
                    date('d/m/Y', strtotime($c['created_at']))];
    }
    export_xlsx_send('clients_' . date('Y-m-d'), 'Clients',
        ['Nom', 'Téléphone', 'Statut', 'Total ventes à crédit', 'Total réglé', 'Dette restante', 'Créé le'], $rowsX);
}

$clients = $db->query("
    SELECT c.*,
           COALESCE(dette_credits.total_credit, 0) AS dette_ventes,
           COALESCE(reglements_total.total_regle, 0) AS total_regle,
           COALESCE(dette_credits.total_credit, 0) - COALESCE(reglements_total.total_regle, 0) AS dette_restante
    FROM clients c
    LEFT JOIN (
        SELECT client_id, SUM(total) AS total_credit
        FROM ventes
        WHERE (mode_paiement = 'crédit' OR mode_paiement = 'credit' OR statut_paiement = 'en_attente' OR statut_paiement = 'partiel')
        GROUP BY client_id
    ) dette_credits ON c.id = dette_credits.client_id
    LEFT JOIN (
        SELECT client_id, SUM(montant) AS total_regle
        FROM reglements
        GROUP BY client_id
    ) reglements_total ON c.id = reglements_total.client_id
    ORDER BY c.nom ASC
    LIMIT $perPage OFFSET $offset
")->fetchAll();
?>

<div class="page-header">
  <h1>Clients</h1>
  <div class="flex gap-8">
  <a href="<?= url('clients', ['export' => '1']) ?>" class="btn btn-ghost" title="Exporter au format Excel (.xlsx)"><?= icon('download', 14) ?> Exporter</a>
  <?php if (hasPermission('clients.ajouter')): ?>
  <button type="button" class="btn btn-primary" onclick="openModal('modal-new-client')"><?= icon('plus',14) ?> Nouveau client</button>
  <?php endif; ?>
  </div>
</div>

<div class="card">
  <?php if (count($clients) === 0): ?>
  <p style="color:var(--text3);text-align:center;padding:24px;">Aucun client enregistré.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Nom</th><th>Téléphone</th><th>Dette restante</th><th>Statut</th><th style="width:120px;">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($clients as $c): ?>
      <?php
      $dette = (float)$c['dette_restante'];
      if (!$c['actif']) {
          $statusBadge = '<span class="badge badge-gray">Inactif</span>';
      } elseif ($dette > 0 && (float)$c['total_regle'] > 0) {
          $statusBadge = '<span class="badge badge-orange">Dette partielle</span>';
      } elseif ($dette > 0) {
          $statusBadge = '<span class="badge badge-red">Dette</span>';
      } else {
          $statusBadge = '<span class="badge badge-green">OK</span>';
      }
      ?>
      <tr>
        <td>
          <a href="<?= url('clients', ['action'=>'detail','id'=>$c['id']], $c['nom'] ?? null) ?>" style="font-weight:500;text-decoration:none;color:var(--text);">
            <?= e($c['nom']) ?>
          </a>
        </td>
        <td style="color:var(--text2);"><?= e($c['telephone'] ?: '—') ?></td>
        <td style="font-weight:600;color:<?= $dette > 0 ? 'var(--red)' : 'var(--teal)' ?>;">
          <?= fmtMoney($dette) ?>
        </td>
        <td><?= $statusBadge ?></td>
        <td>
          <div style="display:flex;gap:4px;">
            <a href="<?= url('clients', ['action'=>'detail','id'=>$c['id']], $c['nom'] ?? null) ?>" class="btn btn-ghost" style="padding:4px 6px;" title="Détail"><?= icon('eye',14) ?></a>
            <?php if (hasPermission('clients.modifier')): ?>
            <a href="<?= url('clients', ['action'=>'edit','id'=>$c['id']], $c['nom'] ?? null) ?>" class="btn btn-ghost" style="padding:4px 6px;" title="Modifier"><?= icon('edit',14) ?></a>
            <?php endif; ?>
            <?php if (hasPermission('clients.supprimer')): ?>
              <?php if ($c['actif']): ?>
              <a href="<?= url('clients', ['action'=>'disable','id'=>$c['id'],'csrf'=>csrf()], $c['nom'] ?? null) ?>" class="btn btn-ghost" style="padding:4px 6px;color:var(--red);" title="Désactiver" onclick="showConfirm('Désactiver ce client ?','Ce client ne pourra plus être sélectionné lors des ventes.',function(){window.location.href=this.href;}.bind(this));return false;"><?= icon('trash',14) ?></a>
              <?php else: ?>
              <a href="<?= url('clients', ['action'=>'enable','id'=>$c['id'],'csrf'=>csrf()], $c['nom'] ?? null) ?>" class="btn btn-ghost" style="padding:4px 6px;color:var(--teal);" title="Réactiver"><?= icon('refresh',14) ?></a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
  <?= renderPagination($page, $perPage, $totalClients, []) ?>
</div>

<!-- ── Modal Nouveau Client ── -->
<div class="modal-overlay" id="modal-new-client">
  <div class="modal" style="width:420px;">
    <div class="modal-header">
      <div class="modal-title">Nouveau client</div>
      <button type="button" class="modal-close" onclick="closeModal('modal-new-client')">✕</button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/modules/clients.php?action=add">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <div class="card-pad">
        <div class="form-group">
          <label>Nom <span style="color:var(--red);">*</span></label>
          <input type="text" name="nom" required autofocus>
        </div>
        <div class="form-group">
          <label>Téléphone</label>
          <input type="text" name="telephone">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-ghost" onclick="closeModal('modal-new-client')">Annuler</button>
        <button type="submit" class="btn btn-primary"><?= icon('plus',14) ?> Créer le client</button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<?php layout_foot(); ?>
