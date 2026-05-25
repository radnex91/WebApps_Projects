<?php
$page_title = 'Clients';
$page_id = 'clients';
require_once '../includes/header.php';
requireAuth();
if (!canDo('produit_view')) { echo '<div class="alert alert-danger">Accès refusé.</div>'; require_once '../includes/footer.php'; exit; }
$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && canDo('produit_edit')) {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);
        $nom  = trim($_POST['nom'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $tel  = trim($_POST['telephone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $adresse = trim($_POST['adresse'] ?? '');
        $societe = trim($_POST['societe'] ?? '');

        if ($nom === '') {
            $msg = '<div class="alert alert-danger">Le nom du client est obligatoire.</div>';
        } else {
            if ($id) {
                $db->prepare("UPDATE clients SET nom=?,contact=?,telephone=?,email=?,adresse=?,societe=? WHERE id=?")
                   ->execute([$nom,$contact,$tel,$email,$adresse,$societe,$id]);
                $msg = '<div class="alert alert-success">Client modifié avec succès.</div>';
            } else {
                $db->prepare("INSERT INTO clients (nom,contact,telephone,email,adresse,societe) VALUES(?,?,?,?,?,?)")
                   ->execute([$nom,$contact,$tel,$email,$adresse,$societe]);
                $msg = '<div class="alert alert-success">Client ajouté avec succès.</div>';
            }
        }
    } elseif ($action === 'delete' && canDo('produit_delete')) {
        $db->prepare("UPDATE clients SET actif=0 WHERE id=?")->execute([(int)$_POST['id']]);
        $msg = '<div class="alert alert-success">Client supprimé.</div>';
    }
}

// Recherche
$search = trim($_GET['s'] ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));
$per = 15;

$where = "actif=1";
$params = [];
if ($search) { $where .= " AND (nom LIKE ? OR contact LIKE ? OR email LIKE ? OR societe LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }

$total = $db->prepare("SELECT COUNT(*) FROM clients WHERE $where");
$total->execute($params);
$total = $total->fetchColumn();
$pages = ceil($total / $per) ?: 1;
$offset = ($page - 1) * $per;

$st = $db->prepare("SELECT c.* FROM clients c WHERE $where ORDER BY c.nom LIMIT $per OFFSET $offset");
$st->execute($params);
$clients = $st->fetchAll();
?>
<?= $msg ?>

<div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:200px">
    <div class="search-bar" style="flex:1;max-width:360px">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      <form method="GET" style="display:flex;width:100%"><input class="form-control" name="s" placeholder="Rechercher un client..." value="<?= htmlspecialchars($search) ?>" style="padding-left:36px"></form>
    </div>
    <span style="font-size:13px;color:var(--muted)"><?= $total ?> client(s)</span>
  </div>
  <?php if (canDo('produit_edit')): ?>
  <button class="btn btn-primary" onclick="openModal('modal-client')">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
    Ajouter un client
  </button>
  <?php endif; ?>
</div>

<?php if (empty($clients) && !$search): ?>
<div class="empty-state">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
  <h4>Aucun client</h4>
  <p>Commencez par ajouter votre premier client.</p>
</div>
<?php elseif (empty($clients) && $search): ?>
<div class="empty-state">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  <h4>Aucun résultat</h4>
  <p>Aucun client trouvé pour "<?= htmlspecialchars($search) ?>".</p>
</div>
<?php else: ?>
<div class="card">
  <div style="overflow-x:auto">
    <table>
      <thead><tr><th>Nom / Société</th><th>Contact</th><th>Téléphone</th><th>Email</th><th>Adresse</th><th style="width:100px">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($clients as $c): ?>
      <tr>
        <td>
          <div style="font-weight:600"><?= htmlspecialchars($c['nom']) ?></div>
          <?php if ($c['societe']): ?><div style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($c['societe']) ?></div><?php endif; ?>
        </td>
        <td><?= htmlspecialchars($c['contact'] ?? '—') ?></td>
        <td><?= htmlspecialchars($c['telephone'] ?? '—') ?></td>
        <td style="font-size:13px"><?= $c['email'] ? htmlspecialchars($c['email']) : '—' ?></td>
        <td style="font-size:13px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($c['adresse'] ?? '—') ?></td>
        <td>
          <div style="display:flex;gap:6px">
            <?php if (canDo('produit_edit')): ?>
            <button class="icon-btn" title="Modifier" onclick="editClient(<?= htmlspecialchars(json_encode($c, JSON_UNESCAPED_UNICODE)) ?>)">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            </button>
            <?php endif; ?>
            <?php if (canDo('produit_delete')): ?>
            <button class="icon-btn" title="Supprimer" onclick="confirmDelete(<?= $c['id'] ?>,'<?= htmlspecialchars($c['nom'],ENT_QUOTES) ?>')" style="color:var(--danger)">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </button>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pages > 1): ?>
  <div class="pagination">
    <span class="page-info"><?= $total ?> résultat(s) · Page <?= $page ?>/<?= $pages ?></span>
    <?php if ($page > 1): ?><a href="?s=<?= urlencode($search) ?>&p=<?= $page-1 ?>" class="page-btn">←</a><?php endif; ?>
    <?php for ($i=max(1,$page-2);$i<=min($pages,$page+2);$i++): ?>
    <a href="?s=<?= urlencode($search) ?>&p=<?= $i ?>" class="page-btn <?= $i===$page?'active':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($page < $pages): ?><a href="?s=<?= urlencode($search) ?>&p=<?= $page+1 ?>" class="page-btn">→</a><?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Modal Client -->
<div class="modal-bg" id="modal-client">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title" id="modal-client-title">Nouveau client</div>
      <button class="modal-close" onclick="closeModal('modal-client')">✕</button>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" id="client-id" value="">
      <div class="modal-body">
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Nom *</label>
            <input class="form-control" name="nom" id="client-nom" required placeholder="Nom complet">
          </div>
          <div class="form-group">
            <label class="form-label">Société</label>
            <input class="form-control" name="societe" id="client-societe" placeholder="Nom de l'entreprise">
          </div>
        </div>
        <div class="form-row form-row-2">
          <div class="form-group">
            <label class="form-label">Personne de contact</label>
            <input class="form-control" name="contact" id="client-contact" placeholder="Prénom Nom">
          </div>
          <div class="form-group">
            <label class="form-label">Téléphone</label>
            <input class="form-control" name="telephone" id="client-telephone" placeholder="+237 ...">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email</label>
          <input class="form-control" type="email" name="email" id="client-email" placeholder="client@example.com">
        </div>
        <div class="form-group">
          <label class="form-label">Adresse</label>
          <textarea class="form-control" name="adresse" id="client-adresse" rows="2" placeholder="Adresse complète"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="closeModal('modal-client')">Annuler</button>
        <button type="submit" class="btn btn-primary">Enregistrer</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Suppression -->
<div class="modal-bg" id="modal-delete">
  <div class="modal" style="max-width:420px">
    <div class="modal-header"><div class="modal-title">Supprimer le client ?</div><button class="modal-close" onclick="closeModal('modal-delete')">✕</button></div>
    <div class="modal-body">
      <p>Êtes-vous sûr de vouloir supprimer <strong id="delete-name"></strong> ?</p>
    </div>
    <div class="modal-footer">
      <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" id="delete-id"><button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">Annuler</button><button type="submit" class="btn btn-danger">Supprimer</button></form>
    </div>
  </div>
</div>

<script>
function editClient(c) {
  document.getElementById('modal-client-title').textContent = 'Modifier le client';
  document.getElementById('client-id').value = c.id;
  document.getElementById('client-nom').value = c.nom || '';
  document.getElementById('client-societe').value = c.societe || '';
  document.getElementById('client-contact').value = c.contact || '';
  document.getElementById('client-telephone').value = c.telephone || '';
  document.getElementById('client-email').value = c.email || '';
  document.getElementById('client-adresse').value = c.adresse || '';
  openModal('modal-client');
}

function confirmDelete(id, name) {
  document.getElementById('delete-id').value = id;
  document.getElementById('delete-name').textContent = name;
  openModal('modal-delete');
}
</script>

<?php require_once '../includes/footer.php'; ?>