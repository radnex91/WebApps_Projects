# Module « Résultat consultation » (entretien médecin) — Spec

> Date : 2026-07-02 · Projet : MediCore ERP · Suite du module Consultations

## Contexte & problème

Le module Consultations (`pages/consultations.php`, workspace médecin) liste les arrivées `en_consultation` orientées vers le médecin connecté, avec 4 actions (Voir dossier, Prescrire, Réorienter, Terminer). Aujourd'hui le médecin peut **terminer** une consultation sans rien saisir : aucun compte-rendu d'entretien n'est enregistré. Le parcours clinique est donc incomplet — on perd la trace de l'entretien médecin-patient.

## Objectif

Ajouter un bouton d'action **« Résultat consultation »** ouvrant un modal permettant au médecin de saisir l'entretien avec le patient selon **5 champs** français :

1. **Motif de consultation**
2. **Histoire de la maladie**
3. **Examen clinique**
4. **Diagnostic**
5. **Conduite à tenir**

Le résultat est stocké en réutilisant la table `notes_cliniques` (`type_note='consultation'`) et apparaît automatiquement dans le dossier patient. **Gating strict** : le bouton « Terminer » est désactivé tant qu'aucun résultat n'a été saisi pour cette consultation (avec contrôle serveur de tamper-proofing).

## Décisions produit

- **Stockage** : réutiliser `notes_cliniques` + ajouter une colonne `arrivee_id` nullable (liaison résultat ↔ arrivée). Une seule source of truth pour les notes cliniques ; affichage dossier gratuit (déjà agrégé dans `dossiers.php`).
- **Champs** : entretien français 5 champs, mappés sur les colonnes SOAP existantes (`titre`, `subjective`, `objective`, `analyse`, `plan`).
- **Lien avec Terminer** : gating strict — Terminer exige qu'un résultat existe pour cette arrivée (désactivé côté UI + re-check serveur).
- **Édition** : le modal est un **upsert** — réouvrir le bouton pré-remplit le résultat existant pour lecture/édition. Pas de vue read-only séparée.
- **Périmètre** : un résultat par arrivée (clé logique `arrivee_id` + `type_note='consultation'`).

## Architecture

### 1. Migration `sql/update_v7.sql` (idempotente)

Garir chaque `ALTER` par `INFORMATION_SCHEMA` ; `CREATE INDEX` idempotent.

- `notes_cliniques` : `ADD COLUMN arrivee_id INT NULL` (AFTER `hospitalisation_id`).
- `ADD INDEX idx_notes_arrivee (arrivee_id)`.
- `ADD CONSTRAINT fk_notes_arrivee FOREIGN KEY (arrivee_id) REFERENCES arrivees_patients (id) ON DELETE SET NULL` (guardé : drop si existe déjà).
- **Pas de seed de permissions dans le SQL** — le seeder `sql/seed_permissions.php` est canonique (cohérent avec le module Consultations livré).

### 2. RBAC

- `includes/permissions.php` `ALL_ACTIONS` : `'consultations.resultat' => ['label'=>'Saisir le résultat d\'une consultation','module'=>'Consultations']` (après `consultations.terminer`).
- `sql/seed_permissions.php` matrice : `medecin` actions += `consultations.resultat` ; `admin` (auto via `$ALL_ACTIONS`). Les autres rôles : absent → 0.
- Re-exécuter le seeder puis **se reconnecter** (cache perms en session).

### 3. Helpers — `includes/accueil.php` (après les helpers Consultations existants)

Tous wrappés `try { ... } catch (Throwable $e) { _log_error('CONSULT', ...); return valeur sûre; }`.

- `get_resultat_consultation(int $arrivee_id): ?array`
  - `SELECT id, titre, subjective, objective, analyse, plan FROM notes_cliniques WHERE arrivee_id = ? AND type_note = 'consultation' ORDER BY date_creation DESC LIMIT 1`
  - Retourne la ligne ou `null`.

- `save_resultat_consultation(int $arrivee_id, int $patient_id, int $medecin_id, array $f): int`
  - `$f` = `['motif'=>..., 'histoire'=>..., 'examen'=>..., 'diagnostic'=>..., 'conduite'=>...]` (chaînes déjà nettoyées via `post_str` côté page).
  - **Upsert** : si `get_resultat_consultation($arrivee_id)` existe → `UPDATE ... SET titre=?, subjective=?, objective=?, analyse=?, plan=?, date_note=NOW(), utilisateur_id=? WHERE id=?`. Sinon → `INSERT INTO notes_cliniques (patient_id, utilisateur_id, type_note, titre, subjective, objective, analyse, plan, date_note, arrivee_id) VALUES (?,?,?,?,?,?,?,NOW(),?)`.
  - Retourne l'id de la ligne (lastInsertId pour l'insert, id existant pour l'update). `return 0` en cas d'échec.

- `get_mes_consultations(int $medecin_id): array` (modification)
  - Ajouter au SELECT : `EXISTS(SELECT 1 FROM notes_cliniques n WHERE n.arrivee_id = a.id AND n.type_note = 'consultation') AS a_resultat`.

### 4. `pages/consultations.php`

Suit le pattern existant. Ajouts :

- **POST `resultat`** (perm `consultations.resultat`), inséré avant le handler `terminer` :
  - `csrf_verify()` d'abord.
  - Lire `$arrivee_id = post_int('arrivee_id')` ; valider `> 0`.
  - **IDOR** (même patron que les handlers existants) : `$owner = (int) db_scalar("SELECT medecin_id FROM arrivees_patients WHERE id = ? AND statut = 'en_consultation'", [$arrivee_id])` ; si `$owner !== $me` → flash red « Arrivée introuvable ou non attribuée à vous. » + skip.
  - Lire les 5 champs via `post_str` : `motif`, `histoire`, `examen`, `diagnostic`, `conduite`. **Mêmes noms que les clés du helper `$f` et que les ids JS** (pas de mapping intermédiaire).
  - Si **les 5 sont vides** → flash red « Saisissez au moins un champ du résultat. » + skip. (Un seul champ non vide suffit pour créer le résultat.)
  - Récupérer `$patient_id` de l'arrivée : `db_scalar("SELECT patient_id FROM arrivees_patients WHERE id=?", [$arrivee_id])`.
  - Appeler `save_resultat_consultation($arrivee_id, (int)$patient_id, $me, [...])`.
  - Si OK → `logActivity('Résultat de consultation saisi (arrivée ' . $arrivee_id . ')', 'blue', 'notes_cliniques', $resultatId)` + flash green « Résultat de consultation enregistré. »
  - Sinon → flash red « Échec de l'enregistrement. »
  - Redirect (PRG) vers `consultations.php` (pour rafraîchir l'état `a_resultat`).

- **POST `terminer`** (modification) :
  - Conserver l'ownership check existant.
  - **Nouveau re-check serveur (tamper-proofing du gating strict)** : `$aResultat = (int) db_scalar("SELECT COUNT(*) FROM notes_cliniques WHERE arrivee_id = ? AND type_note = 'consultation'", [$arrivee_id])` ; si `=== 0` → flash red « Saisissez d'abord le résultat de la consultation. » + skip (ne pas appeler `terminer_arrivee`).
  - Reste inchangé sinon.

- **Chargement** : `$consultations = get_mes_consultations($me)` (inclut maintenant `a_resultat`).
  - Charger en une requête les résultats existants pour les arrivées visibles :
    `$arriveeIds = array_column($consultations, 'id')` ; si non vide, `SELECT arrivee_id, titre, subjective, objective, analyse, plan FROM notes_cliniques WHERE type_note='consultation' AND arrivee_id IN (placeholders)` → tableau `$resultats` clé par `arrivee_id`. (Si vide, `$resultats = []`.)

- **UI — colonne Actions** (`.row-actions`), nouveau bouton **avant** « Terminer » :
  - `<button class="btn btn-sm btn-ghost" data-resultat="<?= (int)$a['id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>"<?php if(!empty($a['a_resultat'])): ?> data-exists="1"<?php endif; ?>>📝 Résultat<?php if(!empty($a['a_resultat'])): ?> ✓<?php endif; ?></button>`
  - Gated sur `can('consultations.resultat')`.

- **UI — bouton « Terminer »** (modification) :
  - Ajouter `<?php if (empty($a['a_resultat'])): ?> disabled title="Saisissez d'abord le résultat de consultation"<?php else: ?> title="Terminer la consultation"<?php endif; ?>` sur le `<button type="submit">`.
  - Visuellement : bouton disabled est grisé par le CSS existant (`btn-green[disabled]` hérite du navigateur) ; ajout d'une opacity via inline style si nécessaire.

- **Modal `modal-resultat`** (rendu une seule fois, après `modal-reorienter`) :
  - Header « Résultat de la consultation » + nom patient affiché (`#res-nom`).
  - Form POST → action `resultat` :
    - hidden `arrivee_id` (id `#res-arrivee_id`), `csrf_field()`.
    - 5 champs : Motif (input text, id `#res-motif`), Histoire de la maladie (textarea `#res-histoire`), Examen clinique (textarea `#res-examen`), Diagnostic (textarea `#res-diagnostic`), Conduite à tenir (textarea `#res-conduite`).
    - Boutons Annuler / Enregistrer.
  - Fermeture : `modal-close` + clic extérieur (même patron que `modal-reorienter`).

- **JS** (après le JS réorienter existant) :
  - Émettre `$resultats` en JSON : `var RES = <?= json_encode($resultats ?: (object)[]) ?>;` (clé = arrivee_id ; `(object)[]` force un objet même vide pour `RES[id]`).
  - Const de mapping champ JS → colonne BDD : `var COLS = {motif:'titre', histoire:'subjective', examen:'objective', diagnostic:'analyse', conduite:'plan'};`
  - `document.querySelectorAll('[data-resultat]').forEach(function(btn){ btn.addEventListener('click', function(){ var m = document.getElementById('modal-resultat'); if(!m) return; var id = btn.getAttribute('data-resultat'); document.getElementById('res-arrivee_id').value = id; document.getElementById('res-nom').textContent = btn.getAttribute('data-nom'); var r = RES[id] || null; ['motif','histoire','examen','diagnostic','conduite'].forEach(function(k){ var el = document.getElementById('res-'+k); if(el) el.value = r ? (r[COLS[k]]||'') : ''; }); m.style.display='flex'; }); });`
  - Le nom patient vient du `data-nom` du bouton (pas d'objet JS séparé).

### 5. Dossier patient (`pages/dossiers.php`)

**Aucune modification** — `notes_cliniques` est déjà agrégée dans le carnet électronique (livré par le module tickets-dossier). Le résultat (`type_note='consultation'`) apparaît automatiquement.

## Sécurité (conventions MediCore préservées)

- POST `resultat` : `csrf_verify()` d'abord, `can('consultations.resultat')`, IDOR (arrivee_id appartient au médecin connecté, statut `en_consultation`).
- POST `terminer` : re-check serveur qu'un résultat existe (tamper-proofing du gating strict) — le disabled côté UI ne suffit pas.
- Échappement : `h()` sur toutes les sorties patient/médecin ; les valeurs du formulaire sont lues via `post_str` (nettoyées) puis stockées en prepared statements.
- DB : prepared statements via `db_select`/`db_row`/`db_scalar`/`db_exec` uniquement — jamais de concaténation. Le `IN (...)` pour les arrivées visibles utilise un placeholder `?` par id (pas d'interpôle).
- JSON : `json_encode($resultats)` pour le pré-remplissage JS — valeurs issues de la BDD, échappées par `json_encode` (pas d'injection HTML ; `json_encode` neutralise les `</script>`).

## Fichiers

- **Créer** : `sql/update_v7.sql`
- **Modifier** : `includes/permissions.php` (ALL_ACTIONS +1), `sql/seed_permissions.php` (matrice medecin +1 action), `includes/accueil.php` (+ 2 helpers, modif `get_mes_consultations`), `pages/consultations.php` (POST `resultat`, modif POST `terminer`, bouton + modal + JS, bouton Terminer disabled).

## Hors périmètre (YAGNI)

- Pas de PDF / impression du résultat, pas de signature électronique.
- Pas de page read-only dédiée (le modal pré-remplit = lecture + édition ; le dossier = lecture).
- Pas de liaison résultat → ordonnance/imagerie (les actions existantes couvrent déjà les prescriptions).
- Pas de multi-résultats par consultation (un seul résultat upsert par arrivée).
- Pas de gating sur Réorienter (la réorientation peut se faire sans résultat — seule la terminaison l'exige).

## QA (manuel, end-to-end)

1. Exécuter `sql/update_v7.sql` (1x puis 2x → no-op, vérifie l'idempotence). Re-seed permissions : `php sql/seed_permissions.php` → se reconnecter en `medecin`.
2. Se connecter en `infirmier`/`admin` : accueil → check-in → constantes → orienter vers Dr X.
3. Reconnecter en Dr X → « Consultations » affiche le patient. Le bouton « ✓ Terminer » est **désactivé** (grisé, hint « Saisissez d'abord le résultat »).
4. Cliquer « 📝 Résultat » → modal vide (Motif pré-rempli depuis le motif d'arrivée) → saisir les 5 champs → Enregistrer → flash vert ; la ligne revient, le bouton devient « 📝 Résultat ✓ » et « ✓ Terminer » est activé.
5. Re-cliquer « 📝 Résultat ✓ » → modal pré-rempli avec les valeurs → modifier → Enregistrer (upsert, pas de doublon).
6. Ouvrir `dossiers.php` du patient → la note `consultation` apparaît dans la section Notes cliniques avec les 5 champs.
7. Cliquer « ✓ Terminer » → `statut='termine'`, le patient quitte la file. Vérifier qu'un POST `terminer` sans résultat (curl/crafté) → flash red + pas de terminaison (tamper-proofing).
8. Comptes autres rôles : pas d'accès `consultations.resultat` ; le bouton « Résultat » n'apparaît pas (gated `can('consultations.resultat')`).
9. Vérifier `logActivity` trace la saisie du résultat (entité `notes_cliniques`).