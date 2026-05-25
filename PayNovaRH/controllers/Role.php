<?php
/**
 * RoleController - Gestion des rôles et permissions
 */
class RoleController extends Controller
{
    public function index($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('roles', 'view');

        $model = new RoleModel();
        $roles = $model->query(
            "SELECT r.*, (SELECT COUNT(*) FROM users WHERE role_id = r.id) as user_count
             FROM roles r ORDER BY r.name"
        );

        $this->view('roles.index', [
            'title' => 'Rôles & Permissions',
            'roles' => $roles
        ]);
    }

    public function create($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('roles', 'create');

        $permModel = new PermissionModel();
        $permissions = $permModel->all('module, action');

        $grouped = [];
        foreach ($permissions as $p) {
            $grouped[$p['module']][] = $p;
        }

        $this->view('roles.create', [
            'title' => 'Nouveau rôle',
            'groupedPermissions' => $grouped
        ]);
    }

    public function store($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('roles', 'create');

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            Flash::error('Le nom du rôle est requis.');
            $this->redirect('/roles/create');
        }

        $model = new RoleModel();
        $roleId = $model->create([
            'name' => $name,
            'description' => $description
        ]);

        // Sync permissions
        $permissionIds = $_POST['permissions'] ?? [];
        $permModel = new PermissionModel();
        $permModel->syncPermissions($roleId, $permissionIds);

        Flash::success('Rôle créé avec succès.');
        $this->redirect('/roles');
    }

    public function edit($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('roles', 'edit');

        $model = new RoleModel();
        $role = $model->find($params['id']);

        if (!$role) {
            Flash::error('Rôle non trouvé.');
            $this->redirect('/roles');
        }

        $permModel = new PermissionModel();
        $permissions = $permModel->all('module, action');
        $rolePerms = $permModel->getByRoleId($params['id']);
        $rolePermIds = array_column($rolePerms, 'id');

        $grouped = [];
        foreach ($permissions as $p) {
            $p['checked'] = in_array($p['id'], $rolePermIds);
            $grouped[$p['module']][] = $p;
        }

        $this->view('roles.edit', [
            'title' => 'Modifier le rôle',
            'role' => $role,
            'groupedPermissions' => $grouped
        ]);
    }

    public function update($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('roles', 'edit');

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');

        if (empty($name)) {
            Flash::error('Le nom du rôle est requis.');
            $this->redirect('/roles/' . $params['id'] . '/edit');
        }

        $model = new RoleModel();
        $model->update($params['id'], [
            'name' => $name,
            'description' => $description
        ]);

        // Sync permissions
        $permissionIds = $_POST['permissions'] ?? [];
        $permModel = new PermissionModel();
        $permModel->syncPermissions($params['id'], $permissionIds);

        Flash::success('Rôle mis à jour avec succès.');
        $this->redirect('/roles');
    }

    public function delete($params = [])
    {
        $this->requireAuth();
        $this->requirePermission('roles', 'delete');

        $model = new RoleModel();
        $role = $model->find($params['id']);

        if ($role && ($role['name'] === 'admin' || $role['name'] === 'manager' || $role['name'] === 'employé')) {
            Flash::error('Impossible de supprimer un rôle système.');
            $this->redirect('/roles');
        }

        $userModel = new UserModel();
        $userCount = $userModel->count(['role_id' => $params['id']]);
        if ($userCount > 0) {
            Flash::error('Ce rôle est assigné à ' . $userCount . ' utilisateur(s). Veuillez les réassigner d\'abord.');
            $this->redirect('/roles');
        }

        $model->delete($params['id']);
        Flash::success('Rôle supprimé avec succès.');
        $this->redirect('/roles');
    }
}