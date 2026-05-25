<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/database.php';
require_once __DIR__ . '/../includes/auth.php';

Auth::requirePermission('agences_gerer');

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $errors = [];
        if (empty($_POST['code'])) $errors[] = 'Code requis';
        if (empty($_POST['nom']))  $errors[] = 'Nom requis';
        if (!$errors) {
            Database::execute(
                "INSERT INTO agences (code,nom,adresse,telephone) VALUES (?,?,?,?)",
                [strtoupper(trim($_POST['code'])), trim($_POST['nom']),
                 trim($_POST['adresse']??''), trim($_POST['telephone']??'')]
            );
            Auth::logAction('AGENCE_CREEE','agences');
            $_SESSION['flash']=['type'=>'success','message'=>'Agence créée.'];
        }
    } elseif ($action === 'update') {
        Database::execute(
            "UPDATE agences SET nom=?,adresse=?,telephone=?,actif=? WHERE id=?",
            [trim($_POST['nom']), trim($_POST['adresse']??''),
             trim($_POST['telephone']??''), isset($_POST['actif'])?1:0, intval($_POST['id'])]
        );
        Auth::logAction('AGENCE_MODIFIE','agences',intval($_POST['id']));
        $_SESSION['flash']=['type'=>'success','message'=>'Agence mise à jour.'];
    } elseif ($action === 'toggle') {
        $aid = intval($_POST['agence_id']);
        Database::execute("UPDATE agences SET actif = NOT actif WHERE id=?", [$aid]);
        $_SESSION['flash']=['type'=>'success','message'=>'Statut agence modifié.'];
    }
    header('Location: agences.php'); exit;
}

$pageTitle = 'Gestion des agences';
require_once __DIR__ . '/../includes/header.php';

$agences = Database::fetchAll(
    "SELECT a.*, 
     (SELECT COUNT(*) FROM colis WHERE agence_depart_id=a.id) AS nb_depart,
     (SELECT COUNT(*) FROM colis WHERE agence_arrivee_id=a.id) AS nb_arrivee
     FROM agences a ORDER BY a.nom"
);
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px">
    <div>
        <h2 style="font-size:1.3rem;font-weight:700">Agences</h2>
        <p class="text-muted" style="font-size:.85rem"><?= count($agences) ?> agences configurées</p>
    </div>
    <button class="btn btn-primary" data-modal="modal-add-agence">
        <i class="fas fa-plus"></i> Nouvelle agence
    </button>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(320px,1fr));gap:16px">
    <?php foreach ($agences as $a): ?>
    <div class="card" style="border-color:<?= $a['actif']?'var(--border)':'rgba(220,38,38,0.2)' ?>;
         background:<?= $a['actif']?'var(--bg-card)':'rgba(220,38,38,0.03)' ?>">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
            <div style="display:flex;align-items:center;gap:10px">
                <div style="width:40px;height:40px;background:<?= $a['actif']?'linear-gradient(135deg,var(--primary),var(--accent))':'rgba(100,100,100,0.2)' ?>;
                     border-radius:10px;display:flex;align-items:center;justify-content:center;
                     font-family:var(--font-mono);font-size:.75rem;font-weight:700;color:white;flex-shrink:0">
                    <?= $a['code'] ?>
                </div>
                <div>
                    <div style="font-weight:700;font-size:.95rem"><?= htmlspecialchars($a['nom']) ?></div>
                </div>
            </div>
            <span class="badge <?= $a['actif']?'badge-success':'badge-danger' ?>"><?= $a['actif']?'Active':'Inactive' ?></span>
        </div>

        <?php if ($a['telephone']): ?>
        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:8px">
            <i class="fas fa-phone" style="margin-right:6px;color:var(--primary)"></i><?= htmlspecialchars($a['telephone']) ?>
        </div>
        <?php endif; ?>
        <?php if ($a['adresse']): ?>
        <div style="font-size:.8rem;color:var(--text-muted);margin-bottom:12px">
            <i class="fas fa-location-dot" style="margin-right:6px;color:var(--primary)"></i><?= htmlspecialchars($a['adresse']) ?>
        </div>
        <?php endif; ?>

        <div style="display:flex;gap:16px;margin-bottom:14px;padding:10px;background:var(--bg-card2);border-radius:8px">
            <div style="text-align:center;flex:1">
                <div style="font-size:1.1rem;font-weight:700;font-family:var(--font-mono)"><?= $a['nb_depart'] ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)">Départs</div>
            </div>
            <div style="width:1px;background:var(--border)"></div>
            <div style="text-align:center;flex:1">
                <div style="font-size:1.1rem;font-weight:700;font-family:var(--font-mono)"><?= $a['nb_arrivee'] ?></div>
                <div style="font-size:.7rem;color:var(--text-muted)">Arrivées</div>
            </div>
        </div>

        <div style="display:flex;gap:8px">
            <button class="btn btn-secondary btn-sm" style="flex:1"
                    onclick="openEditAgence(<?= htmlspecialchars(json_encode($a)) ?>)">
                <i class="fas fa-pencil"></i> Modifier
            </button>
            <form method="post" style="flex:1">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="agence_id" value="<?= $a['id'] ?>">
                <button type="submit" class="btn btn-ghost btn-sm w-100"
                        data-confirm="Confirmer le changement de statut de cette agence ?">
                    <?= $a['actif'] ? '<i class="fas fa-ban"></i> Désactiver' : '<i class="fas fa-check"></i> Activer' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- MODAL ADD -->
<div class="modal-backdrop" id="modal-add-agence">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Nouvelle agence</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="create">
            <div class="form-grid-2">
                <div class="form-group">
                    <label class="form-label">Code (2-5 lettres) <span class="req">*</span></label>
                    <input type="text" name="code" class="form-control" required maxlength="10"
                           placeholder="EX: NGD" style="text-transform:uppercase">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Nom de l'agence <span class="req">*</span></label>
                    <input type="text" name="nom" class="form-control" required placeholder="Agence XYZ Centre">
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" name="telephone" class="form-control" placeholder="+237...">
                </div>
                <div class="form-group">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" class="form-control" placeholder="Quartier, rue...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="this.closest('.modal-backdrop').classList.remove('open')">Annuler</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Créer</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDIT -->
<div class="modal-backdrop" id="modal-edit-agence">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title">Modifier l'agence</div>
            <button class="modal-close" onclick="this.closest('.modal-backdrop').classList.remove('open')">&times;</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit_agence_id">
            <div class="form-grid-2">
                <div class="form-group col-span-2">
                    <label class="form-label">Nom</label>
                    <input type="text" name="nom" id="edit_agence_nom" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" name="telephone" id="edit_agence_tel" class="form-control">
                </div>
                <div class="form-group col-span-2">
                    <label class="form-label">Adresse</label>
                    <input type="text" name="adresse" id="edit_agence_adr" class="form-control">
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.9rem">
                        <input type="checkbox" name="actif" id="edit_agence_actif" value="1">
                        Agence active
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
function openEditAgence(data) {
    document.getElementById('edit_agence_id').value  = data.id;
    document.getElementById('edit_agence_nom').value  = data.nom;
    document.getElementById('edit_agence_tel').value  = data.telephone || '';
    document.getElementById('edit_agence_adr').value  = data.adresse || '';
    document.getElementById('edit_agence_actif').checked = data.actif == 1;
    document.getElementById('modal-edit-agence').classList.add('open');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
