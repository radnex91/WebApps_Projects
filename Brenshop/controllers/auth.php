<?php
// controllers/auth.php
require_once __DIR__ . '/../includes/bootstrap.php';

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'logout':
        session_unset();
        session_destroy();
        redirect(BASE_URL . '/index.php');
        break;

    case 'switch_store':
        requirePermission('stores');
        $storeId = (int)($_GET['store_id'] ?? 0);
        if ($storeId > 0) {
            $store = (new Store())->find($storeId);
            if ($store) {
                $_SESSION['store_id'] = $storeId;
                $userModel = new User();
                $warehouses = $userModel->getAccessibleWarehouses((int)$_SESSION['user_id'], $storeId);
                $_SESSION['warehouse_id'] = !empty($warehouses) ? $warehouses[0]['warehouse_id'] : null;
                unset($_SESSION['caisse_id']); // Caisses are store-specific
                setFlash('success', 'Boutique changée : ' . $store['name']);
            }
        }
        redirect(BASE_URL . '/views/dashboard.php');
        break;

    case 'switch_warehouse':
        requireLogin();
        $wid = (int)($_GET['warehouse_id'] ?? 0);
        if ($wid > 0) {
            $_SESSION['warehouse_id'] = $wid;
        }
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/views/dashboard.php');
        break;

    default:
        redirect(BASE_URL . '/index.php');
}