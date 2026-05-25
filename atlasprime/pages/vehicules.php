<?php
$pageTitle = 'Gestion des vehicules';
require_once __DIR__ . '/../includes/header.php';
Auth::requirePermission('vehicules_gerer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $errors = [];
        if (empty($_POST['immatriculation'])) $errors[] = 'Immatriculation requise';
        if (empty($_POST['designation'])) $errors[] = 'Designation requise';
        $existing = Database::fetchOne("SELECT id FROM vehicules WHERE immatriculation=?", [strtoupper(trim($_POST['immatriculation']))]);
        if ($existing) $errors[] = 'Cette immatriculation existe deja';
        if (!$errors) {
            Database::execute(
                "INSERT INTO vehicules (immatriculation,designation,statut) VALUES (?,?,?)",
                [strtoupper(trim($_POST['immatriculation'])), trim($_POST['designation']),
                 $_POST['statut'] ?? 'disponible']
            );
            Auth::logAction('VEHICULE_CREE', 'vehicules');
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Vehicule cree.'];
        } else {
            $_SESSION['flash'] = ['type' => 'error', 'message' => implode(' ', $errors)];
        }
    } elseif ($action === 'update') {
        Database::execute(
            "UPDATE vehicules SET immatriculation=?, designation=?, actif=? WHERE id=?",
            [strtoupper(trim($_POST['immatriculation'])), trim($_POST['designation']),
             isset($_POST['actif']) ? 1 : 0, intval($_POST['id'])]
        );
        Auth::logAction('VEHICULE_MODIFIE', 'vehicules', intval($_POST['id']));
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Vehicule mis a jour.'];
    } elseif ($action === 'toggle_statut') {
        $vid = intval($_POST['id']);
        $vehicule = Database::fetchOne("SELECT statut FROM vehicules WHERE id=?", [$vid]);
        if ($vehicule && $vehicule['statut'] === 'disponible') {
            // Declaration en panne : annuler les voyages en cours de ce vehicule
            $voyagesActifs = Database::fetchAll(
                "SELECT id FROM voyages WHERE vehicule_id=? AND statut IN ('planifie','en_cours')", [$vid]
            );
            foreach ($voyagesActifs as $v) {
                transitionVoyageStatut($v['id'], 'annuler', null, $user['id']);
            }
            Database::execute("UPDATE vehicules SET statut='en_panne' WHERE id=?", [$vid]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Vehicule declare en panne. Voyages associes annules.'];
        } else {
            Database::execute("UPDATE vehicules SET statut='disponible' WHERE id=?", [$vid]);
            $_SESSION['flash'] = ['type' => 'success', 'message' => 'Vehicule remis en service.'];
        }
    } elseif ($action === 'toggle_actif') {
        Database::execute("UPDATE vehicules SET actif = NOT actif WHERE id=?", [intval($_POST['id'])]);
        $_SESSION['flash'] = ['type' => 'success', 'message' => 'Statut vehicule modifie.'];
    }
    header('Location: vehicules.php');
    exit;
}

$vehicules = Database::fetchAll(
    "SELECT v.*,
     (SELECT COUNT(*) FROM voyages WHERE vehicule_id = v.id) AS nb_voyages
     FROM vehicules v ORDER BY v.actif DESC, v.designation"
);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <div>
        <h2 style="font-size:1.3rem;font-weight:700">Vehicules</h2>
        <p class="text-muted" style="font-size:.85rem"><?= count($vehicules) ?> vehicules enregistres</p>
    </div>
    <button class="btn btn-primary" data-modal="modal-add-vehicule">
        <i class="fas fa-plus"></i> Nouveau vehicule
    </button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
    <?php foreach ($vehicules as $v): ?>
    <div class="card" style="border-color:<?= $v['actif'] ? 'var(--border)' : 'rgba(220,38,38,0.2)' ?>;
         background:<?= $v['actif'] ? 'var(--bg-card)' : 'rgba(220,38,38,0.03)' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:40px;height:40px;background:<?= $v['actif'] ? 'linear-gradient(135deg,var(--primary),var(--accent))' : 'rgba(100,100,100,0.2)' ?>;
                     border-radius:10px;display:flex;align-items:center;justify-content:center;
                     font-family:var(--font-mono);font-size:.7rem;font-weight:700;color:white;flex-shrink:0">
                    <?= substr($v['immatriculation'], 0, 4) ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.95rem"><?= htmlspecialchars($v['designation']) ?></div>
                    <div style="font-family:var(--font-mono);font-size:.8rem;color:var(--text-muted)"><?= htmlspecialchars($v['immatriculation']) ?></div>
                </div>
            </div>
            <?= statutBadge($v['statut']) ?>
        </div>

        <div style="display:flex;gap:16px;margin-bottom:14px;padding:10px;background:var(--bg-card2);border-radius:8px">
            <div style="text-align:center;flex:1">
                <div style="font-size:1.1rem;font-weight:700;font-family:var(--font-mono)"><?= $v['nb_voyages'] ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)">Voyages</div>
            </div>
        </div>

        <div style="display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm" style="flex:1"
                    onclick='openEditVehicule(<?= json_encode($v) ?>)'>
                <i class="fas fa-pencil"></i> Modifier
            </button>
            <form method="post" style="flex:1">
                <input type="hidden" name="action" value="toggle_statut">
                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm w-100"
                        data-confirm="Changer le statut de ce vehicule ?">
                    <?php if ($v['statut'] === 'disponible'): ?>
                    <i class="fas fa-exclamation-triangle"></i> En panne
                    <?php else: ?>
                    <i class="fas fa-check"></i> Disponible
                    <?php endif; ?>
                </button>
            </form>
            <?php if ($v['nb_voyages'] == 0): ?>
            <form method="post" style="flex:0 0 auto">
                <input type="hidden" name="action" value="toggle_actif">
                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button type="submit" class="action-btn del" title="<?= $v['actif'] ? 'Desactiver' : 'Activer' ?>"
                        data-confirm="Confirmer ?">
                    <i class="fas fa-<?= $v['actif'] ? 'ban' : 'check' ?>"></i>
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($vehicules)): ?>
    <div class="card" style="grid-column:1/-1">
        <div class="empty-state">
            <i class="fas fa-truck" style="font-size:2rem;color:var(--text-muted)"></i>
            <h3>Aucun vehicule</h3>
            <p>Ajoutez votre premier vehicule pour commencer.</p>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- MODAL ADD -->
<div class="modal-backdrop" id="modal-add-vehicule">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouveau vehicule</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Immatriculation <span class="req">*</span></label>
                    <input type="text" name="immatriculation" class="form-control" required maxlength="30"
                           placeholder="EX: LT-1234-A" style="text-transform:uppercase">
                </div>
                <div class="form-group">
                    <label class="form-label">Designation <span class="req">*</span></label>
                    <input type="text" name="designation" class="form-control" required
                           placeholder="EX: Volvo FH 500">
                </div>
                <div class="form-group">
                    <label class="form-label">Statut</label>
                    <select name="statut" class="form-control">
                        <option value="disponible">Disponible</option>
                        <option value="en_panne">En panne</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Creer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-backdrop" id="modal-edit-vehicule">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Modifier le vehicule</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="ev_id">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Immatriculation</label>
                    <input type="text" name="immatriculation" id="ev_immatriculation" class="form-control" required
                           style="text-transform:uppercase">
                </div>
                <div class="form-group">
                    <label class="form-label">Designation</label>
                    <input type="text" name="designation" id="ev_designation" class="form-control" required>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                        <input type="checkbox" name="actif" id="ev_actif" value="1"> Vehicule actif
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditVehicule(data) {
    document.getElementById('ev_id').value = data.id;
    document.getElementById('ev_immatriculation').value = data.immatriculation;
    document.getElementById('ev_designation').value = data.designation;
    document.getElementById('ev_actif').checked = data.actif == 1;
    document.getElementById('modal-edit-vehicule').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>