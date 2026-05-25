# Sessions de Caisse (Ouverture/Fermeture) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Empêcher les ventes sans ouverture préalable d'une session de caisse avec fond de caisse, permettre retraits/apports, et générer un rapport Z à la fermeture.

**Architecture:** Nouvelle table `caisse_sessions` trace chaque session ouverture→fermeture avec calcul automatique du solde attendu. Table `caisse_operations` pour les retraits/apports. Les ventes sont liées à leur session via `sales.session_id`. Le POS vérifie la session active avant d'afficher l'interface de vente.

**Tech Stack:** PHP 8.0+ (no framework), MySQL/MariaDB, Bootstrap 5, jQuery, vanilla JS

---

## File Structure

| File | Action | Responsibility |
|---|---|---|
| `database.sql` | Modify | Ajout tables `caisse_sessions`, `caisse_operations`, colonne `session_id` sur `sales` |
| `models/CaisseSession.php` | **Create** | Classes CaisseSession et CaisseOperation |
| `controllers/caisse_session_ajax.php` | **Create** | Endpoints AJAX : open, close, status, summary, report, add_operation, history |
| `includes/bootstrap.php` | Modify | Charger `CaisseSession.php` |
| `includes/helpers.php` | Modify | `getActiveCaisseSession()`, mise à jour `isCaisseRequired()` |
| `controllers/sale_controller.php` | Modify | Ajouter `session_id` aux données de vente |
| `views/pos.php` | Modify | Écran ouverture, badge session, modales opérations/fermeture, rapport Z |

---

### Task 1: Ajouter les tables `caisse_sessions` et `caisse_operations` + colonne `session_id` sur `sales`

**Files:**
- Modify: `database.sql` (append after line 483, the last `INSERT INTO caisses`)

- [ ] **Step 1: Add new tables and alter sales**

At the end of `database.sql`, after the last `INSERT INTO caisses` block (line 483), append:

```sql
-- ============================================================
-- Caisse Sessions (Cash Register Sessions)
-- ============================================================

CREATE TABLE IF NOT EXISTS `caisse_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `caisse_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `opening_balance` decimal(15,2) NOT NULL DEFAULT 0.00,
  `opening_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `closing_balance_expected` decimal(15,2) DEFAULT NULL,
  `closing_balance_actual` decimal(15,2) DEFAULT NULL,
  `closing_discrepancy` decimal(15,2) DEFAULT NULL,
  `closed_by` int(10) unsigned DEFAULT NULL,
  `closing_time` timestamp NULL DEFAULT NULL,
  `status` enum('open','closed') NOT NULL DEFAULT 'open',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_caisse_id` (`caisse_id`),
  KEY `idx_store_id` (`store_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_session_caisse` FOREIGN KEY (`caisse_id`) REFERENCES `caisses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_session_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `fk_session_closed_by` FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `caisse_operations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `session_id` int(10) unsigned NOT NULL,
  `type` enum('deposit','withdrawal') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  CONSTRAINT `fk_operation_session` FOREIGN KEY (`session_id`) REFERENCES `caisse_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_operation_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `sales`
  ADD COLUMN `session_id` int(10) unsigned DEFAULT NULL AFTER `caisse_id`,
  ADD KEY `idx_sale_session` (`session_id`),
  ADD CONSTRAINT `fk_sale_session` FOREIGN KEY (`session_id`) REFERENCES `caisse_sessions` (`id`) ON DELETE SET NULL;
```

- [ ] **Step 2: Apply migration to database**

Run the SQL against the `pos_system` database via phpMyAdmin or mysql CLI:

```sql
USE pos_system;
SOURCE database.sql;
```

Or import the full `database.sql` via phpMyAdmin (the `IF NOT EXISTS` and `ALTER TABLE` with `ADD COLUMN` are safe to re-run — the column add will fail if it already exists, which is acceptable for this migration).

Verify with:
```sql
DESCRIBE caisse_sessions;
DESCRIBE caisse_operations;
DESCRIBE sales;
```

Expected: `caisse_sessions` and `caisse_operations` tables exist. `sales` table has a `session_id` column of type `int unsigned`, nullable, with a foreign key to `caisse_sessions`.

---

### Task 2: Créer le modèle `CaisseSession.php`

**Files:**
- Create: `models/CaisseSession.php`

- [ ] **Step 1: Create the model file**

Create `models/CaisseSession.php`:

```php
<?php
// models/CaisseSession.php

class CaisseSession extends BaseModel {
    protected string $table = 'caisse_sessions';

    /**
     * Retourne la session ouverte pour une caisse donnée.
     */
    public function getActiveSession(int $caisseId): ?array {
        return $this->queryOne(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             WHERE cs.caisse_id = ? AND cs.status = 'open'
             ORDER BY cs.id DESC LIMIT 1",
            [$caisseId]
        );
    }

    /**
     * Retourne la session ouverte par l'utilisateur courant dans son magasin.
     */
    public function getActiveSessionForUser(int $userId, int $storeId): ?array {
        return $this->queryOne(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             WHERE cs.user_id = ? AND cs.store_id = ? AND cs.status = 'open'
             ORDER BY cs.id DESC LIMIT 1",
            [$userId, $storeId]
        );
    }

    /**
     * Ouvre une nouvelle session de caisse.
     * Retourne l'ID de la session créée.
     */
    public function open(int $caisseId, int $storeId, int $userId, float $openingBalance): int {
        return $this->insert([
            'caisse_id'       => $caisseId,
            'store_id'        => $storeId,
            'user_id'         => $userId,
            'opening_balance' => $openingBalance,
            'opening_time'    => date('Y-m-d H:i:s'),
            'status'          => 'open',
        ]);
    }

    /**
     * Ferme une session de caisse.
     * Calcule le solde attendu et l'écart.
     */
    public function close(int $sessionId, float $actualBalance, int $closedBy, ?string $notes = null): bool {
        $summary = $this->getSessionSummary($sessionId);
        $expected = $summary['expected_balance'];

        $data = [
            'closing_balance_expected' => $expected,
            'closing_balance_actual'   => $actualBalance,
            'closing_discrepancy'      => $actualBalance - $expected,
            'closed_by'                => $closedBy,
            'closing_time'             => date('Y-m-d H:i:s'),
            'status'                   => 'closed',
        ];
        if ($notes !== null) {
            $data['notes'] = $notes;
        }
        return $this->update($sessionId, $data);
    }

    /**
     * Résumé complet d'une session pour la fermeture et le rapport Z.
     * Retourne opening_balance, cash_sales, deposits, withdrawals, expected_balance.
     */
    public function getSessionSummary(int $sessionId): array {
        $session = $this->find($sessionId);
        if (!$session) {
            return [
                'opening_balance' => 0,
                'cash_sales'      => 0,
                'deposits'        => 0,
                'withdrawals'     => 0,
                'expected_balance'=> 0,
            ];
        }

        // Somme des ventes en espèces (inclut cash, mobile_money, orange_money, momo — pas credit)
        $cashSales = $this->queryOne(
            "SELECT COALESCE(SUM(total_amount), 0) as total
             FROM sales
             WHERE session_id = ? AND status = 'completed'
               AND payment_method IN ('cash', 'mobile_money', 'orange_money', 'momo')",
            [$sessionId]
        )['total'] ?? 0;

        // Somme des apports
        $deposits = $this->queryOne(
            "SELECT COALESCE(SUM(amount), 0) as total
             FROM caisse_operations
             WHERE session_id = ? AND type = 'deposit'",
            [$sessionId]
        )['total'] ?? 0;

        // Somme des retraits
        $withdrawals = $this->queryOne(
            "SELECT COALESCE(SUM(amount), 0) as total
             FROM caisse_operations
             WHERE session_id = ? AND type = 'withdrawal'",
            [$sessionId]
        )['total'] ?? 0;

        $openingBalance = (float)($session['opening_balance'] ?? 0);
        $expected = $openingBalance + (float)$cashSales + (float)$deposits - (float)$withdrawals;

        return [
            'opening_balance'  => $openingBalance,
            'cash_sales'       => (float)$cashSales,
            'deposits'         => (float)$deposits,
            'withdrawals'      => (float)$withdrawals,
            'expected_balance' => $expected,
        ];
    }

    /**
     * Données complètes pour le rapport Z.
     */
    public function getReportData(int $sessionId): array {
        $session = $this->queryOne(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name,
                    st.name as store_name, cu.name as closed_by_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             JOIN stores st ON cs.store_id = st.id
             LEFT JOIN users cu ON cs.closed_by = cu.id
             WHERE cs.id = ?",
            [$sessionId]
        );

        $summary = $this->getSessionSummary($sessionId);

        $operations = $this->query(
            "SELECT co.*, u.name as user_name
             FROM caisse_operations co
             JOIN users u ON co.user_id = u.id
             WHERE co.session_id = ?
             ORDER BY co.created_at ASC",
            [$sessionId]
        );

        // Ventilation par mode de paiement
        $paymentBreakdown = $this->query(
            "SELECT payment_method, COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
             FROM sales
             WHERE session_id = ? AND status = 'completed'
             GROUP BY payment_method",
            [$sessionId]
        );

        $salesCount = $this->queryOne(
            "SELECT COUNT(*) as cnt FROM sales WHERE session_id = ? AND status = 'completed'",
            [$sessionId]
        )['cnt'] ?? 0;

        return [
            'session'           => $session,
            'summary'           => $summary,
            'operations'        => $operations,
            'payment_breakdown' => $paymentBreakdown,
            'sales_count'       => (int)$salesCount,
        ];
    }

    /**
     * Historique des sessions pour un magasin, avec filtre date optionnel.
     */
    public function getHistory(int $storeId, string $date = ''): array {
        $where = "cs.store_id = ?";
        $params = [$storeId];

        if ($date) {
            $where .= " AND DATE(cs.opening_time) = ?";
            $params[] = $date;
        }

        return $this->query(
            "SELECT cs.*, u.name as user_name, c.name as caisse_name,
                    cu.name as closed_by_name
             FROM caisse_sessions cs
             JOIN users u ON cs.user_id = u.id
             JOIN caisses c ON cs.caisse_id = c.id
             LEFT JOIN users cu ON cs.closed_by = cu.id
             WHERE $where
             ORDER BY cs.opening_time DESC
             LIMIT 50",
            $params
        );
    }
}

class CaisseOperation extends BaseModel {
    protected string $table = 'caisse_operations';

    public function getBySession(int $sessionId): array {
        return $this->query(
            "SELECT co.*, u.name as user_name
             FROM caisse_operations co
             JOIN users u ON co.user_id = u.id
             WHERE co.session_id = ?
             ORDER BY co.created_at DESC",
            [$sessionId]
        );
    }

    public function addDeposit(int $sessionId, float $amount, string $reason, int $userId): int {
        return $this->insert([
            'session_id' => $sessionId,
            'type'       => 'deposit',
            'amount'     => $amount,
            'reason'     => $reason,
            'user_id'    => $userId,
        ]);
    }

    public function addWithdrawal(int $sessionId, float $amount, string $reason, int $userId): int {
        return $this->insert([
            'session_id' => $sessionId,
            'type'       => 'withdrawal',
            'amount'     => $amount,
            'reason'     => $reason,
            'user_id'    => $userId,
        ]);
    }
}
```

- [ ] **Step 2: Verify the file is syntactically valid**

```powershell
php -l "C:\xampp\htdocs\Brenshop\models\CaisseSession.php"
```

Expected: `No syntax errors detected`

---

### Task 3: Créer le contrôleur AJAX `caisse_session_ajax.php`

**Files:**
- Create: `controllers/caisse_session_ajax.php`

- [ ] **Step 1: Create the controller**

Create `controllers/caisse_session_ajax.php`:

```php
<?php
// controllers/caisse_session_ajax.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {

    // ---- OPEN ----
    case 'open':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Méthode non autorisée', null, 405);
            break;
        }
        $caisseId = (int)($_POST['caisse_id'] ?? 0);
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);

        if ($caisseId <= 0) {
            jsonResponse(false, 'Veuillez sélectionner une caisse', null, 400);
            break;
        }
        if ($openingBalance < 0) {
            jsonResponse(false, 'Le fond de caisse ne peut pas être négatif', null, 400);
            break;
        }

        $caisse = (new Caisse())->find($caisseId);
        if (!$caisse || !$caisse['is_active'] || (int)$caisse['store_id'] !== currentStoreId()) {
            jsonResponse(false, 'Caisse introuvable ou non autorisée', null, 404);
            break;
        }

        // Vérifier qu'aucune session n'est déjà ouverte sur cette caisse
        $sessionModel = new CaisseSession();
        $existing = $sessionModel->getActiveSession($caisseId);
        if ($existing) {
            // Si la session appartient au même utilisateur, la retourner (reprise)
            if ((int)$existing['user_id'] === (int)$_SESSION['user_id']) {
                $_SESSION['caisse_id'] = $caisseId;
                jsonResponse(true, 'Session reprise', [
                    'session_id' => $existing['id'],
                    'caisse_name' => $caisse['name']
                ]);
                break;
            }
            // Sinon, quelqu'un d'autre utilise cette caisse
            jsonResponse(false, 'Cette caisse est déjà utilisée par ' . $existing['user_name'], null, 409);
            break;
        }

        // Créer la session
        $sessionId = $sessionModel->open(
            $caisseId,
            currentStoreId(),
            (int)$_SESSION['user_id'],
            $openingBalance
        );

        $_SESSION['caisse_id'] = $caisseId;
        jsonResponse(true, 'Caisse ouverte avec succès', [
            'session_id'  => $sessionId,
            'caisse_name' => $caisse['name']
        ]);
        break;

    // ---- CLOSE ----
    case 'close':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Méthode non autorisée', null, 405);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $caisseId = (int)($data['caisse_id'] ?? currentCaisseId());
        $actualBalance = (float)($data['closing_balance_actual'] ?? 0);
        $notes = sanitize($data['notes'] ?? '');

        $sessionModel = new CaisseSession();
        $activeSession = $sessionModel->getActiveSession($caisseId);

        if (!$activeSession) {
            jsonResponse(false, 'Aucune session ouverte pour cette caisse', null, 404);
            break;
        }

        // Vérifier permissions : le caissier qui a ouvert, ou un admin/manager
        $userRole = $_SESSION['user_role'] ?? '';
        $userId = (int)$_SESSION['user_id'];
        if ((int)$activeSession['user_id'] !== $userId && !in_array($userRole, ['admin', 'manager'])) {
            jsonResponse(false, 'Vous n\'avez pas la permission de fermer cette caisse', null, 403);
            break;
        }

        if ($actualBalance < 0) {
            jsonResponse(false, 'Le montant compté ne peut pas être négatif', null, 400);
            break;
        }

        $sessionModel->close(
            (int)$activeSession['id'],
            $actualBalance,
            $userId,
            $notes ?: null
        );

        // Nettoyer la session
        unset($_SESSION['caisse_id']);
        jsonResponse(true, 'Caisse fermée avec succès', [
            'session_id' => $activeSession['id']
        ]);
        break;

    // ---- STATUS ----
    case 'status':
        $sessionModel = new CaisseSession();
        $activeSession = $sessionModel->getActiveSessionForUser(
            (int)$_SESSION['user_id'],
            currentStoreId()
        );

        if (!$activeSession) {
            jsonResponse(true, '', [
                'has_active_session' => false,
                'session' => null,
                'summary' => null
            ]);
            break;
        }

        $summary = $sessionModel->getSessionSummary((int)$activeSession['id']);
        jsonResponse(true, '', [
            'has_active_session' => true,
            'session' => $activeSession,
            'summary' => $summary
        ]);
        break;

    // ---- SUMMARY ----
    case 'summary':
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if ($sessionId <= 0) {
            jsonResponse(false, 'Session invalide', null, 400);
            break;
        }
        $sessionModel = new CaisseSession();
        $summary = $sessionModel->getSessionSummary($sessionId);
        $session = $sessionModel->find($sessionId);
        jsonResponse(true, '', [
            'session' => $session,
            'summary' => $summary
        ]);
        break;

    // ---- REPORT (Z-reader data) ----
    case 'report':
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if ($sessionId <= 0) {
            jsonResponse(false, 'Session invalide', null, 400);
            break;
        }
        $sessionModel = new CaisseSession();
        $reportData = $sessionModel->getReportData($sessionId);
        jsonResponse(true, '', $reportData);
        break;

    // ---- ADD OPERATION (deposit/withdrawal) ----
    case 'add_operation':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Méthode non autorisée', null, 405);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $sessionId = (int)($data['session_id'] ?? 0);
        $type = $data['type'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $reason = sanitize(trim($data['reason'] ?? ''));

        if ($sessionId <= 0) {
            jsonResponse(false, 'Session invalide', null, 400);
            break;
        }
        if (!in_array($type, ['deposit', 'withdrawal'])) {
            jsonResponse(false, 'Type d\'opération invalide', null, 400);
            break;
        }
        if ($amount <= 0) {
            jsonResponse(false, 'Le montant doit être supérieur à 0', null, 400);
            break;
        }
        if (empty($reason)) {
            jsonResponse(false, 'Le motif est obligatoire', null, 400);
            break;
        }

        // Vérifier que la session est bien ouverte
        $sessionModel = new CaisseSession();
        $session = $sessionModel->find($sessionId);
        if (!$session || $session['status'] !== 'open') {
            jsonResponse(false, 'La session n\'est pas ouverte', null, 400);
            break;
        }

        $opModel = new CaisseOperation();
        if ($type === 'deposit') {
            $opModel->addDeposit($sessionId, $amount, $reason, (int)$_SESSION['user_id']);
        } else {
            $opModel->addWithdrawal($sessionId, $amount, $reason, (int)$_SESSION['user_id']);
        }

        jsonResponse(true, 'Opération enregistrée');
        break;

    // ---- HISTORY ----
    case 'history':
        $storeId = currentStoreId();
        $date = sanitize($_GET['date'] ?? '');
        $sessionModel = new CaisseSession();
        $history = $sessionModel->getHistory($storeId, $date);
        jsonResponse(true, '', $history);
        break;

    // ---- LIST CAISSES (for opening screen) ----
    case 'list_caisses':
        $storeId = currentStoreId();
        $caisses = (new Caisse())->getByStore($storeId);
        jsonResponse(true, '', $caisses);
        break;

    default:
        jsonResponse(false, 'Action inconnue', null, 400);
}
```

- [ ] **Step 2: Verify syntax**

```powershell
php -l "C:\xampp\htdocs\Brenshop\controllers\caisse_session_ajax.php"
```

Expected: `No syntax errors detected`

---

### Task 4: Mettre à jour `bootstrap.php` pour charger le nouveau modèle

**Files:**
- Modify: `includes/bootstrap.php` (line 17 area, after `Sale.php` require)

- [ ] **Step 1: Add require for CaisseSession**

In `includes/bootstrap.php`, after line 17 (`require_once __DIR__ . '/../models/Sale.php';`), add:

```php
require_once __DIR__ . '/../models/CaisseSession.php';
```

The relevant section will look like:

```php
require_once __DIR__ . '/../models/BaseModel.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/models.php';
require_once __DIR__ . '/../models/Sale.php';
require_once __DIR__ . '/../models/CaisseSession.php';
require_once __DIR__ . '/../models/Setting.php';
```

- [ ] **Step 2: Verify syntax**

```powershell
php -l "C:\xampp\htdocs\Brenshop\includes\bootstrap.php"
```

Expected: `No syntax errors detected`

---

### Task 5: Mettre à jour `helpers.php` — nouvelle logique de vérification de caisse

**Files:**
- Modify: `includes/helpers.php` (lines 72-78)

- [ ] **Step 1: Replace `isCaisseRequired()` and add `getActiveCaisseSession()`**

Replace lines 72-78:

```php
function currentCaisseId(): int {
    return (int)($_SESSION['caisse_id'] ?? 0);
}

function isCaisseRequired(): bool {
    return ($_SESSION['user_role'] ?? '') === 'cashier' && currentCaisseId() === 0;
}
```

With:

```php
function currentCaisseId(): int {
    return (int)($_SESSION['caisse_id'] ?? 0);
}

/**
 * Vérifie si l'utilisateur a une session de caisse active.
 * Tout le monde (admin, manager, caissier) doit ouvrir une caisse avant de vendre.
 */
function hasCaisseSessionOpen(): bool {
    if (currentCaisseId() === 0) return false;
    $sessionModel = new CaisseSession();
    $active = $sessionModel->getActiveSession(currentCaisseId());
    if (!$active) {
        unset($_SESSION['caisse_id']);
        return false;
    }
    return true;
}

/**
 * Retourne la session de caisse active de l'utilisateur courant,
 * ou null si aucune session n'est ouverte.
 */
function getActiveCaisseSession(): ?array {
    if (!hasCaisseSessionOpen()) return null;
    $sessionModel = new CaisseSession();
    return $sessionModel->getActiveSession(currentCaisseId());
}
```

- [ ] **Step 2: Verify syntax**

```powershell
php -l "C:\xampp\htdocs\Brenshop\includes\helpers.php"
```

Expected: `No syntax errors detected`

---

### Task 6: Mettre à jour `sale_controller.php` — lier les ventes à la session active

**Files:**
- Modify: `controllers/sale_controller.php` (lines 20-59)

- [ ] **Step 1: Add session_id lookup and include it in sale data**

Replace lines 20-39 of `sale_controller.php` (the `$saleData` array construction):

The current code at lines 20-39 is:
```php
$saleData = [
    'invoice_number'  => generateInvoiceNumber((int)($data['store_id'] ?? currentStoreId())),
    'store_id'        => (int)($data['store_id'] ?? currentStoreId()),
    'warehouse_id'    => (int)($data['warehouse_id'] ?? currentWarehouseId()),
    'caisse_id'       => !empty($data['caisse_id']) ? (int)$data['caisse_id'] : (currentCaisseId() > 0 ? currentCaisseId() : null),
    'user_id'         => (int)($_SESSION['user_id']),
    'customer_id'     => !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
    ...
];
```

Replace with:

```php
// Vérifier qu'une session de caisse est active
$activeCaisseSession = getActiveCaisseSession();
if (!$activeCaisseSession) {
    jsonResponse(false, 'Aucune caisse ouverte. Veuillez ouvrir une caisse avant de faire une vente.', null, 400);
}

$saleData = [
    'invoice_number'  => generateInvoiceNumber((int)($data['store_id'] ?? currentStoreId())),
    'store_id'        => (int)($data['store_id'] ?? currentStoreId()),
    'warehouse_id'    => (int)($data['warehouse_id'] ?? currentWarehouseId()),
    'caisse_id'       => !empty($data['caisse_id']) ? (int)$data['caisse_id'] : (currentCaisseId() > 0 ? currentCaisseId() : null),
    'session_id'      => (int)$activeCaisseSession['id'],
    'user_id'         => (int)($_SESSION['user_id']),
    'customer_id'     => !empty($data['customer_id']) ? (int)$data['customer_id'] : null,
    'subtotal'        => (float)($data['subtotal'] ?? 0),
    'discount_amount' => (float)($data['discount_amount'] ?? 0),
    'tax_amount'      => (float)($data['tax_amount'] ?? 0),
    'total_amount'    => (float)($data['total_amount'] ?? 0),
    'paid_amount'     => (float)($data['paid_amount'] ?? 0),
    'change_amount'   => (float)($data['change_amount'] ?? 0),
    'change_refunded' => !empty($data['change_refunded']) ? 1 : 0,
    'payment_method'  => in_array($data['payment_method'] ?? '', ['cash','mobile_money','orange_money','momo','card','credit','mixed'])
        ? $data['payment_method'] : 'cash',
    'status'          => 'completed',
    'notes'           => sanitize($data['notes'] ?? ''),
];
```

- [ ] **Step 2: Verify syntax**

```powershell
php -l "C:\xampp\htdocs\Brenshop\controllers\sale_controller.php"
```

Expected: `No syntax errors detected`

---

### Task 7: Modifier `pos.php` — Nouvel écran d'ouverture de caisse

**Files:**
- Modify: `views/pos.php` (lines 22-27 PHP logic + lines 311-368 caisse picker HTML/JS)

- [ ] **Step 1: Update PHP logic at top of pos.php**

Replace lines 22-27:

```php
$caisseRequired = isCaisseRequired();
$activeCaisse = null;
if (!$caisseRequired && currentCaisseId() > 0) {
    $caisseModel = new Caisse();
    $activeCaisse = $caisseModel->find(currentCaisseId());
}
```

With:

```php
// Vérifier l'état de la session de caisse
$caisseSessionOpen = hasCaisseSessionOpen();
$activeSession = null;
$activeCaisse = null;
if ($caisseSessionOpen) {
    $sessionModel = new CaisseSession();
    $activeSession = $sessionModel->getActiveSession(currentCaisseId());
    $caisseModel = new Caisse();
    $activeCaisse = $caisseModel->find(currentCaisseId());
}
```

- [ ] **Step 2: Replace caisse picker with opening screen**

Replace lines 311-368 (the entire `<?php if ($caisseRequired): ?>` ... `loadCaissePicker();` ... `<?php else: ?>` block) with:

```php
<?php if (!$caisseSessionOpen): ?>
<!-- ============================================================
     ÉCRAN D'OUVERTURE DE CAISSE — tout le monde doit ouvrir
     ============================================================ -->
<div style="display:flex;align-items:center;justify-content:center;min-height:60vh">
    <div style="background:var(--card-bg);border:1px solid var(--border);border-radius:16px;padding:2.5rem;max-width:480px;width:100%;text-align:center;box-shadow:var(--shadow-md)">
        <div style="width:64px;height:64px;border-radius:50%;background:rgba(99,102,241,0.1);display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem">
            <i class="bi bi-cash-register" style="font-size:2rem;color:var(--accent)"></i>
        </div>
        <h5 style="font-family:Manrope,sans-serif;font-weight:700;color:var(--text);margin-bottom:.5rem">Ouverture de Caisse</h5>
        <p style="color:var(--text-muted);font-size:.85rem;margin-bottom:1.5rem">Vous devez ouvrir une caisse avant de pouvoir effectuer des ventes.</p>

        <div class="mb-3 text-start">
            <label class="form-label" style="font-size:.82rem;font-weight:600">Caisse</label>
            <select id="openCaisseSelect" class="form-select" style="border-radius:8px">
                <option value="">Chargement...</option>
            </select>
        </div>

        <div class="mb-3 text-start">
            <label class="form-label" style="font-size:.82rem;font-weight:600">Fond de caisse (FCFA)</label>
            <input type="number" id="openingBalance" class="form-control" style="border-radius:8px;font-size:1.1rem;font-weight:600" placeholder="Ex: 50000" min="0" step="1" value="0">
        </div>

        <div id="openCaisseMsg" class="mb-3" style="font-size:.82rem"></div>

        <button id="openCaisseBtn" class="btn btn-primary w-100" style="border-radius:10px;padding:0.75rem;font-family:Manrope,sans-serif;font-weight:700;font-size:.92rem" onclick="openCaisse()">
            <i class="bi bi-unlock me-2"></i>Ouvrir la caisse
        </button>

        <div id="openCaisseLastSession" class="mt-3" style="font-size:.78rem;color:var(--text-muted)"></div>
    </div>
</div>

<script>
function loadOpeningScreen() {
    // Charger la liste des caisses
    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=list_caisses')
        .then(r => r.json())
        .then(data => {
            const sel = document.getElementById('openCaisseSelect');
            if (!data.success || !data.data || !data.data.length) {
                sel.innerHTML = '<option value="">Aucune caisse disponible</option>';
                document.getElementById('openCaisseBtn').disabled = true;
                return;
            }
            sel.innerHTML = '<option value="">-- Choisir une caisse --</option>' +
                data.data.map(c => '<option value="' + c.id + '">' + escHtml(c.name) + '</option>').join('');
        })
        .catch(() => {
            document.getElementById('openCaisseSelect').innerHTML = '<option value="">Erreur de chargement</option>';
        });

    // Vérifier le statut actuel (afficher dernière session fermée)
    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data && data.data.has_active_session && data.data.session) {
                // Déjà une session ouverte sur une autre caisse — rediriger
                window.location.reload();
            }
        });
}

function openCaisse() {
    const caisseId = document.getElementById('openCaisseSelect').value;
    const openingBalance = document.getElementById('openingBalance').value;
    const msgEl = document.getElementById('openCaisseMsg');
    const btn = document.getElementById('openCaisseBtn');

    if (!caisseId) {
        msgEl.innerHTML = '<div class="alert alert-warning py-1 px-2 m-0">Veuillez sélectionner une caisse.</div>';
        return;
    }
    if (openingBalance === '' || parseFloat(openingBalance) < 0) {
        msgEl.innerHTML = '<div class="alert alert-warning py-1 px-2 m-0">Veuillez entrer un fond de caisse valide.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Ouverture...';
    msgEl.innerHTML = '';

    const formData = new FormData();
    formData.append('caisse_id', caisseId);
    formData.append('opening_balance', openingBalance);

    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=open', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        } else {
            msgEl.innerHTML = '<div class="alert alert-danger py-1 px-2 m-0">' + escHtml(res.message) + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-unlock me-2"></i>Ouvrir la caisse';
        }
    })
    .catch(() => {
        msgEl.innerHTML = '<div class="alert alert-danger py-1 px-2 m-0">Erreur réseau</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-unlock me-2"></i>Ouvrir la caisse';
    });
}

loadOpeningScreen();
</script>

<?php else: ?>
```

- [ ] **Step 3: Verify the file is syntactically valid**

```powershell
php -l "C:\xampp\htdocs\Brenshop\views\pos.php"
```

Expected: `No syntax errors detected`

---

### Task 8: Modifier `pos.php` — Badge session + modale opérations (retrait/apport)

**Files:**
- Modify: `views/pos.php` (toolbar section, around lines 433-445; and the closing `<?php endif; ?>` at line 1188)

- [ ] **Step 1: Replace the toolbar caisse badge with session info**

Replace the current caisse badge in the toolbar (lines 433-437):

```php
                <?php if ($activeCaisse): ?>
                <span class="badge" style="background:rgba(99,102,241,0.12);color:var(--accent);font-size:.72rem;padding:5px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;white-space:nowrap">
                    <i class="bi bi-cash-register"></i> <?= e($activeCaisse['name']) ?>
                </span>
                <?php endif; ?>
```

With:

```php
                <?php if ($activeCaisse): ?>
                <span class="badge" id="sessionBadge" style="background:rgba(99,102,241,0.12);color:var(--accent);font-size:.72rem;padding:5px 10px;border-radius:6px;display:inline-flex;align-items:center;gap:4px;white-space:nowrap;cursor:pointer" onclick="openOperationsModal()" title="Voir la session">
                    <i class="bi bi-cash-register"></i> <?= e($activeCaisse['name']) ?>
                </span>
                <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem;padding:3px 8px;border-radius:6px" onclick="openOperationsModal()" title="Retrait / Apport">
                    <i class="bi bi-plus-slash-minus"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" style="font-size:.72rem;padding:3px 8px;border-radius:6px" onclick="openCloseModal()" title="Fermer la caisse">
                    <i class="bi bi-lock"></i>
                </button>
                <?php endif; ?>
```

- [ ] **Step 2: Add session info refresh and operations/closing modals before the `</script>` closing tag**

Before the closing `</script>` tag (around line 1185), add the session status refresh function:

```javascript
// ---- Session Status Refresh ----
let sessionRefreshInterval;

function refreshSessionStatus() {
    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data) {
                if (!data.data.has_active_session) {
                    // Session fermée — recharger pour afficher l'écran d'ouverture
                    window.location.reload();
                }
                // Mettre à jour le badge si nécessaire
                if (data.data.summary) {
                    const s = data.data.summary;
                    const badge = document.getElementById('sessionBadge');
                    if (badge) {
                        badge.title = 'Fond: ' + formatMoney(s.opening_balance) +
                            ' | Ventes: ' + formatMoney(s.cash_sales) +
                            ' | Attendu: ' + formatMoney(s.expected_balance);
                    }
                }
            }
        });
}

// Rafraîchir toutes les 30 secondes
sessionRefreshInterval = setInterval(refreshSessionStatus, 30000);
```

- [ ] **Step 3: Add Operations Modal HTML and JS**

Insert after the Payment Modal `</div>` (after line 571, before the `<!-- TICKET MODAL -->` comment):

```html
<!-- OPERATIONS MODAL (Retrait/Apport) -->
<div class="modal fade" id="operationsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:Manrope,sans-serif;font-weight:700">
                    <i class="bi bi-plus-slash-minus me-2"></i>Opération sur caisse
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Session info header -->
                <div class="p-3 rounded-3 mb-3" style="background:var(--body-bg)" id="opsSessionInfo">
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Fond de caisse</span><strong id="opsOpeningBalance">-</strong></div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Ventes</span><strong id="opsCashSales">-</strong></div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Apports</span><strong id="opsDeposits" style="color:var(--accent2)">-</strong></div>
                    <div class="d-flex justify-content-between mb-1" style="font-size:.82rem"><span>Retraits</span><strong id="opsWithdrawals" style="color:var(--danger)">-</strong></div>
                    <div class="d-flex justify-content-between pt-1" style="font-size:.85rem;border-top:1px solid var(--border)"><span>Solde attendu</span><strong id="opsExpected">-</strong></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.82rem">Type d'opération</label>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="payment-option active" data-optype="deposit" onclick="selectOpType('deposit')" style="cursor:pointer">
                                <i class="bi bi-arrow-down-circle" style="color:var(--accent2);font-size:1.3rem"></i>
                                <span>Apport (ajout)</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="payment-option" data-optype="withdrawal" onclick="selectOpType('withdrawal')" style="cursor:pointer">
                                <i class="bi bi-arrow-up-circle" style="color:var(--danger);font-size:1.3rem"></i>
                                <span>Retrait</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.82rem">Montant (FCFA)</label>
                    <input type="number" id="opAmount" class="form-control" style="border-radius:8px;font-size:1.1rem;font-weight:600" placeholder="0" min="1" step="1">
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:.82rem">Motif <span class="text-danger">*</span></label>
                    <input type="text" id="opReason" class="form-control" style="border-radius:8px" placeholder="Ex: Achat fournitures, Ajout monnaie...">
                </div>

                <div id="opsMsg" style="font-size:.82rem"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-primary px-4" id="saveOpBtn" onclick="saveOperation()" style="border-radius:8px">
                    <i class="bi bi-check-circle me-2"></i>Enregistrer
                </button>
            </div>
        </div>
    </div>
</div>
```

And the JavaScript:

```javascript
// ---- Operations Modal ----
let selectedOpType = 'deposit';
let opsModalInstance = null;

function selectOpType(type) {
    selectedOpType = type;
    document.querySelectorAll('.payment-option[data-optype]').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-optype="' + type + '"]').classList.add('active');
}

function openOperationsModal() {
    // Charger le résumé de la session
    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
        .then(r => r.json())
        .then(data => {
            if (data.success && data.data && data.data.summary) {
                const s = data.data.summary;
                document.getElementById('opsOpeningBalance').textContent = formatMoney(s.opening_balance);
                document.getElementById('opsCashSales').textContent = formatMoney(s.cash_sales);
                document.getElementById('opsDeposits').textContent = '+' + formatMoney(s.deposits);
                document.getElementById('opsWithdrawals').textContent = '-' + formatMoney(s.withdrawals);
                document.getElementById('opsExpected').textContent = formatMoney(s.expected_balance);
            }
        });

    // Reset form
    selectedOpType = 'deposit';
    document.querySelectorAll('.payment-option[data-optype]').forEach(el => el.classList.remove('active'));
    document.querySelector('[data-optype="deposit"]').classList.add('active');
    document.getElementById('opAmount').value = '';
    document.getElementById('opReason').value = '';
    document.getElementById('opsMsg').innerHTML = '';

    opsModalInstance = new bootstrap.Modal(document.getElementById('operationsModal'));
    opsModalInstance.show();
}

function saveOperation() {
    const amount = parseFloat(document.getElementById('opAmount').value);
    const reason = document.getElementById('opReason').value.trim();
    const msgEl = document.getElementById('opsMsg');

    if (!amount || amount <= 0) {
        msgEl.innerHTML = '<div class="alert alert-warning py-1 px-2 m-0">Veuillez entrer un montant valide.</div>';
        return;
    }
    if (!reason) {
        msgEl.innerHTML = '<div class="alert alert-warning py-1 px-2 m-0">Le motif est obligatoire.</div>';
        return;
    }

    const btn = document.getElementById('saveOpBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Enregistrement...';

    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data || !data.data.has_active_session) {
                throw new Error('Aucune session active');
            }
            const sessionId = data.data.session.id;

            return fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=add_operation', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    session_id: sessionId,
                    type: selectedOpType,
                    amount: amount,
                    reason: reason
                })
            });
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                opsModalInstance.hide();
                showToast('Opération enregistrée avec succès', 'success');
            } else {
                msgEl.innerHTML = '<div class="alert alert-danger py-1 px-2 m-0">' + escHtml(res.message) + '</div>';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Enregistrer';
            }
        })
        .catch(err => {
            msgEl.innerHTML = '<div class="alert alert-danger py-1 px-2 m-0">' + escHtml(err.message) + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Enregistrer';
        });
}
```

---

### Task 9: Modifier `pos.php` — Modale de fermeture et rapport Z

**Files:**
- Modify: `views/pos.php` (after the operations modal HTML, before TICKET MODAL comment)

- [ ] **Step 1: Add closing modal HTML**

Insert after the operations modal `</div>` (after the new operations modal, before `<!-- TICKET MODAL -->`):

```html
<!-- CLOSE CAISSE MODAL -->
<div class="modal fade" id="closeCaisseModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" style="font-family:Manrope,sans-serif;font-weight:700">
                    <i class="bi bi-lock me-2"></i>Fermeture de Caisse
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Résumé -->
                <div class="p-3 rounded-3 mb-3" style="background:var(--body-bg)" id="closeSummary">
                    <div style="font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem" id="closeCaisseInfo">Chargement...</div>
                    <table style="width:100%;font-size:.85rem">
                        <tr><td style="padding:2px 0">Fond de caisse</td><td class="text-end fw-semibold" id="closeOpeningBalance">-</td></tr>
                        <tr><td style="padding:2px 0">Ventes</td><td class="text-end fw-semibold" id="closeCashSales">-</td></tr>
                        <tr><td style="padding:2px 0">Apports</td><td class="text-end fw-semibold" style="color:var(--accent2)" id="closeDeposits">-</td></tr>
                        <tr><td style="padding:2px 0">Retraits</td><td class="text-end fw-semibold" style="color:var(--danger)" id="closeWithdrawals">-</td></tr>
                        <tr style="border-top:2px solid var(--border)"><td style="padding:4px 0;font-weight:700">Solde attendu</td><td class="text-end fw-bold" style="font-size:.95rem;color:var(--accent)" id="closeExpected">-</td></tr>
                    </table>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Montant compté (FCFA) <span class="text-danger">*</span></label>
                    <input type="number" id="closeActualBalance" class="form-control" style="border-radius:8px;font-size:1.1rem;font-weight:600" placeholder="Montant physique compté" min="0" step="1" oninput="updateCloseDiscrepancy()">
                </div>

                <div class="mb-3 p-2 rounded-2 text-center fw-bold" id="closeDiscrepancy" style="font-size:.95rem;display:none"></div>

                <div class="mb-3">
                    <label class="form-label">Note (optionnel)</label>
                    <textarea id="closeNotes" class="form-control" rows="2" style="resize:none" placeholder="Remarques de fermeture..."></textarea>
                </div>

                <div id="closeMsg" style="font-size:.82rem"></div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Annuler</button>
                <button class="btn btn-danger px-4" id="confirmCloseBtn" onclick="confirmCloseCaisse()" style="border-radius:8px">
                    <i class="bi bi-lock-fill me-2"></i>Fermer et imprimer rapport
                </button>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Add closing and Z-report JavaScript**

```javascript
// ---- Close Caisse ----
let closeModalInstance = null;
let closeSessionId = null;
let closeExpectedBalance = 0;

function openCloseModal() {
    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=status')
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data || !data.data.has_active_session) {
                showToast('Aucune session de caisse active', 'warning');
                return;
            }
            const session = data.data.session;
            const summary = data.data.summary;
            closeSessionId = session.id;
            closeExpectedBalance = summary.expected_balance;

            document.getElementById('closeCaisseInfo').textContent =
                escHtml(session.caisse_name) + ' — Ouverte depuis ' +
                new Date(session.opening_time).toLocaleTimeString('fr-FR', {hour:'2-digit',minute:'2-digit'});
            document.getElementById('closeOpeningBalance').textContent = formatMoney(summary.opening_balance);
            document.getElementById('closeCashSales').textContent = '+' + formatMoney(summary.cash_sales);
            document.getElementById('closeDeposits').textContent = '+' + formatMoney(summary.deposits);
            document.getElementById('closeWithdrawals').textContent = '-' + formatMoney(summary.withdrawals);
            document.getElementById('closeExpected').textContent = formatMoney(summary.expected_balance);

            document.getElementById('closeActualBalance').value = '';
            document.getElementById('closeNotes').value = '';
            document.getElementById('closeDiscrepancy').style.display = 'none';
            document.getElementById('closeMsg').innerHTML = '';
            document.getElementById('confirmCloseBtn').disabled = false;

            closeModalInstance = new bootstrap.Modal(document.getElementById('closeCaisseModal'));
            closeModalInstance.show();
        });
}

function updateCloseDiscrepancy() {
    const actual = parseFloat(document.getElementById('closeActualBalance').value);
    const el = document.getElementById('closeDiscrepancy');
    if (isNaN(actual) || document.getElementById('closeActualBalance').value === '') {
        el.style.display = 'none';
        return;
    }
    const diff = actual - closeExpectedBalance;
    el.style.display = '';
    if (diff >= 0) {
        el.className = 'mb-3 p-2 rounded-2 text-center fw-bold';
        el.style.cssText = 'background:rgba(16,185,129,0.1);color:var(--accent2);font-size:.95rem';
        el.textContent = 'Écart : +' + formatMoney(diff);
    } else {
        el.className = 'mb-3 p-2 rounded-2 text-center fw-bold';
        el.style.cssText = 'background:rgba(239,68,68,0.1);color:var(--danger);font-size:.95rem';
        el.textContent = 'Écart : ' + formatMoney(diff);
    }
}

function confirmCloseCaisse() {
    const actualBalance = document.getElementById('closeActualBalance').value;
    const notes = document.getElementById('closeNotes').value;
    const msgEl = document.getElementById('closeMsg');
    const btn = document.getElementById('confirmCloseBtn');

    if (actualBalance === '' || parseFloat(actualBalance) < 0) {
        msgEl.innerHTML = '<div class="alert alert-warning py-1 px-2 m-0">Veuillez entrer le montant compté.</div>';
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Fermeture...';

    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=close', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            caisse_id: <?= currentCaisseId() ?>,
            closing_balance_actual: parseFloat(actualBalance),
            notes: notes
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            closeModalInstance.hide();
            showZReport(res.data.session_id);
        } else {
            msgEl.innerHTML = '<div class="alert alert-danger py-1 px-2 m-0">' + escHtml(res.message) + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Fermer et imprimer rapport';
        }
    })
    .catch(() => {
        msgEl.innerHTML = '<div class="alert alert-danger py-1 px-2 m-0">Erreur réseau</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-lock-fill me-2"></i>Fermer et imprimer rapport';
    });
}

// ---- Z-Report Modal ----
let zReportInstance = null;

function showZReport(sessionId) {
    document.getElementById('ticketModalBody').innerHTML = '<div class="text-center py-3 text-muted"><div class="spinner-border spinner-border-sm mb-2"></div><br>Génération du rapport Z...</div>';

    fetch(BASE_URL + '/controllers/caisse_session_ajax.php?action=report&session_id=' + sessionId)
        .then(r => r.json())
        .then(data => {
            if (!data.success || !data.data) {
                document.getElementById('ticketModalBody').innerHTML = '<div class="alert alert-danger m-0">Erreur lors de la génération du rapport.</div>';
                return;
            }
            const d = data.data;
            const session = d.session || {};
            const summary = d.summary || {};
            const operations = d.operations || [];
            const breakdown = d.payment_breakdown || [];

            let html = '<div class="ticket-wrapper" style="font-family:\'DM Mono\',monospace;font-size:.72rem;background:#fff;color:#1a2236;padding:0.5rem">';

            // Header
            html += '<div class="ticket-header" style="text-align:center;border-bottom:1px dashed #999;padding-bottom:.5rem;margin-bottom:.5rem">';
            html += '<div style="font-weight:700;font-size:.85rem">' + escHtml(d.session.store_name || 'BRENSHOP') + '</div>';
            html += '<div style="font-size:.72rem">Rapport de Caisse (Z)</div>';
            html += '</div>';

            // Info
            html += '<div style="margin-bottom:.5rem">';
            html += '<div>Caisse : ' + escHtml(d.session.caisse_name || '-') + '</div>';
            html += '<div>Caissier : ' + escHtml(d.session.user_name || '-') + '</div>';
            html += '<div>Ouverture : ' + (session.opening_time ? new Date(session.opening_time).toLocaleString('fr-FR') : '-') + '</div>';
            html += '<div>Fermeture : ' + (session.closing_time ? new Date(session.closing_time).toLocaleString('fr-FR') : '-') + '</div>';
            html += '</div>';

            html += '<div class="ticket-divider"></div>';

            // Totaux
            html += '<div style="margin-bottom:.5rem">';
            html += '<div class="ticket-row"><span>Fond de caisse</span><span>' + formatMoney(summary.opening_balance) + '</span></div>';
            html += '<div class="ticket-row"><span>Ventes</span><span>+' + formatMoney(summary.cash_sales) + '</span></div>';
            html += '<div class="ticket-row"><span>Apports</span><span>+' + formatMoney(summary.deposits) + '</span></div>';
            html += '<div class="ticket-row"><span>Retraits</span><span>-' + formatMoney(summary.withdrawals) + '</span></div>';
            html += '</div>';

            html += '<div class="ticket-divider"></div>';

            html += '<div class="ticket-row ticket-total"><span>Solde attendu</span><span>' + formatMoney(summary.expected_balance) + '</span></div>';
            html += '<div class="ticket-row ticket-total"><span>Solde compté</span><span>' + formatMoney(parseFloat(session.closing_balance_actual || 0)) + '</span></div>';
            const disc = parseFloat(session.closing_discrepancy || 0);
            html += '<div class="ticket-row ticket-total"><span>Écart</span><span style="color:' + (disc >= 0 ? '#10B981' : '#EF4444') + '">' + (disc >= 0 ? '+' : '') + formatMoney(disc) + '</span></div>';

            html += '<div class="ticket-divider"></div>';

            // Nb ventes
            html += '<div class="ticket-row"><span>Nb ventes</span><span>' + (d.sales_count || 0) + '</span></div>';

            // Détail par mode de paiement
            if (breakdown.length > 0) {
                html += '<div style="margin-top:.3rem">';
                html += '<div style="font-weight:600;margin-bottom:.2rem">Paiements :</div>';
                breakdown.forEach(function(p) {
                    const label = {cash:'Espèces',mobile_money:'Orange Money',orange_money:'Orange Money',momo:'MoMo',card:'Carte',credit:'Crédit'}[p.payment_method] || p.payment_method;
                    html += '<div class="ticket-row"><span>  ' + label + ' (' + p.count + ')</span><span>' + formatMoney(parseFloat(p.total)) + '</span></div>';
                });
                html += '</div>';
            }

            // Opérations
            if (operations.length > 0) {
                html += '<div class="ticket-divider"></div>';
                html += '<div style="font-weight:600;margin-bottom:.2rem">Opérations :</div>';
                operations.forEach(function(op) {
                    const prefix = op.type === 'deposit' ? '+' : '-';
                    html += '<div class="ticket-row"><span>' + prefix + formatMoney(parseFloat(op.amount)) + ' (' + escHtml(op.reason) + ')</span><span style="font-size:.65rem">' + escHtml(op.user_name || '') + '</span></div>';
                });
            }

            if (session.notes) {
                html += '<div class="ticket-divider"></div>';
                html += '<div style="font-size:.65rem;font-style:italic">Note : ' + escHtml(session.notes) + '</div>';
            }

            html += '<div class="ticket-divider"></div>';
            html += '<div style="text-align:center;font-size:.65rem;margin-top:.4rem"><?= e($appSettings['receipt_footer'] ?? 'Merci de votre confiance !') ?></div>';

            html += '</div>';

            document.getElementById('ticketModalBody').innerHTML = html;
            document.getElementById('btnPrintTicket').style.display = '';
            document.getElementById('btnPrintTicket').onclick = function() { window.print(); };
        })
        .catch(() => {
            document.getElementById('ticketModalBody').innerHTML = '<div class="alert alert-danger m-0">Erreur réseau.</div>';
        });

    zReportInstance = new bootstrap.Modal(document.getElementById('ticketModal'));
    zReportInstance.show();
}

// After Z-report close, reload to show opening screen
document.getElementById('ticketModal').addEventListener('hidden.bs.modal', function() {
    window.location.reload();
});
```

- [ ] **Step 2: Update the TICKET MODAL title for Z-report**

In the existing TICKET MODAL (line 578-579), change the title to be dynamic:

Replace:
```html
<h5 class="modal-title" style="font-family:Manrope,sans-serif;font-weight:700;font-size:.95rem">
    <i class="bi bi-check-circle-fill text-success me-1"></i>Vente validée
</h5>
```

With:
```html
<h5 class="modal-title" style="font-family:Manrope,sans-serif;font-weight:700;font-size:.95rem" id="ticketModalTitle">
    <i class="bi bi-check-circle-fill text-success me-1"></i>Vente validée
</h5>
```

And update the `showTicketModal` function to set the title:
```javascript
function showTicketModal(saleId) {
    currentSaleId = saleId;
    document.getElementById('ticketModalTitle').innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i>Vente validée';
    // ... rest unchanged
```

And in `showZReport`, set the title:
```javascript
    document.getElementById('ticketModalTitle').innerHTML = '<i class="bi bi-lock-fill text-danger me-1"></i>Rapport de fermeture';
```

- [ ] **Step 3: Verify syntax**

```powershell
php -l "C:\xampp\htdocs\Brenshop\views\pos.php"
```

Expected: `No syntax errors detected`

---

### Task 10: Vérification complète — tester le flux de bout en bout

- [ ] **Step 1: Open the browser and test**

1. Navigate to `http://localhost/Brenshop/views/pos.php`
2. Log in as `caisse.douala@brenshop.cm` (password: `password`)
3. Verify: Opening screen appears with caisse selector and opening balance field
4. Select "Caisse Principale Douala", enter 50000 as fond, click "Ouvrir la caisse"
5. Verify: POS interface appears with session badge in toolbar
6. Add a product, complete a sale
7. Verify: Sale goes through successfully
8. Click the retrait/apport button, add a deposit of 10000 with reason "Ajout monnaie"
9. Verify: Operation saved successfully
10. Click "Fermer", enter physical count of 60000
11. Verify: Discrepancy shown, confirm close
12. Verify: Z-report appears, can be printed
13. After closing Z-report, verify: returns to opening screen

- [ ] **Step 2: Test edge cases**

1. Try to open a caisse with negative balance → should show error
2. Try to sell without opening a caisse (direct POST to sale_controller.php) → should return error about no open caisse
3. Open caisse A, then try to open caisse A from another session → should show "déjà utilisée"
4. Add an operation with empty reason → should show error
5. Close caisse with negative physical count → should show error

---

### Task 11: Vérification syntaxe PHP globale

- [ ] **Step 1: Check all modified files for syntax errors**

```powershell
php -l "C:\xampp\htdocs\Brenshop\models\CaisseSession.php"
php -l "C:\xampp\htdocs\Brenshop\controllers\caisse_session_ajax.php"
php -l "C:\xampp\htdocs\Brenshop\includes\bootstrap.php"
php -l "C:\xampp\htdocs\Brenshop\includes\helpers.php"
php -l "C:\xampp\htdocs\Brenshop\controllers\sale_controller.php"
php -l "C:\xampp\htdocs\Brenshop\views\pos.php"
```

Expected: All files return `No syntax errors detected`
