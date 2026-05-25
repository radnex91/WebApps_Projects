<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-flag"></i> Priorités</h1>
    </div>
    <div class="page-header-right">
        <button class="btn-material btn-material-primary" onclick="openAddPriority()">
            <i class="bi bi-plus"></i> Nouveau
        </button>
    </div>
</div>

<div class="table-container" style="margin-top:20px">
    <table class="table-material">
        <thead>
            <tr><th>Nom</th><th>Couleur</th><th>SLA (h)</th><th style="width:110px">Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($priorities as $p): ?>
            <tr>
                <td><span class="badge-status" style="background:<?= htmlspecialchars($p->couleur) ?>22;color:<?= htmlspecialchars($p->couleur) ?>;border:1px solid <?= htmlspecialchars($p->couleur) ?>44"><?= htmlspecialchars($p->nom) ?></span></td>
                <td><span class="color-swatch"><span class="color-swatch-dot" style="background:<?= htmlspecialchars($p->couleur) ?>"></span><span class="color-swatch-hex"><?= htmlspecialchars($p->couleur) ?></span></span></td>
                <td><?= $p->temps_resolution_heures ?>h</td>
                <td class="actions-cell">
                    <button class="btn-material btn-material-small btn-material-primary" title="Modifier" onclick="openEditPriority(<?= $p->id ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= BASE_URL ?>/admin/priorities/delete/<?= $p->id ?>" style="display:inline" onsubmit="return confirm('Confirmer la suppression ?')">
                        <button class="btn-material btn-material-small btn-material-danger" title="Supprimer">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Add Modal -->
<div class="modal-backdrop" id="addPriorityModal" style="display:none" onclick="if(event.target===this)closeAddPriority()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3><i class="bi bi-plus-circle"></i> Nouvelle priorité</h3>
            <button class="modal-close" onclick="closeAddPriority()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/priorities/add" class="form-material">
            <div class="modal-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="add_name">Nom</label>
                    <input type="text" name="name" id="add_name" class="form-input-material" placeholder="Ex: Critique, Haute..." required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_color">Couleur</label>
                    <input type="color" name="color" id="add_color" class="form-input-material" value="#dc3545" style="height:44px;padding:4px">
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_sla_hours">SLA (heures)</label>
                    <input type="number" name="sla_hours" id="add_sla_hours" class="form-input-material" placeholder="24" value="24" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeAddPriority()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-backdrop" id="editPriorityModal" style="display:none" onclick="if(event.target===this)closeEditPriority()">
    <div class="modal-card" onclick="event.stopPropagation()">
        <div class="modal-header">
            <h3><i class="bi bi-pencil"></i> Modifier la priorité</h3>
            <button class="modal-close" onclick="closeEditPriority()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/priorities/edit/" id="editPriorityForm" class="form-material">
            <div class="modal-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_name">Nom</label>
                    <input type="text" name="name" id="edit_name" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_color">Couleur</label>
                    <input type="color" name="color" id="edit_color" class="form-input-material" style="height:44px;padding:4px">
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_sla_hours">SLA (heures)</label>
                    <input type="number" name="sla_hours" id="edit_sla_hours" class="form-input-material" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeEditPriority()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
const prioritiesData = <?= json_encode($priorities) ?>;

function openAddPriority() {
    document.getElementById('add_name').value = '';
    document.getElementById('add_color').value = '#dc3545';
    document.getElementById('add_sla_hours').value = '24';
    document.getElementById('addPriorityModal').style.display = 'flex';
}

function closeAddPriority() {
    document.getElementById('addPriorityModal').style.display = 'none';
}

function openEditPriority(id) {
    const p = prioritiesData.find(prio => prio.id === id);
    if (!p) return;
    document.getElementById('editPriorityForm').action = '<?= BASE_URL ?>/admin/priorities/edit/' + id;
    document.getElementById('edit_name').value = p.nom;
    document.getElementById('edit_color').value = p.couleur;
    document.getElementById('edit_sla_hours').value = p.temps_resolution_heures;
    document.getElementById('editPriorityModal').style.display = 'flex';
}

function closeEditPriority() {
    document.getElementById('editPriorityModal').style.display = 'none';
}
</script>
