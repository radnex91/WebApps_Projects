<?php
// ButcheryPOS - Front Controller
// All page requests flow through this file

// Load bootstrap (session, DB, i18n, permissions, settings)
require __DIR__ . '/../config/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Permission;
use App\Repositories\BaseRepository;
use App\Models\Setting;
use App\Models\User;
use App\Models\Role;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\ExpiryAlert;
use App\Models\ScaleReading;
use App\Models\Breakdown;
use App\Models\BreakdownItem;
use App\Services\AuthService;
use App\Services\RbacService;
use App\Services\SettingsService;
use App\Services\I18nService;
use App\Services\ProductService;
use App\Services\StockService;
use App\Services\SaleService;
use App\Services\PaymentService;
use App\Services\PosSessionService;
use App\Services\ExpiryService;
use App\Services\BreakdownService;

// Make settings globally accessible for services that need them
$GLOBALS['appSettings'] = $appSettings;

// Initialize repository and services
$repo = new BaseRepository($pdo);
$settingModel = new Setting($repo);
$userModel = new User($repo);
$roleModel = new Role($repo);
$productModel = new Product($repo);
$categoryModel = new Category($repo);
$unitModel = new Unit($repo);
$customerModel = new Customer($repo);
$supplierModel = new Supplier($repo);
$batchModel = new StockBatch($repo);
$movementModel = new StockMovement($repo);
$saleModel = new Sale($repo);
$saleItemModel = new SaleItem($repo);
$paymentModel = new Payment($repo);
$posSessionModel = new PosSession($repo);
$alertModel = new ExpiryAlert($repo);
$scaleReadingModel = new ScaleReading($repo);
$breakdownModel = new Breakdown($repo);
$breakdownItemModel = new BreakdownItem($repo);

$authService = new AuthService($repo, $userModel);
$rbacService = new RbacService($repo, $roleModel);
$settingsService = new SettingsService($settingModel);
$i18nService = new I18nService(__DIR__ . '/../config/lang/');
$productService = new ProductService($repo, $productModel, $categoryModel, $unitModel);
$stockService = new StockService($repo, $batchModel, $movementModel, $productModel);
$paymentService = new PaymentService($repo, $paymentModel, $appSettings);
$saleService = new SaleService($repo, $saleModel, $saleItemModel, $posSessionModel, $stockService, $paymentService);
$posSessionService = new PosSessionService($repo, $posSessionModel);
$expiryService = new ExpiryService($repo, $alertModel, $batchModel);
$breakdownService = new BreakdownService($repo, $breakdownModel, $breakdownItemModel, $productModel, $batchModel, $movementModel);

// ============================================
// Handle POST Actions
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? $_GET['action'] ?? null;

    // CSRF validation for all POST requests (except API)
    if ($action !== null && $action !== 'api') {
        if (!Csrf::validate($_POST['_csrf'] ?? '')) {
            flash('danger', t('error_occurred'));
            redirect($_SERVER['HTTP_REFERER'] ?? url('?page=dashboard'));
        }
    }

    switch ($action) {
        case 'login':
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $result = $authService->attempt($username, $password);
            if ($result['success']) {
                redirect(url('?page=dashboard'));
            } else {
                $loginError = $result['message'];
            }
            break;

        case 'logout':
            $authService->logout();
            redirect(url('?page=login'));
            break;

        case 'set_language':
            $newLang = ($_POST['lang'] ?? 'fr');
            if (in_array($newLang, ['en', 'fr'])) {
                $authService->switchLanguage($newLang);
            }
            $redirect = $_POST['redirect'] ?? url('?page=dashboard');
            redirect($redirect);
            break;

        // ---- Products: save_product (create or update) ----
        case 'save_product':
            $productId = (int)($_POST['product_id'] ?? 0);
            $data = [
                'name' => $_POST['name'] ?? '',
                'sku' => $_POST['sku'] ?: null,
                'category_id' => (int)($_POST['category_id'] ?? 0) ?: null,
                'unit_id' => (int)($_POST['unit_id'] ?? 0),
                'cost_price' => (float)($_POST['cost_price'] ?? 0),
                'sale_price' => (float)($_POST['sale_price'] ?? 0),
                'reorder_level' => (float)($_POST['reorder_level'] ?? 0),
                'track_expiry' => (int)(isset($_POST['track_expiry']) && $_POST['track_expiry'] == 1),
                'is_carcass' => (int)(isset($_POST['is_carcass']) && $_POST['is_carcass'] == 1),
                'default_shelf_life_days' => $_POST['default_shelf_life_days'] ?: null,
            ];
            if ($productId > 0) {
                if (Permission::currentUserCan('products', 'can_edit')) {
                    $productService->updateProduct($productId, $data);
                    flash('success', t('update_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            } else {
                if (Permission::currentUserCan('products', 'can_create')) {
                    $data['is_active'] = 1;
                    $productService->createProduct($data);
                    flash('success', t('save_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            }
            redirect(url('?page=products'));
            break;

        case 'delete_product':
            if (Permission::currentUserCan('products', 'can_delete')) {
                $id = (int)($_POST['product_id'] ?? $_POST['id'] ?? 0);
                $productService->deleteProduct($id);
                flash('success', t('delete_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=products'));
            break;

        // ---- Categories: save_category (create or update) ----
        case 'save_category':
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?: null,
                'sort_order' => (int)($_POST['sort_order'] ?? 0),
            ];
            if ($categoryId > 0) {
                if (Permission::currentUserCan('categories', 'can_edit')) {
                    $productService->updateCategory($categoryId, $data);
                    flash('success', t('update_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            } else {
                if (Permission::currentUserCan('categories', 'can_create')) {
                    $data['is_active'] = 1;
                    $productService->createCategory($data);
                    flash('success', t('save_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            }
            redirect(url('?page=categories'));
            break;

        case 'delete_category':
            if (Permission::currentUserCan('categories', 'can_delete')) {
                $id = (int)($_POST['category_id'] ?? $_POST['id'] ?? 0);
                $productService->deleteCategory($id);
                flash('success', t('delete_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=categories'));
            break;

        // ---- Stock ----
        case 'receive_stock':
            if (Permission::currentUserCan('stock', 'can_create')) {
                try {
                    $stockService->receiveStock(
                        (int)($_POST['product_id'] ?? 0),
                        (float)($_POST['quantity'] ?? 0),
                        (float)($_POST['unit_cost'] ?? 0),
                        $_POST['expiry_date'] ?: null,
                        (int)($_POST['supplier_id'] ?? 0) ?: null,
                        $_POST['batch_reference'] ?: null
                    );
                    flash('success', t('save_success'));
                } catch (\Exception $e) {
                    flash('danger', $e->getMessage());
                }
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=stock'));
            break;

        case 'adjust_stock':
            if (Permission::currentUserCan('stock', 'can_edit')) {
                try {
                    $stockService->adjustStock(
                        (int)($_POST['product_id'] ?? 0),
                        (float)($_POST['quantity'] ?? 0),
                        $_POST['adjustment_type'] ?? 'adjustment',
                        $_POST['reason'] ?? '',
                        Auth::get('id')
                    );
                    flash('success', t('save_success'));
                } catch (\Exception $e) {
                    flash('danger', $e->getMessage());
                }
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=stock'));
            break;

        // ---- Customers: save_customer (create or update) ----
        case 'save_customer':
            $customerId = (int)($_POST['id'] ?? 0);
            $data = [
                'name' => $_POST['name'] ?? '',
                'phone' => $_POST['phone'] ?: null,
                'email' => $_POST['email'] ?: null,
                'address' => $_POST['address'] ?: null,
                'is_active' => (int)(isset($_POST['is_active'])),
            ];
            if ($customerId > 0) {
                if (Permission::currentUserCan('customers', 'can_edit')) {
                    $customerModel->update($customerId, $data);
                    flash('success', t('update_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            } else {
                if (Permission::currentUserCan('customers', 'can_create')) {
                    $data['is_active'] = 1;
                    $customerModel->create($data);
                    flash('success', t('save_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            }
            redirect(url('?page=customers'));
            break;

        case 'delete_customer':
            if (Permission::currentUserCan('customers', 'can_delete')) {
                $id = (int)($_POST['id'] ?? 0);
                $customerModel->delete($id);
                flash('success', t('delete_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=customers'));
            break;

        // ---- Suppliers: save_supplier (create or update) ----
        case 'save_supplier':
            $supplierId = (int)($_POST['id'] ?? 0);
            $data = [
                'name' => $_POST['name'] ?? '',
                'phone' => $_POST['phone'] ?: null,
                'email' => $_POST['email'] ?: null,
                'address' => $_POST['address'] ?: null,
                'is_active' => (int)(isset($_POST['is_active'])),
            ];
            if ($supplierId > 0) {
                if (Permission::currentUserCan('suppliers', 'can_edit')) {
                    $supplierModel->update($supplierId, $data);
                    flash('success', t('update_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            } else {
                if (Permission::currentUserCan('suppliers', 'can_create')) {
                    $data['is_active'] = 1;
                    $supplierModel->create($data);
                    flash('success', t('save_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            }
            redirect(url('?page=suppliers'));
            break;

        case 'delete_supplier':
            if (Permission::currentUserCan('suppliers', 'can_delete')) {
                $id = (int)($_POST['id'] ?? 0);
                $supplierModel->delete($id);
                flash('success', t('delete_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=suppliers'));
            break;

        // ---- Users ----
        case 'create_user':
            if (Permission::currentUserCan('users', 'can_create')) {
                $data = [
                    'full_name' => $_POST['full_name'] ?? '',
                    'username' => $_POST['username'] ?? '',
                    'email' => $_POST['email'] ?: null,
                    'password' => $_POST['password'] ?? '',
                    'role_id' => (int)($_POST['role_id'] ?? 0),
                    'preferred_language' => $_POST['preferred_language'] ?? 'fr',
                    'phone' => $_POST['phone'] ?: null,
                    'is_active' => (int)(isset($_POST['is_active'])),
                ];
                $authService->createUser($data);
                flash('success', t('save_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=users'));
            break;

        case 'update_user':
            if (Permission::currentUserCan('users', 'can_edit')) {
                $id = (int)($_POST['id'] ?? 0);
                $data = [
                    'full_name' => $_POST['full_name'] ?? '',
                    'email' => $_POST['email'] ?: null,
                    'role_id' => (int)($_POST['role_id'] ?? 0),
                    'preferred_language' => $_POST['preferred_language'] ?? 'fr',
                    'phone' => $_POST['phone'] ?: null,
                    'is_active' => (int)(isset($_POST['is_active'])),
                ];
                if (!empty($_POST['password'])) {
                    $data['password'] = password_hash($_POST['password'], PASSWORD_BCRYPT);
                }
                $userModel->update($id, $data);
                flash('success', t('update_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=users'));
            break;

        case 'delete_user':
            if (Permission::currentUserCan('users', 'can_delete')) {
                $id = (int)($_POST['id'] ?? 0);
                if ($id !== Auth::get('id')) {
                    $userModel->delete($id);
                    flash('success', t('delete_success'));
                } else {
                    flash('danger', t('error_occurred'));
                }
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=users'));
            break;

        // ---- Roles ----
        case 'create_role':
            if (Permission::currentUserCan('roles', 'can_create')) {
                $data = [
                    'role_name' => $_POST['role_name'] ?? '',
                    'display_name' => $_POST['display_name'] ?? '',
                    'description' => $_POST['description'] ?: null,
                    'is_system_role' => 0,
                ];
                $rbacService->createRole($data);
                flash('success', t('save_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=roles'));
            break;

        case 'delete_role':
            if (Permission::currentUserCan('roles', 'can_delete')) {
                $id = (int)($_POST['id'] ?? 0);
                $rbacService->deleteRole($id);
                flash('success', t('delete_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=roles'));
            break;

        case 'update_permissions':
            if (Permission::currentUserCan('roles', 'can_manage')) {
                $roleId = (int)($_POST['role_id'] ?? 0);
                $permissions = $_POST['permissions'] ?? [];
                $rbacService->updatePermissions($roleId, $permissions);
                flash('success', t('update_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=roles'));
            break;

        // ---- Settings ----
        case 'update_settings':
            if (Permission::currentUserCan('settings', 'can_edit')) {
                $settings = $_POST['settings'] ?? [];
                $settingsService->updateMany($settings);
                flash('success', t('update_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=settings'));
            break;

        // ---- Expiry ----
        case 'generate_expiry_alerts':
            if (Permission::currentUserCan('expiry', 'can_manage')) {
                try {
                    $expiryService->generateAlerts();
                    flash('success', t('save_success'));
                } catch (\Exception $e) {
                    flash('danger', $e->getMessage());
                }
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=expiry'));
            break;

        case 'dismiss_expiry_alert':
            if (Permission::currentUserCan('expiry', 'can_edit')) {
                $expiryService->dismissAlert((int)($_POST['alert_id'] ?? 0));
                flash('success', t('update_success'));
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=expiry'));
            break;

        case 'mark_batch_waste':
            if (Permission::currentUserCan('expiry', 'can_edit')) {
                try {
                    $expiryService->markAsWaste((int)($_POST['batch_id'] ?? 0), Auth::get('id'));
                    flash('success', t('save_success'));
                } catch (\Exception $e) {
                    flash('danger', $e->getMessage());
                }
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=expiry'));
            break;

        // ---- Breakdown (Découpe) ----
        case 'record_breakdown':
            if (Permission::currentUserCan('breakdown', 'can_create')) {
                try {
                    $outputItems = [];
                    $productIds = $_POST['output_product_id'] ?? [];
                    $quantities = $_POST['output_quantity'] ?? [];
                    $byproducts = $_POST['output_is_byproduct'] ?? [];
                    foreach ($productIds as $i => $pid) {
                        if ((int)$pid > 0 && (float)($quantities[$i] ?? 0) > 0) {
                            $outputItems[] = [
                                'product_id' => (int)$pid,
                                'quantity' => (float)$quantities[$i],
                                'is_byproduct' => isset($byproducts[$i]) ? 1 : 0,
                            ];
                        }
                    }
                    $breakdownService->recordBreakdown(
                        (int)($_POST['source_product_id'] ?? 0),
                        (int)($_POST['source_batch_id'] ?? 0),
                        (float)($_POST['source_quantity'] ?? 0),
                        $outputItems,
                        (float)($_POST['waste_weight'] ?? 0),
                        $_POST['notes'] ?: null
                    );
                    flash('success', t('breakdown_recorded'));
                } catch (\Exception $e) {
                    flash('danger', $e->getMessage());
                }
            } else {
                flash('danger', t('error_occurred'));
            }
            redirect(url('?page=breakdown'));
            break;

        default:
            flash('danger', t('error_occurred'));
            redirect(url('?page=dashboard'));
            break;
    }
}

// ============================================
// Route to Page Templates
// ============================================
$page = $_GET['page'] ?? 'dashboard';
$loginError = $loginError ?? null;

// If not logged in, only allow login page
if (!Auth::check() && $page !== 'login') {
    redirect(url('?page=login'));
}

// If logged in, redirect login to dashboard
if (Auth::check() && $page === 'login') {
    redirect(url('?page=dashboard'));
}

// Check page-level permission
if (Auth::check() && $page !== 'login' && $page !== 'dashboard') {
    if (Permission::currentUserCannot($page, 'can_view')) {
        flash('danger', 'Access denied');
        redirect(url('?page=dashboard'));
    }
}

// Map pages to templates
$pageTemplates = [
    'login' => 'pages/login.php',
    'dashboard' => 'pages/dashboard.php',
    'pos' => 'pages/pos.php',
    'products' => 'pages/products.php',
    'categories' => 'pages/categories.php',
    'stock' => 'pages/stock.php',
    'sales' => 'pages/sales-history.php',
    'customers' => 'pages/customers.php',
    'suppliers' => 'pages/suppliers.php',
    'users' => 'pages/users.php',
    'roles' => 'pages/roles.php',
    'settings' => 'pages/settings.php',
    'expiry' => 'pages/expiry-dashboard.php',
    'breakdown' => 'pages/breakdown.php',
];

$templateFile = $pageTemplates[$page] ?? null;

if ($page === 'login') {
    // Login page has its own layout (no sidebar)
    if ($templateFile && is_file(__DIR__ . '/../templates/' . $templateFile)) {
        require __DIR__ . '/../templates/' . $templateFile;
    }
} else {
    // All other pages use the app shell
    $pageTitle = t($page);

    // Make services available to page templates
    $templateVars = compact(
        'repo', 'authService', 'rbacService', 'settingsService', 'productService',
        'stockService', 'saleService', 'paymentService', 'posSessionService',
        'expiryService', 'breakdownService', 'productModel', 'categoryModel', 'unitModel',
        'customerModel', 'supplierModel', 'userModel', 'roleModel',
        'posSessionModel', 'breakdownModel', 'batchModel'
    );
    extract($templateVars);

    ob_start();
    if ($templateFile && is_file(__DIR__ . '/../templates/' . $templateFile)) {
        require __DIR__ . '/../templates/' . $templateFile;
    } else {
        echo '<div class="alert alert-warning">Page not found: ' . e($page) . '</div>';
    }
    $content = ob_get_clean();

    require __DIR__ . '/../templates/app.php';
}