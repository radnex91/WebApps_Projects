<?php
// modules/parametres/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('parametres.manage');
$pageTitle = 'Paramètres généraux';
$annee = getAnneeActive($pdo);
$aid   = $annee['id'] ?? 1;

// HANDLERS
$action = $_POST['action'] ?? '';

if ($action === 'save_annee') {
    $libelle = trim($_POST['libelle'] ?? '');
    $debut   = $_POST['date_debut'] ?? '';
    $fin     = $_POST['date_fin']   ?? '';
    $active  = isset($_POST['active']) ? 1 : 0;
    if ($libelle && $debut && $fin) {
        $id = (int)($_POST['id'] ?? 0);
        if ($active) $pdo->exec("UPDATE annees_scolaires SET active=0");
        if ($id) {
            $pdo->prepare("UPDATE annees_scolaires SET libelle=?,date_debut=?,date_fin=?,active=? WHERE id=?")->execute([$libelle,$debut,$fin,$active,$id]);
            flash("Année $libelle mise à jour.");
        } else {
            $pdo->prepare("INSERT INTO annees_scolaires (libelle,date_debut,date_fin,active) VALUES (?,?,?,?)")->execute([$libelle,$debut,$fin,$active]);
            flash("Année $libelle créée.");
        }
    } else { flash('Champs obligatoires.','danger'); }
    redirect(BASE_URL.'modules/parametres/');
}

if ($action === 'save_filiere') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    $nom  = trim($_POST['nom'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $col  = trim($_POST['couleur'] ?? '#2563eb');
    $id   = (int)($_POST['id'] ?? 0);
    if ($code && $nom) {
        if ($id) { $pdo->prepare("UPDATE filieres SET code=?,nom=?,description=?,couleur=? WHERE id=?")->execute([$code,$nom,$desc,$col,$id]); flash("Filière $nom modifiée."); }
        else     { $pdo->prepare("INSERT INTO filieres (code,nom,description,couleur) VALUES (?,?,?,?)")->execute([$code,$nom,$desc,$col]); flash("Filière $nom créée."); }
    } else { flash('Code et nom obligatoires.','danger'); }
    redirect(BASE_URL.'modules/parametres/#filieres');
}

if ($action === 'save_periode') {
    $nom     = trim($_POST['nom'] ?? '');
    $type    = $_POST['type'] ?? 'sequence';
    $ordre   = (int)($_POST['ordre'] ?? 1);
    $deb     = $_POST['date_debut'] ?? null;
    $fin2    = $_POST['date_fin']   ?? null;
    $idP     = (int)($_POST['id'] ?? 0);
    if ($nom) {
        if ($idP) { $pdo->prepare("UPDATE periodes SET nom=?,type=?,ordre=?,date_debut=?,date_fin=? WHERE id=?")->execute([$nom,$type,$ordre,$deb,$fin2,$idP]); flash("Période $nom modifiée."); }
        else      { $pdo->prepare("INSERT INTO periodes (annee_id,nom,type,ordre,date_debut,date_fin) VALUES (?,?,?,?,?,?)")->execute([$aid,$nom,$type,$ordre,$deb,$fin2]); flash("Période $nom créée."); }
    } else { flash('Nom obligatoire.','danger'); }
    redirect(BASE_URL.'modules/parametres/#periodes');
}

// Delete handlers
if (isset($_GET['del_filiere']))  { $pdo->prepare("DELETE FROM filieres WHERE id=?")->execute([$_GET['del_filiere']]);   flash('Filière supprimée.','warning');  redirect(BASE_URL.'modules/parametres/'); }
if (isset($_GET['del_periode']))  { $pdo->prepare("DELETE FROM periodes WHERE id=?")->execute([$_GET['del_periode']]);   flash('Période supprimée.','warning');   redirect(BASE_URL.'modules/parametres/'); }
if (isset($_GET['set_annee']))    { $pdo->exec("UPDATE annees_scolaires SET active=0"); $pdo->prepare("UPDATE annees_scolaires SET active=1 WHERE id=?")->execute([$_GET['set_annee']]); flash('Année scolaire activée.');  redirect(BASE_URL.'modules/parametres/'); }

// Data
$annees  = $pdo->query("SELECT * FROM annees_scolaires ORDER BY libelle DESC")->fetchAll();
$filieres = $pdo->query("SELECT * FROM filieres ORDER BY nom")->fetchAll();
$periodes = $pdo->query("SELECT * FROM periodes WHERE annee_id=$aid ORDER BY type,ordre")->fetchAll();
$niveaux  = $pdo->query("SELECT * FROM niveaux ORDER BY ordre")->fetchAll();

include '../../includes/header.php';
?>

<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Paramètres</div>

<div class="tabs">
  <div class="tab active" onclick="showSection('annees',this)">📅 Années scolaires</div>
  <div class="tab" onclick="showSection('filieres',this)" id="tab-filieres">🎓 Filières</div>
  <div class="tab" onclick="showSection('periodes',this)" id="tab-periodes">📆 Périodes</div>
  <div class="tab" onclick="showSection('niveaux',this)">🏫 Niveaux</div>
  <div class="tab" onclick="window.location='bulletin.php'">🖨️ Bulletin</div>
</div>

<!-- ─ ANNÉES ────────────────────────────────────────────── -->
<div id="sec-annees" class="fade-in">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-calendar-alt"></i> Années scolaires</h3>
      <button class="btn btn-primary btn-sm" onclick="openModal('annee-modal');resetForm('annee-form')"><i class="fas fa-plus"></i> Nouvelle année</button>
    </div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>Libellé</th><th>Début</th><th>Fin</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($annees as $a): ?>
          <tr style="<?= $a['active']?'background:#f0fdf4;':'' ?>">
            <td><strong><?= sanitize($a['libelle']) ?></strong></td>
            <td><?= formatDate($a['date_debut']) ?></td>
            <td><?= formatDate($a['date_fin']) ?></td>
            <td>
              <?php if($a['active']): ?><span class="badge badge-success"><i class="fas fa-check"></i> Active</span>
              <?php else: ?><a href="?set_annee=<?= $a['id'] ?>" class="badge badge-secondary" style="cursor:pointer;text-decoration:none;" onclick="return confirm('Activer cette année ?')">Inactive</a>
              <?php endif; ?>
            </td>
            <td>
              <div style="display:flex;gap:4px;">
                <button class="btn btn-sm btn-warning btn-icon" onclick="editAnnee(<?= htmlspecialchars(json_encode($a),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─ FILIÈRES ───────────────────────────────────────────── -->
<div id="sec-filieres" class="fade-in" style="display:none;">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-graduation-cap"></i> Filières techniques (<?= count($filieres) ?>)</h3>
      <button class="btn btn-primary btn-sm" onclick="openModal('filiere-modal');resetForm('filiere-form')"><i class="fas fa-plus"></i> Nouvelle filière</button>
    </div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>Code</th><th>Nom</th><th>Description</th><th>Couleur</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($filieres as $f): ?>
          <tr>
            <td><code><?= sanitize($f['code']) ?></code></td>
            <td><span class="filiere-badge" style="background:<?= sanitize($f['couleur']) ?>20;color:<?= sanitize($f['couleur']) ?>"><?= sanitize($f['nom']) ?></span></td>
            <td style="font-size:12px;color:var(--text2);"><?= sanitize($f['description']??'—') ?></td>
            <td><div style="width:20px;height:20px;border-radius:4px;background:<?= sanitize($f['couleur']) ?>;border:1px solid var(--border);"></div></td>
            <td>
              <div style="display:flex;gap:4px;">
                <button class="btn btn-sm btn-warning btn-icon" onclick="editFiliere(<?= htmlspecialchars(json_encode($f),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                <a href="?del_filiere=<?= $f['id'] ?>" class="btn btn-sm btn-danger btn-icon" onclick="return confirm('Supprimer cette filière ?')"><i class="fas fa-trash"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─ PÉRIODES ───────────────────────────────────────────── -->
<div id="sec-periodes" class="fade-in" style="display:none;">
  <div class="card">
    <div class="card-header">
      <h3><i class="fas fa-calendar-week"></i> Périodes — <?= sanitize($annee['libelle']??'') ?></h3>
      <button class="btn btn-primary btn-sm" onclick="openModal('periode-modal');resetForm('periode-form')"><i class="fas fa-plus"></i> Nouvelle période</button>
    </div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>Nom</th><th>Type</th><th>Ordre</th><th>Début</th><th>Fin</th><th>Statut</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($periodes as $p): ?>
          <tr>
            <td><strong><?= sanitize($p['nom']) ?></strong></td>
            <td><span class="badge <?= $p['type']==='sequence'?'badge-primary':($p['type']==='trimestre'?'badge-info':'badge-secondary') ?>"><?= sanitize($p['type']) ?></span></td>
            <td style="text-align:center;"><?= $p['ordre'] ?></td>
            <td><?= formatDate($p['date_debut']??'') ?></td>
            <td><?= formatDate($p['date_fin']??'') ?></td>
            <td><?= $p['cloturee']?'<span class="badge badge-secondary">Clôturée</span>':'<span class="badge badge-success">Ouverte</span>' ?></td>
            <td>
              <div style="display:flex;gap:4px;">
                <button class="btn btn-sm btn-warning btn-icon" onclick="editPeriode(<?= htmlspecialchars(json_encode($p),ENT_QUOTES) ?>)"><i class="fas fa-edit"></i></button>
                <a href="?del_periode=<?= $p['id'] ?>" class="btn btn-sm btn-danger btn-icon" onclick="return confirm('Supprimer ?')"><i class="fas fa-trash"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ─ NIVEAUX ────────────────────────────────────────────── -->
<div id="sec-niveaux" class="fade-in" style="display:none;">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-stairs"></i> Niveaux d'enseignement</h3></div>
    <div class="card-body" style="padding:0;">
      <table>
        <thead><tr><th>Code</th><th>Nom</th><th>Ordre</th><th>Cycle</th></tr></thead>
        <tbody>
          <?php foreach($niveaux as $n): ?>
          <tr>
            <td><code><?= sanitize($n['code']) ?></code></td>
            <td><strong><?= sanitize($n['nom']) ?></strong></td>
            <td style="text-align:center;"><?= $n['ordre'] ?></td>
            <td><span class="badge <?= $n['cycle']==='BTS'?'badge-secondary':'badge-primary' ?>"><?= sanitize($n['cycle']) ?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- MODALS -->
<!-- Année -->
<div class="modal-overlay" id="annee-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-calendar-alt"></i> Année scolaire</h3><button class="modal-close" onclick="closeModal('annee-modal')">✕</button></div>
    <form method="POST" id="annee-form">
      <input type="hidden" name="action" value="save_annee">
      <input type="hidden" name="id" id="a-id">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Libellé (ex: 2025-2026)</label><input type="text" name="libelle" id="a-libelle" class="form-control" required placeholder="2025-2026"></div>
        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div class="form-group"><label class="form-label">Début</label><input type="date" name="date_debut" id="a-debut" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Fin</label><input type="date" name="date_fin" id="a-fin" class="form-control" required></div>
        </div>
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer;"><input type="checkbox" name="active" id="a-active"> <span>Définir comme année active</span></label>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('annee-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<!-- Filière -->
<div class="modal-overlay" id="filiere-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3 id="filiere-modal-t"><i class="fas fa-graduation-cap"></i> Filière</h3><button class="modal-close" onclick="closeModal('filiere-modal')">✕</button></div>
    <form method="POST" id="filiere-form">
      <input type="hidden" name="action" value="save_filiere">
      <input type="hidden" name="id" id="f-id">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Code <span class="form-required">*</span></label><input type="text" name="code" id="f-code" class="form-control" required placeholder="INFO, ELEC, MECA..."></div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="nom" id="f-nom" class="form-control" required></div>
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Description</label><textarea name="description" id="f-desc" class="form-control" rows="2"></textarea></div>
        <div class="form-group"><label class="form-label">Couleur</label><div style="display:flex;gap:8px;align-items:center;"><input type="color" name="couleur" id="f-col" value="#2563eb" style="height:38px;width:50px;cursor:pointer;border-radius:6px;"><input type="text" id="f-col-txt" value="#2563eb" class="form-control" style="width:100px;" oninput="document.getElementById('f-col').value=this.value"></div></div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('filiere-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<!-- Période -->
<div class="modal-overlay" id="periode-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-calendar-week"></i> Période</h3><button class="modal-close" onclick="closeModal('periode-modal')">✕</button></div>
    <form method="POST" id="periode-form">
      <input type="hidden" name="action" value="save_periode">
      <input type="hidden" name="id" id="p-id">
      <div class="modal-body">
        <div class="form-group" style="margin-bottom:12px;"><label class="form-label">Nom <span class="form-required">*</span></label><input type="text" name="nom" id="p-nom" class="form-control" required placeholder="Séquence 1, 1er Trimestre..."></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
          <div class="form-group"><label class="form-label">Type</label><select name="type" id="p-type" class="form-control"><option value="sequence">Séquence</option><option value="trimestre">Trimestre</option><option value="semestre">Semestre</option><option value="annuel">Annuel</option></select></div>
          <div class="form-group"><label class="form-label">Ordre</label><input type="number" name="ordre" id="p-ordre" class="form-control" value="1" min="1"></div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
          <div class="form-group"><label class="form-label">Début</label><input type="date" name="date_debut" id="p-debut" class="form-control"></div>
          <div class="form-group"><label class="form-label">Fin</label><input type="date" name="date_fin" id="p-fin" class="form-control"></div>
        </div>
      </div>
      <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('periode-modal')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>

<script>
function showSection(id, el) {
    document.querySelectorAll('[id^="sec-"]').forEach(s => s.style.display='none');
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.getElementById('sec-'+id).style.display=''; el.classList.add('active');
}
function resetForm(id) { document.getElementById(id)?.reset(); }
function editAnnee(a) {
    document.getElementById('a-id').value     = a.id;
    document.getElementById('a-libelle').value= a.libelle;
    document.getElementById('a-debut').value  = a.date_debut;
    document.getElementById('a-fin').value    = a.date_fin;
    document.getElementById('a-active').checked = !!a.active;
    openModal('annee-modal');
}
function editFiliere(f) {
    document.getElementById('f-id').value   = f.id;
    document.getElementById('f-code').value = f.code;
    document.getElementById('f-nom').value  = f.nom;
    document.getElementById('f-desc').value = f.description||'';
    document.getElementById('f-col').value  = f.couleur||'#2563eb';
    document.getElementById('f-col-txt').value = f.couleur||'#2563eb';
    openModal('filiere-modal');
}
function editPeriode(p) {
    document.getElementById('p-id').value    = p.id;
    document.getElementById('p-nom').value   = p.nom;
    document.getElementById('p-type').value  = p.type;
    document.getElementById('p-ordre').value = p.ordre;
    document.getElementById('p-debut').value = p.date_debut||'';
    document.getElementById('p-fin').value   = p.date_fin||'';
    openModal('periode-modal');
}
// Check hash for tab
const hash = location.hash.replace('#','');
if (hash === 'filieres') showSection('filieres', document.getElementById('tab-filieres'));
if (hash === 'periodes') showSection('periodes', document.getElementById('tab-periodes'));
document.getElementById('f-col')?.addEventListener('input', e => { document.getElementById('f-col-txt').value = e.target.value; });
</script>

<?php include '../../includes/footer.php'; ?>
