<?php
class SettingsController extends Controller {
    private $settingsModel;

    public function __construct() {
        $this->requireAuth();
        PermissionMiddleware::requirePermission('settings_view');
        $this->settingsModel = new Settings();
    }

    public function index() {
        $settings = $this->settingsModel->getSettings();
        $title = 'Paramètres';
        
        ob_start();
        require __DIR__ . '/../views/settings/index.php';
        $content = ob_get_clean();
        
        require __DIR__ . '/../views/layouts/main.php';
    }

    public function store() {
        PermissionMiddleware::requirePermission('settings_edit');
        $this->validateCsrf();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $settings = [
                'app_name' => $this->sanitize($_POST['app_name'] ?? 'RentFlow'),
                'currency' => $this->sanitize($_POST['currency'] ?? 'XOF'),
                'timezone' => $this->sanitize($_POST['timezone'] ?? 'Africa/Abidjan'),
                'language' => $this->sanitize($_POST['language'] ?? 'fr'),
                'notifications_enabled' => isset($_POST['notifications_enabled']) ? 1 : 0,
                'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
                'items_per_page' => (int)($_POST['items_per_page'] ?? 10),
                'date_format' => $this->sanitize($_POST['date_format'] ?? 'd/m/Y'),
            ];

            // Handle logo upload
            if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../../public/assets/images/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($fileInfo, $_FILES['logo']['tmp_name']);
                finfo_close($fileInfo);

                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml'];
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'svg'];
                $extension = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));

                if (in_array($mimeType, $allowedMimes) && in_array($extension, $allowedExts)) {
                    $filename = 'logo_' . time() . '.' . $extension;
                    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                        $settings['logo'] = 'assets/images/' . $filename;
                    }
                }
            }

            foreach ($settings as $key => $value) {
                $this->settingsModel->update($key, $value);
            }

            $_SESSION['success'] = 'Paramètres enregistrés avec succès';
            $this->redirect('settings');
        }
    }
}
