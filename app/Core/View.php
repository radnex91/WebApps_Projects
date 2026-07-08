<?php
namespace App\Core;
class View
{
    private string $viewsPath;
    public function __construct()
    {
        $this->viewsPath = __DIR__ . '/../../app/views/';
    }
    public function render(string $view, array $data = []): void
    {
        extract($data);
        $viewFile = $this->viewsPath . $view . '.php';
        if (!file_exists($viewFile)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        require $viewFile;
    }
    public function partial(string $partial, array $data = []): void
    {
        extract($data);
        $partialFile = $this->viewsPath . 'partials/' . $partial . '.php';
        if (file_exists($partialFile)) {
            require $partialFile;
        }
    }
    public function layout(string $layout, string $view, array $data = []): void
    {
        $data['content'] = function () use ($view, $data) {
            $this->render($view, $data);
        };
        $data['__view'] = $this;
        extract($data);
        $layoutFile = $this->viewsPath . 'layouts/' . $layout . '.php';
        if (!file_exists($layoutFile)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }
        require $layoutFile;
    }
    public function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}
