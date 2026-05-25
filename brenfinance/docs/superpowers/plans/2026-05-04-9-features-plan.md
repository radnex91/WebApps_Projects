# Plan d'implémentation — 9 Fonctionnalités Avancées BrenFinance

> **Execution:** Inline sequential, verification manuelle par navigation navigateur.
> **Spec:** docs/superpowers/specs/2026-05-04-brenfinance-9-features-design.md
> **Goal:** Déployer Paie, Rapprochement bancaire, Export, Clôture, Bons de commande, Compta analytique, Ratios/SIG, Validation bancaire, Verrouillage exercice.
> **Tech Stack:** PHP 7.4+, MySQL, PDO, PhpSpreadsheet (manual lib), Chart.js 4.x, QRCode.js

---

## Phase 0 — Migration SQL (fondation de tout)

### Task 0.1: Script de migration SQL complet
- Create: `sql/migrations/009_features_avances.sql`
- Contient toutes les CREATE TABLE, ALTER TABLE, INSERT seed
- Exécuter: `mysql -u root brenfinance < sql/migrations/009_features_avances.sql`

---

## Phase 1 — Fondations (#19, #9, #16)

### Task 1.1: includes/export.php — Export CSV + Excel
### Task 1.2: includes/functions.php — Ajouts helpers (exerciceVerrouille, etc.)
### Task 1.3: modules/exercices/index.php — Gestion exercices + verrouillage
### Task 1.4: Modification modules/tresorerie/index.php — Workflow validation bancaire
### Task 1.5: Modification includes/header.php — Nouveaux liens navigation

---

## Phase 2 — Conformité (#10, #12)

### Task 2.1: modules/bons_commande/index.php — Liste BC
### Task 2.2: modules/bons_commande/creer.php — Création BC
### Task 2.3: modules/bons_commande/detail.php — Détail BC
### Task 2.4: modules/cloture/index.php — Assistant clôture 4 étapes

---

## Phase 3 — Pilotage (#7, #15)

### Task 3.1: Extension modules/tresorerie/index.php — Import relevé + rapprochement
### Task 3.2: Extension modules/reporting/index.php — SIG + Ratios

---

## Phase 4 — Complexe (#6, #14)

### Task 4.1: modules/paie/index.php — Dashboard paie
### Task 4.2: modules/paie/rubriques.php — CRUD rubriques
### Task 4.3: modules/paie/bulletins.php — Gestion période
### Task 4.4: modules/paie/bulletin_detail.php — Détail bulletin
### Task 4.5: modules/paie/declarations.php — Déclarations
### Task 4.6: modules/compta_analytique/index.php — Axes analytiques
### Task 4.7: modules/compta_analytique/repartition.php — Clés de répartition

---

## Phase 5 — Intégration finale

### Task 5.1: Mise à jour dashboard.php — Nouveaux KPIs
### Task 5.2: Mise à jour includes/header.php — Permissions sidebar
