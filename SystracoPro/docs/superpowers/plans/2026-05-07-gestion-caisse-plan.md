# Système de Gestion de Caisse Guichetier — Plan d'Implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un système de caisse individuelle pour les guichetiers avec cycle complet (ouverture → ventes → mouvements → transferts → clôture).

**Architecture:** Page unique `modules/caisse/index.php` gérant tout le workflow (ouverture, dashboard temps réel, dépenses/recettes, transferts, clôture, supervision). 4 nouvelles tables SQL. Intégration légère dans l'existant (blocage vente sans caisse, garde déconnexion, lien sidebar).

**Tech Stack:** PHP 7.4+ procédural, MySQL, CSS existant (classes `.card`, `.btn`, `.modal`, `.filter-bar`), JS existant (`openModal`, `closeModal`, `showToast`)

---

## File Structure

| Fichier | Action | Responsabilité |
|---------|--------|---------------|
| `database.sql` | Modify | Ajout 4 tables + permission + role_permissions |
| `includes/config.php` | Modify | Fonctions `caisseOuverte()`, `requireCaisseOuverte()` |
| `modules/caisse/index.php` | Create | Page principale : tout le workflow caisse |
| `includes/header.php` | Modify | Lien sidebar "Caisse" |
| `modules/tickets/vente.php` | Modify | Blocage vente sans caisse ouverte |
| `logout.php` | Modify | Garde pause/clôture pour guichetier |

---

### Task 1: Mettre à jour database.sql avec les nouvelles tables

**Fichier :**
- Modify: `database.sql`

- [ ] **Step 1: Ajouter les 4 nouvelles tables**

Insérer ce bloc après la déclaration de `rapports_guichetiers` (après ligne 478) et avant `CREATE TABLE notifications` :

```sql
-- ── CAISSES (gestion caisse guichetier) ────────────────────
CREATE TABLE caisses (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    numero                  VARCHAR(20) NOT NULL UNIQUE,
    guichetier_id           INT NOT NULL,
    agence_id               INT NOT NULL,
    date_ouverture          DATETIME NOT NULL,
    fond_initial            DECIMAL(12,2) NOT NULL DEFAULT 0,
    statut                  ENUM('ouverte','fermee') DEFAULT 'ouverte',
    date_fermeture          DATETIME NULL,
    total_tickets_especes   DECIMAL(12,2) DEFAULT 0,
    total_tickets_om        DECIMAL(12,2) DEFAULT 0,
    total_tickets_momo      DECIMAL(12,2) DEFAULT 0,
    total_tickets_carte     DECIMAL(12,2) DEFAULT 0,
    total_tickets_cheque    DECIMAL(12,2) DEFAULT 0,
    nb_tickets_vendus       INT DEFAULT 0,
    nb_tickets_annules      INT DEFAULT 0,
    montant_annulations     DECIMAL(12,2) DEFAULT 0,
    total_depenses          DECIMAL(12,2) DEFAULT 0,
    total_autres_recettes   DECIMAL(12,2) DEFAULT 0,
    transfert_emis          DECIMAL(12,2) DEFAULT 0,
    transfert_recu          DECIMAL(12,2) DEFAULT 0,
    solde_physique          DECIMAL(12,2) DEFAULT 0,
    ecart                   DECIMAL(12,2) DEFAULT 0,
    observations            TEXT,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY(guichetier_id) REFERENCES utilisateurs(id),
    FOREIGN KEY(agence_id)     REFERENCES agences(id)
);

CREATE TABLE mouvements_caisse (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    caisse_id       INT NOT NULL,
    type            ENUM('depense','recette') NOT NULL,
    libelle         VARCHAR(300) NOT NULL,
    montant         DECIMAL(12,2) NOT NULL,
    mode_paiement   ENUM('especes','om','momo','carte','cheque') DEFAULT 'especes',
    date_mouvement  DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(caisse_id) REFERENCES caisses(id) ON DELETE CASCADE
);

CREATE TABLE transferts_caisse (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    caisse_source_id     INT NOT NULL,
    caisse_dest_id       INT NULL,
    guichetier_source_id INT NOT NULL,
    guichetier_dest_id   INT NOT NULL,
    montant_total        DECIMAL(12,2) NOT NULL,
    nb_tickets           INT DEFAULT 0,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY(caisse_source_id) REFERENCES caisses(id),
    FOREIGN KEY(caisse_dest_id)   REFERENCES caisses(id),
    FOREIGN KEY(guichetier_source_id) REFERENCES utilisateurs(id),
    FOREIGN KEY(guichetier_dest_id)   REFERENCES utilisateurs(id)
);

CREATE TABLE transfert_tickets (
    transfert_id    INT NOT NULL,
    ticket_id       INT NOT NULL,
    PRIMARY KEY(transfert_id, ticket_id),
    FOREIGN KEY(transfert_id) REFERENCES transferts_caisse(id) ON DELETE CASCADE,
    FOREIGN KEY(ticket_id)    REFERENCES tickets(id)
);
```

- [ ] **Step 2: Ajouter la permission caisse.manage**

Dans la section `INSERT INTO permissions`, ajouter après la dernière permission :

```sql
('caisse.manage',       'finances',       'Gérer la caisse');
```

- [ ] **Step 3: Ajouter la permission aux rôles existants**

Mettre à jour le guichetier (ligne 583) — ajouter `'caisse.manage'` :

```sql
-- guichetier
INSERT INTO role_permissions (role_id, permission_id) SELECT 5, id FROM permissions WHERE code IN ('tickets.create','tickets.view','tickets.print','bordereaux.create','bordereaux.view','bordereaux.print','bordereaux.validate','reservations.manage','transits.manage','rapports.guichet','caisse.manage');
```

Mettre à jour le chef_guichet (ligne 581) — ajouter `'caisse.manage'` :

```sql
-- chef_guichet
INSERT INTO role_permissions (role_id, permission_id) SELECT 4, id FROM permissions WHERE code IN ('tickets.view','tickets.create','tickets.cancel','tickets.print','bordereaux.view','bordereaux.validate','rapports.agence','rapports.guichet','depenses.create','versements.create','voyages.cancel','reservations.manage','transits.manage','caisse.manage');
```

Mettre à jour le chef_agence (ligne 579) — ajouter `'caisse.manage'` :

La ligne chef_agence est longue. Ajouter `,'caisse.manage'` à la fin de la liste des codes (avant la parenthèse fermante).

---

### Task 2: Ajouter les fonctions helper dans includes/config.php

**Fichier :**
- Modify: `includes/config.php` — après la ligne 51

- [ ] **Step 1: Ajouter caisseOuverte() et requireCaisseOuverte()**

Insérer après `function isGuichetier(): bool { return hasRole('guichetier'); }` :

```php
// ── CAISSE ─────────────────────────────────────────────────
function caisseOuverte(): ?array {
    global $pdo;
    if (!isLoggedIn()) return null;
    $uid = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $s = $pdo->prepare("SELECT * FROM caisses WHERE guichetier_id=? AND DATE(date_ouverture)=? AND statut='ouverte' ORDER BY id DESC LIMIT 1");
    $s->execute([$uid, $today]);
    return $s->fetch() ?: null;
}
function requireCaisseOuverte(): void {
    if (isGuichetier() && !caisseOuverte()) {
        if (isIframe()) {
            echo "<script>if(window.parent!==window){window.parent.location.href='".BASE_URL."modules/caisse/index.php';}else{window.location.href='".BASE_URL."modules/caisse/index.php';}</script>";
            exit();
        }
        flash('Vous devez ouvrir votre caisse avant de vendre des tickets.', 'danger');
        redirect(BASE_URL.'modules/caisse/index.php');
    }
}
```

- [ ] **Step 2: Vérifier la syntaxe PHP**

```bash
php -l "C:/xampp/htdocs/SystracoPro/includes/config.php"
```

Expected: `No syntax errors detected`

---

### Task 3: Ajouter le lien sidebar dans includes/header.php

**Fichier :**
- Modify: `includes/header.php` — section FINANCES (vers ligne 86-92)

- [ ] **Step 1: Ajouter "Caisse" comme premier lien de FINANCES**

Remplacer le bloc FINANCES (lignes 86-92) :

```php
    <?php if(can('caisse.manage')): ?>
    <div class="nav-lbl">FINANCES</div>
    <a href="<?= $b ?>modules/caisse/index.php" class="nav-link <?= strpos($_path,'caisse')!==false?'active':'' ?>"><i class="fas fa-cash-register"></i><span>Caisse</span></a>
    <?php endif; ?>
    <?php if(can('depenses.create')): ?>
    <?php if(!can('caisse.manage')): ?><div class="nav-lbl">FINANCES</div><?php endif; ?>
    <a href="<?= $b ?>modules/depenses/" class="nav-link <?= strpos($_path,'depenses')!==false?'active':'' ?>"><i class="fas fa-money-bill-wave"></i><span>Dépenses</span></a>
    <?php endif; ?>
    <?php if(can('versements.create')): ?>
    <a href="<?= $b ?>modules/versements/" class="nav-link <?= strpos($_path,'versements')!==false?'active':'' ?>"><i class="fas fa-university"></i><span>Versements</span></a>
    <?php endif; ?>
```

---

### Task 4: Bloquer les ventes sans caisse dans modules/tickets/vente.php

**Fichier :**
- Modify: `modules/tickets/vente.php`

- [ ] **Step 1: Ajouter la vérification de caisse avant la vente POST**

Dans la section POST vente, avant `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendre']))` (ligne ~111), ajouter :

```php
// Vérifier caisse ouverte (guichetier)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendre']) && isGuichetier()) {
    if (!caisseOuverte()) {
        ob_clean(); header('Content-Type: application/json');
        echo json_encode(['success'=>false,'error'=>'Aucune caisse ouverte. Veuillez ouvrir votre caisse avant de vendre.','caisse_required'=>true,'caisse_url'=>BASE_URL.'modules/caisse/index.php']);
        exit;
    }
}
```

Placer ce bloc juste avant le `if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendre'])) {` existant.

---

### Task 5: Créer modules/caisse/index.php — Page principale

**Fichier :**
- Create: `modules/caisse/index.php`

- [ ] **Step 1: Créer le répertoire**

```bash
mkdir -p "C:/xampp/htdocs/SystracoPro/modules/caisse"
```

- [ ] **Step 2: Écrire le fichier modules/caisse/index.php**

```php
<?php
require_once '../../includes/config.php';
requireLogin(); requirePerm('caisse.manage');

$pageTitle = 'Gestion de Caisse';
$aid = getUserAgenceId();
$uid = $_SESSION['user_id'];
$today = date('Y-m-d');

// Récupérer la caisse du jour
$caisse = null;
$s = $pdo->prepare("SELECT * FROM caisses WHERE guichetier_id=? AND DATE(date_ouverture)=? ORDER BY id DESC LIMIT 1");
$s->execute([$uid, $today]);
$caisse = $s->fetch();

// Transferts en attente
$transfPend = null;
if (!$caisse || $caisse['statut'] === 'ouverte') {
    $tp = $pdo->prepare("SELECT COALESCE(SUM(tc.montant_total),0) as total, COUNT(DISTINCT tc.id) as nb FROM transferts_caisse tc WHERE tc.guichetier_dest_id=? AND tc.caisse_dest_id IS NULL");
    $tp->execute([$uid]);
    $transfPend = $tp->fetch();
    if (!$transfPend['total']) $transfPend = null;
}

// Supervision
$vue_guichetier_id = $uid;
$guichetiers = [];
if (isChefGuichet() || isChefAgence() || isAdmin()) {
    $wg = $aid ? "AND u.agence_id=$aid" : "";
    $guichetiers = $pdo->query("SELECT u.id, CONCAT(u.prenom,' ',u.nom) as nom, u.username FROM utilisateurs u JOIN roles r ON u.role_id=r.id WHERE r.code IN ('guichetier','chef_guichet') $wg ORDER BY u.nom")->fetchAll();
    if (isset($_GET['guichetier_id']) && (int)$_GET['guichetier_id'] > 0) {
        $vue_guichetier_id = (int)$_GET['guichetier_id'];
        $s2 = $pdo->prepare("SELECT * FROM caisses WHERE guichetier_id=? AND DATE(date_ouverture)=? ORDER BY id DESC LIMIT 1");
        $s2->execute([$vue_guichetier_id, $today]);
        $caisse = $s2->fetch();
    }
}
$is_own = ($vue_guichetier_id == $uid);

// Stats tickets (temps réel)
$stats = ['especes'=>0,'om'=>0,'momo'=>0,'carte'=>0,'cheque'=>0,'nb_vendu'=>0,'nb_annule'=>0,'annule_m'=>0,'total'=>0];
if ($caisse) {
    $dateCaisse = date('Y-m-d', strtotime($caisse['date_ouverture']));
    $st = $pdo->prepare("SELECT mode_paiement, COUNT(*) as nb, COALESCE(SUM(montant_total),0) as total FROM tickets WHERE guichetier_id=? AND DATE(date_vente)=? AND statut='vendu' GROUP BY mode_paiement");
    $st->execute([$caisse['guichetier_id'], $dateCaisse]);
    foreach ($st->fetchAll() as $row) {
        $m = $row['mode_paiement'];
        if (isset($stats[$m])) $stats[$m] = (float)$row['total'];
        $stats['nb_vendu'] += (int)$row['nb'];
        $stats['total'] += (float)$row['total'];
    }
    $an = $pdo->prepare("SELECT COUNT(*) as nb, COALESCE(SUM(montant_total),0) as total FROM tickets WHERE annule_par=? AND DATE(date_annulation)=? AND statut='annule'");
    $an->execute([$caisse['guichetier_id'], $dateCaisse]);
    $arow = $an->fetch();
    $stats['nb_annule'] = (int)$arow['nb'];
    $stats['annule_m'] = (float)$arow['total'];
}

// Mouvements manuels
$mvmts = ['depenses'=>0,'depenses_esp'=>0,'recettes'=>0,'recettes_esp'=>0];
if ($caisse) {
    $mm = $pdo->prepare("SELECT type, mode_paiement, COALESCE(SUM(montant),0) as total FROM mouvements_caisse WHERE caisse_id=? GROUP BY type, mode_paiement");
    $mm->execute([$caisse['id']]);
    foreach ($mm->fetchAll() as $row) {
        if ($row['type'] === 'depense') {
            $mvmts['depenses'] += (float)$row['total'];
            if ($row['mode_paiement'] === 'especes') $mvmts['depenses_esp'] += (float)$row['total'];
        } else {
            $mvmts['recettes'] += (float)$row['total'];
            if ($row['mode_paiement'] === 'especes') $mvmts['recettes_esp'] += (float)$row['total'];
        }
    }
}

// Solde théorique espèces
$solde_theorique = 0;
if ($caisse) {
    $solde_theorique = (float)$caisse['fond_initial']
        + (float)$caisse['transfert_recu']
        + $stats['especes']
        + $mvmts['recettes_esp']
        - $mvmts['depenses_esp'];
}

// ═══ ACTIONS POST ═══════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();

    // OUVRIR CAISSE
    if (isset($_POST['ouvrir_caisse'])) {
        if (caisseOuverte()) {
            flash('Vous avez deja une caisse ouverte aujourd\'hui.', 'warning');
        } else {
            $fond = (float)($_POST['fond_initial'] ?? 0);
            $pdo->beginTransaction();
            try {
                $num = genNumero($pdo, 'caisses', 'numero', 'CAISSE', getUserAgenceCode());
                $transf_recu = $transfPend ? (float)$transfPend['total'] : 0;
                $ins = $pdo->prepare("INSERT INTO caisses (numero, guichetier_id, agence_id, date_ouverture, fond_initial, transfert_recu, statut) VALUES (?,?,?,NOW(),?,?,'ouverte')");
                $ins->execute([$num, $uid, $aid ?: currentAgenceId() ?: 1, $fond, $transf_recu]);
                $nc_id = $pdo->lastInsertId();

                if ($transfPend) {
                    $pdo->prepare("UPDATE transferts_caisse SET caisse_dest_id=? WHERE guichetier_dest_id=? AND caisse_dest_id IS NULL")->execute([$nc_id, $uid]);
                }
                $pdo->commit();
                logAction($pdo, 'ouverture_caisse', 'caisse', "Caisse $num ouverte — Fond: ".money($fond));
                flash('Caisse ouverte avec succes !', 'success');
            } catch(Exception $e) {
                $pdo->rollBack();
                flash('Erreur: '.$e->getMessage(), 'danger');
            }
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }

    // AJOUTER DEPENSE ou RECETTE
    if (isset($_POST['ajout_mouvement'])) {
        if (!$caisse) {
            flash('Aucune caisse ouverte.', 'danger');
        } else {
            $type = $_POST['type_mvt'];
            $libelle = mb_strtoupper(trim($_POST['libelle'] ?? ''));
            $montant = (float)($_POST['montant'] ?? 0);
            $mode = $_POST['mode_paiement'] ?? 'especes';
            if ($libelle && $montant > 0) {
                $pdo->prepare("INSERT INTO mouvements_caisse (caisse_id, type, libelle, montant, mode_paiement) VALUES (?,?,?,?,?)")
                    ->execute([$caisse['id'], $type, $libelle, $montant, $mode]);
                $label = $type === 'depense' ? 'Depense' : 'Recette';
                flash("$label enregistree : $libelle — ".money($montant), 'success');
            } else {
                flash('Libelle et montant obligatoires.', 'danger');
            }
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }

    // CLÔTURER
    if (isset($_POST['cloturer'])) {
        if (!$caisse || $caisse['statut'] !== 'ouverte') {
            flash('Aucune caisse ouverte a cloturer.', 'danger');
        } else {
            $solde_physique = (float)($_POST['solde_physique'] ?? 0);
            $obs = trim($_POST['observations'] ?? '');
            $transfert_id = (int)($_POST['transfert_to'] ?? 0);
            $tickets_to_transfer = $_POST['tickets_transfer'] ?? [];

            $t_emis_total = 0;
            $nb_transf = 0;

            $pdo->beginTransaction();
            try {
                // Transfert de tickets si specifie
                if ($transfert_id > 0 && !empty($tickets_to_transfer)) {
                    $ids = array_map('intval', $tickets_to_transfer);
                    $in = implode(',', $ids);
                    $tm = $pdo->query("SELECT COALESCE(SUM(montant_total),0) FROM tickets WHERE id IN ($in) AND statut IN ('vendu','reserve')")->fetchColumn();
                    $t_emis_total = (float)$tm;
                    $nb_transf = count($ids);

                    $pdo->prepare("INSERT INTO transferts_caisse (caisse_source_id, caisse_dest_id, guichetier_source_id, guichetier_dest_id, montant_total, nb_tickets) VALUES (?,NULL,?,?,?,?)")
                        ->execute([$caisse['id'], $uid, $transfert_id, $t_emis_total, $nb_transf]);
                    $transfId = $pdo->lastInsertId();

                    $insT = $pdo->prepare("INSERT INTO transfert_tickets (transfert_id, ticket_id) VALUES ($transfId, ?)");
                    foreach ($ids as $tid) {
                        $insT->execute([$tid]);
                        $pdo->prepare("UPDATE tickets SET guichetier_id=? WHERE id=?")->execute([$transfert_id, $tid]);
                    }

                    // Mettre a jour le transfert_emis de la caisse source immediatement
                    $pdo->prepare("UPDATE caisses SET transfert_emis=transfert_emis+? WHERE id=?")->execute([$t_emis_total, $caisse['id']]);
                }

                // Calculer les totaux finaux
                $dateCaisse = date('Y-m-d', strtotime($caisse['date_ouverture']));

                $tt = $pdo->prepare("SELECT mode_paiement, COALESCE(SUM(montant_total),0) as total FROM tickets WHERE guichetier_id=? AND DATE(date_vente)=? AND statut='vendu' GROUP BY mode_paiement");
                $tt->execute([$caisse['guichetier_id'], $dateCaisse]);
                $totaux = ['especes'=>0,'om'=>0,'momo'=>0,'carte'=>0,'cheque'=>0];
                foreach ($tt->fetchAll() as $r) { if (isset($totaux[$r['mode_paiement']])) $totaux[$r['mode_paiement']] = (float)$r['total']; }

                $nbV = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE guichetier_id=? AND DATE(date_vente)=? AND statut='vendu'");
                $nbV->execute([$caisse['guichetier_id'], $dateCaisse]);
                $nbVendus = (int)$nbV->fetchColumn();

                $nbA = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(montant_total),0) FROM tickets WHERE annule_par=? AND DATE(date_annulation)=? AND statut='annule'");
                $nbA->execute([$caisse['guichetier_id'], $dateCaisse]);
                $annData = $nbA->fetch();

                $dep = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id=? AND type='depense'");
                $dep->execute([$caisse['id']]); $totDep = (float)$dep->fetchColumn();

                $rec = $pdo->prepare("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id=? AND type='recette'");
                $rec->execute([$caisse['id']]); $totRec = (float)$rec->fetchColumn();

                // Recalculer solde_theorique apres transfert
                $st_final = (float)$caisse['fond_initial'] + (float)$caisse['transfert_recu']
                    + $totaux['especes'] + 0 - 0;
                // Simplification: recalcul exact
                $rec_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='recette' AND mode_paiement='especes'")->fetchColumn());
                $dep_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='depense' AND mode_paiement='especes'")->fetchColumn());
                $st_final = (float)$caisse['fond_initial'] + (float)$caisse['transfert_recu']
                    + $totaux['especes'] + $rec_esp - $dep_esp;

                $ecart = $solde_physique - $st_final;

                $upd = $pdo->prepare("UPDATE caisses SET statut='fermee', date_fermeture=NOW(), total_tickets_especes=?, total_tickets_om=?, total_tickets_momo=?, total_tickets_carte=?, total_tickets_cheque=?, nb_tickets_vendus=?, nb_tickets_annules=?, montant_annulations=?, total_depenses=?, total_autres_recettes=?, solde_physique=?, ecart=?, observations=? WHERE id=?");
                $upd->execute([$totaux['especes'], $totaux['om'], $totaux['momo'], $totaux['carte'], $totaux['cheque'], $nbVendus, (int)$annData[0], (float)$annData[1], $totDep, $totRec, $solde_physique, $ecart, $obs, $caisse['id']]);

                $pdo->commit();
                logAction($pdo, 'cloture_caisse', 'caisse', "Caisse #{$caisse['id']} cloturee — Ecart: ".money($ecart));
                flash('Caisse cloturee avec succes !', 'success');
            } catch(Exception $e) {
                $pdo->rollBack();
                flash('Erreur cloture: '.$e->getMessage(), 'danger');
            }
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }

    // CLÔTURE PAR SUPERVISEUR (chef)
    if (isset($_POST['cloturer_superviseur']) && (isChefGuichet() || isChefAgence() || isAdmin())) {
        $caisse_id = (int)($_POST['caisse_id'] ?? 0);
        $cs = $caisse_id ? $pdo->prepare("SELECT * FROM caisses WHERE id=? AND statut='ouverte'") : null;
        if ($cs) {
            $cs->execute([$caisse_id]);
            $c = $cs->fetch();
            if ($c) {
                $dateCaisse = date('Y-m-d', strtotime($c['date_ouverture']));
                $upd = $pdo->prepare("UPDATE caisses SET statut='fermee', date_fermeture=NOW(), observations=CONCAT(IFNULL(observations,''),' [Cloture forcee par superviseur le ".date('d/m/Y H:i')."]') WHERE id=?");
                $upd->execute([$c['id']]);
                logAction($pdo, 'cloture_forcee_caisse', 'caisse', "Caisse #{$c['id']} cloturee par superviseur");
                flash('Caisse cloturee (forcee).', 'warning');
            }
        }
        redirect(BASE_URL.'modules/caisse/index.php');
    }
}

// Recharger la page si caisse fermee (pour afficher recap)
if ($caisse && $caisse['statut'] === 'fermee') {
    $rec_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='recette' AND mode_paiement='especes'")->fetchColumn());
    $dep_esp = (float)($pdo->query("SELECT COALESCE(SUM(montant),0) FROM mouvements_caisse WHERE caisse_id={$caisse['id']} AND type='depense' AND mode_paiement='especes'")->fetchColumn());
    $solde_theorique = (float)$caisse['fond_initial'] + (float)$caisse['transfert_recu']
        + (float)$caisse['total_tickets_especes'] + $rec_esp - $dep_esp;
}

// Autres guichetiers (pour le select de transfert)
$autres_guichetiers = $pdo->query("SELECT u.id, CONCAT(u.prenom,' ',u.nom) as nom, u.username, a.ville FROM utilisateurs u JOIN roles r ON u.role_id=r.id LEFT JOIN agences a ON u.agence_id=a.id WHERE r.code IN ('guichetier','chef_guichet') AND u.id != $uid ORDER BY u.nom")->fetchAll();

// Tickets transferables
$tickets_transferables = [];
if ($caisse && $caisse['statut'] === 'ouverte' && $is_own) {
    $ttf = $pdo->prepare("SELECT t.*, v.date_depart, a1.ville as dep, a2.ville as arr FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN agences a1 ON t.agence_depart_id=a1.id LEFT JOIN agences a2 ON t.agence_arrivee_id=a2.id WHERE t.guichetier_id=? AND t.statut IN ('vendu','reserve') AND t.voyage_id IS NOT NULL AND v.statut IN ('programme','en_cours') ORDER BY t.date_vente");
    $ttf->execute([$uid]);
    $tickets_transferables = $ttf->fetchAll();
}

include '../../includes/header.php';
?>

<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Caisse</div>

<?php if (!empty($guichetiers)): ?>
<div class="no-print card" style="margin-bottom:16px;">
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <select name="guichetier_id" class="fc" style="max-width:250px;">
        <option value="">— Ma caisse —</option>
        <?php foreach($guichetiers as $g): ?>
        <option value="<?= $g['id'] ?>" <?= $vue_guichetier_id==$g['id']?'selected':'' ?>><?= sanitize($g['nom']) ?> (@<?= sanitize($g['username']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <input type="date" name="date" class="fc" value="<?= $_GET['date'] ?? $today ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-eye"></i> Voir</button>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if (!$caisse): ?>
<!-- ETAT : AUCUNE CAISSE -->
<div class="card">
  <div class="card-body" style="text-align:center;padding:50px;">
    <i class="fas fa-cash-register" style="font-size:60px;color:var(--text3);opacity:.3;"></i>
    <h3 style="color:var(--text2);margin:16px 0 4px;">Aucune caisse ouverte aujourd'hui</h3>
    <p style="color:var(--text3);font-size:13px;margin-bottom:16px;">Vous devez ouvrir votre caisse pour commencer a vendre des tickets.</p>

    <?php if ($transfPend): ?>
    <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;padding:12px;max-width:400px;margin:0 auto 16px;text-align:left;">
      <strong><i class="fas fa-exchange-alt"></i> Transfert(s) en attente :</strong>
      <span style="font-size:16px;font-weight:700;margin-left:8px;"><?= money((float)$transfPend['total']) ?></span>
      <div style="font-size:11px;color:#92400e;margin-top:4px;">Ce montant sera automatiquement ajoute a votre caisse a l'ouverture.</div>
    </div>
    <?php endif; ?>

    <button onclick="openModal('modal-ouvrir')" class="btn btn-primary btn-lg"><i class="fas fa-door-open"></i> Ouvrir la caisse</button>
  </div>
</div>

<?php elseif ($caisse['statut'] === 'ouverte'): ?>
<!-- ETAT : CAISSE OUVERTE (DASHBOARD) -->
<div class="card" style="margin-bottom:12px;">
  <div class="card-head" style="background:#1e3a8a;color:#fff;padding:10px 16px;border-radius:8px 8px 0 0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
    <div>
      <strong style="font-size:14px;">Caisse <?= sanitize($caisse['numero']) ?></strong>
      <span style="margin-left:12px;font-size:11px;opacity:.85;">Ouverte <?= fdatetime($caisse['date_ouverture']) ?></span>
    </div>
    <span style="background:#16a34a;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px;font-weight:600;">OUVERTE</span>
  </div>
  <div class="card-body">
    <!-- Cartes resume -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(155px,1fr)); gap:10px;margin-bottom:16px;">

      <div style="background:#dbeafe;padding:10px 12px;border-radius:6px;border-left:3px solid #2563eb;">
        <div style="font-size:10px;color:#1e3a8a;text-transform:uppercase;font-weight:600;">Fond de caisse</div>
        <div style="font-size:17px;font-weight:700;"><?= money((float)$caisse['fond_initial']) ?></div>
      </div>

      <?php if((float)$caisse['transfert_recu'] > 0): ?>
      <div style="background:#fef3c7;padding:10px 12px;border-radius:6px;border-left:3px solid #f59e0b;">
        <div style="font-size:10px;color:#92400e;text-transform:uppercase;font-weight:600;">Transfert recu</div>
        <div style="font-size:17px;font-weight:700;"><?= money((float)$caisse['transfert_recu']) ?></div>
      </div>
      <?php endif; ?>

      <?php if((float)$caisse['transfert_emis'] > 0): ?>
      <div style="background:#fef2f2;padding:10px 12px;border-radius:6px;border-left:3px solid #dc2626;">
        <div style="font-size:10px;color:#991b1b;text-transform:uppercase;font-weight:600;">Transfert emis</div>
        <div style="font-size:17px;font-weight:700;"><?= money((float)$caisse['transfert_emis']) ?></div>
      </div>
      <?php endif; ?>

      <div style="background:#dcfce7;padding:10px 12px;border-radius:6px;border-left:3px solid #16a34a;">
        <div style="font-size:10px;color:#166534;text-transform:uppercase;font-weight:600;">Solde theor. especes</div>
        <div style="font-size:17px;font-weight:700;"><?= money($solde_theorique) ?></div>
      </div>
    </div>

    <!-- Tickets vendus -->
    <div style="margin-bottom:12px;">
      <div style="font-weight:600;font-size:12px;margin-bottom:6px;color:#475569;">TICKETS VENDUS</div>
      <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-money-bill-wave" style="color:#16a34a;"></i> Especes</td><td style="text-align:right;font-weight:600;"><?= money($stats['especes']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-mobile-alt" style="color:#f97316;"></i> Orange Money</td><td style="text-align:right;"><?= money($stats['om']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-mobile-alt" style="color:#f59e0b;"></i> MTN MoMo</td><td style="text-align:right;"><?= money($stats['momo']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-credit-card"></i> Carte</td><td style="text-align:right;"><?= money($stats['carte']) ?></td></tr>
        <tr style="border-bottom:1px solid var(--border);"><td style="padding:5px 0;"><i class="fas fa-file-invoice"></i> Cheque</td><td style="text-align:right;"><?= money($stats['cheque']) ?></td></tr>
        <tr style="font-weight:700;background:var(--bg);"><td style="padding:6px 0;">TOTAL (<?= $stats['nb_vendu'] ?> tickets)</td><td style="text-align:right;"><?= money($stats['total']) ?></td></tr>
      </table>
    </div>

    <!-- Mouvements -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
      <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:10px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:10px;color:#991b1b;text-transform:uppercase;font-weight:600;">DEPENSES</span>
          <span style="font-size:16px;font-weight:700;color:#dc2626;"><?= money($mvmts['depenses']) ?></span>
        </div>
      </div>
      <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:10px;">
        <div style="display:flex;justify-content:space-between;align-items:center;">
          <span style="font-size:10px;color:#166534;text-transform:uppercase;font-weight:600;">AUTRES RECETTES</span>
          <span style="font-size:16px;font-weight:700;color:#16a34a;"><?= money($mvmts['recettes']) ?></span>
        </div>
      </div>
    </div>

    <?php if ($stats['nb_annule'] > 0): ?>
    <div style="margin-top:10px;font-size:11px;color:#dc2626;"><i class="fas fa-ban"></i> <?= $stats['nb_annule'] ?> ticket(s) annule(s) : <?= money($stats['annule_m']) ?></div>
    <?php endif; ?>

    <!-- Boutons d'action -->
    <?php if ($is_own): ?>
    <div style="display:flex;gap:8px;margin-top:16px;flex-wrap:wrap;">
      <button onclick="openModal('modal-depense')" class="btn btn-danger btn-sm"><i class="fas fa-minus-circle"></i> + Depense</button>
      <button onclick="openModal('modal-recette')" class="btn btn-success btn-sm"><i class="fas fa-plus-circle"></i> + Autre recette</button>
      <button onclick="openModal('modal-cloturer')" class="btn btn-warning btn-sm" style="margin-left:auto;"><i class="fas fa-lock"></i> Cloturer la caisse</button>
    </div>
    <?php elseif (isChefGuichet() || isChefAgence() || isAdmin()): ?>
    <div style="margin-top:16px;">
      <form method="POST" style="display:inline;">
        <?= csrfField() ?>
        <input type="hidden" name="caisse_id" value="<?= $caisse['id'] ?>">
        <button type="submit" name="cloturer_superviseur" value="1" class="btn btn-warning btn-sm" onclick="return confirm('Cloturer cette caisse (forcee) ?')"><i class="fas fa-lock"></i> Cloturer (superviseur)</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php else: ?>
<!-- ETAT : CAISSE FERMEE (RECAPITULATIF) -->
<div id="rpt-zone">
<div class="card">
  <div class="card-head" style="background:#16a34a;color:#fff;padding:10px 16px;border-radius:8px 8px 0 0;display:flex;justify-content:space-between;align-items:center;">
    <strong>Caisse <?= sanitize($caisse['numero']) ?> — CLOTUREE</strong>
    <span style="font-size:11px;opacity:.9;">Fermee le <?= fdatetime($caisse['date_fermeture'] ?? $caisse['updated_at']) ?></span>
  </div>
  <div class="card-body">
    <table style="width:100%;border-collapse:collapse;font-size:12px;">
      <tr style="background:var(--bg);"><td style="padding:6px 10px;font-weight:600;">Fond de caisse</td><td style="text-align:right;"><?= money((float)$caisse['fond_initial']) ?></td></tr>
      <?php if((float)$caisse['transfert_recu'] > 0): ?><tr><td style="padding:6px 10px;font-weight:600;">Transfert recu</td><td style="text-align:right;"><?= money((float)$caisse['transfert_recu']) ?></td></tr><?php endif; ?>
      <?php if((float)$caisse['transfert_emis'] > 0): ?><tr><td style="padding:6px 10px;font-weight:600;color:#dc2626;">Transfert emis</td><td style="text-align:right;color:#dc2626;"><?= money((float)$caisse['transfert_emis']) ?></td></tr><?php endif; ?>
      <tr><td style="padding:6px 10px;font-weight:600;">Tickets (<?= $caisse['nb_tickets_vendus'] ?> vendus)</td><td style="text-align:right;"><?= money((float)$caisse['total_tickets_especes'] + (float)$caisse['total_tickets_om'] + (float)$caisse['total_tickets_momo'] + (float)$caisse['total_tickets_carte'] + (float)$caisse['total_tickets_cheque']) ?></td></tr>
      <?php if((float)$caisse['nb_tickets_annules'] > 0): ?><tr><td style="padding:6px 10px;font-weight:600;color:#dc2626;">Tickets annules (<?= $caisse['nb_tickets_annules'] ?>)</td><td style="text-align:right;color:#dc2626;"><?= money((float)$caisse['montant_annulations']) ?></td></tr><?php endif; ?>
      <tr><td style="padding:6px 10px;font-weight:600;">Depenses</td><td style="text-align:right;color:#dc2626;"><?= money((float)$caisse['total_depenses']) ?></td></tr>
      <tr><td style="padding:6px 10px;font-weight:600;">Autres recettes</td><td style="text-align:right;color:#16a34a;"><?= money((float)$caisse['total_autres_recettes']) ?></td></tr>
      <tr><td colspan="2"><hr></td></tr>
      <tr style="background:#eff6ff;font-weight:700;"><td style="padding:6px 10px;">Solde especes theorique</td><td style="text-align:right;"><?= money($solde_theorique) ?></td></tr>
      <tr style="background:#fff;font-weight:700;"><td style="padding:6px 10px;">Especes comptees</td><td style="text-align:right;"><?= money((float)$caisse['solde_physique']) ?></td></tr>
      <tr style="background:<?= $caisse['ecart']==0?'#f0fdf4':($caisse['ecart']>0?'#fffbeb':'#fef2f2') ?>;font-weight:700;font-size:14px;">
        <td style="padding:8px 10px;">Ecart <?= $caisse['ecart']==0?'':($caisse['ecart']>0?'(Excedent)':'(Manquant)') ?></td>
        <td style="text-align:right;color:<?= $caisse['ecart']==0?'#16a34a':($caisse['ecart']>0?'#d97706':'#dc2626') ?>;"><?= money((float)$caisse['ecart']) ?></td>
      </tr>
    </table>
    <?php if($caisse['observations']): ?><div style="margin-top:10px;font-size:11px;color:var(--text2);font-style:italic;">Obs: <?= sanitize($caisse['observations']) ?></div><?php endif; ?>

    <div class="no-print" style="margin-top:16px;">
      <button onclick="window.print()" class="btn btn-info btn-sm"><i class="fas fa-print"></i> Imprimer</button>
    </div>
  </div>
</div>
</div>
<?php endif; ?>

<!-- ════ MODALS ════════════════════════════════════════════════ -->

<!-- Modal OUVRIR -->
<div class="modal-over" id="modal-ouvrir">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-door-open"></i> Ouvrir la caisse</h3><button class="modal-x" onclick="closeModal('modal-ouvrir')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <?php if ($transfPend): ?>
        <div style="background:#fef3c7;border:1px solid #fcd34d;border-radius:6px;padding:8px;margin-bottom:12px;font-size:12px;">
          <i class="fas fa-exchange-alt"></i> Transfert recu en attente : <strong><?= money((float)$transfPend['total']) ?></strong> (ajoute automatiquement)
        </div>
        <?php endif; ?>
        <div class="fg">
          <label class="fl">Fond de caisse (FCFA)</label>
          <input type="number" name="fond_initial" class="fc" value="0" required min="0" style="font-size:20px;font-weight:700;text-align:center;" placeholder="0">
          <small style="color:var(--text3);">Montant en especes que vous avez en caisse au demarrage.</small>
        </div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-ouvrir')">Annuler</button>
        <button type="submit" name="ouvrir_caisse" value="1" class="btn btn-primary btn-sm"><i class="fas fa-check"></i> Ouvrir</button>
      </div>
    </form>
  </div>
</div>

<?php if ($caisse && $caisse['statut']==='ouverte' && $is_own): ?>

<!-- Modal DEPENSE -->
<div class="modal-over" id="modal-depense">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-minus-circle" style="color:#dc2626;"></i> Nouvelle depense</h3><button class="modal-x" onclick="closeModal('modal-depense')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="type_mvt" value="depense">
      <div class="modal-body">
        <div class="fg"><label class="fl">Libelle</label><input type="text" name="libelle" class="fc" required placeholder="Ex: Peage, Carburant, Remboursement..."></div>
        <div class="fg"><label class="fl">Montant (FCFA)</label><input type="number" name="montant" class="fc" required min="1" value="0"></div>
        <div class="fg"><label class="fl">Mode</label><select name="mode_paiement" class="fc"><option value="especes">Especes</option><option value="om">Orange Money</option><option value="momo">MTN MoMo</option><option value="carte">Carte</option><option value="cheque">Cheque</option></select></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-depense')">Annuler</button>
        <button type="submit" name="ajout_mouvement" value="1" class="btn btn-danger btn-sm">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal AUTRE RECETTE -->
<div class="modal-over" id="modal-recette">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-plus-circle" style="color:#16a34a;"></i> Autre recette</h3><button class="modal-x" onclick="closeModal('modal-recette')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="type_mvt" value="recette">
      <div class="modal-body">
        <div class="fg"><label class="fl">Libelle</label><input type="text" name="libelle" class="fc" required placeholder="Ex: Vente colis, Frais reservation..."></div>
        <div class="fg"><label class="fl">Montant (FCFA)</label><input type="number" name="montant" class="fc" required min="1" value="0"></div>
        <div class="fg"><label class="fl">Mode</label><select name="mode_paiement" class="fc"><option value="especes">Especes</option><option value="om">Orange Money</option><option value="momo">MTN MoMo</option><option value="carte">Carte</option><option value="cheque">Cheque</option></select></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-recette')">Annuler</button>
        <button type="submit" name="ajout_mouvement" value="1" class="btn btn-success btn-sm">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal CLÔTURER -->
<div class="modal-over" id="modal-cloturer">
  <div class="modal modal-lg">
    <div class="modal-head"><h3><i class="fas fa-lock"></i> Cloturer la caisse</h3><button class="modal-x" onclick="closeModal('modal-cloturer')">&times;</button></div>
    <form method="POST">
      <?= csrfField() ?>
      <div class="modal-body">
        <div style="background:#f0f7ff;border:1px solid #bfdbfe;border-radius:8px;padding:12px;margin-bottom:12px;">
          <strong>Solde especes theorique :</strong>
          <span style="font-size:20px;font-weight:700;margin-left:8px;"><?= money($solde_theorique) ?></span>
          <div style="font-size:10px;color:var(--text3);margin-top:4px;">
            = <?= money((float)$caisse['fond_initial']) ?> (fond) + <?= money((float)$caisse['transfert_recu']) ?> (transf) + <?= money($stats['especes']) ?> (ventes) + <?= money($mvmts['recettes_esp']) ?> (recettes) - <?= money($mvmts['depenses_esp']) ?> (depenses)
          </div>
        </div>

        <?php if (!empty($tickets_transferables)): ?>
        <div style="margin-bottom:14px;">
          <div style="font-weight:600;font-size:12px;color:#7c3aed;margin-bottom:6px;"><i class="fas fa-exchange-alt"></i> TRANSFERT DE TICKETS (optionnel)</div>
          <table style="width:100%;font-size:11px;border-collapse:collapse;">
            <thead><tr style="background:var(--bg);"><th style="padding:4px 6px;text-align:left;">Sel.</th><th>No Ticket</th><th>Passager</th><th>Trajet</th><th style="text-align:right;">Montant</th></tr></thead>
            <tbody>
            <?php foreach($tickets_transferables as $tk): ?>
            <tr style="border-bottom:1px solid var(--border);">
              <td><input type="checkbox" name="tickets_transfer[]" value="<?= $tk['id'] ?>" style="width:16px;height:16px;"></td>
              <td style="font-family:monospace;font-size:10px;"><?= sanitize($tk['numero']) ?></td>
              <td><?= sanitize($tk['passager_nom']) ?></td>
              <td><?= sanitize(($tk['dep']??'—').'→'.($tk['arr']??'—')) ?></td>
              <td style="text-align:right;"><?= money((float)$tk['montant_total']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <select name="transfert_to" class="fc" style="max-width:280px;margin-top:8px;">
            <option value="0">— Choisir guichetier destinataire —</option>
            <?php foreach($autres_guichetiers as $g): ?><option value="<?= $g['id'] ?>"><?= sanitize($g['nom']) ?> (<?= sanitize($g['ville']??'—') ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>

        <div class="fg">
          <label class="fl" style="font-weight:600;">Especes comptees physiquement (FCFA)</label>
          <input type="number" name="solde_physique" class="fc" id="solde-physique" required min="0" value="<?= $solde_theorique ?>" style="font-size:22px;font-weight:700;text-align:center;border:2px solid #2563eb;" oninput="calcEcart()">
        </div>

        <div id="ecart-display" style="text-align:center;padding:10px;border-radius:8px;font-size:14px;font-weight:700;margin-top:8px;"></div>

        <div class="fg"><label class="fl">Observations</label><textarea name="observations" class="fc" rows="2" placeholder="Note eventuelle..."></textarea></div>
      </div>
      <div class="modal-foot">
        <button type="button" class="btn btn-ghost btn-sm" onclick="closeModal('modal-cloturer')">Annuler</button>
        <button type="submit" name="cloturer" value="1" class="btn btn-warning btn-sm"><i class="fas fa-check-circle"></i> Confirmer la cloture</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function calcEcart() {
  var physique = parseFloat(document.getElementById('solde-physique').value) || 0;
  var theorique = <?= $solde_theorique ?>;
  var ecart = physique - theorique;
  var el = document.getElementById('ecart-display');
  var sign = ecart >= 0 ? '+' : '';
  if (ecart === 0) {
    el.innerHTML = '<span style="color:#16a34a;">Ecart : 0 FCFA — Caisse juste</span>';
    el.style.background = '#f0fdf4';
  } else if (ecart > 0) {
    el.innerHTML = '<span style="color:#d97706;">Ecart : ' + sign + format(ecart) + ' FCFA (Excedent)</span>';
    el.style.background = '#fffbeb';
  } else {
    el.innerHTML = '<span style="color:#dc2626;">Ecart : ' + format(ecart) + ' FCFA (Manquant)</span>';
    el.style.background = '#fef2f2';
  }
}
function format(n) { return n.toFixed(0).replace(/\\B(?=(\\d{3})+(?!\\d))/g, ' '); }
document.addEventListener('DOMContentLoaded', calcEcart);
</script>

<?php include '../../includes/footer.php'; ?>
```

- [ ] **Step 3: Vérifier la syntaxe PHP du fichier créé**

```bash
php -l "C:/xampp/htdocs/SystracoPro/modules/caisse/index.php"
```

Expected: `No syntax errors detected`

---

### Task 6: Modifier logout.php — Garde pause/clôture

**Fichier :**
- Modify: `logout.php`

- [ ] **Step 1: Remplacer logout.php**

```php
<?php
require_once 'includes/config.php';

// Garde caisse : guichetier avec caisse ouverte
if (isLoggedIn() && isGuichetier()) {
    $ca = caisseOuverte();
    if ($ca) {
        $action = $_POST['logout_action'] ?? $_GET['logout_action'] ?? '';
        if ($action === 'pause') {
            logAction($pdo, 'deconnexion_pause', 'auth', "Caisse {$ca['id']} reste ouverte (pause)");
            session_destroy();
            redirect(BASE_URL . 'login.php');
        } elseif ($action === 'cloture') {
            flash('Veuillez d\'abord cloturer votre caisse avant de vous deconnecter.', 'warning');
            redirect(BASE_URL . 'modules/caisse/index.php');
        }
        // Afficher la page pause/cloture
        $appNom = sanitize(getParam('nom_entreprise', 'TransportManager'));
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Deconnexion — '.$appNom.'</title>
        <link rel="stylesheet" href="'.BASE_URL.'css/app.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>.lo-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);}.lo-card{background:#fff;border-radius:var(--radius-lg);padding:40px;text-align:center;max-width:420px;width:90%;box-shadow:var(--shadow-lg);}</style></head><body>
        <div class="lo-wrap"><div class="lo-card">
        <i class="fas fa-cash-register" style="font-size:48px;color:#1e3a8a;margin-bottom:12px;"></i>
        <h3 style="margin-bottom:4px;">Caisse ouverte</h3>
        <p style="color:var(--text3);font-size:13px;margin-bottom:20px;">Votre caisse <strong>'.sanitize($ca['numero']).'</strong> est encore ouverte. Que souhaitez-vous faire ?</p>
        <form method="POST" style="display:flex;gap:10px;justify-content:center;">
        <input type="hidden" name="_csrf" value="'.csrfToken().'">
        <button type="submit" name="logout_action" value="pause" class="btn btn-info btn-sm"><i class="fas fa-coffee"></i> C\'est une pause</button>
        <button type="submit" name="logout_action" value="cloture" class="btn btn-warning btn-sm"><i class="fas fa-lock"></i> Cloture</button>
        </form>
        <p style="font-size:10px;color:var(--text3);margin-top:12px;">Pause = vous revenez plus tard, la caisse reste ouverte.<br>Cloture = fin de journee, vous devez cloturer votre caisse.</p>
        </div></div></body></html>';
        exit;
    }
}

// Deconnexion normale
session_destroy();
redirect(BASE_URL . 'login.php');
```

- [ ] **Step 2: Vérifier la syntaxe PHP**

```bash
php -l "C:/xampp/htdocs/SystracoPro/logout.php"
```

Expected: `No syntax errors detected`

---

### Task 7: Vérification finale — tests manuels

- [ ] **Step 1: Appliquer les changements de base de données**

Importer `database.sql` dans phpMyAdmin (ou exécuter les nouvelles instructions CREATE TABLE + INSERT manuellement). Si la DB existe déjà, exécuter uniquement les CREATE TABLE et INSERT des 4 nouvelles tables + permission.

- [ ] **Step 2: Parcourir le cycle complet**

1. Se connecter en tant que `guichetier1` (mot de passe: `password`)
2. Aller sur **Caisse** dans le menu FINANCES → page "Aucune caisse ouverte"
3. Cliquer "Ouvrir la caisse" → saisir un fond (ex: 50000) → valider
4. Vérifier le dashboard : fond affiché, 0 tickets
5. Aller sur Vente tickets → vérifier que la vente fonctionne
6. Revenir sur Caisse → vérifier que les tickets apparaissent avec leurs montants
7. Ajouter une dépense → vérifier qu'elle apparaît et modifie le solde théorique
8. Ajouter une autre recette → vérifier
9. Vérifier le calcul du solde théorique espèces
10. Clôturer la caisse → saisir espèces physiques → vérifier l'écart → confirmer
11. Vérifier le récapitulatif de clôture → imprimer
12. Tenter de rouvrir une caisse → vérifier que c'est refusé

- [ ] **Step 3: Tester la garde déconnexion**

1. Ouvrir une caisse avec `guichetier1`
2. Cliquer sur Déconnexion → vérifier la page "Pause ou Clôture ?"
3. Choisir "Pause" → se reconnecter → vérifier que la caisse est toujours ouverte
4. Cliquer Déconnexion → choisir "Clôture" → vérifier la redirection vers la page caisse

- [ ] **Step 4: Tester le transfert de tickets**

1. Avec `guichetier1`, vendre un ticket pour un voyage futur
2. Ouvrir la clôture → cocher le ticket → choisir `guichetier2` → confirmer la clôture
3. Se connecter en tant que `guichetier2` → ouvrir la caisse → vérifier que "Transfert reçu" affiche le montant

- [ ] **Step 5: Tester la supervision**

1. Se connecter en tant que `chef_guichet1`
2. Aller sur Caisse → sélectionner un guichetier dans la liste déroulante
3. Vérifier que la caisse du guichetier s'affiche en lecture seule
4. Cliquer "Clôturer (superviseur)" → vérifier que la caisse est fermée
