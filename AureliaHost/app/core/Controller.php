<?php
class Controller
{
    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        $viewPath = VIEWS_DIR . '/' . $view . '.php';

        if (!file_exists($viewPath)) {
            throw new RuntimeException("Vue introuvable : $view");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        require VIEWS_DIR . "/layouts/{$layout}.php";
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . BASE_URL . $url);
        exit;
    }

    protected function redirectBack(): void
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? BASE_URL;
        header('Location: ' . $referer);
        exit;
    }

    protected function json(array $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function isLoggedIn(): bool
    {
        return Session::isLoggedIn();
    }

    protected function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            Session::setFlash('error', 'Veuillez vous connecter.');
            $this->redirect('/auth/login');
        }
    }

    protected function requireRole(string|array $roles): void
    {
        $this->requireAuth();
        $allowed = is_array($roles) ? $roles : [$roles];
        $userRole = Session::userRole();

        if (!in_array($userRole, $allowed) && $userRole !== 'admin') {
            Session::setFlash('error', 'Accès non autorisé.');
            $this->redirect('/');
        }
    }

    protected function old(string $key, mixed $default = ''): mixed
    {
        return Session::getFlash('_old_input')[$key] ?? $default;
    }

    protected function error(string $key): ?string
    {
        $errors = Session::getFlash('_errors') ?? [];
        return $errors[$key] ?? null;
    }

    protected function wantsJson(): bool
    {
        return isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json');
    }

    protected function validateRequired(array $data, array $fields): array
    {
        $errors = [];
        foreach ($fields as $field) {
            if (empty($data[$field]) && $data[$field] !== '0') {
                $errors[$field] = 'Ce champ est obligatoire.';
            }
        }
        return $errors;
    }
}
