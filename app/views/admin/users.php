<div class="page-header">
    <div class="page-header-left">
        <h1><i class="bi bi-people"></i> Utilisateurs</h1>
    </div>
    <div class="page-header-right">
        <button class="btn-material btn-material-primary" onclick="openAddUser()">
            <i class="bi bi-plus"></i> Nouveau
        </button>
    </div>
</div>

<div class="search-bar" style="margin-top:16px">
    <i class="bi bi-search search-icon"></i>
    <input type="text" id="searchInput" class="form-input-material" placeholder="Rechercher par nom, email ou rôle..." oninput="renderTable()" style="padding-left:36px">
</div>

<div id="usersTableContainer" style="margin-top:16px"></div>

<div id="pagination" style="display:flex;justify-content:center;align-items:center;gap:12px;margin-top:16px"></div>

<!-- Add Modal -->
<div class="modal-material-overlay" id="addUserModal" style="display:none">
    <div class="modal-material">
        <div class="modal-material-header">
            <h5><i class="bi bi-person-plus"></i> Nouvel utilisateur</h5>
            <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/users/add">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="add_name">Nom</label>
                    <input type="text" name="name" id="add_name" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_email">Email</label>
                    <input type="email" name="email" id="add_email" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_password">Mot de passe (min. 8 caractères)</label>
                    <input type="password" name="password" id="add_password" class="form-input-material" minlength="8" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="add_role">Rôle</label>
                    <select name="role" id="add_role" class="form-input-material" required>
                        <option value="client">Client</option>
                        <option value="technicien">Technicien</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material" onclick="closeModal('addUserModal')">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-check"></i> Ajouter</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal-material-overlay" id="editUserModal" style="display:none">
    <div class="modal-material">
        <div class="modal-material-header">
            <h5><i class="bi bi-pencil"></i> Modifier utilisateur</h5>
            <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
        </div>
        <form method="POST" action="<?= BASE_URL ?>/admin/users/edit/" id="editUserForm">
            <div class="modal-material-body">
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_name">Nom</label>
                    <input type="text" name="name" id="edit_name" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_email">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-input-material" required>
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_password">Nouveau mot de passe (min. 8 car., laisser vide pour conserver)</label>
                    <input type="password" name="password" id="edit_password" class="form-input-material" minlength="8">
                </div>
                <div class="form-group-material">
                    <label class="form-label-material" for="edit_role">Rôle</label>
                    <select name="role" id="edit_role" class="form-input-material" required>
                        <option value="client">Client</option>
                        <option value="technicien">Technicien</option>
                        <option value="admin">Administrateur</option>
                    </select>
                </div>
            </div>
            <div class="modal-material-footer">
                <button type="button" class="btn-material" onclick="closeModal('editUserModal')">Annuler</button>
                <button type="submit" class="btn-material btn-material-primary"><i class="bi bi-check"></i> Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<script>
const allUsers = <?= json_encode($users) ?>;
const ITEMS_PER_PAGE = 10;
let currentPage = 1;
let sortField = null;
let sortDir = 'asc';

function closeModal(id) {
    document.getElementById(id).style.display = 'none';
}

function openModal(id) {
    document.getElementById(id).style.display = 'flex';
}

function openAddUser() {
    document.getElementById('add_name').value = '';
    document.getElementById('add_email').value = '';
    document.getElementById('add_password').value = '';
    document.getElementById('add_role').value = 'client';
    openModal('addUserModal');
}

function openEditUser(id) {
    const u = allUsers.find(user => user.id === id);
    if (!u) return;
    document.getElementById('editUserForm').action = '<?= BASE_URL ?>/admin/users/edit/' + id;
    document.getElementById('edit_name').value = u.nom;
    document.getElementById('edit_email').value = u.email;
    document.getElementById('edit_password').value = '';
    document.getElementById('edit_role').value = u.role;
    openModal('editUserModal');
}

function getBadgeClass(role) {
    const map = { admin: 'badge-critical', technicien: 'badge-medium', client: 'badge-low' };
    return 'badge-status ' + (map[role] || '');
}

function renderTable() {
    const q = (document.getElementById('searchInput').value || '').toLowerCase().trim();

    let filtered = allUsers.filter(u =>
        (u.nom || '').toLowerCase().includes(q) ||
        (u.email || '').toLowerCase().includes(q) ||
        (u.role || '').toLowerCase().includes(q)
    );

    if (sortField) {
        filtered.sort((a, b) => {
            let va = (a[sortField] || '').toString().toLowerCase();
            let vb = (b[sortField] || '').toString().toLowerCase();
            if (sortField === 'id') { va = Number(a.id); vb = Number(b.id); }
            return sortDir === 'asc' ? (va > vb ? 1 : -1) : (va < vb ? 1 : -1);
        });
    }

    const totalPages = Math.max(1, Math.ceil(filtered.length / ITEMS_PER_PAGE));
    if (currentPage > totalPages) currentPage = totalPages;
    const start = (currentPage - 1) * ITEMS_PER_PAGE;
    const page = filtered.slice(start, start + ITEMS_PER_PAGE);

    if (filtered.length === 0) {
        document.getElementById('usersTableContainer').innerHTML = `
            <div class="empty-state">
                <i class="bi bi-people" style="font-size:48px;color:#9e9e9e"></i>
                <p>Aucun utilisateur trouvé</p>
            </div>`;
        document.getElementById('pagination').innerHTML = '';
        return;
    }

    const sortIcon = (f) => sortField === f ? (sortDir === 'asc' ? '&#9650;' : '&#9660;') : '&#9654;&#9664;';

    let html = `<div class="table-container"><table class="table-material"><thead><tr>
        <th onclick="toggleSort('nom')" style="cursor:pointer">Nom ${sortIcon('nom')}</th>
        <th onclick="toggleSort('email')" style="cursor:pointer">Email ${sortIcon('email')}</th>
        <th onclick="toggleSort('role')" style="cursor:pointer">Rôle ${sortIcon('role')}</th>
        <th style="width:110px">Actions</th>
    </tr></thead><tbody>`;

    page.forEach(u => {
        html += `<tr>
            <td><div class="user-cell"><div class="user-avatar initials">${u.nom.charAt(0).toUpperCase()}</div>${escapeHtml(u.nom)}</div></td>
            <td>${escapeHtml(u.email)}</td>
            <td><span class="${getBadgeClass(u.role)}">${u.role.charAt(0).toUpperCase() + u.role.slice(1)}</span></td>
            <td class="actions-cell">
                <button class="btn-material btn-material-small btn-material-primary" title="Modifier" onclick="openEditUser(${u.id})"><i class="bi bi-pencil"></i></button>
                <form method="POST" action="<?= BASE_URL ?>/admin/users/delete/${u.id}" style="display:inline" onsubmit="return confirm('Confirmer la suppression de cet utilisateur ?')">
                    <button class="btn-material btn-material-small btn-material-danger" title="Supprimer"><i class="bi bi-trash"></i></button>
                </form>
            </td>
        </tr>`;
    });

    html += '</tbody></table></div>';
    document.getElementById('usersTableContainer').innerHTML = html;

    let p = `<button class="btn-material btn-material-small" onclick="goPage(${currentPage-1})" ${currentPage <= 1 ? 'disabled' : ''}>Précédent</button>
        <span style="font-size:13px;color:#666">Page ${currentPage} / ${totalPages} (${filtered.length} utilisateurs)</span>
        <button class="btn-material btn-material-small" onclick="goPage(${currentPage+1})" ${currentPage >= totalPages ? 'disabled' : ''}>Suivant</button>`;
    document.getElementById('pagination').innerHTML = p;
}

function toggleSort(field) {
    if (sortField === field) {
        sortDir = sortDir === 'asc' ? 'desc' : 'asc';
    } else {
        sortField = field;
        sortDir = 'asc';
    }
    currentPage = 1;
    renderTable();
}

function goPage(p) {
    currentPage = p;
    renderTable();
}

function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.querySelectorAll('.modal-material-overlay').forEach(el => {
    el.addEventListener('click', function(e) { if (e.target === this) this.style.display = 'none'; });
});

renderTable();
</script>
