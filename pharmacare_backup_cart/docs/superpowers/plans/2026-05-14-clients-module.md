# Module Clients — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a client management module (fiches clients, ventes a credit, reglements partiels avec historique) integrated into the sidebar and POS.

**Architecture:** New `clients` and `reglements` tables, new `modules/clients.php` with 3 views (list/detail/form), sidebar entry in the Gestion section, POS integration for client selection and credit payment mode, accounting entries for credit sales.

**Tech Stack:** Vanilla PHP 7.4+, MySQL/MariaDB, no framework, no Composer, no tests.

---

### Task 1: Create the migration SQL patch

**Files:**
- Create: `patch_clients.sql`

- [ ] **Step 1: Write the SQL patch file**

```sql
-- patch_clients.sql
-- Ajoute les tables clients + reglements et modifie ventes pour le credit

CREATE TABLE IF NOT EXISTS clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    telephone   VARCHAR(20) DEFAULT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reglements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT NOT NULL,
    vente_id        INT DEFAULT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    mode_paiement   ENUM('espèces','carte','chèque','mobile') NOT NULL DEFAULT 'espèces',
    note            VARCHAR(255) DEFAULT NULL,
    date_reglement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vente_id) REFERENCES ventes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE ventes
    ADD COLUMN client_id INT DEFAULT NULL AFTER client_telephone,
    ADD COLUMN statut_paiement ENUM('payé','en_attente','partiel') DEFAULT 'payé' AFTER mode_paiement,
    MODIFY COLUMN mode_paiement ENUM('espèces','carte','chèque','assurance','crédit') NOT NULL DEFAULT 'espèces',
    ADD FOREIGN KEY (client_id) REFERENCES clients(id);

-- Nouvelles permissions clients (IDs 26-30)
INSERT INTO permissions (id, code, libelle, module) VALUES
(26, 'clients.voir',       'Voir la liste des clients',   'clients'),
(27, 'clients.ajouter',    'Créer un client',              'clients'),
(28, 'clients.modifier',   'Modifier une fiche client',    'clients'),
(29, 'clients.supprimer',  'Désactiver un client',         'clients'),
(30, 'clients.paiements',  'Enregistrer des règlements',   'clients');

-- Admin (role_id=1) : toutes les perms clients
INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions WHERE module = 'clients';

-- Pharmacien (role_id=2) : voir seulement
INSERT INTO role_permissions (role_id, permission_id) VALUES
(2, 26);

-- Caissier (role_id=3) : voir, ajouter, paiements
INSERT INTO role_permissions (role_id, permission_id) VALUES
(3, 26), (3, 27), (3, 30);
```

- [ ] **Step 2: Apply the patch to verify it runs**

Run: `mysql -u root pharmacare < patch_clients.sql`

- [ ] **Step 3: Commit**

```bash
git add patch_clients.sql
git commit -m "feat: add clients + reglements tables and permissions patch"
```

---

### Task 2: Update database.sql for fresh installs

**Files:**
- Modify: `database.sql`

- [ ] **Step 1: Add clients and reglements tables after fournisseurs table**

Find the end of `CREATE TABLE fournisseurs` (around line 72). Insert after it:

```sql
CREATE TABLE clients (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nom         VARCHAR(150) NOT NULL,
    telephone   VARCHAR(20) DEFAULT NULL,
    actif       TINYINT(1) DEFAULT 1,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE reglements (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    client_id       INT NOT NULL,
    vente_id        INT DEFAULT NULL,
    montant         DECIMAL(10,2) NOT NULL,
    mode_paiement   ENUM('espèces','carte','chèque','mobile') NOT NULL DEFAULT 'espèces',
    note            VARCHAR(255) DEFAULT NULL,
    date_reglement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id),
    FOREIGN KEY (vente_id) REFERENCES ventes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Update ventes table to include client_id, statut_paiement, and credit mode**

Replace the `CREATE TABLE ventes` block. The new version adds `client_id`, `statut_paiement`, and adds `'crédit'` to the mode_paiement ENUM:

```sql
CREATE TABLE ventes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reference       VARCHAR(20) UNIQUE NOT NULL,
    client_nom      VARCHAR(150),
    client_telephone VARCHAR(20),
    client_id       INT NULL,
    caissier_id     INT,
    sous_total      DECIMAL(10,2) DEFAULT 0,
    tva_total       DECIMAL(10,2) DEFAULT 0,
    total           DECIMAL(10,2) DEFAULT 0,
    mode_paiement   ENUM('espèces','carte','chèque','assurance','crédit') DEFAULT 'espèces',
    statut_paiement ENUM('payé','en_attente','partiel') DEFAULT 'payé',
    montant_recu    DECIMAL(10,2) DEFAULT 0,
    monnaie         DECIMAL(10,2) DEFAULT 0,
    note            TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (client_id) REFERENCES clients(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 3: Add the 5 new client permissions (IDs 26-30)**

After the existing permission INSERT (line 189, after `(25, 'roles.gerer', ...)`), add:

```sql
(26, 'clients.voir',       'Voir la liste des clients',   'clients'),
(27, 'clients.ajouter',    'Créer un client',              'clients'),
(28, 'clients.modifier',   'Modifier une fiche client',    'clients'),
(29, 'clients.supprimer',  'Désactiver un client',         'clients'),
(30, 'clients.paiements',  'Enregistrer des règlements',   'clients');
```

- [ ] **Step 4: Add client permissions to caissier role**

After line 204 `(3,1),(3,2),(3,3),(3,16),(3,17);`, change to:

```sql
(3,1),(3,2),(3,3),(3,16),(3,17),
(3,26),(3,27),(3,30);
```

- [ ] **Step 5: Commit**

```bash
git add database.sql
git commit -m "feat: add clients/reglements tables and permissions to fresh install schema"
```

---

### Task 3: Create the clients module

**Files:**
- Create: `modules/clients.php`

- [ ] **Step 1: Write the full module file**

```php
<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../config/settings.php';
requirePermission('clients.voir');
$db     = getDB();
$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── POST : ajout / modification client ───────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add','edit'], true)) {
    $permNeeded = ($action === 'add') ? 'clients.ajouter' : 'clients.modifier';
    requirePermission($permNeeded);
    verifyCsrf();
    $nom       = trim($_POST['nom'] ?? '');
    $telephone = trim($_POST['telephone'] ?? '');

    if ($nom === '') {
        flash('Le nom du client est requis.', 'error');
        $redirect = ($action === 'edit' && $id) ? '?action=edit&id='.$id : '?action=add';
        header('Location: ' . APP_URL . '/modules/clients.php'.$redirect); exit;
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
        header('Location: ' . APP_URL . '/modules/clients.php'); exit;
    } catch (Exception $e) {
        flash('Erreur : ' . $e->getMessage(), 'error');
        $redirect = ($action === 'edit' && $id) ? '?action=edit&id='.$id : '?action=add';
        header('Location: ' . APP_URL . '/modules/clients.php'.$redirect); exit;
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
        header('Location: ' . APP_URL . '/modules/clients.php?action=detail&id='.$clientId); exit;
    }

    try {
        $db->prepare("INSERT INTO reglements (client_id, vente_id, montant, mode_paiement, note) VALUES (?,?,?,?,?)")
           ->execute([$clientId, $venteId, $montant, $mode, $note]);

        // Mettre à jour le statut de la dette
        // Calculer la dette restante apres ce reglement
        $stmtDette = $db->prepare("
            SELECT COALESCE(SUM(v.total), 0) - COALESCE(SUM(v.montant_recu), 0) AS dette_ventes
            FROM ventes v
            WHERE v.client_id = ? AND v.mode_paiement = 'crédit' AND v.statut_paiement != 'payé'
        ");
        $stmtDette->execute([$clientId]);
        $detteVentes = (float)$stmtDette->fetchColumn();

        $stmtRegl = $db->prepare("SELECT COALESCE(SUM(montant), 0) FROM reglements WHERE client_id = ?");
        $stmtRegl->execute([$clientId]);
        $totalRegle = (float)$stmtRegl->fetchColumn();

        if ($totalRegle >= $detteVentes && $detteVentes > 0) {
            // Toutes les ventes a credit sont payees
            $db->prepare("UPDATE ventes SET statut_paiement='payé' WHERE client_id=? AND mode_paiement='crédit' AND statut_paiement!='payé'")
               ->execute([$clientId]);
        } elseif ($totalRegle > 0 && $totalRegle < $detteVentes) {
            $db->prepare("UPDATE ventes SET statut_paiement='partiel' WHERE client_id=? AND mode_paiement='crédit' AND statut_paiement='en_attente'")
               ->execute([$clientId]);
        }

        flash('Règlement enregistré.', 'success');
        header('Location: ' . APP_URL . '/modules/clients.php?action=detail&id='.$clientId); exit;
    } catch (Exception $e) {
        flash('Erreur : ' . $e->getMessage(), 'error');
        header('Location: ' . APP_URL . '/modules/clients.php?action=detail&id='.$clientId); exit;
    }
}

// ── GET : désactiver un client ────────────────────────────────
if ($action === 'disable' && $id && hasPermission('clients.supprimer')) {
    if (($_GET['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) die('Requête invalide (CSRF).');
    $db->prepare("UPDATE clients SET actif=0 WHERE id=?")->execute([$id]);
    flash('Client désactivé.', 'success');
    header('Location: ' . APP_URL . '/modules/clients.php'); exit;
}

// ── GET : réactiver un client ─────────────────────────────────
if ($action === 'enable' && $id && hasPermission('clients.supprimer')) {
    if (($_GET['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) die('Requête invalide (CSRF).');
    $db->prepare("UPDATE clients SET actif=1 WHERE id=?")->execute([$id]);
    flash('Client réactivé.', 'success');
    header('Location: ' . APP_URL . '/modules/clients.php'); exit;
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
    WHERE v.client_id = ? AND v.mode_paiement = 'crédit'
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
$detteVentes = 0;
foreach ($ventesClient as $v) {
    if ($v['statut_paiement'] !== 'payé') {
        $detteVentes += (float)$v['total'];
    }
}
$totalRegle = 0;
foreach ($reglements as $r) {
    $totalRegle += (float)$r['montant'];
}
$detteRestante = max(0, $detteVentes - $totalRegle);

if ($detteRestante <= 0 && $detteVentes > 0) {
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
    <a href="<?= APP_URL ?>/modules/clients.php" class="btn btn-ghost" style="padding:4px 8px;"><?= icon('chevron-left',16) ?></a>
    <h1 style="margin:0;"><?= e($client['nom']) ?></h1>
    <?= $badgeDette ?>
  </div>
  <div style="display:flex;gap:8px;">
    <?php if (hasPermission('clients.modifier')): ?>
    <a href="<?= APP_URL ?>/modules/clients.php?action=edit&id=<?= $client['id'] ?>" class="btn btn-outline"><?= icon('edit',14) ?> Modifier</a>
    <?php endif; ?>
  </div>
</div>

<div class="card-grid" style="grid-template-columns:1fr 1fr;">
  <div class="card">
    <div class="card-title">Informations</div>
    <table class="info-table">
      <tr><td class="label">Téléphone</td><td><?= e($client['telephone'] ?: '—') ?></td></tr>
      <tr><td class="label">Client depuis</td><td><?= date('d/m/Y', strtotime($client['created_at'])) ?></td></tr>
    </table>
  </div>
  <div class="card">
    <div class="card-title">Dette</div>
    <div style="font-size:28px;font-weight:700;color:<?= $detteRestante > 0 ? 'var(--red)' : 'var(--teal)' ?>;font-family:var(--font-title);">
      <?= fmtMoney($detteRestante) ?>
    </div>
    <div style="font-size:12px;color:var(--text2);">Total crédit : <?= fmtMoney($detteVentes) ?> · Réglé : <?= fmtMoney($totalRegle) ?></div>
  </div>
</div>

<?php if (hasPermission('clients.paiements') && $detteRestante > 0): ?>
<div class="card" style="margin-top:16px;">
  <div class="card-title">Enregistrer un règlement</div>
  <form method="POST" action="<?= APP_URL ?>/modules/clients.php?action=reglement" style="display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
    <?= csrf() ?>
    <input type="hidden" name="client_id" value="<?= $client['id'] ?>">
    <div class="form-group" style="margin-bottom:0;">
      <label>Montant</label>
      <input type="number" name="montant" step="0.01" min="1" max="<?= $detteRestante ?>" required style="width:130px;">
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
        <?php foreach ($ventesClient as $v): ?>
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
<?php endif; ?>

<div class="card" style="margin-top:16px;">
  <div class="card-title">Ventes à crédit</div>
  <?php if (count($ventesClient) === 0): ?>
  <p style="color:var(--text3);">Aucune vente à crédit.</p>
  <?php else: ?>
  <table class="table">
    <thead><tr><th>Réf</th><th>Date</th><th>Caissier</th><th>Total</th><th>Statut</th></tr></thead>
    <tbody>
    <?php foreach ($ventesClient as $v): ?>
      <?php
      $sBadge = $v['statut_paiement'] === 'payé' ? '<span class="badge badge-green">Payé</span>'
              : ($v['statut_paiement'] === 'partiel' ? '<span class="badge badge-orange">Partiel</span>'
              : '<span class="badge badge-red">En attente</span>');
      ?>
      <tr>
        <td><?= e($v['reference']) ?></td>
        <td><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?></td>
        <td><?= e($v['prenom'].' '.$v['u_nom']) ?></td>
        <td><?= fmtMoney($v['total']) ?></td>
        <td><?= $sBadge ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<div class="card" style="margin-top:16px;">
  <div class="card-title">Historique des règlements</div>
  <?php if (count($reglements) === 0): ?>
  <p style="color:var(--text3);">Aucun règlement.</p>
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
  <div class="card-title"><?= $isEdit ? 'Modifier le client' : 'Nouveau client' ?></div>
  <form method="POST" action="<?= APP_URL ?>/modules/clients.php?action=<?= $isEdit ? 'edit&id='.$editClient['id'] : 'add' ?>">
    <?= csrf() ?>
    <div class="form-group">
      <label>Nom <span style="color:var(--red);">*</span></label>
      <input type="text" name="nom" value="<?= $isEdit ? e($editClient['nom']) : '' ?>" required>
    </div>
    <div class="form-group">
      <label>Téléphone</label>
      <input type="text" name="telephone" value="<?= $isEdit ? e($editClient['telephone']) : '' ?>">
    </div>
    <div style="display:flex;gap:8px;justify-content:flex-end;">
      <a href="<?= APP_URL ?>/modules/clients.php" class="btn btn-ghost">Annuler</a>
      <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Enregistrer' : 'Créer le client' ?></button>
    </div>
  </form>
</div>

<?php else: ?>
<?php
// Liste des clients avec dette calculee
$clients = $db->query("
    SELECT c.*,
           COALESCE(dette_credits.total_credit, 0) AS dette_ventes,
           COALESCE(reglements_total.total_regle, 0) AS total_regle,
           COALESCE(dette_credits.total_credit, 0) - COALESCE(reglements_total.total_regle, 0) AS dette_restante
    FROM clients c
    LEFT JOIN (
        SELECT client_id, SUM(total) AS total_credit
        FROM ventes
        WHERE mode_paiement = 'crédit' AND statut_paiement != 'payé'
        GROUP BY client_id
    ) dette_credits ON c.id = dette_credits.client_id
    LEFT JOIN (
        SELECT client_id, SUM(montant) AS total_regle
        FROM reglements
        GROUP BY client_id
    ) reglements_total ON c.id = reglements_total.client_id
    ORDER BY c.nom ASC
")->fetchAll();
?>

<div class="page-header">
  <h1>Clients</h1>
  <?php if (hasPermission('clients.ajouter')): ?>
  <a href="<?= APP_URL ?>/modules/clients.php?action=add" class="btn btn-primary"><?= icon('plus',14) ?> Nouveau client</a>
  <?php endif; ?>
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
          <a href="<?= APP_URL ?>/modules/clients.php?action=detail&id=<?= $c['id'] ?>" style="font-weight:500;text-decoration:none;color:var(--text);">
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
            <a href="<?= APP_URL ?>/modules/clients.php?action=detail&id=<?= $c['id'] ?>" class="btn btn-ghost" style="padding:4px 6px;" title="Détail"><?= icon('eye',14) ?></a>
            <?php if (hasPermission('clients.modifier')): ?>
            <a href="<?= APP_URL ?>/modules/clients.php?action=edit&id=<?= $c['id'] ?>" class="btn btn-ghost" style="padding:4px 6px;" title="Modifier"><?= icon('edit',14) ?></a>
            <?php endif; ?>
            <?php if (hasPermission('clients.supprimer')): ?>
              <?php if ($c['actif']): ?>
              <a href="<?= APP_URL ?>/modules/clients.php?action=disable&id=<?= $c['id'] ?>&csrf=<?= csrf() ?>" class="btn btn-ghost" style="padding:4px 6px;color:var(--red);" title="Désactiver" onclick="return confirm('Désactiver ce client ?')"><?= icon('trash',14) ?></a>
              <?php else: ?>
              <a href="<?= APP_URL ?>/modules/clients.php?action=enable&id=<?= $c['id'] ?>&csrf=<?= csrf() ?>" class="btn btn-ghost" style="padding:4px 6px;color:var(--teal);" title="Réactiver"><?= icon('refresh',14) ?></a>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php layout_foot(); ?>
```

- [ ] **Step 2: Commit**

```bash
git add modules/clients.php
git commit -m "feat: add clients module with list, detail, add/edit, and payments"
```

---

### Task 4: Add Clients to the sidebar

**Files:**
- Modify: `includes/layout.php` (line ~191, between Fournisseurs and Commandes)

- [ ] **Step 1: Insert the sidebar entry**

Insert after the `<?php endif; ?>` that closes the fournisseurs block (after line ~191) and before the commandes block:

```php
	    <?php if(hasPermission('clients.voir')): ?>
	    <a href="<?= APP_URL ?>/modules/clients.php" class="nav-item <?= $activePage==='clients'?'active':'' ?>">
	      <span class="nav-icon i-cyan"><?= icon('users',14) ?></span> Clients
	    </a>
	    <?php endif; ?>
```

- [ ] **Step 2: Commit**

```bash
git add includes/layout.php
git commit -m "feat: add Clients entry to sidebar Gestion section"
```

---

### Task 5: Integrate client selection and credit mode into POS

**Files:**
- Modify: `modules/vente.php`

This task has 4 sub-parts: (a) client search/select in the form, (b) credit as payment mode option, (c) handling in POST, (d) accounting entry for credit.

- [ ] **Step 1: Update the $modeLabels array to include credit**

At line 9-14, change:

```php
$modeLabels = [
    'espèces'   => 'Espèces',
    'carte'     => 'Carte bancaire',
    'chèque'    => 'Chèque',
    'assurance' => 'Assurance',
];
```

To:

```php
$modeLabels = [
    'espèces'   => 'Espèces',
    'carte'     => 'Carte bancaire',
    'chèque'    => 'Chèque',
    'assurance' => 'Assurance',
    'crédit'    => 'Crédit client',
];
```

- [ ] **Step 2: Update mode_paiement validation in POST handler**

At line ~58-61, change:

```php
    // Vérifier que le mode de paiement est valide
    $modePaiement = $_POST['mode_paiement'] ?? 'espèces';
    if (!isset($modeLabels[$modePaiement])) {
        $modePaiement = 'espèces';
    }
```

To:

```php
    // Vérifier que le mode de paiement est valide
    $modePaiement = $_POST['mode_paiement'] ?? 'espèces';
    if (!isset($modeLabels[$modePaiement])) {
        $modePaiement = 'espèces';
    }

    // Client pour credit obligatoire
    $clientId = null;
    if ($modePaiement === 'crédit') {
        $clientId = !empty($_POST['client_id']) ? (int)$_POST['client_id'] : null;
    }
    // Si credit sans client, on repasse en especes
    if ($modePaiement === 'crédit' && !$clientId) {
        flash('Un client est requis pour une vente à crédit.', 'error');
        header('Location: ' . APP_URL . '/modules/vente.php'); exit;
    }
```

- [ ] **Step 3: Update the INSERT INTO ventes to include client_id and statut_paiement**

Replace the INSERT block (lines ~97-116):

```php
        $stmt = $db->prepare("
            INSERT INTO ventes
                (reference, client_nom, client_telephone, client_id, caissier_id,
                 sous_total, tva_total, total, mode_paiement, statut_paiement,
                 montant_recu, monnaie, note)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        $stmt->execute([
            $ref,
            trim($_POST['client_nom'] ?? ''),
            trim($_POST['client_tel']  ?? ''),
            $clientId,
            currentUser()['id'],
            round($subtotal,  2),
            round($tva_total, 2),
            round($total,     2),
            $modePaiement,
            $modePaiement === 'crédit' ? 'en_attente' : 'payé',
            $modePaiement === 'crédit' ? 0 : $recuRaw,
            $modePaiement === 'crédit' ? 0 : round($monnaie, 2),
            trim($_POST['note'] ?? '')
        ]);
```

- [ ] **Step 4: Update the caisse movement block — skip for credit**

At line ~153, change:

```php
        // Mouvement de caisse (ventes en espèces uniquement)
        if ($modePaiement === 'espèces' && $sessionActive) {
```

To:

```php
        // Mouvement de caisse (ventes en espèces uniquement, pas de credit)
        if ($modePaiement === 'espèces' && $sessionActive) {
```

- [ ] **Step 5: Update the accounting entry block — credit goes to compte client**

Replace the accounting block (lines ~167-185) with:

```php
        // ── Écriture comptable automatique ────────────────
        if ($modePaiement === 'crédit' && $clientId) {
            // Vente a credit : debit compte client au lieu de caisse
            $clientInfo = $db->prepare("SELECT nom FROM clients WHERE id=?");
            $clientInfo->execute([$clientId]);
            $clientNom = $clientInfo->fetchColumn() ?: 'Client #'.$clientId;
            $compteClient = compteFindOrCreate($db, '4112', 'Clients - Crédit', 4, 'debit');
            $compteVente  = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
            $compteTva    = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
            ecritureCreate($db,
                'Vente ' . $ref . ' - Crédit ' . $clientNom,
                date('Y-m-d'),
                [
                    [$compteClient, round($total, 2), 0, 'Crédit client ' . $clientNom . ' - ' . $ref],
                    [$compteVente,  0, round($subtotal, 2), 'Vente médicaments ' . $ref],
                    [$compteTva,    0, round($tva_total, 2), 'TVA collectée ' . $ref],
                ],
                'vente', $ref, currentUser()['id']
            );
        } else {
            $mapCompte = [
                'espèces'   => compteFindOrCreate($db, '5711', 'Caisse principale', 5, 'debit'),
                'carte'     => compteFindOrCreate($db, '512', 'Banque', 5, 'debit'),
                'chèque'    => compteFindOrCreate($db, '511', 'Chèques à encaisser', 5, 'debit'),
                'assurance' => compteFindOrCreate($db, '4111', 'Clients - Assurance', 4, 'debit'),
            ];
            $caisseCompte  = $mapCompte[$modePaiement] ?? $mapCompte['espèces'];
            $compteVente   = compteFindOrCreate($db, '7011', 'Ventes de médicaments', 7, 'credit');
            $compteTva     = compteFindOrCreate($db, '4411', 'TVA collectée 19.25%', 4, 'credit');
            ecritureCreate($db,
                'Vente ' . $ref . ' - ' . $modeLabels[$modePaiement],
                date('Y-m-d'),
                [
                    [$caisseCompte, round($total, 2), 0, 'Vente ' . $ref],
                    [$compteVente,  0, round($subtotal, 2), 'Vente médicaments ' . $ref],
                    [$compteTva,    0, round($tva_total, 2), 'TVA collectée ' . $ref],
                ],
                'vente', $ref, currentUser()['id']
            );
        }
```

- [ ] **Step 6: Add client search dropdown and credit radio button in the HTML form**

Find the `client_nom` input block (around line 296-300) and replace:

```php
      <div class="form-group" style="margin-bottom:8px;">
        <label>Client (optionnel)</label>
        <input type="text" name="client_nom" placeholder="Nom du client">
      </div>
```

With:

```php
      <div class="form-group" style="margin-bottom:8px;">
        <label>Client (optionnel)</label>
        <select name="client_id" id="client-select" style="width:100%;" onchange="document.getElementById('mode-credit').style.display=this.value?'':'none'">
          <option value="">— Sélectionner —</option>
          <?php
          $allClients = $db->query("SELECT id, nom, telephone FROM clients WHERE actif=1 ORDER BY nom")->fetchAll();
          foreach ($allClients as $cl):
          ?>
          <option value="<?= $cl['id'] ?>"><?= e($cl['nom']) . ($cl['telephone'] ? ' — ' . $cl['telephone'] : '') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
```

- [ ] **Step 7: Add the credit mode radio button (hidden by default, shown when client selected)**

Find the payment mode radios section. Add credit radio after the existing modes, wrapped in a span that shows only when a client is selected:

```php
        <span id="mode-credit" style="display:none;">
          <label class="radio-label" style="border-color:var(--blue);">
            <input type="radio" name="mode_paiement" value="crédit" onchange="handleCreditMode(this)">
            <span class="radio-mark" style="background:var(--blue-dim);color:var(--blue);">C</span>
            Crédit
          </label>
        </span>
```

- [ ] **Step 8: Add JS helper for credit mode in the page script section**

In the `<script>` block at the bottom of vente.php, add:

```js
function handleCreditMode(radio) {
  if (radio.checked) {
    document.getElementById('montant-recu-group').style.display = 'none';
    document.getElementById('monnaie-display').style.display = 'none';
  } else {
    document.getElementById('montant-recu-group').style.display = '';
    document.getElementById('monnaie-display').style.display = '';
  }
}
```

- [ ] **Step 9: Commit**

```bash
git add modules/vente.php
git commit -m "feat: add client selection and credit payment mode to POS"
```

---

### Task 6: Verify with the patch on a real database

- [ ] **Step 1: Apply the migration**

```bash
mysql -u root pharmacare < patch_clients.sql
```

- [ ] **Step 2: Verify tables exist**

```bash
mysql -u root pharmacare -e "DESCRIBE clients; DESCRIBE reglements; DESCRIBE ventes;"
```

Expected: clients has 5 columns, reglements has 7 columns, ventes has client_id and statut_paiement columns.

- [ ] **Step 3: Verify permissions exist**

```bash
mysql -u root pharmacare -e "SELECT * FROM permissions WHERE module='clients';"
```

Expected: 5 rows (IDs 26-30).

- [ ] **Step 4: Open the app and test**

1. Open `http://localhost/pharmacare`
2. Login as admin (`admin` / `password`)
3. Sidebar should show "Clients" in the Gestion section
4. Click "Clients" → empty list with "+ Nouveau client" button
5. Create a client
6. Go to POS → client should appear in dropdown
7. Select client → credit mode should appear
8. Create a credit sale → client detail should show the debt
9. Record a payment → debt should decrease

- [ ] **Step 5: Commit any final fixes**
