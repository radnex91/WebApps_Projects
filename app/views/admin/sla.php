<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-clock"></i> Configuration SLA</h1>
    </div>
    <div class="page-header-right">
        <button class="btn-material btn-material-primary" onclick="openAddSla()">
            <i class="bi bi-plus"></i> Nouveau
        </button>
    </div>
</div>

<div class="table-container" style="margin-top:20px">
    <table class="table-material">
        <thead>
            <tr><th>Priorité</th><th>Réponse (h)</th><th>Résolution (h)</th><th style="width:110px">Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach ($slaConfigs as $s): ?>
            <tr>
                <td>
                    <?php if (!empty($s->priority_couleur) && !empty($s->priority_nom)): ?>
                    <div class="color-swatch">
                        <span class="color-swatch-dot" style="background:<?= htmlspecialchars($s->priority_couleur) ?>"></span>
                        <span class="color-swatch-hex"><?= htmlspecialchars($s->priority_nom) ?></span>
                    </div>
                    <?php else: ?>
                    <span class="badge-status"><?= htmlspecialchars($s->priority_nom ?? $s->priorite_id ?? '') ?></span>
                    <?php endif; ?>
                </td>
                <td><span class="badge-status badge-medium"><?= $s->temps_reponse_heures ?>h</span></td>
                <td><span class="badge-status badge-low"><?= $s->temps_resolution_heures ?>h</span></td>
                <td class="actions-cell">
                    <button class="btn-material btn-material-small btn-material-primary" title="Modifier" onclick="openEditSla(<?= $s->id ?>)">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <form method="POST" action="<?= BASE_URL ?>/admin/sla/delete/<?= $s->id ?>" style="display:inline" onsubmit="return confirm('Confirmer la suppression ?')">
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
<div class="modal-material-overlay" id="addSlaModal" style="display:none" onclick="if(event.target===this)closeAddSla()">
    <div class="modal-material" onclick="event.stopPropagation()">
        <div class="modal-material-header">
            <h5><i class="bi bi-plus-circle"></i> Nouveau SLA</h5>
            <button class="modal-close" onclick="closeAddSla()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/sla/add" class="form-material">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="add_priority_id">Priorité</label>
                    <select name="priority_id" id="add_priority_id" class="form-input-material" required>
                        <option value="">Sélectionner une priorité</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p->id ?>"><?= htmlspecialchars($p->nom ?? $p->name ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_response_hours">Délai de réponse (heures)</label>
                    <input type="number" name="response_hours" id="add_response_hours" class="form-input-material" placeholder="Ex: 4" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_resolution_hours">Délai de résolution (heures)</label>
                    <input type="number" name="resolution_hours" id="add_resolution_hours" class="form-input-material" placeholder="Ex: 24" required>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeAddSla()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-material-overlay" id="editSlaModal" style="display:none" onclick="if(event.target===this)closeEditSla()">
    <div class="modal-material" onclick="event.stopPropagation()">
        <div class="modal-material-header">
            <h5><i class="bi bi-pencil"></i> Modifier le SLA</h5>
            <button class="modal-close" onclick="closeEditSla()">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/sla/edit/" id="editSlaForm" class="form-material">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_priority_id">Priorité</label>
                    <select name="priority_id" id="edit_priority_id" class="form-input-material" required>
                        <option value="">Sélectionner une priorité</option>
                        <?php foreach ($priorities as $p): ?>
                        <option value="<?= $p->id ?>"><?= htmlspecialchars($p->nom ?? $p->name ?? '') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_response_hours">Délai de réponse (heures)</label>
                    <input type="number" name="response_hours" id="edit_response_hours" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_resolution_hours">Délai de résolution (heures)</label>
                    <input type="number" name="resolution_hours" id="edit_resolution_hours" class="form-input-material" required>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material btn-material-danger" onclick="closeEditSla()">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
const slaData = <?= json_encode($slaConfigs) ?>;

function openAddSla() {
    document.getElementById('add_priority_id').value = '';
    document.getElementById('add_response_hours').value = '';
    document.getElementById('add_resolution_hours').value = '';
    document.getElementById('addSlaModal').style.display = 'flex';
}

function closeAddSla() {
    document.getElementById('addSlaModal').style.display = 'none';
}

function openEditSla(id) {
    const sla = slaData.find(s => s.id === id);
    if (!sla) return;
    document.getElementById('editSlaForm').action = '<?= BASE_URL ?>/admin/sla/edit/' + id;
    document.getElementById('edit_priority_id').value = sla.priorite_id;
    document.getElementById('edit_response_hours').value = sla.temps_reponse_heures;
    document.getElementById('edit_resolution_hours').value = sla.temps_resolution_heures;
    document.getElementById('editSlaModal').style.display = 'flex';
}

function closeEditSla() {
    document.getElementById('editSlaModal').style.display = 'none';
}
</script>
