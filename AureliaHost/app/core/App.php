<?php
class App
{
    private string $controller = 'DashboardController';
    private string $method = 'index';
    private array $params = [];

    public function __construct()
    {
        Session::start();
        $this->registerAutoloader();
        $this->parseUrl();
        $this->dispatch();
    }

    private function registerAutoloader(): void
    {
        spl_autoload_register(function (string $class): void {
            static $classMap = null;

            if ($classMap === null) {
                $classMap = [];
                $modelsDir = APP_DIR . '/models';
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($modelsDir, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                foreach ($iterator as $file) {
                    if ($file->getExtension() === 'php') {
                        $classMap[$file->getBasename('.php')] = $file->getPathname();
                    }
                }
            }

            $pos = strrpos($class, '\\');
            $className = $pos !== false ? substr($class, $pos + 1) : $class;

            if (isset($classMap[$className])) {
                require_once $classMap[$className];
            }
        });
    }

    private function parseUrl(): void
    {
        $url = $_GET['url'] ?? '';
        $url = filter_var(trim($url, '/'), FILTER_SANITIZE_URL);
        if (empty($url)) return;

        $segments = explode('/', $url);
        $count = count($segments);

        if ($count >= 1 && $segments[0] !== '') {
            // Module prefix: hotel, hr, accounting, or auth/dashboard
            $prefix = $segments[0];

            // Map plural/special URL segments to singular controller names
            $controllerAliases = [
                'reservations' => 'Reservation',
                'rooms'        => 'Room',
                'clients'      => 'Client',
                'room-types'   => 'RoomType',
                'services'     => 'Service',
                'employees'    => 'Employee',
                'departments'  => 'Department',
                'leaves'       => 'Leave',
                'invoices'     => 'Invoice',
                'payments'     => 'Payment',
                'expenses'     => 'Expense',
                'taxes'        => 'Tax',
                'reports'      => 'Report',
            ];

            // Map URL prefix to controller namespace
            $moduleMap = [
                'hotel'       => 'hotel',
                'hr'          => 'hr',
                'accounting'  => 'accounting',
                'auth'        => '',
                'dashboard'   => '',
            ];

            if (isset($moduleMap[$prefix])) {
                $module = $moduleMap[$prefix];
                if ($module !== '') {
                    // Module/Controller
                    if ($count >= 2) {
                        $seg = $segments[1];
                        $controllerName = $controllerAliases[$seg] ?? ucfirst($seg);
                        $this->controller = $controllerName . 'Controller';
                        $this->method = $count >= 3 ? $segments[2] : 'index';
                        $this->params = array_slice($segments, min(3, $count));
                    }
                    $this->controller = ($module ? $module . '\\' : '') . $this->controller;
                } else {
                    // Top-level controller (Auth, Dashboard)
                    $this->controller = ucfirst($segments[0]) . 'Controller';
                    $this->method = $count >= 2 ? $segments[1] : 'index';
                    $this->params = array_slice($segments, 2);
                }
            } else {
                // Default: treat as controller/method/params
                $this->controller = ucfirst($segments[0]) . 'Controller';
                $this->method = $count >= 2 ? $segments[1] : 'index';
                $this->params = array_slice($segments, 2);
            }
        }
    }

    private function dispatch(): void
    {
        $controllerClass = $this->controller;
        $controllerFile = APP_DIR . '/controllers/' . str_replace('\\', '/', $controllerClass) . '.php';

        if (!file_exists($controllerFile)) {
            $this->send404("Contrôleur introuvable : $controllerClass");
            return;
        }

        require_once $controllerFile;

        $pos = strrpos($controllerClass, '\\');
        $baseClass = $pos !== false ? substr($controllerClass, $pos + 1) : $controllerClass;

        if (!class_exists($baseClass)) {
            $this->send404("Classe introuvable : $baseClass");
            return;
        }

        $controller = new $baseClass();

        if (!method_exists($controller, $this->method)) {
            $this->send404("Méthode introuvable : {$this->method}");
            return;
        }

        call_user_func_array([$controller, $this->method], $this->params);
    }

    private function send404(string $message = 'Page introuvable'): void
    {
        http_response_code(404);
        if (file_exists(VIEWS_DIR . '/errors/404.php')) {
            require VIEWS_DIR . '/errors/404.php';
        } else {
            echo "<h1>404</h1><p>$message</p>";
        }
    }
}
