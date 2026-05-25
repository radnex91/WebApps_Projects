<?php
class Router {
    private $routes = [];
    
    public function get($path, $handler) {
        $this->routes['GET'][$path] = $handler;
    }
    
    public function post($path, $handler) {
        $this->routes['POST'][$path] = $handler;
    }
    
    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = str_replace('/systraco', '', $uri);
        $uri = $uri ?: '/';
        
        if (isset($this->routes[$method][$uri])) {
            $handler = $this->routes[$method][$uri];
            $this->callHandler($handler);
        } else {
            foreach ($this->routes[$method] as $route => $handler) {
                if (strpos($route, ':') !== false) {
                    $pattern = $this->convertToRegex($route);
                    if (preg_match($pattern, $uri, $matches)) {
                        $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                        $this->callHandler($handler, $params);
                        return;
                    }
                }
            }
            http_response_code(404);
            echo "Page non trouvée";
        }
    }
    
    private function convertToRegex($route) {
        $route = preg_replace('/\//', '\\/', $route);
        $route = preg_replace('/:(\w+)/', '(?P<$1>[^\/]+)', $route);
        return '/^' . $route . '$/';
    }
    
    private function callHandler($handler, $params = []) {
        if (is_callable($handler)) {
            $handler(...array_values($params));
        } elseif (is_string($handler)) {
            $parts = explode('@', $handler);
            $controllerName = $parts[0];
            $methodName = $parts[1];
            
            require_once ROOT_PATH . '/app/controllers/Controller.php';
            require_once ROOT_PATH . '/app/controllers/AuthController.php';
            require_once ROOT_PATH . '/app/controllers/DashboardController.php';
            require_once ROOT_PATH . '/app/controllers/AgenceController.php';
            require_once ROOT_PATH . '/app/controllers/ItineraireController.php';
            require_once ROOT_PATH . '/app/controllers/OtherControllers.php';
            
            $controllerFile = ROOT_PATH . '/app/controllers/' . $controllerName . '.php';
            if (file_exists($controllerFile)) {
                require_once $controllerFile;
            }
            $controller = new $controllerName();
            $controller->$methodName(...array_values($params));
        }
    }
}