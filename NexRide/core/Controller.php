<?php
namespace Core;

abstract class Controller
{
    protected View $view;
    protected Session $session;
    protected Validator $validator;
    protected CSRF $csrf;

    public function __construct()
    {
        $this->view = new View();
        $this->session = Session::getInstance();
        $this->validator = new Validator();
        $this->csrf = new CSRF();
    }

    protected function render(string $view, array $data = [], string $layout = 'main'): void
    {
        $this->view->render($view, $data, $layout);
    }

    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    protected function redirectBack(): void
    {
        $this->redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }

    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function isPost(): bool { return $_SERVER['REQUEST_METHOD'] === 'POST'; }
    protected function isGet(): bool { return $_SERVER['REQUEST_METHOD'] === 'GET'; }
    protected function post(string $key = null): mixed { return $key === null ? $_POST : ($_POST[$key] ?? null); }
    protected function get(string $key = null): mixed { return $key === null ? $_GET : ($_GET[$key] ?? null); }
    protected function input(string $key, mixed $default = null): mixed { return $_POST[$key] ?? $_GET[$key] ?? $default; }

    protected function requireAuth(): void
    {
        if (!$this->session->has('user_id')) {
            $this->session->setFlash('error', 'Veuillez vous connecter');
            $this->redirect(BASE_URL . '/auth/login');
        }
    }

    protected function requireRole(string $role): void
    {
        $this->requireAuth();
        if ($this->session->get('user_role') !== $role && $this->session->get('user_role') !== 'admin') {
            $this->session->setFlash('error', 'Accès non autorisé');
            $this->redirect(BASE_URL);
        }
    }

    protected function setFlash(string $type, string $message): void
    {
        $this->session->setFlash($type, $message);
    }
}