<?php
namespace Core;

class Router
{
    private array $routes = [];
    private array $middleware = [];
    private string $prefix = '';

    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function put(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    public function group(string $prefix, callable $callback, array $middleware = []): void
    {
        $prevPrefix = $this->prefix;
        $prevMw = $this->middleware;
        $this->prefix .= $prefix;
        if (!empty($middleware)) {
            $this->middleware = array_merge($this->middleware, $middleware);
        }
        $callback($this);
        $this->prefix = $prevPrefix;
        $this->middleware = $prevMw;
    }

    private function addRoute(string $method, string $path, array $handler, array $middleware): void
    {
        $this->routes[] = [
            'method' => $method, 'path' => $this->prefix . $path,
            'handler' => $handler, 'middleware' => array_merge($this->middleware, $middleware),
        ];
    }

    public function dispatch(string $method, string $uri): void
    {
        $uri = parse_url($uri, PHP_URL_PATH);
        $uri = rtrim($uri, '/') ?: '/';
        $basePath = '/NexRide';
        if (strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath)) ?: '/';
        }

        foreach ($this->routes as $route) {
            $pattern = $this->pathToRegex($route['path']);
            if ($route['method'] !== $method) continue;
            if (preg_match($pattern, $uri, $matches)) {
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) $params[] = $value;
                }
                foreach ($route['middleware'] as $mw) {
                    if (class_exists($mw)) {
                        $inst = new $mw();
                        if (method_exists($inst, 'handle')) $inst->handle();
                    }
                }
                [$controllerClass, $action] = $route['handler'];
                $controller = new $controllerClass();
                $controller->$action(...$params);
                return;
            }
        }

        http_response_code(404);
        $view = new View();
        try {
            $view->render('errors.404', ['message' => 'Page non trouvée']);
        } catch (\Throwable $e) {
            echo '<h1>404 - Page non trouvée</h1>';
        }
    }

    private function pathToRegex(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}