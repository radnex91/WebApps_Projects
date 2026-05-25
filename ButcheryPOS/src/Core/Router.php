<?php
namespace App\Core;

class Router
{
    private array $routes = [];
    private string $prefix = '';

    /**
     * Normalize a route path (ensure leading slash)
     */
    private function normalizePath(string $path): string
    {
        return '/' . trim($path, '/');
    }

    /**
     * Register a GET route
     */
    public function get(string $path, callable $handler): self
    {
        $this->routes['GET'][$this->normalizePath($this->prefix . $path)] = $handler;
        return $this;
    }

    /**
     * Register a POST route
     */
    public function post(string $path, callable $handler): self
    {
        $this->routes['POST'][$this->normalizePath($this->prefix . $path)] = $handler;
        return $this;
    }

    /**
     * Register a PUT route
     */
    public function put(string $path, callable $handler): self
    {
        $this->routes['PUT'][$this->normalizePath($this->prefix . $path)] = $handler;
        return $this;
    }

    /**
     * Register a DELETE route
     */
    public function delete(string $path, callable $handler): self
    {
        $this->routes['DELETE'][$this->normalizePath($this->prefix . $path)] = $handler;
        return $this;
    }

    /**
     * Set route prefix (e.g., 'api')
     */
    public function prefix(string $prefix): self
    {
        $this->prefix = '/' . trim($prefix, '/');
        return $this;
    }

    /**
     * Dispatch a request to the matching route
     */
    public function dispatch(string $method, string $uri): mixed
    {
        $method = strtoupper($method);
        $uri = '/' . trim($uri, '/');

        // Try exact match first
        if (isset($this->routes[$method][$uri])) {
            return call_user_func($this->routes[$method][$uri]);
        }

        // Try pattern matching with parameters
        foreach ($this->routes[$method] ?? [] as $route => $handler) {
            $pattern = $this->buildPattern($route);
            if (preg_match($pattern, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                return call_user_func_array($handler, $params);
            }
        }

        return null;
    }

    /**
     * Convert route with {param} to regex pattern
     */
    private function buildPattern(string $route): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $route);
        return '#^' . $pattern . '$#';
    }

    /**
     * Send JSON response
     */
    public static function json(mixed $data, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send JSON error response
     */
    public static function jsonError(string $message, int $code = 400, array $extra = []): void
    {
        self::json(array_merge(['error' => $message], $extra), $code);
    }
}