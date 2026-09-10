# Module Consultations (workspace médecin) — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Donner au médecin un workspace « Mes consultations du jour » listant les patients orientés vers lui (`arrivees_patients.statut='en_consultation'`, `medecin_id=moi`, arrivée du jour), avec actions Voir dossier / Prescrire / Réorienter / Terminer.

**Architecture:** Nouveau module `pages/consultations.php` réutilisant la table `arrivees_patients` (aucune migration). 3 helpers ajoutés à `includes/accueil.php`. Pré-remplissage GET minimal ajouté à `pages/pharmacie.php` pour ouvrir le modal ordonnance avec patient + médecin pré-sélectionnés. RBAC : nouvelle page `consultations` + 3 actions, seedées pour `medecin` + `admin`.

**Tech Stack:** PHP 7.4+ vanilla, MySQL/MariaDB via PDO (helpers `db_select`/`db_row`/`db_exec`), French UI, aucune étape de build. Pas de framework de tests — QA = `php -l` + vérification SQL + test navigateur.

## Global Constraints

- Tous les POST : `csrf_verify()` avant traitement + `can('consultations.*')` (helper `can()` dans `includes/permissions.php`).
- DB : prepared statements uniquement via `db_select`/`db_row`/`db_exec` — jamais concaténer de valeurs dans le SQL.
- Échappement : `h()` sur toute sortie patient/médecin.
- Liens dossier : `secure_url('dossiers.php', $patient_id, 'dossier', ['patient_id'=>$patient_id])` — jamais de `?id=` nu sans `&tok=`.
- UI en français, commentaires en français.
- Helpers résilients : `try { ... } catch (Throwable $e) { _log_error('ACCUEIL', ...); return valeur sûre; }`.
- Aucune migration SQL (table `arrivees_patients` déjà créée par `sql/update_v6.sql`). Mise à jour RBAC via re-exécution du seeder existant `sql/seed_permissions.php`.

---

### Task 1: Helpers consultation dans `includes/accueil.php`

**Files:**
- Modify: `includes/accueil.php` (ajouter 3 fonctions en fin de fichier, après `terminer_arrivee` ~ligne 182)

**Interfaces:**
- Consumes: `db_select`, `db_exec`, `_log_error` (deja definis dans `includes/security.php` charge via `config.php`).
- Produces: `get_mes_consultations(int $medecin_id): array` — lignes `arrivees_patients` (id, patient_id, date_arrivee, date_prise_en_charge, motif, notes + patient nom/prenom/numero/date_naissance/sexe + flags a_dossier/a_consultation) pour `medecin_id=? AND statut='en_consultation' AND DATE(date_arrivee)=CURDATE()`, tri `date_prise_en_charge ASC`. `get_dernieres_constantes(int $patient_id): array` — `['type'=>['v'=>valeur,'u'=>unite], ...]` dernière valeur par type. `reorienter_vers(int $arrivee_id, int $new_medecin_id): bool`.

- [ ] **Step 1: Ajouter les 3 helpers en fin de `includes/accueil.php`**

Remplacer la fin du fichier (après la fonction `terminer_arrivee` existante) par :

```php
/**
 * Arrivées en consultation assignées au médecin connecté aujourd'hui.
 * Triées par heure de prise en charge asc. Joint patient + flags dossier/consultation.
 */
function get_mes_consultations(int $medecin_id): array {
    if ($medecin_id <= 0) return [];
    try {
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
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec get_mes_consultations', __FILE__, __LINE__, $e);
        return [];
    }
}

/**
 * Dernières constantes d'un patient : une valeur (la plus récente) par type_observation.
 * Retourne ['temperature'=>['v'=>36.5,'u'=>'°C'], ...].
 */
function get_dernieres_constantes(int $patient_id): array {
    $out = [];
    if ($patient_id <= 0) return $out;
    try {
        $rows = db_select(
            "SELECT type_observation, valeur, unite FROM observations_infirmieres WHERE patient_id = ? ORDER BY date_observation DESC",
            [$patient_id]
        );
        foreach ($rows as $r) {
            if (!array_key_exists($r['type_observation'], $out)) {
                $out[$r['type_observation']] = ['v' => $r['valeur'], 'u' => $r['unite']];
            }
        }
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec get_dernieres_constantes', __FILE__, __LINE__, $e);
    }
    return $out;
}

/**
 * Réoriente une arrivée vers un autre médecin : réassigne medecin_id et remet
 * date_prise_en_charge. Garde le statut 'en_consultation' (le patient reste en
 * consultation, dans la file du nouveau médecin).
 */
function reorienter_vers(int $arrivee_id, int $new_medecin_id): bool {
    if ($arrivee_id <= 0 || $new_medecin_id <= 0) return false;
    try {
        db_exec(
            "UPDATE arrivees_patients SET medecin_id = ?, date_prise_en_charge = NOW() WHERE id = ? AND statut = 'en_consultation'",
            [$new_medecin_id, $arrivee_id]
        );
        return true;
    } catch (Throwable $e) {
        _log_error('ACCUEIL', 'Échec reorienter_vers', __FILE__, __LINE__, $e);
        return false;
    }
}
```

- [ ] **Step 2: Vérifier la syntaxe**

Run: `php -l includes/accueil.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Commit**

```bash
git add includes/accueil.php
git commit -m "feat(consultations): helpers get_mes_consultations, get_dernieres_constantes, reorienter_vers"
```

---

### Task 2: RBAC — page `consultations` + 3 actions + icône + seed matrice

**Files:**
- Modify: `includes/permissions.php` (ALL_PAGES + ALL_ACTIONS)
- Modify: `includes/icons.php` (ICON_NAV)
- Modify: `sql/seed_permissions.php` (matrice medecin)

**Interfaces:**
- Produces: constante `ALL_PAGES['consultations']`, `ALL_ACTIONS['consultations.prescrire'|'consultations.reorienter'|'consultations.terminer']`, `ICON_NAV['consultations']`. Seeder ré-exécutable seede medecin + admin pour la nouvelle page et les 3 actions.

- [ ] **Step 1: Ajouter `consultations` à `ALL_PAGES` dans `includes/permissions.php`**

Dans `ALL_PAGES`, après la ligne `'appointments' => ['label'=>'Rendez-vous', ...],` ajouter :

```php
    'consultations' => ['label'=>'Consultations',  'icon'=>'consultations','section'=>''],
```

- [ ] **Step 2: Ajouter les 3 actions à `ALL_ACTIONS` dans `includes/permissions.php`**

Dans `ALL_ACTIONS`, après la ligne `'appointments.delete' => ...` ajouter :

```php
    'consultations.prescrire'  => ['label'=>'Prescrire une ordonnance depuis une consultation', 'module'=>'Consultations'],
    'consultations.reorienter' => ['label'=>'Réorienter un patient vers un confrère',           'module'=>'Consultations'],
    'consultations.terminer'   => ['label'=>'Terminer une consultation',                        'module'=>'Consultations'],
```

- [ ] **Step 3: Ajouter l'icône à `ICON_NAV` dans `includes/icons.php`**

Après la ligne `'appointments' => '<i class="bi bi-calendar-event-fill"></i>',` ajouter :

```php
    'consultations' => '<i class="bi bi-clipboard2-pulse-fill"></i>',
```

- [ ] **Step 4: Mettre à jour la matrice `medecin` dans `sql/seed_permissions.php`**

Dans le bloc `'medecin' => [...]`, ajouter `'consultations'` au tableau `'pages'` (après `'appointments'`) et les 3 actions au tableau `'actions'` (après `'appointments.delete'`).

Page : ajouter `'consultations',` dans la liste `medecin.pages` juste après `'appointments',`.

Actions : ajouter dans la liste `medecin.actions` juste après `'appointments.delete',` :

```php
            'consultations.prescrire','consultations.reorienter','consultations.terminer',
```

Note : `admin` hérite déjà de `$ALL_PAGES`/`$ALL_ACTIONS` (tout est autorisé). Les autres rôles ne voient pas `consultations` (absents de leur liste → 0).

- [ ] **Step 5: Vérifier la syntaxe des 3 fichiers modifiés**

Run: `php -l includes/permissions.php && php -l includes/icons.php && php -l sql/seed_permissions.php`
Expected: `No syntax errors detected` x3

- [ ] **Step 6: Re-seeder la BDD et vérifier les comptes medecin**

Run: `php sql/seed_permissions.php`
Expected: `OK : 104 pages + 136 actions insérées (6 rôles).` (medecin passe à 22 pages / 33 actions ; admin à 31 pages / 57 actions).

Vérifier :

```bash
php -r '$pdo=new PDO("mysql:host=127.0.0.1;dbname=medicore;charset=utf8mb4","root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
foreach($pdo->query("SELECT role, COUNT(*) c FROM role_page_access WHERE page=\"consultations\" GROUP BY role") as $r) print_r($r);
foreach($pdo->query("SELECT role, action FROM role_action_access WHERE action LIKE \"consultations.%\" ORDER BY role,action") as $r) echo $r["role"]." ".$r["action"]."\n";'
```
Expected : `admin` et `medecin` ont `consultations` (page) ; `admin`+`medecin` ont chacun les 3 `consultations.*` actions. Aucun autre rôle.

- [ ] **Step 7: Commit**

```bash
git add includes/permissions.php includes/icons.php sql/seed_permissions.php
git commit -m "feat(consultations): page + 3 actions RBAC + icône + seed medecin/admin"
```

---

### Task 3: Module `pages/consultations.php`

**Files:**
- Create: `pages/consultations.php`

**Interfaces:**
- Consumes: `get_mes_consultations`, `get_dernieres_constantes`, `reorienter_vers`, `terminer_arrivee` (Task 1 / `includes/accueil.php`) ; `secure_url`, `csrf_verify`, `csrf_field`, `post_str`, `post_int`, `get_str`, `can`, `currentUser`, `logActivity`, `h`, `ACCUEIL_SEUILS`, `ACCUEIL_UNITES` ; `setting()`.
- Produces: page `consultations.php` répondant GET (liste) + POST `action=reorienter` + POST `action=terminer`.

- [ ] **Step 1: Créer `pages/consultations.php` avec le squelette + POST handlers**

Contenu complet :

```php
<?php
$currentPage = 'consultations';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/accueil.php';
requireLogin();

$flash = null;
$me = (int) currentUser()['id'];

// --- POST : Réorienter vers un confrère ---
if (can('consultations.reorienter') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'reorienter') {
    csrf_verify();
    $arrivee_id    = post_int('arrivee_id');
    $new_medecin_id = post_int('new_medecin_id');
    if ($arrivee_id <= 0 || $new_medecin_id <= 0) {
        $flash = ['red', 'Arrivée et médecin cible requis.'];
    } elseif (reorienter_vers($arrivee_id, $new_medecin_id)) {
        $nomCible = db_scalar("SELECT CONCAT(prenom,' ',nom) FROM utilisateurs WHERE id=?", [$new_medecin_id]);
        logActivity('Consultation : patient réorienté vers Dr ' . ($nomCible ?: '#' . $new_medecin_id) . ' (arrivée ' . $arrivee_id . ')', 'purple', 'rendez_vous', $arrivee_id);
        $flash = ['green', 'Patient réorienté vers Dr ' . ($nomCible ?: 'confrère') . '.'];
    } else {
        $flash = ['red', 'Échec de la réorientation.'];
    }
}

// --- POST : Terminer la consultation ---
if (can('consultations.terminer') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'terminer') {
    csrf_verify();
    $arrivee_id = post_int('arrivee_id');
    if ($arrivee_id <= 0) {
        $flash = ['red', 'Arrivée requise.'];
    } elseif (terminer_arrivee($arrivee_id)) {
        logActivity('Consultation terminée (arrivée ' . $arrivee_id . ')', 'green', 'rendez_vous', $arrivee_id);
        $flash = ['green', 'Consultation terminée.'];
    } else {
        $flash = ['red', 'Échec de la clôture.'];
    }
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('consultations');

// --- Chargement ---
$consultations = get_mes_consultations($me);
$confreres = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' AND id<>? ORDER BY nom", [$me]);

// Helper local : libellé courte d'un type de constante.
$constLabels = [
    'temperature' => 'T', 'ta_systolique' => 'TAS', 'ta_diastolique' => 'TAD',
    'pouls' => 'Pouls', 'spo2' => 'SpO₂', 'glycemie' => 'Glyc', 'poids' => 'Poids', 'bmi' => 'BMI',
];
?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:16px">
  <div>
    <h1 style="margin:0">Mes consultations du jour</h1>
    <div style="font-size:12px;color:var(--text2)"><?= date('d/m/Y') ?> · <?= count($consultations) ?> patient(s) en cours</div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3>Patients orientés vers moi</h3><span style="font-size:12px;color:var(--text2)"><?= count($consultations) ?> en cours</span></div>
  <table class="tbl-actions">
    <thead><tr><th>Patient</th><th>Arrivée / Prise en charge</th><th>Dernières constantes</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($consultations as $a):
        $age = !empty($a['date_naissance']) ? (int)((time()-strtotime($a['date_naissance']))/31536000) : '?';
        $dossierUrl = secure_url('dossiers.php', (int)$a['patient_id'], 'dossier', ['patient_id' => (int)$a['patient_id']]);
        $prescUrl = 'pharmacie.php?tab=ordonnances&patient_id=' . (int)$a['patient_id'] . '&medecin_id=' . $me . '&new_ord=1';
        $c = get_dernieres_constantes((int)$a['patient_id']);
    ?>
      <tr>
        <td>
          <a href="<?= h($dossierUrl) ?>" style="text-decoration:none"><strong><?= h($a['prenom'].' '.$a['nom']) ?></strong></a>
          <div style="font-size:11px;color:var(--text3)"><?= h($a['numero']) ?> · <?= $age ?> ans · <?= h($a['sexe']) ?></div>
          <?php if (!empty($a['motif'])): ?><div style="font-size:11px;color:var(--text3);margin-top:2px">Motif : <?= h($a['motif']) ?></div><?php endif; ?>
        </td>
        <td>
          <div style="font-size:12px">Arrivée : <?= date('H:i', strtotime($a['date_arrivee'])) ?></div>
          <div style="font-size:12px;color:var(--text2)">PEC : <?= !empty($a['date_prise_en_charge']) ? date('H:i', strtotime($a['date_prise_en_charge'])) : '—' ?></div>
        </td>
        <td style="font-size:12px;line-height:1.6">
          <?php
          $parts = [];
          foreach (['temperature','ta_systolique','ta_diastolique','pouls','spo2','poids'] as $t) {
              if (!empty($c[$t])) {
                  $seuil = isset(ACCUEIL_SEUILS[$t]) ? ACCUEIL_SEUILS[$t] : null;
                  $oos = $seuil && ($c[$t]['v'] < $seuil['min'] || $c[$t]['v'] > $seuil['max']);
                  $val = h(($constLabels[$t] ?? $t) . ' ' . $c[$t]['v'] . $c[$t]['u']);
                  $parts[] = $oos ? '<span style="color:var(--accent2);font-weight:600">⚠ '.$val.'</span>' : $val;
              }
          }
          echo $parts ? implode(' · ', $parts) : '<span style="color:var(--text3)">Aucune constante</span>';
          ?>
        </td>
        <td><div class="row-actions"><span class="row-hint" aria-hidden="true">⋯</span>
          <?php if (can('consultations.prescrire')): ?>
          <a class="btn btn-sm btn-ghost" href="<?= h($prescUrl) ?>">💊 Prescrire</a>
          <?php endif; ?>
          <?php if (can('consultations.reorienter')): ?>
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
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($consultations)): ?>
      <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text3)">Aucun patient orienté vers vous aujourd'hui.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if (can('consultations.reorienter') && !empty($confreres)): ?>
<div id="modal-reorienter" class="modal-overlay" role="dialog" aria-modal="true" style="display:none;z-index:200;align-items:center;justify-content:center;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(460px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:16px 20px;border-bottom:1px solid var(--border2);display:flex;align-items:center;justify-content:space-between">
      <h3 style="margin:0;font-size:16px">Réorienter le patient</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-reorienter').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">✕</button>
    </div>
    <form method="POST" style="padding:20px">
      <input type="hidden" name="action" value="reorienter"><?= csrf_field() ?>
      <input type="hidden" name="arrivee_id" id="or-arrivee_id" value="0">
      <p style="margin:0 0 12px;font-size:13px;color:var(--text2)">Patient : <strong id="or-nom" style="color:var(--text)">—</strong></p>
      <label for="or-medecin" style="font-size:12px;color:var(--text2)">Médecin cible *</label>
      <select name="new_medecin_id" id="or-medecin" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%;margin-top:6px">
        <?php foreach ($confreres as $m): ?>
          <option value="<?= (int)$m['id'] ?>">Dr. <?= h($m['nom_complet']) ?><?= !empty($m['specialite']) ? ' — ' . h($m['specialite']) : '' ?></option>
        <?php endforeach; ?>
      </select>
      <div style="margin-top:18px;display:flex;gap:8px;justify-content:flex-end">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-reorienter').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Réorienter</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
document.querySelectorAll('[data-reorienter]').forEach(function(btn){
  btn.addEventListener('click', function(){
    document.getElementById('or-arrivee_id').value = btn.getAttribute('data-reorienter');
    document.getElementById('or-nom').textContent = btn.getAttribute('data-nom');
    var m = document.getElementById('modal-reorienter'); m.style.display='flex';
  });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
```

- [ ] **Step 2: Vérifier la syntaxe**

Run: `php -l pages/consultations.php`
Expected: `No syntax errors detected`

- [ ] **Step 3: Vérifier le boot HTTP (pas de fatal — la page redirige vers login sans session)**

Run: `php -r 'chdir("C:/xampp/htdocs/medicore"); $_SERVER["REQUEST_METHOD"]="GET"; include "pages/consultations.php";' && echo "boot OK (no fatal)"`
Expected: `boot OK (no fatal)` (la page appelle `requireLogin()` qui redirige vers login et `exit`s ; code de sortie 0 → pas de fatal). Si une erreur fatale est levée, le `&&` est court-circuité et le message d'erreur s'affiche dans stderr.

- [ ] **Step 4: Commit**

```bash
git add pages/consultations.php
git commit -m "feat(consultations): module pages/consultations.php (workspace médecin)"
```

---

### Task 4: Pré-remplissage ordonnance dans `pages/pharmacie.php`

**Files:**
- Modify: `pages/pharmacie.php` (ajouter un bloc `<script>` après le JS existant du modal ordonnance, ~après ligne 527 / avant la fermeture)

**Interfaces:**
- Consumes: `get_int`, `can` ; ids DOM `#modal-ordonnance`, `#inp-patient_id`, `#inp-medecin_id` (déjà existants dans la page).

- [ ] **Step 1: Localiser le point d'insertion**

Run: `grep -n "addOrdLigne\|modal-edit-med\|require_once.*footer" pages/pharmacie.php | head`
Le bloc JS du modal ordonnance se termine vers la ligne ~527 (`document.addEventListener('DOMContentLoaded', addOrdLigne);`). On insère juste après ce bloc JS (avant `require footer`).

- [ ] **Step 2: Ajouter le bloc de pré-remplissage**

Insérer ce bloc juste avant la ligne `<?php require_once __DIR__ . '/../includes/footer.php';` de `pages/pharmacie.php` :

```php
<?php // --- Pré-remplissage ordonnance depuis une consultation (GET ?patient_id=&medecin_id=&new_ord=1) ---
$pfPatient = get_int('patient_id'); $pfMedecin = get_int('medecin_id'); $pfNew = get_int('new_ord');
if ($pfNew === 1 && $pfPatient > 0 && can('ordonnances.create')): ?>
<script>
(function(){
  var m = document.getElementById('modal-ordonnance'); if(!m) return;
  m.style.display = 'flex';
  function sel(id, val){ var s = document.getElementById(id); if(!s||!val) return; for(var i=0;i<s.options.length;i++){ if(String(s.options[i].value)===String(val)){ s.selectedIndex=i; break; } } }
  sel('inp-patient_id', <?= (int)$pfPatient ?>);
  sel('inp-medecin_id', <?= (int)$pfMedecin ?>);
})();
</script>
<?php endif; ?>
```

- [ ] **Step 3: Vérifier la syntaxe**

Run: `php -l pages/pharmacie.php`
Expected: `No syntax errors detected`

- [ ] **Step 4: Vérifier le boot HTTP**

Run: `php -r 'chdir("C:/xampp/htdocs/medicore"); $_SERVER["REQUEST_METHOD"]="GET"; $_GET["patient_id"]="1"; $_GET["medecin_id"]="2"; $_GET["new_ord"]="1"; include "pages/pharmacie.php";' && echo "boot OK (no fatal)"`
Expected: `boot OK (no fatal)` (pas de fatal ; la session absente redirige via `requireLogin` et `exit`s proprement).

- [ ] **Step 5: Commit**

```bash
git add pages/pharmacie.php
git commit -m "feat(pharmacie): pré-remplissage GET du modal ordonnance (patient+medecin, new_ord=1)"
```

---

### Task 5: QA end-to-end + vérifications SQL

**Files:** (aucun — vérification manuelle)

- [ ] **Step 1: Vérifier le seeder est idempotent et que la matrice est correcte**

Run: `php sql/seed_permissions.php`
Expected: `OK : 104 pages + 136 actions insérées (6 rôles).` (idempotence — même output à la 2e exécution).

- [ ] **Step 2: Vérifier le lint de tous les fichiers modifiés**

Run: `php -l pages/consultations.php && php -l includes/accueil.php && php -l includes/permissions.php && php -l includes/icons.php && php -l pages/pharmacie.php && php -l sql/seed_permissions.php`
Expected: `No syntax errors detected` x6.

- [ ] **Step 3: Scénario SQL complet (sans navigateur)**

Vérifier le cycle orienter → reorienter → terminer via SQL (dans une transaction rollback) :

```bash
php -r '
$pdo=new PDO("mysql:host=127.0.0.1;dbname=medicore;charset=utf8mb4","root","",[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->beginTransaction();
$pid = $pdo->query("SELECT id FROM patients ORDER BY id LIMIT 1")->fetchColumn();
$medA = $pdo->query("SELECT id FROM utilisateurs WHERE role=\"medecin\" AND statut=\"actif\" ORDER BY id LIMIT 1")->fetchColumn();
$medB = $pdo->query("SELECT id FROM utilisateurs WHERE role=\"medecin\" AND statut=\"actif\" AND id<>$medA ORDER BY id LIMIT 1")->fetchColumn();
$pdo->exec("INSERT INTO arrivees_patients (patient_id,cree_par,statut,medecin_id,date_prise_en_charge) VALUES ($pid,$medA,\"en_consultation\",$medA,NOW())");
$aid = $pdo->lastInsertId();
echo "arrivee $aid orientee vers medA=$medA\n";
$r = $pdo->query("SELECT medecin_id,statut FROM arrivees_patients WHERE id=$aid")->fetch(PDO::FETCH_ASSOC);
echo "avant reorient: medecin_id={$r["medecin_id"]} statut={$r["statut"]}\n";
$pdo->exec("UPDATE arrivees_patients SET medecin_id=$medB,date_prise_en_charge=NOW() WHERE id=$aid AND statut=\"en_consultation\"");
$r = $pdo->query("SELECT medecin_id,statut FROM arrivees_patients WHERE id=$aid")->fetch(PDO::FETCH_ASSOC);
echo "apres reorient: medecin_id={$r["medecin_id"]} statut={$r["statut"]} (attendu: medB=$medB, en_consultation)\n";
$pdo->exec("UPDATE arrivees_patients SET statut=\"termine\",date_fin=NOW() WHERE id=$aid");
$r = $pdo->query("SELECT statut FROM arrivees_patients WHERE id=$aid")->fetch(PDO::FETCH_ASSOC);
echo "apres terminer: statut={$r["statut"]} (attendu: termine)\n";
$pdo->rollBack();
echo "rollback OK\n";'
```
Expected :
```
arrivee <aid> orientee vers medA=<medA>
avant reorient: medecin_id=<medA> statut=en_consultation
apres reorient: medecin_id=<medB> statut=en_consultation (attendu: medB=<medB>, en_consultation)
apres terminer: statut=termine (attendu: termine)
rollback OK
```

- [ ] **Step 4: QA navigateur manuel (à faire par l'utilisateur)**

Lancer XAMPP (Apache+MySQL). Scénario :
1. Se connecter en `admin` → Accueil → enregistrer/check-in un patient → prendre constantes → **Orienter vers Dr X**.
2. Se déconnecter, se reconnecter en `medecin` (compte Dr X) → sidebar affiche **Consultations** → le patient apparaît (T/TA/pouls/SpO₂/poids, hors-seuil en rouge).
3. Cliquer la ligne (révèle les actions via row-actions) → « 💊 Prescrire » → pharmacie s'ouvre, modal ordonnance **auto-ouvert**, patient + Dr X pré-sélectionnés → créer une ordonnance.
4. Retour Consultations → « ↺ Réorienter » → choisir Dr Y → patient disparaît de la file.
5. Reconnecter en Dr Y → Consultations affiche le patient → « ✓ Terminer » → `statut=termine`, patient quitte la file.
6. Vérifier le journal d'activité trace réorientation + terminer.
7. Comptes `infirmier`/`caissier`/`pharmacien`/`comptable` → « Consultations » absent de la sidebar ; accès direct `consultations.php` → 403.

⚠️ **Note** : après le re-seed, les sessions déjà connectées gardent l'ancien cache de permissions → **se déconnecter/reconnecter** pour chaque rôle testé.

- [ ] **Step 5: Commit final (QA)**

```bash
git add -A
git commit --allow-empty -m "test(consultations): QA end-to-end vérifié (orienter→prescrire→reorienter→terminer)"
```

---

## Vérification finale

Après tous les tasks :
- `php -l` clean sur les 6 fichiers.
- `php sql/seed_permissions.php` idempotent.
- Scénario SQL (Step 3 Task 5) passe.
- QA navigateur (Step 4 Task 5) confié à l'utilisateur (je ne peux pas piloter un navigateur).

## Non-poussé vers GitHub

Travail local sur `main` — push à faire par l'utilisateur (voir conventions précédentes).