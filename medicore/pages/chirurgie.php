<?php
$currentPage = 'chirurgie';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$flash = null;

// --- POST : Programmer une intervention ---
if (can('chirurgie.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'programmer') {
    csrf_verify();
    $patient_id      = post_int('patient_id');
    $hosp_id         = post_int('hospitalisation_id');
    $chirurgien_id   = post_int('chirurgien_id');
    $anesthesiste_id = post_int('anesthesiste_id');
    $nom_interv      = post_str('nom_intervention');
    $salle           = post_str('salle');
    $date_prevue     = post_str('date_prevue');
    $duree           = post_int('duree', 120);
    $type_interv     = in_whitelist(post_str('type'), ['programmee','urgence','ambulatoire'], 'programmee');
    $anesth_type     = in_whitelist(post_str('anesthesie'), ['generale','locoregionale','locale','sedation'], 'generale');
    $notes           = post_str('notes');

    $v = (new Validator())->required($nom_interv, 'nom', 'Nom requis.')
        ->required($patient_id > 0, 'patient', 'Patient requis.')
        ->required($chirurgien_id > 0, 'chirurgien', 'Chirurgien requis.')
        ->required($date_prevue, 'date', 'Date prevue requise.');
    if (!$v->passes()) { $flash = ['red', $v->first_error()]; }
    else {
        db_exec("INSERT INTO interventions_chirurgicales (patient_id, hospitalisation_id, chirurgien_id, anesthesiste_id, nom_intervention, salle, date_prevue, duree_prevue, type_intervention, anesthesie_type, notes_postop) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
            [$patient_id, $hosp_id>0?$hosp_id:null, $chirurgien_id, $anesthesiste_id>0?$anesthesiste_id:null, $nom_interv, $salle, $date_prevue, $duree, $type_interv, $anesth_type, $notes]);
        logActivity("Intervention programmee : $nom_interv", 'blue', 'chirurgie');
        $flash = ['green', 'Intervention programmee avec succes.'];
    }
}

// --- POST : Mettre a jour statut intervention ---
if (can('chirurgie.update') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_interv') {
    csrf_verify();
    $id     = get_signed_id('id', 'interventions_chirurgicales');
    $statut = in_whitelist(post_str('statut'), ['planifiee','en_preparation','en_cours','terminee','annulee','reportee'], 'terminee');
    $complications = post_str('complications');

    db_exec("UPDATE interventions_chirurgicales SET statut=?, date_fin=IF(?='terminee',NOW(),date_fin), complications=? WHERE id=?",
        [$statut, $statut, $complications, $id]);
    logActivity("Intervention #$id -> $statut", $statut==='terminee'?'green':'yellow', 'chirurgie', $id);
    $flash = ['green', 'Statut mis a jour.'];
}

// --- POST : Checklist pre-op ---
if (can('chirurgie.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'checklist') {
    csrf_verify();
    $interv_id = post_int('intervention_id');
    $items = ['identite_patient','site_marque','consentement','allergies','jeune','antibioprophylaxie','materiel_dispo'];
    $values = [post_int('intervention_id'), currentUser()['id']];
    $sql_parts = [];
    foreach ($items as $it) {
        $sql_parts[] = "$it=?";
        $values[] = post_int($it) ? 1 : 0;
    }
    $exist = db_row("SELECT * FROM checklist_preop WHERE intervention_id=?", [$interv_id]);
    if ($exist) {
        db_exec("UPDATE checklist_preop SET ".implode(',',$sql_parts)." WHERE intervention_id=?",
            array_merge($values, [$interv_id]));
    } else {
        db_exec("INSERT INTO checklist_preop (intervention_id, utilisateur_id, ".implode(',',$items).") VALUES (?,?, ".implode(',',array_fill(0,count($items),'?')).")",
            $values);
    }
    logActivity("Checklist pre-op #$interv_id", 'green', 'chirurgie', $interv_id);
    $flash = ['green', 'Checklist pre-op enregistree.'];
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('chirurgie');

// --- Donnees ---
$filtre_statut = in_whitelist(get_str('filtre'), ['','planifiee','en_cours','terminee','annulee'], '');
$date_filter   = get_str('date_chir') ?: date('Y-m-d');

$where = "1=1";
$params = [];
if ($filtre_statut) { $where .= " AND i.statut=?"; $params[] = $filtre_statut; }
else { $where .= " AND i.statut!='annulee'"; }

$interventions = db_select("SELECT i.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
    CONCAT(ch.prenom,' ',ch.nom) AS chirurgien_nom,
    CONCAT(an.prenom,' ',an.nom) AS anesthesiste_nom
    FROM interventions_chirurgicales i
    JOIN patients p ON p.id=i.patient_id
    JOIN utilisateurs ch ON ch.id=i.chirurgien_id
    LEFT JOIN utilisateurs an ON an.id=i.anesthesiste_id
    WHERE $where ORDER BY FIELD(i.statut,'en_cours','planifiee','en_preparation','terminee','reportee','annulee'), i.date_prevue ASC LIMIT 100", $params);

// Stats
$stats = [
    'total'     => (int)db_scalar("SELECT COUNT(*) FROM interventions_chirurgicales WHERE statut!='annulee'"),
    'planifiees'=> (int)db_scalar("SELECT COUNT(*) FROM interventions_chirurgicales WHERE statut IN ('planifiee','en_preparation')"),
    'en_cours'  => (int)db_scalar("SELECT COUNT(*) FROM interventions_chirurgicales WHERE statut='en_cours'"),
    'terminees' => (int)db_scalar("SELECT COUNT(*) FROM interventions_chirurgicales WHERE statut='terminee' AND DATE(date_fin)=CURDATE()"),
];

$statBadge = ['planifiee'=>'badge-blue','en_preparation'=>'badge-yellow','en_cours'=>'badge-red','terminee'=>'badge-green','annulee'=>'badge-gray','reportee'=>'badge-yellow'];
$statLabel = ['planifiee'=>'Planifiee','en_preparation'=>'En preparation','en_cours'=>'En cours','terminee'=>'Terminee','annulee'=>'Annulee','reportee'=>'Reportee'];

// Chirurgiens disponibles
$chirurgiens = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom");

// Patients hospitalises (pour selection rapide)
$patientsHosp = db_select("SELECT p.id, CONCAT(p.prenom,' ',p.nom) AS nom_complet, p.numero, d.nom AS dept_nom
    FROM patients p
    LEFT JOIN hospitalisations h ON h.patient_id=p.id AND h.statut='en_cours'
    LEFT JOIN departements d ON d.id=h.departement_id
    WHERE h.id IS NOT NULL ORDER BY p.nom LIMIT 50");
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>🔪 Bloc operatoire &amp; Chirurgie</h2><p><?= $stats['planifiees'] ?> planifiees · <?= $stats['en_cours'] ?> en cours · <?= $stats['terminees'] ?> aujourd'hui</p></div>
  <?php if (can('chirurgie.create')): ?>
  <button class="btn btn-red" onclick="document.getElementById('modal-chirurgie').style.display='flex'">+ Nouvelle intervention</button>
  <?php endif; ?>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card"><div class="stat-icon blue">📋</div><div class="stat-value"><?= $stats['planifiees'] ?></div><div class="stat-label">Planifiees</div></div>
  <div class="stat-card"><div class="stat-icon red">🔴</div><div class="stat-value"><?= $stats['en_cours'] ?></div><div class="stat-label">En cours</div></div>
  <div class="stat-card"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['terminees'] ?></div><div class="stat-label">Terminees (j)</div></div>
  <div class="stat-card"><div class="stat-icon">📊</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total actif</div></div>
</div>

<!-- Filtres -->
<div class="pill-tabs mb-16">
  <?php foreach ([''=>'Toutes','planifiee'=>'Planifiees','en_cours'=>'En cours','terminee'=>'Terminees','annulee'=>'Annulees'] as $v=>$l): ?>
  <a href="?filtre=<?= $v ?>" class="pill-tab <?= $filtre_statut==$v?'active':'' ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<!-- Tableau -->
<div class="card">
  <table>
    <thead><tr><th>Date prevue</th><th>Patient</th><th>Intervention</th><th>Salle</th><th>Chirurgien</th><th>Statut</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($interventions as $i): ?>
      <tr style="border-left:3px solid <?= ['en_cours'=>'var(--red)','planifiee'=>'var(--blue)','en_preparation'=>'var(--yellow)','terminee'=>'var(--green)'][$i['statut']]??'var(--border)' ?>">
        <td><strong><?= fmt_date($i['date_prevue'], true) ?></strong><div style="font-size:10px;color:var(--text3)"><?= $i['duree_prevue'] ?> min</div></td>
        <td><?= h($i['patient_nom']) ?><div style="font-size:10px;color:var(--text3)"><?= h($i['patient_num']) ?></div></td>
        <td><?= h($i['nom_intervention']) ?><div style="font-size:10px;color:var(--text2)"><?= h($i['anesthesie_type'] ?? '-') ?></div></td>
        <td><?= h($i['salle'] ?? '-') ?></td>
        <td><?= h($i['chirurgien_nom']) ?><?php if ($i['anesthesiste_nom']): ?><div style="font-size:10px;color:var(--text3)">Anesth: <?= h($i['anesthesiste_nom']) ?></div><?php endif; ?></td>
        <td><span class="badge <?= $statBadge[$i['statut']]??'badge-gray' ?>"><?= $statLabel[$i['statut']]??$i['statut'] ?></span></td>
        <td>
          <div style="display:flex;gap:4px;flex-wrap:wrap">
          <?php if (can('chirurgie.update') && in_array($i['statut'],['planifiee','en_preparation'])): ?>
            <form method="POST"><input type="hidden" name="action" value="update_interv"><?= csrf_field() ?>
            <input type="hidden" name="statut" value="en_cours">
            <input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><?= get_signed_id('id','interventions_chirurgicales') ?><?php /* Note: secure_url signature should be generated here */ ?>
            <button class="btn btn-sm btn-red" onclick="return confirm('Demarrer intervention?')">▶ Demarrer</button></form>
          <?php endif; ?>
          <?php if (can('chirurgie.update') && $i['statut']==='en_cours'): ?>
            <button class="btn btn-sm btn-green" onclick="openTerminer(<?= (int)$i['id'] ?>)">Terminer</button>
          <?php endif; ?>
          <?php if (can('chirurgie.create') && !in_array($i['statut'],['annulee','terminee'])): ?>
            <button class="btn btn-sm btn-ghost" onclick="openChecklist(<?= (int)$i['id'] ?>,'<?= h($i['nom_intervention']) ?>')">📋 Checklist</button>
          <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php if ($i['complications']): ?>
      <tr><td colspan="7" style="font-size:11px;color:var(--red);padding:2px 12px">Complications : <?= h($i['complications']) ?></td></tr>
      <?php endif; ?>
    <?php endforeach; ?>
    <?php if (empty($interventions)): ?>
      <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--text3)">Aucune intervention pour ce filtre.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal nouvelle intervention -->
<?php if (can('chirurgie.create')): ?>
<div id="modal-chirurgie" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:600px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7);max-height:90vh;overflow-y:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Programmer une intervention</h3>
      <div onclick="document.getElementById('modal-chirurgie').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="programmer">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label>Patient *</label>
          <select name="patient_id" required><option value="">-- Selectionner --</option>
          <?php foreach ($patientsHosp as $ph): ?><option value="<?= (int)$ph['id'] ?>"><?= h($ph['nom_complet'].' - '.$ph['numero'].($ph['dept_nom']?' ['.$ph['dept_nom'].']':'')) ?></option><?php endforeach; ?>
          </select></div>
        <div class="form-group form-full"><label>Nom de l'intervention *</label><input type="text" name="nom_intervention" required placeholder="Ex: Appendicectomie"></div>
        <div class="form-group"><label>Chirurgien *</label><select name="chirurgien_id" required><option value="">-- --</option><?php foreach ($chirurgiens as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nom_complet'].(($c['specialite']??'')?' - '.$c['specialite']:'')) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Anesthesiste</label><select name="anesthesiste_id"><option value="">-- --</option><?php foreach ($chirurgiens as $c): ?><option value="<?= (int)$c['id'] ?>"><?= h($c['nom_complet']) ?></option><?php endforeach; ?></select></div>
        <div class="form-group"><label>Date prevue *</label><input type="datetime-local" name="date_prevue" required></div>
        <div class="form-group"><label>Duree (min)</label><input type="number" name="duree" value="120" min="15" max="600"></div>
        <div class="form-group"><label>Salle</label><input type="text" name="salle" placeholder="Ex: Bloc A - Salle 3"></div>
        <div class="form-group"><label>Type</label><select name="type"><option value="programmee">Programmee</option><option value="urgence">Urgence</option><option value="ambulatoire">Ambulatoire</option></select></div>
        <div class="form-group form-full"><label>Anesthesie</label><select name="anesthesie"><option value="generale">Generale</option><option value="locoregionale">Locoregionale</option><option value="locale">Locale</option><option value="sedation">Sedation</option></select></div>
        <div class="form-group form-full"><label>Notes</label><textarea name="notes" rows="2"></textarea></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-chirurgie').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Programmer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal terminer intervention -->
<?php if (can('chirurgie.update')): ?>
<div id="modal-terminer" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:450px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>Terminer l'intervention</h3>
      <div onclick="document.getElementById('modal-terminer').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_interv">
      <input type="hidden" name="statut" value="terminee">
      <input type="hidden" name="id" id="term-id" value=""><?= csrf_field() ?>
      <div class="form-group"><label>Complications (si aucune, laisser vide)</label><textarea name="complications" rows="2" placeholder="Decrire les complications eventuelles..."></textarea></div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-terminer').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-green">Confirmer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Modal checklist -->
<?php if (can('chirurgie.create')): ?>
<div id="modal-checklist" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px);z-index:200;align-items:center;justify-content:center" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:500px;max-width:95vw;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>📋 Checklist pre-operatorire (OMS)</h3>
      <div onclick="document.getElementById('modal-checklist').style.display='none'" style="cursor:pointer;font-size:18px;color:var(--text2)">✕</div>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="checklist">
      <input type="hidden" name="intervention_id" id="cl-interv-id">
      <?= csrf_field() ?>
      <div id="cl-name" style="font-weight:600;margin-bottom:16px;color:var(--accent2)"></div>
      <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach (['identite_patient'=>'Identite du patient confirmee','site_marque'=>'Site operatoire marque','consentement'=>'Consentement eclaire obtenu','allergies'=>'Allergies verifiees','jeune'=>'Jeune pre-operatoire respecte','antibioprophylaxie'=>'Antibioprophylaxie administree','materiel_dispo'=>'Materiel disponible'] as $ck=>$cl): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:var(--bg);border-radius:8px;cursor:pointer">
          <input type="checkbox" name="<?= $ck ?>" value="1" style="width:18px;height:18px">
          <span><?= $cl ?></span>
        </label>
      <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-checklist').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue">Enregistrer la checklist</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
function openTerminer(id){document.getElementById('term-id').value=id;document.getElementById('modal-terminer').style.display='flex';}
function openChecklist(id,nom){document.getElementById('cl-interv-id').value=id;document.getElementById('cl-name').textContent='Intervention : '+nom;document.getElementById('modal-checklist').style.display='flex';}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
