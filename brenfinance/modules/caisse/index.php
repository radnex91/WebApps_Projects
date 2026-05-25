<?php
require_once __DIR__ . '/../../includes/functions.php';
requireModuleAccess('caisse');
$pageTitle = 'Gestion de Caisse';

$db = getDB();
$userId = $_SESSION['user_id'];
$user = currentUser();
$isCaissier = ($user['role_nom'] ?? '') === 'caissier';

// Find caissier's assigned caisse
$userCaisseId = null;
if ($isCaissier) {
    $cIdR = $db->prepare("SELECT id FROM caisses WHERE responsable_id=? LIMIT 1");
    $cIdR->execute([$user['id']]);
    $userCaisseId = $cIdR->fetchColumn();
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Only caissier can open/close caisse
    if (($action === 'ouvrir_caisse' || $action === 'fermer_caisse') && !$isCaissier) {
        flash('danger', 'Seul un caissier peut ouvrir ou clôturer une caisse.');
        header('Location: index.php'); exit;
    }

    // Caissier can only open/close their own caisse
    if ($isCaissier && $userCaisseId) {
        $postedCaisse = (int)($_POST['caisse_id'] ?? 0);
        $postedSession = $db->prepare("SELECT caisse_id FROM sessions_caisse WHERE id=?");
        $postedSession->execute([(int)($_POST['session_id'] ?? 0)]);
        $sessCaisse = $postedSession->fetchColumn();
        if (($action === 'ouvrir_caisse' && $postedCaisse !== (int)$userCaisseId) ||
            ($action === 'fermer_caisse' && $sessCaisse !== (int)$userCaisseId)) {
            flash('danger', 'Vous n\'êtes pas autorisé à opérer sur cette caisse.');
            header('Location: index.php'); exit;
        }
    }

    if ($action === 'ouvrir_caisse') {
        $caisseId = (int)$_POST['caisse_id'];
        $soldeOuv = (float)str_replace([' ', ','], ['', '.'], $_POST['solde_ouverture'] ?? '0');
        // Check no open session
        $existing = $db->prepare("SELECT id FROM sessions_caisse WHERE caisse_id=? AND statut='ouverte'");
        $existing->execute([$caisseId]);
        if ($existing->fetch()) {
            flash('warning', 'Cette caisse est déjà ouverte.');
        } else {
            $db->prepare("INSERT INTO sessions_caisse (caisse_id,utilisateur_id,solde_ouverture) VALUES (?,?,?)")->execute([$caisseId,$userId,$soldeOuv]);
            $db->prepare("UPDATE caisses SET statut='ouverte', solde_actuel=? WHERE id=?")->execute([$soldeOuv,$caisseId]);
            auditLog('ouverture_caisse','caisse','caisses',$caisseId);
            flash('success','Caisse ouverte avec succès.');
        }
        header('Location: index.php'); exit;
    }

    if ($action === 'fermer_caisse') {
        $sessionId = (int)$_POST['session_id'];
        $soldeFerm = (float)$_POST['solde_fermeture'];
        $obs = trim($_POST['observations']??'');
        $session = $db->prepare("SELECT * FROM sessions_caisse WHERE id=?");
        $session->execute([$sessionId]);
        $sess = $session->fetch();
        if ($sess) {
            $caisse = $db->prepare("SELECT solde_actuel FROM caisses WHERE id=?");
            $caisse->execute([$sess['caisse_id']]);
            $c = $caisse->fetch();
            $theorique = $c['solde_actuel'];
            $ecart = $soldeFerm - $theorique;
            $db->prepare("UPDATE sessions_caisse SET date_fermeture=NOW(), solde_fermeture=?, solde_theorique=?, ecart=?, observations=?, statut='fermee' WHERE id=?")->execute([$soldeFerm,$theorique,$ecart,$obs,$sessionId]);
            $db->prepare("UPDATE caisses SET statut='fermee' WHERE id=?")->execute([$sess['caisse_id']]);
            auditLog('fermeture_caisse','caisse','sessions_caisse',$sessionId);
            flash('success','Caisse clôturée. Écart: ' . formatMontant($ecart));
        }
        header('Location: index.php'); exit;
    }
}

// Data
if ($isCaissier && $userCaisseId) {
    $caissesR = $db->prepare("SELECT c.*, u.prenom, u.nom, (SELECT id FROM sessions_caisse WHERE caisse_id=c.id AND statut='ouverte' LIMIT 1) as session_id FROM caisses c LEFT JOIN utilisateurs u ON c.responsable_id=u.id WHERE c.id=?");
    $caissesR->execute([$userCaisseId]);
    $caisses = $caissesR->fetchAll();
} else {
    $caisses = $db->query("SELECT c.*, u.prenom, u.nom, (SELECT id FROM sessions_caisse WHERE caisse_id=c.id AND statut='ouverte' LIMIT 1) as session_id FROM caisses c LEFT JOIN utilisateurs u ON c.responsable_id=u.id ORDER BY c.libelle")->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-header d-flex justify-between align-center">
  <div>
    <h1>Gestion de Caisse</h1>
    <p>Ouverture et clôture des caisses</p>
  </div>
  <div class="btn-group">
    <?php if (!$isCaissier): ?>
    <button class="btn btn-primary" onclick="openModal('modal-new-caisse')">+ Nouvelle caisse</button>
    <?php endif; ?>
  </div>
</div>

<!-- Caisses cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin-bottom:24px">
  <?php foreach($caisses as $c): ?>
  <?php $isOpen = $c['statut'] === 'ouverte'; ?>
  <div class="card" style="border-top:3px solid <?= $isOpen?'var(--success)':'var(--border)' ?>">
    <div class="card-header">
      <div>
        <div class="card-title"><?= sanitize($c['libelle']) ?></div>
        <div style="font-size:12px;color:var(--text3)"><?= sanitize($c['code']) ?></div>
      </div>
      <span class="badge <?= $isOpen?'badge-success':($c['statut']==='fermee'?'badge-gray':'badge-danger') ?>" style="margin-left:auto">
        <?= $isOpen?'Ouverte':ucfirst($c['statut']) ?>
      </span>
    </div>
    <div class="card-body">
      <div style="font-size:24px;font-weight:700;margin-bottom:4px">
        <?= formatMontant($c['solde_actuel']) ?>
      </div>
      <div style="font-size:12px;color:var(--text3)">Responsable: <?= $c['nom']?sanitize($c['nom'].' '.$c['prenom']):'Non défini' ?></div>
    </div>
    <div class="card-footer d-flex gap-8">
      <?php if ($isOpen): ?>
        <a href="<?= BASE_URL ?>/modules/operations_caisse/index.php?caisse=<?= $c['id'] ?>" class="btn btn-primary btn-sm">Opérations</a>
        <?php if ($isCaissier): ?>
        <button class="btn btn-outline btn-sm" onclick="openFermeture(<?= $c['id'] ?>, <?= $c['session_id'] ?>)">Clôturer</button>
        <?php endif; ?>
      <?php else: ?>
        <?php if ($isCaissier): ?>
        <button class="btn btn-accent btn-sm" onclick="openOuverture(<?= $c['id'] ?>, '<?= sanitize($c['libelle']) ?>', <?= $c['solde_actuel'] ?>)">Ouvrir</button>
        <?php endif; ?>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>/modules/operations_caisse/index.php?caisse=<?= $c['id'] ?>" class="btn btn-ghost btn-sm">Historique</a>
    </div>
  </div>
  <?php endforeach; ?>
  <?php if (empty($caisses)): ?>
  <div class="card" style="grid-column:1/-1">
    <div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-wallet"></i></div><p>Aucune caisse configurée. Créez votre première caisse.</p></div>
  </div>
  <?php endif; ?>
</div>

<!-- Modal: Nouvelle caisse -->
<div class="modal-overlay" id="modal-new-caisse">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Créer une caisse</div>
      <button class="modal-close" onclick="closeModal('modal-new-caisse')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post" action="create_caisse.php">
      <div class="modal-body">
        <div class="form-row-2">
          <div class="form-group">
            <label class="form-label">Code <span class="req">*</span></label>
            <input type="text" name="code" class="form-control" placeholder="CAI-01" required>
          </div>
          <div class="form-group">
            <label class="form-label">Devise</label>
            <input type="text" name="devise" class="form-control" value="FCFA">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Libellé <span class="req">*</span></label>
          <input type="text" name="libelle" class="form-control" placeholder="Caisse principale" required>
        </div>
        <div class="form-group">
          <label class="form-label">Solde initial</label>
          <input type="number" name="solde_initial" class="form-control amount-input" value="0" step="1" min="0">
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-new-caisse')">Annuler</button>
        <button type="submit" class="btn btn-primary">Créer la caisse</button>
      </div>
    </form>
  </div>
</div>

<?php if ($isCaissier): ?>
<!-- Modal: Ouverture caisse -->
<div class="modal-overlay" id="modal-ouvrir">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Ouvrir la caisse — <span id="lbl-caisse"></span></div>
      <button class="modal-close" onclick="closeModal('modal-ouvrir')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="ouvrir_caisse">
      <input type="hidden" name="caisse_id" id="inp-caisse-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Solde d'ouverture (FCFA) <span class="req">*</span></label>
          <input type="number" name="solde_ouverture" id="inp-solde-ouv" class="form-control" step="1" min="0" required>
          <small class="text-muted">Saisissez le montant physiquement présent en caisse.</small>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-ouvrir')">Annuler</button>
        <button type="submit" class="btn btn-accent">Ouvrir la caisse</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: Fermeture caisse -->
<div class="modal-overlay" id="modal-fermer">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Clôturer la caisse</div>
      <button class="modal-close" onclick="closeModal('modal-fermer')"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="post">
      <input type="hidden" name="action" value="fermer_caisse">
      <input type="hidden" name="session_id" id="inp-session-id">
      <div class="modal-body">
        <div class="form-group">
          <label class="form-label">Solde physique constaté (FCFA) <span class="req">*</span></label>
          <input type="number" name="solde_fermeture" class="form-control" step="1" min="0" required>
        </div>
        <div class="form-group">
          <label class="form-label">Observations</label>
          <textarea name="observations" class="form-control" rows="3" placeholder="Remarques, incidents..."></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline" onclick="closeModal('modal-fermer')">Annuler</button>
        <button type="submit" class="btn btn-danger">Clôturer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; /* isCaissier for modals */ ?>

<script>
function openOuverture(id, libelle, solde) {
  document.getElementById('inp-caisse-id').value = id;
  document.getElementById('lbl-caisse').textContent = libelle;
  document.getElementById('inp-solde-ouv').value = solde;
  openModal('modal-ouvrir');
}
function openFermeture(caisseId, sessionId) {
  document.getElementById('inp-session-id').value = sessionId;
  openModal('modal-fermer');
}
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>