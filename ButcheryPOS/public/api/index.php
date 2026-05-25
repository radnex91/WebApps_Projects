<?php
// ButcheryPOS - AJAX API Router
// All POS real-time operations go through this endpoint

require __DIR__ . '/../../config/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Router;
use App\Core\Permission;
use App\Repositories\BaseRepository;
use App\Models\Product;
use App\Models\Category;
use App\Models\Unit;
use App\Models\Customer;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Payment;
use App\Models\PosSession;
use App\Models\ExpiryAlert;
use App\Models\ScaleReading;
use App\Models\Setting;
use App\Services\ProductService;
use App\Services\StockService;
use App\Services\SaleService;
use App\Services\PaymentService;
use App\Services\PosSessionService;
use App\Services\ExpiryService;
use App\Services\BreakdownService;
use App\Models\Breakdown;
use App\Models\BreakdownItem;

header('Content-Type: application/json; charset=utf-8');

$GLOBALS['appSettings'] = $appSettings;

// Check auth
if (!Auth::check()) {
    Router::jsonError('Unauthorized', 401);
}

// Initialize services
$repo = new BaseRepository($pdo);
$productModel = new Product($repo);
$categoryModel = new Category($repo);
$unitModel = new Unit($repo);
$customerModel = new Customer($repo);
$batchModel = new StockBatch($repo);
$movementModel = new StockMovement($repo);
$saleModel = new Sale($repo);
$saleItemModel = new SaleItem($repo);
$paymentModel = new Payment($repo);
$posSessionModel = new PosSession($repo);
$alertModel = new ExpiryAlert($repo);
$scaleReadingModel = new ScaleReading($repo);
$settingModel = new Setting($repo);

$stockService = new StockService($repo, $batchModel, $movementModel, $productModel);
$paymentService = new PaymentService($repo, $paymentModel, $appSettings);
$saleService = new SaleService($repo, $saleModel, $saleItemModel, $posSessionModel, $stockService, $paymentService);
$posSessionService = new PosSessionService($repo, $posSessionModel);
$productService = new ProductService($repo, $productModel, $categoryModel, $unitModel);
$expiryService = new ExpiryService($repo, $alertModel, $batchModel);
$breakdownModel = new Breakdown($repo);
$breakdownItemModel = new BreakdownItem($repo);
$breakdownService = new BreakdownService($repo, $breakdownModel, $breakdownItemModel, $productModel, $batchModel, $movementModel);

// Get request data
$method = $_SERVER['REQUEST_METHOD'];
$route = $_GET['route'] ?? '';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Simple route dispatcher
$router = new Router();

// ---- Products ----
$router->get('api/products', function () use ($productService) {
    $categoryId = (int)($_GET['category_id'] ?? 0) ?: null;
    $search = $_GET['search'] ?? '';
    if ($search) {
        return $productService->searchProducts($search);
    }
    return $productService->getAllProducts($categoryId);
});

$router->get('api/products/{id}', function ($id) use ($productService) {
    $product = $productService->getProduct((int)$id);
    if (!$product) Router::jsonError('Product not found', 404);
    return $product;
});

// ---- POS Session ----
$router->get('api/pos/session', function () use ($posSessionService) {
    $session = $posSessionService->getCurrentSession(Auth::get('id'));
    return ['session' => $session];
});

$router->post('api/pos/session', function () use ($posSessionService, $input) {
    $result = $posSessionService->openSession(
        Auth::get('id'),
        $input['terminal_name'] ?? 'Terminal-1',
        (float)($input['opening_cash'] ?? 0)
    );
    return $result;
});

$router->put('api/pos/session/{id}/close', function ($id) use ($posSessionService, $input) {
    $success = $posSessionService->closeSession((int)$id, (float)($input['closing_cash'] ?? 0));
    return ['success' => $success];
});

// ---- Sales ----
$router->post('api/sales', function () use ($saleService, $input) {
    $result = $saleService->createSale(
        (int)($input['pos_session_id'] ?? 0),
        $input['items'] ?? [],
        $input['payments'] ?? [],
        (int)($input['customer_id'] ?? 0) ?: null,
        (float)($input['discount_amount'] ?? 0),
        $input['notes'] ?? null
    );
    return $result;
});

$router->get('api/sales/{id}', function ($id) use ($saleService) {
    $sale = $saleService->getSaleForReceipt((int)$id);
    if (!$sale) Router::jsonError('Sale not found', 404);
    return $sale;
});

// ---- Stock ----
$router->get('api/stock/product/{id}', function ($id) use ($stockService) {
    return $stockService->getProductBatches((int)$id);
});

// ---- Customers ----
$router->get('api/customers', function () use ($customerModel) {
    $search = $_GET['search'] ?? '';
    if ($search) {
        return $customerModel->getRepo()->query(
            "SELECT * FROM customers WHERE is_active = 1 AND (name LIKE :q OR phone LIKE :q2) ORDER BY name ASC LIMIT 20",
            ['q' => "%{$search}%", 'q2' => "%{$search}%"]
        );
    }
    return $customerModel->all(['is_active' => 1], 'name ASC');
});

// ---- Expiry ----
$router->get('api/expiry/alerts', function () use ($expiryService) {
    return $expiryService->getActiveAlerts();
});

// ---- Breakdown (Découpe) ----
$router->get('api/breakdown/batches/{productId}', function ($productId) use ($breakdownService) {
    return $breakdownService->getCarcassBatches((int)$productId);
});

$router->get('api/breakdown/carcass-products', function () use ($breakdownService) {
    return $breakdownService->getCarcassProducts();
});

$router->get('api/breakdown/retail-products', function () use ($breakdownService) {
    return $breakdownService->getRetailProducts();
});

$router->get('api/breakdown/{id}', function ($id) use ($breakdownService) {
    $detail = $breakdownService->getBreakdownDetail((int)$id);
    if (!$detail) Router::jsonError('Breakdown not found', 404);
    return $detail;
});

// Dispatch
$result = $router->dispatch($method, $route);

if ($result !== null) {
    Router::json($result);
} else {
    Router::jsonError('Endpoint not found', 404);
}