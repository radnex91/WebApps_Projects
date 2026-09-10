# Résultat consultation (entretien médecin) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un bouton « Résultat consultation » dans le workspace médecin (`pages/consultations.php`) ouvrant un modal 5 champs (Motif, Histoire, Examen, Diagnostic, Conduite) stocké dans `notes_cliniques` (`type_note='consultation'`), avec gating strict sur « Terminer ».

**Architecture:** Réutilisation de la table `notes_cliniques` (+ colonne `arrivee_id` nullable via migration idempotente `update_v7.sql`) — pas de nouvelle table. Le résultat apparaît automatiquement dans le dossier patient (déjà agrégé). Gating strict : bouton Terminer désactivé côté UI tant qu'aucun résultat n'existe + re-check serveur tamper-proof dans le handler `terminer`.

**Tech Stack:** PHP 7.4+ vanilla (pas de framework, pas de build, pas de suite de tests — QA = `php -l` + vérification SQL + QA navigateur), MySQL/MariaDB via PDO, helpers `db_*` prepared statements.

## Global Constraints

- **DB** : prepared statements uniquement via `db_select`/`db_row`/`db_scalar`/`db_exec` — jamais concaténer de valeurs dans le SQL.
- **Sécurité POST** : `csrf_verify()` avant traitement, `can('consultations.*')` pour gater, IDOR — vérifier `medecin_id = $me` (statut `en_consultation`) avant d'agir sur un `arrivee_id`.
- **Échappement** : `h()` sur toute sortie patient/médecin. JS reçoit les résultats via `json_encode()` (neutralise `</script>`).
- **Noms de champs unifiés** : les clés du helper `$f`, les noms de champs POST et les ids JS utilisent tous `motif`, `histoire`, `examen`, `diagnostic`, `conduite` (pas de mapping intermédiaire). Le mapping vers les colonnes `notes_cliniques` se fait uniquement dans `save_resultat_consultation` (`titre`/`subjective`/`objective`/`analyse`/`plan`).
- **Helpers résilients** : `try { ... } catch (Throwable $e) { _log_error('CONSULT', ...); return valeur sûre; }`.
- **RBAC** : `consultations.resultat` ajouté à `ALL_ACTIONS` + seeder (`medecin` + admin via `$ALL_ACTIONS`). Re-exécuter le seeder puis **se reconnecter** (cache perms en session).
- **UI française**, commentaires de code en français.
- **Migration idempotente** : chaque `ALTER`/`ADD KEY`/`ADD CONSTRAINT` gardé par `INFORMATION_SCHEMA`. Pas de seed de perms dans le SQL (le seeder est canonique).
- **Pas de redirect PRG** : les handlers `reorienter`/`terminer` existants utilisent une flash **variable locale** rendue sur la même requête (pas de flash session). Un redirect casserait ce mécanisme. `get_mes_consultations` est appelée APRÈS les handlers → le flag `a_resultat` est déjà à jour sur le même rendu. Le handler `resultat` suit donc le même patron (set `$flash` + fall-through, pas de `header()`/`exit`). *(Deviation de la spec qui mentionnait PRG : la flash locale l'exige, et le refresh est de toute façon immédiat.)*

---

## File Structure

- **Créer** `sql/update_v7.sql` — migration idempotente : `notes_cliniques.arrivee_id` + index + FK.
- **Modifier** `includes/permissions.php` — `ALL_ACTIONS` += `consultations.resultat`.
- **Modifier** `sql/seed_permissions.php` — matrice `medecin` actions += `consultations.resultat`.
- **Modifier** `includes/accueil.php` — 2 nouveaux helpers (`get_resultat_consultation`, `save_resultat_consultation`) + flag `a_resultat` dans `get_mes_consultations`.
- **Modifier** `pages/consultations.php` — POST `resultat`, modif POST `terminer` (re-check), bouton 📝 Résultat, bouton Terminer `disabled`, modal `modal-resultat` + JS, chargement `$resultats`.

Décomposition en 4 tâches : (1) migration, (2) RBAC, (3) helpers, (4) page. Chaque tâche est testable indépendamment (lint + vérif SQL / seeder / boot-include).

---

### Task 1: Migration `sql/update_v7.sql`

**Files:**
- Create: `sql/update_v7.sql`

**Interfaces:**
- Produces: colonne `notes_cliniques.arrivee_id` (INT NULL), index `idx_notes_arrivee`, FK `fk_notes_arrivee` → `arrivees_patients(id) ON DELETE SET NULL`. Les tâches 3 et 4 dépendent de ce schéma.

- [ ] **Step 1: Créer le fichier de migration**

Créer `sql/update_v7.sql` :

```sql
-- ============================================================
--  MediCore ERP — Migration v7
--  Résultat de consultation : liaison notes_cliniques ↔ arrivees_patients.
--  Permet de rattacher un compte-rendu d'entretien médecin à une arrivée
--  (pour le gating strict de « Terminer » et le pré-remplissage du modal).
--
--  Idempotent : chaque ALTER est gardé par un contrôle INFORMATION_SCHEMA.
--  Re-exécutable sans erreur.
-- ============================================================

-- ── 1.1  notes_cliniques : colonne arrivee_id (liaison résultat de consultation) ──
SET @v := (SELECT COUNT(*) FROM information_schema.columns
           WHERE table_schema = DATABASE() AND table_name = 'notes_cliniques'
             AND column_name = 'arrivee_id');
SET @s := IF(@v = 0,
  'ALTER TABLE `notes_cliniques` ADD COLUMN `arrivee_id` INT(11) DEFAULT NULL AFTER `hospitalisation_id`',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 1.2  Index sur arrivee_id (lookup du gating Terminer + chargement résultats) ──
SET @v := (SELECT COUNT(*) FROM information_schema.statistics
           WHERE table_schema = DATABASE() AND table_name = 'notes_cliniques'
             AND index_name = 'idx_notes_arrivee');
SET @s := IF(@v = 0,
  'ALTER TABLE `notes_cliniques` ADD KEY `idx_notes_arrivee` (`arrivee_id`)',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 1.3  Clé étrangère arrivee_id → arrivees_patients (ON DELETE SET NULL) ──
SET @v := (SELECT COUNT(*) FROM information_schema.referential_constraints
           WHERE constraint_schema = DATABASE() AND table_name = 'notes_cliniques'
             AND constraint_name = 'fk_notes_arrivee');
SET @s := IF(@v = 0,
  'ALTER TABLE `notes_cliniques` ADD CONSTRAINT `fk_notes_arrivee` FOREIGN KEY (`arrivee_id`) REFERENCES `arrivees_patients` (`id`) ON DELETE SET NULL',
  'SELECT 1');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
```

- [ ] **Step 2: Exécuter la migration une première fois**

Run: `mysql -u root medicore < sql/update_v7.sql` (ou phpMyAdmin)
Expected: aucune erreur.

- [ ] **Step 3: Vérifier le schéma**

Run (depuis mysql ou phpMyAdmin) :
```sql
SHOW COLUMNS FROM notes_cliniques LIKE 'arrivee_id';
SHOW INDEX FROM notes_cliniques WHERE Key_name = 'idx_notes_arrivee';
SELECT COUNT(*) FROM information_schema.referential_constraints
  WHERE constraint_schema = DATABASE() AND table_name = 'notes_cliniques'
    AND constraint_name = 'fk_notes_arrivee';
```
Expected: 1 ligne pour la colonne (`arrivee_id`, `int(11)`, `YES`), 1 ligne pour l'index, `1` pour la FK.

- [ ] **Step 4: Ré-exécuter la migration (idempotence)**

Run: `mysql -u root medicore < sql/update_v7.sql`
Expected: aucune erreur (no-op — les gardes `INFORMATION_SCHEMA` court-circuitent).

- [ ] **Step 5: Commit**

```bash
git add sql/update_v7.sql
git commit -m "feat(consultations): migration v7 — notes_cliniques.arrivee_id + index + FK"
```

---

### Task 2: RBAC — action `consultations.resultat`

**Files:**
- Modify: `includes/permissions.php` (après la ligne `'consultations.terminer'`)
- Modify: `sql/seed_permissions.php` (ligne medecin actions `'consultations.terminer'`)

**Interfaces:**
- Produces: constante `ALL_ACTIONS['consultations.resultat']` + ligne seedée `medecin`/`admin` → `can('consultations.resultat')` utilisable par la tâche 4. Nécessite re-login après re-seed.

- [ ] **Step 1: Ajouter l'action à `ALL_ACTIONS`**

Dans `includes/permissions.php`, trouver la ligne (vers la ligne 63) :
```php
    'consultations.terminer'   => ['label'=>'Terminer une consultation',                        'module'=>'Consultations'],
```
Ajouter immédiatement après :
```php
    'consultations.resultat'   => ['label'=>'Saisir le résultat d\'une consultation',            'module'=>'Consultations'],
```

- [ ] **Step 2: Ajouter l'action au seeder (matrice medecin)**

Dans `sql/seed_permissions.php`, trouver la ligne (vers la ligne 40) :
```php
            'consultations.prescrire','consultations.reorienter','consultations.terminer',
```
Remplacer par :
```php
            'consultations.prescrire','consultations.reorienter','consultations.terminer','consultations.resultat',
```
(`admin` hérite déjà via `$ALL_ACTIONS` — pas de modification de la branche `admin`.)

- [ ] **Step 3: Lint**

Run: `php -l includes/permissions.php && php -l sql/seed_permissions.php`
Expected: `No syntax errors detected` pour les deux.

- [ ] **Step 4: Re-seed et vérifier le compte**

Run: `php sql/seed_permissions.php`
Expected: `OK : 105 pages + 140 actions insérées (6 rôles).` (avant : 139 actions ; +1 `consultations.resultat` = 140 ; pages inchangées à 105).

Vérifier en base :
```sql
SELECT role, action FROM role_action_access WHERE action = 'consultations.resultat';
```
Expected: 2 lignes — `(admin, consultations.resultat)` et `(medecin, consultations.resultat)`.

- [ ] **Step 5: Commit**

```bash
git add includes/permissions.php sql/seed_permissions.php
git commit -m "feat(consultations): RBAC action consultations.resultat (medecin + admin)"
```

---

### Task 3: Helpers dans `includes/accueil.php`

**Files:**
- Modify: `includes/accueil.php` — modifier `get_mes_consultations` + ajouter 2 helpers à la fin du fichier.

**Interfaces:**
- Consumes: colonne `notes_cliniques.arrivee_id` (tâche 1), `db_row`/`db_exec`/`db_select` (security.php).
- Produces:
  - `get_resultat_consultation(int $arrivee_id): ?array` — ligne `['id','titre','subjective','objective','analyse','plan']` ou `null`.
  - `save_resultat_consultation(int $arrivee_id, int $patient_id, int $medecin_id, array $f): int` — upsert, retourne l'id de la ligne (`>0`) ou `0` si échec. `$f` clés : `motif, histoire, examen, diagnostic, conduite`.
  - `get_mes_consultations(int $medecin_id): array` gagne un flag `a_resultat` (booléen) par ligne.

- [ ] **Step 1: Ajouter le flag `a_resultat` à `get_mes_consultations`**

Dans `includes/accueil.php`, trouver le SELECT de `get_mes_consultations` (vers les lignes 191-200) :
```php
        return db_select(
            "SELECT a.id, a.patient_id, a.date_arrivee, a.date_prise_en_charge, a.motif, a.notes,
                    p.nom, p.prenom, p.numero, p.date_naissance, p.sexe,
                    EXISTS(SELECT 1 FROM dossiers_medicaux dm WHERE dm.patient_id = a.patient_id) AS a_dossier,
                    EXISTS(SELECT 1 FROM caisse_ventes cv WHERE cv.patient_id = a.patient_id AND cv.type_vente = 'consultation' AND cv.statut = 'paye') AS a_consultation
             FROM arrivees_patients a
             JOIN patients p ON p.id = a.patient_id
             WHERE a.medecin_id = ? AND a.statut = 'en_consultation' AND DATE(a.date_arrivee) = CURDATE()
             ORDER BY a.date_prise_en_charge ASC",
            [$medecin_id]
        );
```
Remplacer la ligne `EXISTS(... caisse_ventes ...) AS a_consultation` par deux lignes (ajouter `a_resultat`) :
```php
        return db_select(
            "SELECT a.id, a.patient_id, a.date_arrivee, a.date_prise_en_charge, a.motif, a.notes,
                    p.nom, p.prenom, p.numero, p.date_naissance, p.sexe,
                    EXISTS(SELECT 1 FROM dossiers_medicaux dm WHERE dm.patient_id = a.patient_id) AS a_dossier,
                    EXISTS(SELECT 1 FROM caisse_ventes cv WHERE cv.patient_id = a.patient_id AND cv.type_vente = 'consultation' AND cv.statut = 'paye') AS a_consultation,
                    EXISTS(SELECT 1 FROM notes_cliniques n WHERE n.arrivee_id = a.id AND n.type_note = 'consultation') AS a_resultat
             FROM arrivees_patients a
             JOIN patients p ON p.id = a.patient_id
             WHERE a.medecin_id = ? AND a.statut = 'en_consultation' AND DATE(a.date_arrivee) = CURDATE()
             ORDER BY a.date_prise_en_charge ASC",
            [$medecin_id]
        );
```

- [ ] **Step 2: Ajouter `get_resultat_consultation` à la fin du fichier**

À la fin de `includes/accueil.php` (après la fonction `reorienter_vers`), ajouter :

```php

/**
 * Récupère le résultat de consultation (note clinique type 'consultation')
 * lié à une arrivée, s'il existe — pour pré-remplir le modal en édition.
 * Retourne ['id','titre','subjective','objective','analyse','plan'] ou null.
 */
function get_resultat_consultation(int $arrivee_id): ?array {
    if ($arrivee_id <= 0) return null;
    try {
        return db_row(
            "SELECT id, titre, subjective, objective, analyse, plan
             FROM notes_cliniques
             WHERE arrivee_id = ? AND type_note = 'consultation'
             ORDER BY date_creation DESC LIMIT 1",
            [$arrivee_id]
        );
    } catch (Throwable $e) {
        _log_error('CONSULT', 'Échec get_resultat_consultation', __FILE__, __LINE__, $e);
        return null;
    }
}

/**
 * Enregistre (upsert) le résultat de consultation d'une arrivée.
 * $f = ['motif'=>..., 'histoire'=>..., 'examen'=>..., 'diagnostic'=>..., 'conduite'=>...]
 * (chaînes déjà nettoyées via post_str côté page). Mapping colonnes :
 *   motif→titre, histoire→subjective, examen→objective, diagnostic→analyse, conduite→plan.
 * Retourne l'id de la ligne (>0) ou 0 en cas d'échec.
 */
function save_resultat_consultation(int $arrivee_id, int $patient_id, int $medecin_id, array $f): int {
    if ($arrivee_id <= 0 || $patient_id <= 0 || $medecin_id <= 0) return 0;
    try {
        $existant = db_row(
            "SELECT id FROM notes_cliniques
             WHERE arrivee_id = ? AND type_note = 'consultation'
             ORDER BY date_creation DESC LIMIT 1",
            [$arrivee_id]
        );
        if (!empty($existant['id'])) {
            db_exec(
                "UPDATE notes_cliniques
                 SET titre = ?, subjective = ?, objective = ?, analyse = ?, plan = ?,
                     utilisateur_id = ?, date_note = NOW()
                 WHERE id = ?",
                [$f['motif'], $f['histoire'], $f['examen'], $f['diagnostic'], $f['conduite'],
                 $medecin_id, (int)$existant['id']]
            );
            return (int)$existant['id'];
        }
        return (int) db_exec(
            "INSERT INTO notes_cliniques
                (patient_id, utilisateur_id, type_note, titre, subjective, objective, analyse, plan, date_note, arrivee_id)
             VALUES (?,?,?,?,?,?,?,NOW(),?)",
            [$patient_id, $medecin_id, 'consultation',
             $f['motif'], $f['histoire'], $f['examen'], $f['diagnostic'], $f['conduite'],
             $arrivee_id]
        );
    } catch (Throwable $e) {
        _log_error('CONSULT', 'Échec save_resultat_consultation', __FILE__, __LINE__, $e);
        return 0;
    }
}
```

- [ ] **Step 3: Lint**

Run: `php -l includes/accueil.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Boot-include (pas de fatal au chargement des helpers)**

Run (depuis `medicore/`) :
```bash
php -r 'require "includes/config.php"; require "includes/accueil.php"; var_dump(function_exists("get_resultat_consultation"), function_exists("save_resultat_consultation"));' && echo "boot OK"
```
Expected: `bool(true) bool(true)` puis `boot OK`. (Note : `config.php` amorce `getDB()`/`_log_error` ; si l'include de config seul provoque un fatal côté DB, préfixer `@` n'est pas nécessaire — les helpers ne s'exécutent pas au chargement, seules leurs définitions sont vérifiées.)

- [ ] **Step 5: Commit**

```bash
git add includes/accueil.php
git commit -m "feat(consultations): helpers resultat (get/save) + flag a_resultat dans get_mes_consultations"
```

---

### Task 4: Page `pages/consultations.php` — handler, gating, bouton, modal, JS

**Files:**
- Modify: `pages/consultations.php` — POST `resultat` + modif POST `terminer` + bouton 📝 Résultat + bouton Terminer `disabled` + chargement `$resultats` + modal `modal-resultat` + JS.

**Interfaces:**
- Consumes: `save_resultat_consultation`, `get_mes_consultations` (avec `a_resultat`) (tâche 3), `can('consultations.resultat')` (tâche 2), colonne `arrivee_id` (tâche 1).
- Produces: le bouton « 📝 Résultat » + modal fonctionnel + gating strict sur « Terminer ».

- [ ] **Step 1: Ajouter le POST handler `resultat` (avant le handler `terminer`)**

Dans `pages/consultations.php`, trouver la fin du handler `reorienter` (le bloc fermé par `}` juste avant `// --- POST : Terminer la consultation ---`). Insérer **entre les deux** :

```php

// --- POST : Résultat de consultation (entretien médecin) ---
if (can('consultations.resultat') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'resultat') {
    csrf_verify();
    $arrivee_id = post_int('arrivee_id');
    if ($arrivee_id <= 0) {
        $flash = ['red', 'Arrivée requise.'];
    } else {
        // IDOR : vérifier que l'arrivée appartient bien au médecin connecté.
        $owner = (int) db_scalar("SELECT medecin_id FROM arrivees_patients WHERE id = ? AND statut = 'en_consultation'", [$arrivee_id]);
        if ($owner !== $me) {
            $flash = ['red', 'Arrivée introuvable ou non attribuée à vous.'];
        } else {
            $motif      = post_str('motif');
            $histoire   = post_str('histoire');
            $examen     = post_str('examen');
            $diagnostic = post_str('diagnostic');
            $conduite   = post_str('conduite');
            if (trim($motif) === '' && trim($histoire) === '' && trim($examen) === '' && trim($diagnostic) === '' && trim($conduite) === '') {
                $flash = ['red', 'Saisissez au moins un champ du résultat.'];
            } else {
                $patient_id = (int) db_scalar("SELECT patient_id FROM arrivees_patients WHERE id = ?", [$arrivee_id]);
                $rid = save_resultat_consultation($arrivee_id, $patient_id, $me, [
                    'motif' => $motif, 'histoire' => $histoire, 'examen' => $examen,
                    'diagnostic' => $diagnostic, 'conduite' => $conduite,
                ]);
                if ($rid > 0) {
                    logActivity('Résultat de consultation saisi (arrivée ' . $arrivee_id . ')', 'blue', 'notes_cliniques', $rid);
                    $flash = ['green', 'Résultat de consultation enregistré.'];
                } else {
                    $flash = ['red', 'Échec de l\'enregistrement.'];
                }
            }
        }
    }
}
```

(Pas de `header()`/`exit` : la flash est une variable locale rendue sur la même requête, comme pour `reorienter`/`terminer`. `get_mes_consultations` est appelée plus bas et reflète immédiatement `a_resultat`.)

- [ ] **Step 2: Ajouter le re-check résultat dans le handler `terminer` (gating strict serveur)**

Dans `pages/consultations.php`, trouver le handler `terminer` actuel :
```php
        } else {
            // IDOR : vérifier que l'arrivée appartient bien au médecin connecté.
            $owner = (int) db_scalar("SELECT medecin_id FROM arrivees_patients WHERE id = ? AND statut = 'en_consultation'", [$arrivee_id]);
            if ($owner !== $me) {
                $flash = ['red', 'Arrivée introuvable ou non attribuée à vous.'];
            } elseif (terminer_arrivee($arrivee_id)) {
                logActivity('Consultation terminée (arrivée ' . $arrivee_id . ')', 'green', 'rendez_vous', $arrivee_id);
                $flash = ['green', 'Consultation terminée.'];
            } else {
                $flash = ['red', 'Échec de la clôture.'];
            }
        }
```
Remplacer par (insertion du re-check `aResultat` entre l'IDOR et `terminer_arrivee`, transformation du `elseif` en `else { ... }` imbriqué) :
```php
        } else {
            // IDOR : vérifier que l'arrivée appartient bien au médecin connecté.
            $owner = (int) db_scalar("SELECT medecin_id FROM arrivees_patients WHERE id = ? AND statut = 'en_consultation'", [$arrivee_id]);
            if ($owner !== $me) {
                $flash = ['red', 'Arrivée introuvable ou non attribuée à vous.'];
            } else {
                // Gating strict (tamper-proof) : un résultat de consultation doit avoir été saisi.
                $aResultat = (int) db_scalar("SELECT COUNT(*) FROM notes_cliniques WHERE arrivee_id = ? AND type_note = 'consultation'", [$arrivee_id]);
                if ($aResultat === 0) {
                    $flash = ['red', 'Saisissez d\'abord le résultat de la consultation.'];
                } elseif (terminer_arrivee($arrivee_id)) {
                    logActivity('Consultation terminée (arrivée ' . $arrivee_id . ')', 'green', 'rendez_vous', $arrivee_id);
                    $flash = ['green', 'Consultation terminée.'];
                } else {
                    $flash = ['red', 'Échec de la clôture.'];
                }
            }
        }
```

- [ ] **Step 3: Charger les résultats existants (après `$confreres`)**

Dans `pages/consultations.php`, trouver le bloc de chargement :
```php
// --- Chargement ---
$consultations = get_mes_consultations($me);
$confreres = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' AND id<>? ORDER BY nom", [$me]);
```
Ajouter immédiatement après (pattern par-ligne, cohérent avec `get_dernieres_constantes` déjà appelé par ligne plus bas — `get_resultat_consultation` est résilient en interne) :
```php

// Résultats de consultation déjà saisis pour les arrivées visibles (pré-remplissage modal).
$resultats = [];
foreach ($consultations as $a) {
    $r = get_resultat_consultation((int)$a['id']);
    if ($r) {
        $resultats[(int)$a['id']] = $r;
    }
}
```

- [ ] **Step 4: Ajouter le bouton « 📝 Résultat » et désactiver « Terminer » sans résultat**

Dans `pages/consultations.php`, trouver le bloc d'actions de ligne :
```php
          <?php if (can('consultations.reorienter') && !empty($confreres)): ?>
          <button class="btn btn-sm btn-ghost" data-reorienter="<?= (int)$a['id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">↺ Réorienter</button>
          <?php endif; ?>
          <?php if (can('consultations.terminer')): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Terminer la consultation de ce patient ?')">
            <input type="hidden" name="action" value="terminer">
            <input type="hidden" name="arrivee_id" value="<?= (int)$a['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-green" style="font-size:11px">✓</button>
          </form>
          <?php endif; ?>
```
Remplacer par (insertion du bouton Résultat avant le form terminer + attribut `disabled`/`title` sur le bouton terminer) :
```php
          <?php if (can('consultations.reorienter') && !empty($confreres)): ?>
          <button class="btn btn-sm btn-ghost" data-reorienter="<?= (int)$a['id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">↺ Réorienter</button>
          <?php endif; ?>
          <?php if (can('consultations.resultat')): ?>
          <button class="btn btn-sm btn-ghost" data-resultat="<?= (int)$a['id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">📝 Résultat<?php if (!empty($a['a_resultat'])): ?> ✓<?php endif; ?></button>
          <?php endif; ?>
          <?php if (can('consultations.terminer')): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Terminer la consultation de ce patient ?')">
            <input type="hidden" name="action" value="terminer">
            <input type="hidden" name="arrivee_id" value="<?= (int)$a['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-green" style="font-size:11px"<?php if (empty($a['a_resultat'])): ?> disabled title="Saisissez d'abord le résultat de consultation"<?php else: ?> title="Terminer la consultation"<?php endif; ?>>✓</button>
          </form>
          <?php endif; ?>
```

- [ ] **Step 5: Ajouter le modal `modal-resultat` + le JS (après le script réorienter, avant le footer)**

Dans `pages/consultations.php`, trouver la fin du script réorienter :
```php
<script>
document.querySelectorAll('[data-reorienter]').forEach(function(btn){
  btn.addEventListener('click', function(){
    var m = document.getElementById('modal-reorienter'); if(!m) return;
    document.getElementById('or-arrivee_id').value = btn.getAttribute('data-reorienter');
    document.getElementById('or-nom').textContent = btn.getAttribute('data-nom');
    m.style.display='flex';
  });
});
</script>
```
Ajouter immédiatement après ce `</script>` (et avant `<?php require_once __DIR__ . '/../includes/footer.php';`) :

```php
<?php if (can('consultations.resultat')): ?>
<div id="modal-resultat" class="modal-overlay" role="dialog" aria-modal="true" style="display:none;z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(560px,95vw);max-height:90vh;overflow:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0;font-size:16px">Résultat de la consultation</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-resultat').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">✕</button>
    </div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="resultat"><?= csrf_field() ?>
      <input type="hidden" name="arrivee_id" id="res-arrivee_id" value="0">
      <p style="margin:0 0 12px;font-size:13px;color:var(--text2)">Patient : <strong id="res-nom" style="color:var(--text)">—</strong></p>
      <label style="font-size:12px;color:var(--text2)">Motif de consultation</label>
      <input type="text" name="motif" id="res-motif" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%;margin:6px 0 12px">
      <label style="font-size:12px;color:var(--text2)">Histoire de la maladie</label>
      <textarea name="histoire" id="res-histoire" rows="2" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%;margin:6px 0 12px;resize:vertical"></textarea>
      <label style="font-size:12px;color:var(--text2)">Examen clinique</label>
      <textarea name="examen" id="res-examen" rows="2" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%;margin:6px 0 12px;resize:vertical"></textarea>
      <label style="font-size:12px;color:var(--text2)">Diagnostic</label>
      <textarea name="diagnostic" id="res-diagnostic" rows="2" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%;margin:6px 0 12px;resize:vertical"></textarea>
      <label style="font-size:12px;color:var(--text2)">Conduite à tenir</label>
      <textarea name="conduite" id="res-conduite" rows="2" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%;margin:6px 0 12px;resize:vertical"></textarea>
      <div style="margin-top:18px;display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-resultat').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<script>
(function(){
  var RES = <?= json_encode($resultats ?: (object)[]) ?>;
  var COLS = {motif:'titre', histoire:'subjective', examen:'objective', diagnostic:'analyse', conduite:'plan'};
  document.querySelectorAll('[data-resultat]').forEach(function(btn){
    btn.addEventListener('click', function(){
      var m = document.getElementById('modal-resultat'); if(!m) return;
      var id = btn.getAttribute('data-resultat');
      document.getElementById('res-arrivee_id').value = id;
      document.getElementById('res-nom').textContent = btn.getAttribute('data-nom');
      var r = RES[id] || null;
      ['motif','histoire','examen','diagnostic','conduite'].forEach(function(k){
        var el = document.getElementById('res-'+k);
        if(el) el.value = r ? (r[COLS[k]] || '') : '';
      });
      m.style.display = 'flex';
    });
  });
})();
</script>
<?php endif; ?>
```

- [ ] **Step 6: Lint**

Run: `php -l pages/consultations.php`
Expected: `No syntax errors detected`

- [ ] **Step 7: Commit**

```bash
git add pages/consultations.php
git commit -m "feat(consultations): bouton Résultat + modal entretien 5 champs + gating strict Terminer"
```

---

## QA final (manuel, end-to-end — à exécuter après les 4 tâches)

1. S'assurer que `sql/update_v7.sql` a été exécuté (tâche 1) et le seeder re-exécuté (tâche 2). **Se reconnecter** en `medecin` (cache perms rafraîchi).
2. En `infirmier`/`admin` : accueil → check-in → constantes → orienter vers Dr X.
3. Reconnecter en Dr X → « Consultations » affiche le patient. Le bouton `✓` Terminer est **désactivé** (hint « Saisissez d'abord le résultat de consultation »).
4. Cliquer « 📝 Résultat » → modal vide → saisir les 5 champs → Enregistrer → flash vert « Résultat de consultation enregistré. » ; la ligne revient, le bouton devient « 📝 Résultat ✓ » et `✓` Terminer est activé.
5. Re-cliquer « 📝 Résultat ✓ » → modal **pré-rempli** avec les valeurs → modifier → Enregistrer (upsert, vérifier qu'aucun doublon n'apparaît : `SELECT COUNT(*) FROM notes_cliniques WHERE arrivee_id=X AND type_note='consultation'` = 1).
6. Ouvrir `dossiers.php` du patient (lien signé) → la note `consultation` apparaît dans la section Notes cliniques avec les 5 champs.
7. Cliquer `✓` Terminer → `statut='termine'`, patient quitte la file. Vérifier `SELECT statut, date_fin FROM arrivees_patients WHERE id=X`.
8. **Tamper-proofing** : ré-orienter un nouveau patient vers Dr X, puis (sans saisir de résultat) forcer un POST `action=terminer` (curl ou devtools) → flash red « Saisissez d'abord le résultat de la consultation. » et la consultation n'est PAS terminée.
9. Comptes autres rôles (infirmier, caissier, pharmacien, comptable) : le bouton « 📝 Résultat » n'apparaît pas (gated `can('consultations.resultat')`).
10. Vérifier le journal d'activité : `logActivity` trace la saisie du résultat (entité `notes_cliniques`).