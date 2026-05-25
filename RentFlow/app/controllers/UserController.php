<?php
class UserController extends Controller {
    private $userModel;
    private $roleModel;

    public function __construct() {
        $this->requireAuth();
        PermissionMiddleware::requirePermission('users_view');
        $this->userModel = new User();
        $this->roleModel = new Role();
    }

    public function index() {
        $users = $this->userModel->getWithRoles();
        $title = 'Gestion des Utilisateurs';

        ob_start();
        require __DIR__ . '/../views/users/index.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    public function create() {
        PermissionMiddleware::requirePermission('users_create');
        $roles = $this->roleModel->getAllWithUsersCount();
        $title = 'Ajouter un Utilisateur';

        ob_start();
        require __DIR__ . '/../views/users/create.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    public function store() {
        PermissionMiddleware::requirePermission('users_create');
        $this->validateCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate($_POST, [
                'name' => 'required',
                'email' => 'required',
                'password' => 'required',
            ]);

            // Check if email exists
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$_POST['email']]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Cet email est déjà utilisé';
            }

            if (empty($errors)) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $data = [
                    'name' => $this->sanitize($_POST['name']),
                    'email' => $this->sanitize($_POST['email']),
                    'password' => $password,
                ];

                $userId = $this->userModel->create($data);
                logInfo('User created', ['id' => $userId, 'name' => $data['name'], 'email' => $data['email']]);

                // Assign role
                if (isset($_POST['role_id']) && !empty($_POST['role_id'])) {
                    $this->roleModel->assignToUser($_POST['role_id'], $userId);
                    logInfo('Role assigned to user', ['user_id' => $userId, 'role_id' => $_POST['role_id']]);
                }

                $_SESSION['success'] = 'Utilisateur ajouté avec succès';
                $this->redirect('users');
            } else {
                $_SESSION['errors'] = $errors;
                $_SESSION['old_input'] = $_POST;
                $this->redirect('users/create');
            }
        }
    }

    public function edit($id) {
        PermissionMiddleware::requirePermission('users_edit');
        $user = $this->userModel->find($id);
        if (!$user) {
            $_SESSION['error'] = 'Utilisateur non trouvé';
            $this->redirect('users');
        }

        $roles = $this->roleModel->getAllWithUsersCount();
        $userRoles = $this->roleModel->getUserRoles($id);
        $title = 'Modifier l\'utilisateur';

        ob_start();
        require __DIR__ . '/../views/users/edit.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    public function update($id) {
        PermissionMiddleware::requirePermission('users_edit');
        $this->validateCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $errors = $this->validate($_POST, [
                'name' => 'required',
                'email' => 'required',
            ]);

            // Check if email exists (excluding current user)
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$_POST['email'], $id]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Cet email est déjà utilisé';
            }

            if (empty($errors)) {
                $data = [
                    'name' => $this->sanitize($_POST['name']),
                    'email' => $this->sanitize($_POST['email']),
                ];

                // Update password if provided
                if (!empty($_POST['password'])) {
                    $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                }

                $this->userModel->update($id, $data);
                logInfo('User updated', ['id' => $id, 'name' => $data['name'], 'email' => $data['email']]);

                // Mettre a jour la session si l'utilisateur modifie son propre profil
                if ((int)$id === Auth::userId()) {
                    $_SESSION['user']['name'] = $data['name'];
                    $_SESSION['user']['email'] = $data['email'];
                }

                // Update role
                if (isset($_POST['role_id'])) {
                    $this->roleModel->assignToUser($_POST['role_id'], $id);
                    logInfo('Role updated for user', ['user_id' => $id, 'role_id' => $_POST['role_id']]);
                }

                $_SESSION['success'] = 'Utilisateur modifié avec succès';
                $this->redirect('users');
            } else {
                $_SESSION['errors'] = $errors;
                $_SESSION['old_input'] = $_POST;
                $this->redirect('users/' . $id . '/edit');
            }
        }
    }

    public function delete($id) {
        PermissionMiddleware::requirePermission('users_delete');
        $this->validateCsrf();
        $this->userModel->delete($id);
        $_SESSION['success'] = 'Utilisateur supprimé avec succès';
        $this->redirect('users');
    }

    public function roles() {
        PermissionMiddleware::requirePermission('users_roles');
        $roles = $this->roleModel->getAllWithUsersCount();
        $permissions = (new Permission())->getAllGroupedByModule();

        // Get all role-permission mappings for the matrix
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("SELECT role_id, permission_id FROM permission_role");
        $mappings = [];
        while ($row = $stmt->fetch()) {
            $mappings[$row['role_id']][$row['permission_id']] = true;
        }

        $title = 'Gestion des Rôles';

        ob_start();
        require __DIR__ . '/../views/users/roles.php';
        $content = ob_get_clean();

        require __DIR__ . '/../views/layouts/main.php';
    }

    public function updateRoles() {
        PermissionMiddleware::requirePermission('users_roles');
        $this->validateCsrf();
        
        $roleId = $_POST['role_id'] ?? null;
        $permissionIds = $_POST['permissions'] ?? [];

        if ($roleId) {
            $this->roleModel->syncPermissions($roleId, $permissionIds);
            $_SESSION['success'] = 'Permissions enregistrées avec succès';
            logInfo('Permissions updated for role', ['role_id' => $roleId, 'permissions' => $permissionIds]);
        } else {
            $_SESSION['error'] = 'Rôle invalide';
        }

        $this->redirect('users/roles');
    }
}
