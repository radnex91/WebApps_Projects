<?php
class Router {
    private $routes = [];

    public function get($uri, $action) {
        $this->routes['GET'][$uri] = $action;
    }

    public function post($uri, $action) {
        $this->routes['POST'][$uri] = $action;
    }

    public function dispatch() {
        $uri = $_SERVER['REQUEST_URI'];
        $script = $_SERVER['SCRIPT_NAME'];

        $base = dirname($script);
        $base = str_replace('\\', '/', $base);

        if (!empty($base) && $base != '/' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
        }

        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = trim($uri, '/');

        if (empty($uri)) {
            $uri = '/';
        }

        $method = $_SERVER['REQUEST_METHOD'];

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route => $action) {
                $pattern = str_replace('/', '\/', $route);
                $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '([^\/]+)', $pattern);
                $pattern = '/^' . $pattern . '$/';

                if (preg_match($pattern, $uri, $matches)) {
                    array_shift($matches);
                    $this->callAction($action, $matches);
                    return;
                }
            }
        }

        // 404
        http_response_code(404);
        $errorFile = __DIR__ . '/../app/views/errors/404.php';
        if (file_exists($errorFile)) {
            require $errorFile;
        } else {
            echo "<h1>404 - Page non trouvée</h1>";
        }
    }

    private function callAction($action, $params = []) {
        $parts = explode('@', $action);
        if (count($parts) !== 2) {
            self::serverError('Invalid route action format');
            return;
        }

        list($controller, $method) = $parts;
        $controllerFile = __DIR__ . '/../app/controllers/' . $controller . '.php';

        if (!file_exists($controllerFile)) {
            self::serverError('Controller not found');
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controller)) {
            self::serverError('Controller class missing');
            return;
        }

        $instance = new $controller();

        if (!method_exists($instance, $method)) {
            self::serverError('Controller method missing');
            return;
        }

        call_user_func_array([$instance, $method], $params);
    }

    private static function serverError($reason) {
        http_response_code(500);
        error_log('[' . date('Y-m-d H:i:s') . '] Router error: ' . $reason);
        if (file_exists(__DIR__ . '/../app/views/errors/500.php')) {
            require __DIR__ . '/../app/views/errors/500.php';
        } else {
            echo "<h1>500 - Erreur interne</h1>";
        }
    }
}
