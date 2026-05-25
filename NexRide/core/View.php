<?php
namespace Core;

class View
{
    private string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = ROOT_DIR . 'views' . DIRECTORY_SEPARATOR;
    }

    public function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data);
        $viewPath = $this->viewsPath . str_replace('.', DIRECTORY_SEPARATOR, $view) . '.php';
        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        ob_start();
        require $viewPath;
        $content = ob_get_clean();
        $layoutPath = $this->viewsPath . 'layouts' . DIRECTORY_SEPARATOR . $layout . '.php';
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $content;
        }
    }

    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}