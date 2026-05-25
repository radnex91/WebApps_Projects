<?php
namespace App\Core;
class Controller
{
    protected View $view;
    public function __construct()
    {
        $this->view = new View();
    }
    protected function render(string $view, array $data = []): void
    {
        $this->view->render($view, $data);
    }
    protected function layout(string $layout, string $view, array $data = []): void
    {
        $this->view->layout($layout, $view, $data);
    }
    protected function partial(string $partial, array $data = []): void
    {
        $this->view->partial($partial, $data);
    }
    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit;
    }
    protected function redirectBack(): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '/';
        $this->redirect($ref);
    }
    protected function json(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    protected function setFlash(string $type, string $message): void
    {
        if (!isset($_SESSION['flash'])) {
            $_SESSION['flash'] = [];
        }
        $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
    }
    protected function getFlash(): array
    {
        $flash = $_SESSION['flash'] ?? [];
        unset($_SESSION['flash']);
        return $flash;
    }
    protected function hasFlash(): bool
    {
        return !empty($_SESSION['flash']);
    }
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }
    protected function isGet(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'GET';
    }
    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }
    protected function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
    protected function request(string $key, $default = null)
    {
        return $_REQUEST[$key] ?? $default;
    }
}
