<?php
$currentPage = 'lits';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

//  CHANGER STATUT LIT
if (can('lits.update_statut') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_lit') {
    csrf_verify();
    $id     = post_int('lit_id');
    $statut = in_whitelist(post_str('statut'), ['libre','occupée','nettoyage','hors_service'], 'libre');
    assert_owns('lits', $id);
    db_exec("UPDATE lits SET statut=? WHERE id=?", [$statut, $id]);
    logActivity("Lit #$id -> $statut", 'blue', 'lit', $id);
    header('Location: '.APP_URL.'/lits.php?ok=1'); exit;
}

//  AJOUTER LIT
if (can('lits.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'add_lit') {
    csrf_verify();
    $numero  = post_str('numero');
    $dept_id = post_int('departement_id');
    $type    = in_whitelist(post_str('type'), ['standard','soins_intensifs','reanimation','isolement'], 'standard');
    if (!$numero || !$dept_id) { $flash = ['red','Numéro et département obligatoires.']; }
    else {
        db_exec("INSERT INTO lits (numero,departement_id,statut,type) VALUES (?,?,'libre',?)", [$numero,$dept_id,$type]);
        logActivity("Nouveau lit $numero ajouté", 'green', 'lit');
        header('Location: '.APP_URL.'/lits.php?ok=1'); exit;
    }
}

//  CREER DEPARTEMENT
if (can('lits.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_dept') {
    csrf_verify();
    $nom = trim(post_str('dept_nom'));
    $code = strtoupper(trim(post_str('dept_code')));
    if (!$nom || !$code) { $flash = ['red','Nom et code obligatoires.']; }
    else {
        $exists = (int)db_scalar("SELECT COUNT(*) FROM departements WHERE code=?", [$code]);
        if ($exists) { $flash = ['red','Le code " $code "  est deja utilise.']; }
        else {
            db_exec(
                "INSERT INTO departements (nom,code,capacite_lits,etage,couleur) VALUES (?,?,?,?,?)",
                [$nom,$code,post_int('dept_capacite',20),post_str('dept_etage'),'#'.ltrim(post_str('dept_couleur'),'#')]
            );
            logActivity("Département créé: $nom", 'green', 'lit');
            header('Location: '.APP_URL.'/lits.php?ok=1'); exit;
        }
    }
}

//  MODIFIER DEPARTEMENT
if (can('lits.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_dept') {
    csrf_verify();
    $id  = post_int('dept_id');
    $nom = trim(post_str('dept_nom'));
    $code = strtoupper(trim(post_str('dept_code')));
    if (!$nom || !$code) { $flash = ['red','Nom et code obligatoires.']; }
    else {
        $exists = (int)db_scalar("SELECT COUNT(*) FROM departements WHERE code=? AND id!=?", [$code,$id]);
        if ($exists) { $flash = ['red','Le code " $code "  est deja utilise.']; }
        else {
            db_exec(
                "UPDATE departements SET nom=?,code=?,capacite_lits=?,etage=?,couleur=? WHERE id=?",
                [$nom,$code,post_int('dept_capacite',20),post_str('dept_etage'),'#'.ltrim(post_str('dept_couleur'),'#'),$id]
            );
            logActivity("Département modifié ID:$id", 'blue', 'lit');
            header('Location: '.APP_URL.'/lits.php?ok=1'); exit;
        }
    }
}

//  SUPPRIMER DEPARTEMENT
if (can('lits.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'delete_dept') {
    csrf_verify();
    $id = post_int('dept_id');
    $nb_lits = (int)db_scalar("SELECT COUNT(*) FROM lits WHERE departement_id=?", [$id]);
    if ($nb_lits > 0) {
        $flash = ['red',"Impossible : ce département contient $nb_lits lit(s). Supprimez-les d'abord."];
    } else {
        db_exec("DELETE FROM departements WHERE id=?", [$id]);
        logActivity("Département ID supprimé", 'red', 'lit');
        header('Location: '.APP_URL.'/lits.php?ok=1'); exit;
    }
}

//  DONNÉES
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('lits');

$editDept = null;
if (get_int('edit_dept') > 0 && can('lits.create')) {
    $editDept = db_row("SELECT * FROM departements WHERE id=?", [get_int('edit_dept')]);
}

$filtreStatutLit = in_whitelist(get_str('statut'), ['','libre','occupée','maintenance','nettoyage','hors_service'], '');
$filtreDeptLit   = get_int('dept_id');
$whereL = []; $paramsL = [];
if ($filtreStatutLit) { $whereL[] = 'l.statut=?';           $paramsL[] = $filtreStatutLit; }
if ($filtreDeptLit)   { $whereL[] = 'l.departement_id=?';   $paramsL[] = $filtreDeptLit; }
$sqlWhereL = $whereL ? 'WHERE '.implode(' AND ',$whereL) : '';

$lits = db_select("SELECT l.*,d.nom AS dept_nom,d.couleur AS dept_couleur,
    CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.id AS patient_id_hosp, h.date_admission, h.priorite
    FROM lits l
    JOIN departements d ON d.id=l.departement_id
    LEFT JOIN hospitalisations h ON h.lit_id=l.id AND h.statut='en_cours'
    LEFT JOIN patients p ON p.id=h.patient_id
    $sqlWhereL
    ORDER BY d.nom,l.numero", $paramsL);

$depts = db_select("SELECT d.*,
    COUNT(l.id) AS total_lits,
    SUM(l.statut='occupée') AS occupées,
    SUM(l.statut='libre') AS libres,
    SUM(l.statut='nettoyage') AS nettoyage,
    SUM(l.statut='hors_service') AS hs
    FROM departements d LEFT JOIN lits l ON l.departement_id=d.id
    GROUP BY d.id ORDER BY d.nom");

$all_depts = db_select("SELECT id,nom FROM departements ORDER BY nom");

$total  = (int)db_scalar("SELECT COUNT(*) FROM lits");
$occupé  = (int)db_scalar("SELECT COUNT(*) FROM lits WHERE statut='occupée'");
$libres = (int)db_scalar("SELECT COUNT(*) FROM lits WHERE statut='libre'");
$nett   = (int)db_scalar("SELECT COUNT(*) FROM lits WHERE statut='nettoyage'");
$hs     = (int)db_scalar("SELECT COUNT(*) FROM lits WHERE statut='hors_service'");

$statutColor = ['libre'=>'var(--green)','occupée'=>'var(--red)','nettoyage'=>'var(--yellow)','hors_service'=>'var(--text3)'];
$statutLabel = ['libre'=>'Libre','occupée'=>'Occupé','nettoyage'=>'Nettoyage','hors_service'=>'Hors service'];
$statutBadge = ['libre'=>'badge-green','occupée'=>'badge-red','nettoyage'=>'badge-yellow','hors_service'=>'badge-gray'];

// Grouper les lits par département
$litsByDept = [];
foreach ($lits as $l) { $litsByDept[$l['dept_nom']][] = $l; }
?>

<?php if (isset($_GET['ok'])): ?><div class="alert alert-green alert-auto"> Mis à jour.</div><?php endif; ?>
<?php if (!empty($flash)): ?><div class="alert alert-red alert-auto"> <?= h($flash[1]) ?></div><?php endif; ?>

<div class="page-header-row">
  <div><h2>🛏️ Gestion des lits</h2><p><?= $total ?> lits — <?= $occupé ?> occupéés — <?= $libres ?> libres</p></div>
  <div style="display:flex;gap:8px">
    <?php if (can('lits.create')): ?>
    <button class="btn btn-ghost" onclick="document.getElementById('modal-dept').style.display='flex'">🏢 Nouveau département</button>
    <button class="btn btn-blue" onclick="document.getElementById('modal-lit').style.display='flex'">+ Ajouter un lit</button>
    <?php endif; ?>
  </div>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $libres ?></div><div class="stat-label">Lits libres</div></div>
  <div class="stat-card red"><div class="stat-icon red">🛏️</div><div class="stat-value"><?= $occupé ?></div><div class="stat-label">Lits occupéés</div><div class="stat-delta"><?= $total>0?round($occupé/$total*100,1):0 ?>% taux d'occupéation</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">🧹</div><div class="stat-value"><?= $nett ?></div><div class="stat-label">En nettoyage</div></div>
  <div class="stat-card blue"><div class="stat-icon blue">🔧</div><div class="stat-value"><?= $hs ?></div><div class="stat-label">Hors service</div></div>
</div>

<!-- Occupéation par département -->
<div class="card mb-24">
  <div class="card-header">
    <h3>🏢 Occupéation par département</h3>
    <?php if (can('lits.create')): ?>
    <button class="btn btn-ghost btn-sm" onclick="document.getElementById('modal-dept').style.display='flex'">+ Nouveau département</button>
    <?php endif; ?>
  </div>
  <div style="padding:20px;display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px">
    <?php foreach ($depts as $d):
      $pct = $d['total_lits']>0 ? round($d['occupées']/$d['total_lits']*100) : 0;
      $col = $pct>=90?'var(--red)':($pct>=70?'var(--yellow)':'var(--green)');
    ?>
    <div style="background:var(--surface2);border-radius:10px;padding:14px;border-left:3px solid <?= h($d['couleur'] ?? '#3b82f6') ?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
        <div>
          <strong style="font-size:13px"><?= h($d['nom']) ?></strong>
          <div style="font-size:10px;color:var(--text3);margin-top:1px"><?= h($d['code']) ?> — <?= h($d['etage'] ?? '') ?></div>
        </div>
        <?php if (can('lits.create')): ?>
        <a href="lits.php?edit_dept=<?= (int)$d['id'] ?>" class="btn btn-ghost btn-sm" style="padding:2px 7px;font-size:11px" title="Modifier">✏️</a>
        <?php endif; ?>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text2);margin-bottom:6px">
        <span><?= $d['occupées'] ?>/<?= $d['total_lits'] ?> lits</span>
        <span style="font-weight:600;color:<?= $col ?>"><?= $pct ?>%</span>
      </div>
      <div class="progress-bar" style="margin-bottom:8px"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      <div style="display:flex;gap:8px;font-size:10px;flex-wrap:wrap">
        <span style="color:var(--green)">🟢 <?= $d['libres'] ?> libres</span>
        <span style="color:var(--red)">🔴 <?= $d['occupées'] ?> occupéés</span>
        <?php if ($d['nettoyage']): ?><span style="color:var(--yellow)">🟡 <?= $d['nettoyage'] ?> nettoyage</span><?php endif; ?>
        <?php if ($d['hs']): ?><span style="color:var(--text3)">⚫ <?= $d['hs'] ?> HS</span><?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- Filtres lits -->
<div style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap;flex:1">
    <?php foreach ([''=>'Tous','libre'=>' Libres','occupée'=>' Occupéés','nettoyage'=>' Nettoyage','hors_service'=>' H.S.'] as $v=>$l): ?>
    <label style="display:flex;align-items:center;padding:7px 12px;background:var(--surface);border:1px solid <?= $filtreStatutLit===$v?'var(--accent)':'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:12px;color:<?= $filtreStatutLit===$v?'var(--accent2)':'var(--text2)' ?>">
      <input type="radio" name="statut" value="<?= $v ?>" <?= $filtreStatutLit===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none"><?= $l ?>
    </label>
    <?php endforeach; ?>
    <select name="dept_id" onchange="this.form.submit()" style="padding:7px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-family:inherit;font-size:12px;outline:none">
      <option value="0">Tous départements</option>
      <?php foreach ($all_depts as $d): ?>
      <option value="<?= (int)$d['id'] ?>" <?= $filtreDeptLit===(int)$d['id']?'selected':'' ?>><?= h($d['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <?php if ($filtreStatutLit||$filtreDeptLit): ?><a href="lits.php" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
  </form>
</div>

<!-- Plan dtaill des lits -->
<?php foreach ($litsByDept as $deptNom => $deptLits): ?>
<div class="card mb-24">
  <div class="card-header">
    <h3><?= h($deptNom) ?></h3>
    <span style="font-size:12px;color:var(--text2)"><?= count($deptLits) ?> lits</span>
  </div>
  <table>
    <thead><tr><th>Lit</th><th>Type</th><th>Statut</th><th>Patient</th><th>Admission</th><th>Priorit</th><th>Changer statut</th></tr></thead>
    <tbody>
    <?php foreach ($deptLits as $l): ?>
    <tr style="<?= $l['statut']==='occupée'?'background:rgba(var(--red-rgb),.04)':($l['statut']==='libre'?'background:rgba(var(--green-rgb),.03)':'') ?>">
      <td><strong style="color:<?= $statutColor[$l['statut']]??'var(--text)' ?>"><?= h($l['numero']) ?></strong></td>
      <td style="font-size:12px;color:var(--text2)"><?= ucfirst(str_replace('_',' ',$l['type'])) ?></td>
      <td><span class="badge <?= $statutBadge[$l['statut']]??'badge-gray' ?>"><?= $statutLabel[$l['statut']]??$l['statut'] ?></span></td>
      <td><?= $l['patient_nom']?h($l['patient_nom']):'<span style="color:var(--text3);font-style:italic">🟢 Libre</span>' ?></td>
      <td style="font-size:12px"><?= $l['date_admission']?fmt_date($l['date_admission'],true):'' ?></td>
      <td><?php if ($l['priorite']): ?>
        <span class="badge <?= $l['priorite']==='critique'?'badge-red':($l['priorite']==='urgent'?'badge-yellow':'badge-blue') ?>"><?= ucfirst($l['priorite']) ?></span>
      <?php else: ?><?php endif; ?></td>
      <td>
        <form method="POST" style="display:flex;gap:4px">
          <input type="hidden" name="action" value="update_lit">
          <input type="hidden" name="lit_id" value="<?= (int)$l['id'] ?>">
          <?= csrf_field() ?>
          <select name="statut" onchange="this.form.submit()" style="padding:5px 8px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:11px;outline:none;cursor:pointer">
            <?php foreach (['libre','occupée','nettoyage','hors_service'] as $s): ?>
            <option value="<?= $s ?>" <?= $l['statut']===$s?'selected':'' ?>><?= $statutLabel[$s] ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endforeach; ?>

<!-- MODAL AJOUTER LIT -->
<div id="modal-lit" class="modal-overlay" role="dialog" aria-modal="true" style="display:none" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(460px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3> Ajouter un lit</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-lit').style.display='none'" aria-label="Fermer"></button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="add_lit"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label for="inp-numero">Numéro de lit *</label><input type="text" name="numero" id="inp-numero" required maxlength="20" placeholder="ex: 12A"></div>
        <div class="form-group"><label for="inp-departement_id">Département *</label>
          <select name="departement_id" id="inp-departement_id" required>
            <option value=""> Sélectionner </option>
            <?php foreach ($all_depts as $d): ?><option value="<?= (int)$d['id'] ?>"><?= h($d['nom']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group form-full"><label for="inp-lit_type">Type de lit</label>
          <select name="type" id="inp-lit_type">
            <option value="standard">Standard</option>
            <option value="soins_intensifs">Soins intensifs</option>
            <option value="reanimation">Réanimation</option>
            <option value="isolement">Isolement</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-lit').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue"> Ajouter</button>
      </div>
    </form>
  </div>
</div>


<!-- MODAL NOUVEAU DÉPARTEMENT -->
<div id="modal-dept" class="modal-overlay" role="dialog" aria-modal="true" style="display:none;padding:20px" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(480px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>🏢 Nouveau département</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-dept').style.display='none'" aria-label="Fermer">✕</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_dept">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label for="inp-dept_nom">Nom du département *</label>
          <input type="text" name="dept_nom" id="inp-dept_nom" required maxlength="100" placeholder="ex: Cardiologie"></div>
        <div class="form-group"><label for="inp-dept_code">Code court *</label>
          <input type="text" name="dept_code" id="inp-dept_code" required maxlength="20" placeholder="ex: CARD" oninput="this.value=this.value.toUpperCase()"></div>
        <div class="form-group"><label for="inp-dept_capacite">Capacité (lits)</label>
          <input type="number" name="dept_capacite" id="inp-dept_capacite" value="20" min="1" max="500"></div>
        <div class="form-group form-full"><label for="inp-dept_etage">Étage / Localisation</label>
          <input type="text" name="dept_etage" id="inp-dept_etage" maxlength="30" placeholder="ex: Étage 3"></div>
        <div class="form-group"><label for="inp-dept_couleur">Couleur d'identification</label>
          <input type="color" name="dept_couleur" id="inp-dept_couleur" value="#3b82f6" style="width:60px;height:36px;padding:2px;border:1px solid var(--border2);border-radius:7px;cursor:pointer"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" onclick="document.getElementById('modal-dept').style.display='none'" class="btn btn-ghost">Annuler</button>
        <button type="submit" class="btn btn-blue">💾 Créer le département</button>
      </div>
    </form>
  </div>
</div>

<!-- MODAL ÉDITION DÉPARTEMENT -->
<?php if ($editDept && can('lits.create')): ?>
<div id="modal-edit-dept" class="modal-overlay" role="dialog" aria-modal="true" style="display:flex;padding:20px" onclick="if(event.target===this)location.href='lits.php'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(480px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>✏️ Modifier — <?= h($editDept['nom']) ?></h3>
      <a href="lits.php" style="color:var(--text2);text-decoration:none;font-size:20px">✕</a>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_dept">
      <input type="hidden" name="dept_id" value="<?= (int)$editDept['id'] ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label>Nom *</label>
          <input type="text" name="dept_nom" value="<?= h($editDept['nom']) ?>" required maxlength="100"></div>
        <div class="form-group"><label>Code *</label>
          <input type="text" name="dept_code" value="<?= h($editDept['code']) ?>" required maxlength="20" oninput="this.value=this.value.toUpperCase()"></div>
        <div class="form-group"><label for="inp-dept_capacite">Capacité (lits)</label>
          <input type="number" name="dept_capacite" id="inp-dept_capacite" value="<?= (int)$editDept['capacite_lits'] ?>" min="1"></div>
        <div class="form-group form-full"><label for="inp-dept_etage">Étage / Localisation</label>
          <input type="text" name="dept_etage" id="inp-dept_etage" value="<?= h($editDept['etage'] ?? '') ?>" maxlength="30"></div>
        <div class="form-group"><label>Couleur</label>
          <input type="color" name="dept_couleur" id="inp-dept_couleur" value="<?= h($editDept['couleur'] ?? '#3b82f6') ?>" style="width:60px;height:36px;padding:2px;border:1px solid var(--border2);border-radius:7px;cursor:pointer"></div>
      </div>
      <?php $nb_lits_dept = (int)db_scalar("SELECT COUNT(*) FROM lits WHERE departement_id=?", [$editDept['id']]); ?>
      <div style="display:flex;gap:10px;justify-content:space-between;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <div>
        <?php if ($nb_lits_dept === 0): ?>
        <form method="POST" style="margin:0;display:inline" onsubmit="return confirm('Supprimer définitivement ce département ?')">
          <input type="hidden" name="action" value="delete_dept">
          <input type="hidden" name="dept_id" value="<?= (int)$editDept['id'] ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-red">🗑 Supprimer</button>
        </form>
        <?php else: ?>
        <span style="font-size:11px;color:var(--text3);line-height:36px">🔒 <?= $nb_lits_dept ?> lit(s) — suppression impossible</span>
        <?php endif; ?>
        </div>
        <div style="display:flex;gap:8px">
          <a href="lits.php" class="btn btn-ghost">Annuler</a>
          <button type="submit" class="btn btn-blue">💾 Enregistrer</button>
        </div>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';
