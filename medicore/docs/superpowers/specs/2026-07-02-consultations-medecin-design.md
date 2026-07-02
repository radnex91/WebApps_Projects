# Module « Consultations » (workspace médecin) — Spec

> Date : 2026-07-02 · Projet : MediCore ERP · Suite du module Accueil

## Contexte & problème

Le module Accueil permet d'enregistrer un patient à l'arrivée, prendre ses constantes, puis l'**orienter vers un médecin** (`orienter_arrivee` → `statut='en_consultation'`, `medecin_id` renseigné, `date_prise_en_charge`).

Mais une fois orienté, **le médecin n'a aucune vue pour reprendre ce patient** : il ne « voit » pas les patients orientés vers lui aujourd'hui. Aujourd'hui le médecin peut prescrire (`pharmacie.php` créer ordonnance) et voir un dossier (`dossiers.php`), mais rien ne relie l'arrivée orientée à son workspace. Le parcours est cassé entre l'accueil et la consultation.

## Objectif

Un workspace médecin « Mes consultations du jour » listant les arrivées `en_consultation` orientées vers le médecin connecté aujourd'hui, avec les actions de poursuite de consultation :
1. **Voir le dossier médical** — lien signé vers `dossiers.php` (déjà existant).
2. **Prescrire** — redirige vers `pharmacie.php` avec le modal ordonnance auto-ouvert, patient + médecin pré-sélectionnés.
3. **Réorienter vers un confrère** — réassigne `medecin_id` (et remet `date_prise_en_charge`) ; le patient reste `en_consultation` et apparaît dans la file du confrère.
4. **Terminer la consultation** — `statut='termine'` + `date_fin` (helper `terminer_arrivee` existant).

## Architecture

### Nouveau module `pages/consultations.php`

Suit le pattern page MediCore : `$currentPage='consultations'` → config/auth → `requireLogin()` → POST handlers (`csrf_verify` + `can()`) → layout → `requirePageAccess('consultations')` → rendu → footer.

- **Chargement** : `$consultations = get_mes_consultations((int) currentUser()['id'])` — arrivées `en_consultation` avec `medecin_id = moi` ET `DATE(date_arrivee) = CURDATE()`, triées par `date_prise_en_charge ASC`. Joint `patients`. Pour chaque patient, résumé des dernières constantes via `get_dernieres_constantes($patient_id)`.
- **POST `reorienter`** (perm `consultations.reorienter`) : valide `arrivee_id>0` et `new_medecin_id>0` (n'importe quel médecin actif, confrère ou moi-même — réorienter vers soi-même est un no-op inoffensif, pas de blocage), appelle `reorienter_vers()`, `logActivity` (purple, rendez_vous), redirige avec flash vert.
- **POST `terminer`** (perm `consultations.terminer`) : valide `arrivee_id>0`, `terminer_arrivee()`, `logActivity`, redirect flash vert.
- **Prescrire / Voir dossier** = liens GET (pas de POST).

### UI

- Titre « Mes consultations du jour » + sous-titre date du jour.
- Carte résumé : « N patient(s) en cours ».
- Table `class="tbl-actions"` (pattern row-actions du app) : colonnes **Patient** (nom + numéro + âge), **Arrivée / Prise en charge** (heures), **Dernières constantes** (T °C · TA sys/dia · pouls · SpO₂ · poids, valeurs hors-seuil surlignées en rouge), **Actions**.
- Row actions (`.row-actions`) : 📋 Dossier (lien signé), 💊 Prescrire (lien pharmacie pré-rempli), ↺ Réorienter (`data-reorienter` + `data-nom`), ✓ Terminer (mini-form).
- Modal `modal-reorienter` : select medecin (confrères actifs, `role='medecin'`, `statut='actif'`, exclure moi) + hidden `arrivee_id` + csrf.
- Empty state : « Aucun patient orienté vers vous aujourd'hui. »

### Helpers — extension `includes/accueil.php`

Ajout (résilients try/catch + `_log_error`) :

- `get_mes_consultations(int $medecin_id): array` — `SELECT ... FROM arrivees_patients a JOIN patients p ... WHERE a.medecin_id=? AND a.statut='en_consultation' AND DATE(a.date_arrivee)=CURDATE() ORDER BY a.date_prise_en_charge ASC`. Inclut flags `a_dossier`/`a_consultation` (réutilisés pour badges).
- `get_dernieres_constantes(int $patient_id): array` — dernières valeurs par `type_observation` (temperature, ta_systolique, ta_diastolique, pouls, spo2, poids) ; `SELECT type_observation, valeur, unite FROM observations_infirmieres WHERE patient_id=? ORDER BY date_observation DESC` puis dédup par type en PHP (limit ~12). Retourne `['temperature'=>['v'=>..,'u'=>'°C'], ...]`.
- `reorienter_vers(int $arrivee_id, int $new_medecin_id): bool` — `UPDATE arrivees_patients SET medecin_id=?, date_prise_en_charge=NOW() WHERE id=? AND statut='en_consultation'`. Garde le statut `en_consultation`.

`terminer_arrivee()` déjà existant — réutilisé tel quel.

### Pré-remplissage pharmacie.php

Ajout minimal à `pages/pharmacie.php` : si `get_int('patient_id') > 0 && get_int('new_ord') === 1`, injecter un bloc `<script>` (après le modal ordonnance / JS existant) qui :
1. `openModal('modal-ordonnance')` (ou set display flex),
2. pré-sélectionne l'`<option value="patient_id">` dans `#inp-patient_id`,
3. pré-sélectionne l'`<option value="medecin_id">` dans `#inp-medecin_id` (si `get_int('medecin_id')` fourni).
Protégé par `can('ordonnances.create')` côté page (déjà gated par layout/requirePageAccess). Si le patient n'existe pas dans le select (cas tordu), pas de fatal — juste pas de sélection.

### RBAC

- `includes/permissions.php` `ALL_PAGES` : `'consultations' => ['label'=>'Consultations','icon'=>'consultations','section'=>'Clinique']` (après `appointments`).
- `includes/icons.php` `ICON_NAV` : `'consultations' => '<i class="bi bi-clipboard2-pulse-fill"></i>'`.
- `ALL_ACTIONS` : `consultations.prescrire`, `consultations.reorienter`, `consultations.terminer` (module 'Consultations').
- `sql/seed_permissions.php` matrice : `medecin` pages += `consultations`, actions += les 3 ; `admin` (auto via ALL_PAGES/ALL_ACTIONS) ; les autres rôles **non** (page et actions absents de leur liste → 0).

### Sécurité (conventions MediCore préservées)

- Tous les POST : `csrf_verify()` + `can('consultations.*')` avant traitement.
- Liens dossier : `secure_url('dossiers.php', $patient_id, 'dossier', ['patient_id'=>$patient_id])` — jamais de `?id=` nu.
- Échappement : `h()` sur toutes les valeurs patient/médecin affichées.
- DB : prepared statements via `db_select`/`db_row`/`db_exec` uniquement.

## Fichiers

- **Créer** : `pages/consultations.php`
- **Modifier** : `includes/accueil.php` (+ 3 helpers), `includes/permissions.php` (ALL_PAGES + 3 ALL_ACTIONS), `includes/icons.php` (ICON_NAV), `sql/seed_permissions.php` (matrice medecin/admin), `pages/pharmacie.php` (pré-remplissage GET ordonnance).

## Hors périmètre (YAGNI)

- Pas de création de RDV automatique depuis la consultation (l'orientation est un walk-in, pas un RDV planifié).
- Pas de saisie de constantes supplémentaires pendant la consultation (les constantes sont prises à l'accueil ; le médecin peut rouvrir le dossier pour ajouter des observations via le module existant si besoin).
- Pas de widget dashboard dédié (la sidebar suffit ; badge possiblement plus tard).
- Pas de facturation depuis la consultation (rôle caissier/comptable séparé).

## QA (manuel, end-to-end)

1. Re-seed permissions (`php sql/seed_permissions.php`) → se connecter en `medecin` → sidebar affiche « Consultations ».
2. En `infirmier`/`admin` : accueil → check-in patient → constantes → orienter vers Dr X.
3. Reconnecter en `medecin` (Dr X) → « Consultations » affiche le patient (T/TA/pouls/SpO₂/poids, hors-seuil en rouge) ; les autres médecins ne voient pas ce patient.
4. « Voir dossier » → ouvre `dossiers.php?patient_id=&tok=` (carnet agrégé).
5. « Prescrire » → `pharmacie.php` s'ouvre, modal ordonnance auto-ouvert, patient + Dr X pré-sélectionnés ; créer une ordonnance → `ordonnances` a `medecin_id` = Dr X.
6. « Réorienter » → choisir Dr Y → le patient disparaît de la file de Dr X, apparaît dans celle de Dr Y (statut reste `en_consultation`).
7. « Terminer » → `statut='termine'`, `date_fin` renseignée, patient quitte la file.
8. Vérifier `logActivity` (journal d'activité) trace réorientation + terminer.
9. Comptes autres rôles (infirmier, caissier, pharmacien, comptable) : « Consultations » absent de la sidebar, accès direct → 403.