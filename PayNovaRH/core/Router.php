<?php
/**
 * Routeur simple avec support de paramètres
 */
class Router
{
    private static $routes = [];
    private static $middleware = [];

    public static function get($path, $handler, $middleware = [])
    {
        self::$routes[] = ['GET', $path, $handler, $middleware];
    }

    public static function post($path, $handler, $middleware = [])
    {
        self::$routes[] = ['POST', $path, $handler, $middleware];
    }

    public static function group($middleware, $callback)
    {
        self::$middleware = array_merge(self::$middleware, (array)$middleware);
        $callback();
        self::$middleware = array_slice(self::$middleware, 0, count(self::$middleware) - count((array)$middleware));
    }

    public static function dispatch($url, $method)
    {
        $url = '/' . trim(parse_url($url, PHP_URL_PATH), '/');

        foreach (self::$routes as $route) {
            list($routeMethod, $routePath, $handler, $middleware) = $route;

            if ($routeMethod !== $method) continue;

            $pattern = self::convertToRegex($routePath);
            if (preg_match($pattern, $url, $matches)) {
                // Extract named parameters
                $params = [];
                if (preg_match_all('/:([^\/]+)/', $routePath, $paramNames)) {
                    array_shift($matches);
                    foreach ($paramNames[1] as $i => $name) {
                        $params[$name] = $matches[$i] ?? null;
                    }
                }

                // Run middleware
                $allMiddleware = array_merge(self::$middleware, (array)$middleware);
                foreach ($allMiddleware as $mw) {
                    $mwClass = $mw . 'Middleware';
                    if (class_exists($mwClass)) {
                        $mwInstance = new $mwClass();
                        $result = $mwInstance->handle();
                        if ($result === false) return;
                    }
                }

                // Call handler
                if (is_callable($handler)) {
                    return $handler($params);
                }

                if (is_string($handler) && strpos($handler, '@') !== false) {
                    list($controllerName, $methodName) = explode('@', $handler);
                    $controllerClass = str_ends_with($controllerName, 'Controller') ? $controllerName : $controllerName . 'Controller';
                    if (class_exists($controllerClass)) {
                        $controller = new $controllerClass();
                        if (method_exists($controller, $methodName)) {
                            return $controller->$methodName($params);
                        }
                    }
                    die("Méthode {$methodName} non trouvée dans {$controllerClass}");
                }

                die("Handler invalide");
            }
        }

        // 404
        http_response_code(404);
        $controller = new Controller();
        $controller->view('errors.404');
    }

    private static function convertToRegex($path)
    {
        $pattern = preg_replace('/\:([^\/]+)/', '(?P<$1>[^\/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}