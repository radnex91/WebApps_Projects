<?php
/**
 * Classe Controller de base
 */
class Controller
{
    protected $viewPath;
    protected $layout = 'main';

    public function view($view, $data = [])
    {
        // Support both $title and $pageTitle in layout
        if (isset($data['title']) && !isset($data['pageTitle'])) {
            $data['pageTitle'] = $data['title'];
        }
        extract($data);
        $viewFile = VIEWS_PATH . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            die("Vue non trouvée : {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($this->layout) {
            $layoutFile = VIEWS_PATH . '/layouts/' . $this->layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }

    public function json($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function redirect($url)
    {
        header("Location: " . APP_URL . $url);
        exit;
    }

    public function redirectTo($url)
    {
        header("Location: {$url}");
        exit;
    }

    protected function requireAuth()
    {
        if (!Auth::check()) {
            Flash::set('error', 'Veuillez vous connecter pour accéder à cette page.');
            $this->redirect('/login');
        }
    }

    protected function requirePermission($module, $action)
    {
        if (!Auth::hasPermission($module, $action)) {
            Flash::set('error', 'Vous n\'avez pas la permission d\'accéder à cette page.');
            $this->redirect('/dashboard');
        }
    }

    protected function getPaginationParams()
    {
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        return compact('page', 'search');
    }
}