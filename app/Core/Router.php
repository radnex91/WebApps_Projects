<?php
namespace App\Core;
class Router
{
    private static array $routes = [];
    public static function add(string $method, string $path, string $controller, string $action): void
    {
        self::$routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'controller' => $controller,
            'action'     => $action,
        ];
    }
    public static function get(string $path, string $controller, string $action): void
    {
        self::add('GET', $path, $controller, $action);
    }
    public static function post(string $path, string $controller, string $action): void
    {
        self::add('POST', $path, $controller, $action);
    }
    public static function dispatch(string $url, string $method): void
    {
        $method = strtoupper($method);
        $url = parse_url($url, PHP_URL_PATH);
        $url = rtrim($url, '/') ?: '/';
        $url = preg_replace('#^/gestion-support#', '', $url);
        $url = rtrim($url, '/') ?: '/';
        foreach (self::$routes as $route) {
            $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if ($route['method'] === $method && preg_match($pattern, $url, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $controllerClass = 'App\\Controllers\\' . $route['controller'];
                if (!class_exists($controllerClass)) {
                    throw new \RuntimeException("Controller not found: {$controllerClass}");
                }
                $controller = new $controllerClass();
                $action = $route['action'];
                if (!method_exists($controller, $action)) {
                    throw new \RuntimeException("Action not found: {$controllerClass}::{$action}");
                }
                call_user_func_array([$controller, $action], $params);
                return;
            }
        }
        http_response_code(404);
        echo "404 - Page not found";
    }
}
