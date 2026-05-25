<?php
class SettingsController extends Controller
{
    public function __construct()
    {
        $this->requireRole('admin');
    }

    public function index(): void
    {
        $settings = Setting::allCached();

        // Récupérer les permissions par rôle
        $db = Database::getInstance()->getConnection();
        $perms = $db->query("SELECT role, module FROM role_permissions ORDER BY role, module")->fetchAll(PDO::FETCH_ASSOC);

        $rolePermissions = [];
        foreach ($perms as $p) {
            $rolePermissions[$p['role']][] = $p['module'];
        }

        // Rôles disponibles dans la table users
        $roles = $db->query("SELECT DISTINCT role FROM users ORDER BY role")->fetchAll(PDO::FETCH_COLUMN);

        // Utilisateurs (pour changement de rôle)
        $users = (new User())->all('', [], 'nom, prenom');

        $this->render('settings/index', [
            'settings'        => $settings,
            'rolePermissions' => $rolePermissions,
            'roles'           => $roles,
            'users'           => $users,
        ]);
    }

    public function update(): void
    {
        $data = $_POST;

        // Mise à jour des paramètres texte
        foreach (['hotel_nom', 'hotel_adresse', 'hotel_telephone', 'hotel_email', 'devise_code', 'devise_symbole', 'tva_defaut', 'theme_defaut'] as $key) {
            if (isset($data[$key])) {
                Setting::set($key, $data[$key]);
            }
        }

        Session::setFlash('success', 'Paramètres mis à jour.');
        $this->redirectBack();
    }

    public function updatePermissions(): void
    {
        $db = Database::getInstance()->getConnection();
        $role = $_POST['role'] ?? '';
        $modules = $_POST['modules'] ?? [];

        if (empty($role)) {
            Session::setFlash('error', 'Rôle requis.');
            $this->redirectBack();
            return;
        }

        $db->beginTransaction();
        try {
            // Supprimer les permissions existantes pour ce rôle
            $db->exec("DELETE FROM role_permissions WHERE role = " . $db->quote($role));

            // Insérer les nouvelles
            $stmt = $db->prepare("INSERT INTO role_permissions (role, module) VALUES (:r, :m)");
            foreach ($modules as $module) {
                $stmt->execute(['r' => $role, 'm' => $module]);
            }

            $db->commit();
            Session::setFlash('success', "Permissions mises à jour pour le rôle « $role ».");
        } catch (Exception $e) {
            $db->rollBack();
            Session::setFlash('error', 'Erreur: ' . $e->getMessage());
        }

        $this->redirectBack();
    }

    public function updateRole(): void
    {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? '';

        if (!$userId || empty($newRole)) {
            Session::setFlash('error', 'Utilisateur et rôle requis.');
            $this->redirectBack();
            return;
        }

        $userModel = new User();
        $user = $userModel->find($userId);
        if (!$user) {
            Session::setFlash('error', 'Utilisateur introuvable.');
            $this->redirectBack();
            return;
        }

        $userModel->update($userId, ['role' => $newRole]);
        Session::setFlash('success', "Rôle de {$user['prenom']} {$user['nom']} mis à jour → {$newRole}.");
        $this->redirectBack();
    }
}
