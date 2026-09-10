# Spec — Module Accueil (salle d'attente / réception)

**Date** : 2026-07-02
**Projet** : MediCore ERP (PHP vanilla, XAMPP/MySQL, UI française)
**Statut** : Validé en brainstorming, en attente de revue utilisateur

## Contexte et motivation

Aujourd'hui l'arrivée d'un patient n'a pas de point d'entrée dédié : la création de
fiche se fait dans `pages/patients.php`, la prise de constantes dans
`pages/observations.php`, et il n'existe **aucune** notion de salle d'attente / file
d'attente (le `triage_urgences` existant est post-hospitalisation et lié à
`hospitalisations.id`, donc inadapté à l'accueil général).

On veut un module **Accueil** qui modélise la salle d'accueil réelle : c'est là qu'on
**enregistre un patient à son arrivée** (nouveau ou check-in d'un existant), qu'on
**prend ses paramètres vitaux** (température, poids, tension, etc.), et qu'on **suit
sa progression** jusqu'à la prise en charge.

## Décisions produit

- **Portée** : salle d'attente vivante — file d'attente du jour avec progression de
  statut (Arrivé → Constantes prises → En consultation → Terminé). *(Validé par
  l'utilisateur, choix A.)*
- **Constantes** : réutilisation de la table existante `observations_infirmieres`
  (format long, `hospitalisation_id` NULL pour le pré-admission). Aucun nouveau
  schéma vitals.
- **Gating souple** : alerte jaune non bloquante si le patient n'a pas de dossier
  médical ou de consultation payée à la caisse (cohérent avec le module tickets
  dossier/consultation livré le 2026-07-02).
- **Rôles** : `admin` (tout), `infirmier` (page + check-in + constantes). Ajustable
  via `pages/roles.php`.

## Architecture

Nouveau module `pages/accueil.php` suivant le patron standard du projet :

```php
$currentPage = 'accueil';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dossiers.php'; // flags dossier/consultation (gating souple)
require_once __DIR__ . '/../includes/accueil.php';   // helpers arrivée
requireLogin();
// POST handlers (csrf_verify) ...
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('accueil');
// ... rendu ...
require_once __DIR__ . '/../includes/footer.php';
```

Nouvelle entrée `accueil` dans `ALL_PAGES` (`includes/permissions.php`), positionnée
**avant** `patients` (c'est l'entrée du flux patient). Icône `ICON_NAV['accueil']`
déclarée dans `includes/icons.php`.

Helpers regroupés dans `includes/accueil.php` (chargement explicite, pattern identique
à `includes/dossiers.php` — tous wrappés try/catch pour rester résilients si la table
`arrivees_patients` est absente).

## Modèle de données

### Nouvelle table `arrivees_patients` (la salle d'attente)

| Colonne | Type | Contraintes |
|---|---|---|
| `id` | INT AI | PK |
| `patient_id` | INT NOT NULL | FK `patients(id)` ON DELETE CASCADE |
| `cree_par` | INT NOT NULL | FK `utilisateurs(id)` |
| `date_arrivee` | DATETIME | DEFAULT CURRENT_TIMESTAMP |
| `statut` | ENUM(`arrive`,`constantes_prises`,`en_consultation`,`termine`,`parti`) | DEFAULT `arrive` |
| `motif` | VARCHAR(255) | NULL |
| `notes` | TEXT | NULL |
| `constantes_prises_par` | INT NULL | FK `utilisateurs(id)` |
| `date_constantes` | DATETIME | NULL |
| `medecin_id` | INT NULL | FK `utilisateurs(id)` |
| `date_prise_en_charge` | DATETIME | NULL |
| `date_fin` | DATETIME | NULL |

Index : `idx_arrivee_patient_date` (`patient_id`, `date_arrivee`),
`idx_arrivee_statut` (`statut`), `idx_arrivee_date` (`date_arrivee`).

Un patient peut avoir plusieurs arrivées (jours différents) — pas de contrainte
d'unicité stricte. Un double check-in le même jour (arrivée active non
`termine`/`parti` déjà existante) est **détecté** : le réceptionniste voit une alerte
jaune sur la fiche existante et reçoit une confirmation JS avant de valider une
nouvelle insertion (pour éviter les doublons de file tout en restant souple).

### Constantes — réutilisation de `observations_infirmieres`

Aucun ALTER. À l'arrivée on insère une ligne par constante prise, avec
`hospitalisation_id = NULL` (pré-admission) et `utilisateur_id = currentUser()`.

Types utilisés (déjà dans l'enum existant) : `temperature`, `poids`, `taille`,
`ta_systolique`, `ta_diastolique`, `pouls`, `freq_respiratoire`, `spo2`, `glycemie`,
`bmi`. Le BMI est calculé côté code depuis poids (kg) + taille (cm) puis inséré comme
type `bmi` (valeur arrondie 1 décimale).

### Migration `sql/update_v6.sql` (idempotente)

- `CREATE TABLE IF NOT EXISTS arrivees_patients` (+ FK + index).
- `INSERT IGNORE INTO role_page_access` : `admin`, `infirmier` → page `accueil`.
- `INSERT IGNORE INTO role_action_access` : `admin` → `accueil.checkin`/`vitals`/`orienter` = 1 ;
  `infirmier` → `accueil.checkin`=1, `accueil.vitals`=1, `accueil.orienter`=0 ;
  `medecin` → page + actions = 0.
- `INSERT IGNORE INTO app_settings` : `accueil_verifier_consultation` = `1`
  (active l'avertissement souple dossier/consultation dans la file d'attente).
- Aucun ALTER de `observations_infirmieres` ou `patients`.

## Permissions

`includes/permissions.php` :

- `ALL_PAGES` : `'accueil' => ['label' => 'Accueil', 'section' => 'Clinique']` (avant
  `patients`).
- `ALL_ACTIONS` :
  - `'accueil.checkin'` → « Enregistrer / check-in d'un patient à l'accueil »
  - `'accueil.vitals'` → « Prendre les constantes d'arrivée »
  - `'accueil.orienter'` → « Orienter un patient vers un médecin »

Seed via `update_v6.sql` (cf. ci-dessus). Cache permissions invalidé par
`invalidate_permissions_cache()` après modification — géré par `pages/roles.php`.

## UI / composants (`pages/accueil.php`)

1. **En-tête + stats** : présents aujourd'hui, en attente de constantes, en
   consultation, terminés (4 cartes, patron `stats-grid` du projet).
2. **Barre d'actions** :
   - `＋ Enregistrer un patient` (modal création fiche + check-in auto).
   - `＋ Check-in patient existant` (recherche par nom/numéro).
   - `＋ Prendre les constantes` (depuis une ligne de la file).
3. **File d'attente du jour** (table) : patient (lien vers `dossiers.php` signé),
   heure d'arrivée, badge statut, constantes ✓/—, médecin orienté, age, actions par
   ligne (Prendre constantes / Orienter / Terminer). Filtre statut + recherche.
4. **Modal enregistrement** : champs identiques à `patients.php` `create_patient`
   (nom, prénom, date_naissance, sexe, adresse, téléphone, email, num_secu,
   groupe_sanguin, allergies, antecedents, contact_urgence_nom/tel, assurance) +
   `motif` d'arrivée. Crée la fiche **et** l'arrivée (`statut='arrive'`) en deux
   `db_exec` successifs (non atomiques : si le 2e échoue, la fiche patient reste
   créée — acceptable, l'arrivée pourra être recréée par check-in existant).
5. **Modal constantes d'arrivée** : température, poids, taille, TA sys/dia, pouls,
   FR, SpO2, glycémie. BMI auto (lecture seule). Alertes seuils **non bloquantes**
   affichées sous les champs hors-normes (seuils réutilisés de `observations.php` :
   température 36–38 °C, TA sys 90–140 / dia 60–90 mmHg, pouls 60–100 bpm,
   SpO2 95–100 %, glycémie 70–110 mg/dL, FR 12–20 /min).
6. **Modal orientation** : select médecin (rôle `medecin`, `statut='actif'`) →
   `statut='en_consultation'`, `medecin_id`, `date_prise_en_charge`. Lien optionnel
   « Créer un RDV » vers `appointments.php` (pré-rempli patient + médecin).
7. **Avertissement souple** (dans la file et les modals) : patient sans dossier
   médical ou sans consultation payée → badge/alerte jaune + lien
   `caisse.php?patient_id=…&ticket_type=…`. Submit reste activé. Désactivable via
   setting `accueil_verifier_consultation=0`.

## Flux de données

1. **Nouveau patient** (`action='create_and_checkin'`, perm `accueil.checkin`) :
   `Validator` sur les champs patient → `INSERT INTO patients` (numero auto
   `P-YYYY-NNNNN`, même logique que `patients.php:30`) → `INSERT INTO arrivees_patients`
   (`statut='arrive'`, `cree_par`, `motif`) → `logActivity` → redirect + refresh.
2. **Check-in existant** (`action='checkin'`, perm `accueil.checkin`) : `patient_id`
   requis (refus si ≤0) → `INSERT INTO arrivees_patients` → redirect. Alerte si déjà
   une arrivée `statut IN ('arrive','constantes_prises','en_consultation')` ce même
   jour pour ce patient.
3. **Prise de constantes** (`action='prendre_constantes'`, perm `accueil.vitals`) :
   `arrivee_id` + valeurs → `Validator` (whitelist types, `post_float` valeurs,
   refus si valeur ≤0 pour les constantes numériques obligatoires) → insertion
   multi-lignes `observations_infirmieres` (une par constante non vide,
   `hospitalisation_id` NULL) → `UPDATE arrivees_patients` SET
   `statut='constantes_prises'`, `date_constantes=NOW()`, `constantes_prises_par` →
   `logActivity` → redirect avec récap des alertes seuils.
4. **Orientation** (`action='orienter'`, perm `accueil.orienter`) : `arrivee_id` +
   `medecin_id` → `UPDATE arrivees_patients` SET `statut='en_consultation'`,
   `medecin_id`, `date_prise_en_charge=NOW()`.
5. **Terminer** (`action='terminer'`, perm `accueil.orienter`) : `UPDATE` SET
   `statut='termine'`, `date_fin=NOW()`.

Helpers `includes/accueil.php` :

- `get_arrivees_du_jour(?string $statut = null): array` — joint `patients` + évent.
  `utilisateurs` (médecin), flags dossier/consultation via sous-requêtes
  `EXISTS(...)` (try/catch → flags true si tables v5 absentes).
- `checkin_patient(int $patient_id, int $user_id, string $motif): int` — insère une
  arrivée, refuse si patient_id ≤0.
- `prendre_constantes(int $arrivee_id, int $patient_id, int $user_id, array $constantes): array`
  — insère les lignes `observations_infirmieres`, met à jour l'arrivée, retourne la
  liste des alertes seuils détectées.
- `orienter_arrivee(int $arrivee_id, int $medecin_id): bool`
- `terminer_arrivee(int $arrivee_id): bool`
- `arrivee_active_du_jour(int $patient_id): ?array` — détecte le double check-in.

## Gestion d'erreurs

- Prepared statements sur toutes les requêtes (`db_select`/`db_row`/`db_scalar`/`db_exec`).
- `csrf_verify()` en tête de chaque handler POST, `csrf_field()` dans chaque form.
- `Validator` pour les chaînes (required, whitelist) et `post_float`/`post_int` pour
  les valeurs ; refus des valeurs négatives/nulles sur les constantes obligatoires.
- Échappement systématique `h()`/`e()` sur toute sortie.
- Flags dossier/consultation et toutes les sous-requêtes v5 wrappées try/catch
  (résilient si migration v5 non jouée — flags à true = pas de gating).
- Helpers `includes/accueil.php` wrappés try/catch (`_log_error('ACCUEIL', ...)`).
- Les alertes seuils sont des **avertissements**, jamais des blocages.

## Tests / QA (manuel, end-to-end)

1. Lancer XAMPP, exécuter `sql/update_v6.sql` (2 fois → idempotence).
2. Se connecter `admin` → sidebar affiche « Accueil » avant « Patients ».
3. `＋ Enregistrer un patient` → remplir fiche + motif → la fiche est créée ET le
   patient apparaît dans la file d'attente (`statut='arrive'`).
4. `＋ Check-in patient existant` → sélectionner un patient déjà connu → apparaît dans
   la file. Re-check-in du même patient le même jour → alerte jaune « déjà présent ».
5. `Prendre les constantes` sur une ligne → saisir temp 39°C (hors seuil) → alerte
   sous le champ → enregistrer → `statut='constantes_prises'`, badge constantes ✓.
   Vérifier `SELECT * FROM observations_infirmieres WHERE patient_id=… AND
   hospitalisation_id IS NULL` (lignes présentes), et qu'elles remontent dans
   `dossiers.php` + `observations.php`.
6. `Orienter` → choisir un médecin → `statut='en_consultation'`, `medecin_id` et
   `date_prise_en_charge` renseignés. Lien « Créer un RDV » pré-remplit correctement
   `appointments.php`.
7. `Terminer` → `statut='termine'`, `date_fin` renseignée. La ligne quitte la file
   active (filtre « tous » la montre).
8. Patient sans dossier / sans consultation payée → alerte jaune + lien caisse dans
   la file et les modals. Mettre `accueil_verifier_consultation=0` → alerte
   disparaît.
9. Vérifier qu'`infirmier` (non admin) accède à la page et peut check-in + constantes
   mais **pas** orienter (bouton masqué / handler 403).
10. `patients.php` reste intact (pas de régression sur `create_patient` existant).

## Fichiers concernés

- `sql/update_v6.sql` (nouveau) — migration idempotente.
- `includes/accueil.php` (nouveau) — helpers arrivée.
- `includes/permissions.php` — `ALL_PAGES['accueil']` + 3 `ALL_ACTIONS`.
- `includes/icons.php` — `ICON_NAV['accueil']`.
- `pages/accueil.php` (nouveau) — le module.
- `assets/css/main.css` — styles mineurs si besoin (file d'attente, badges statut).

## Hors périmètre (YAGNI)

- Pas de triage urgence pré-admission (le `triage_urgences` existant reste
  post-hospitalisation).
- Pas de prise de rendez-vous **depuis** l'accueil autre que le lien de
  pré-remplissage vers `appointments.php`.
- Pas de gestion de présence multi-jours : la file affiche le jour courant (filtre
  date optionnel en lecture, pas d'édition historique).
- Pas d'impression de ticket d'arrivée (la caisse gère déjà ses tickets).