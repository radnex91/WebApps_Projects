<?php
// controllers/caisse_ajax.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
requirePermission('pos');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        $storeId = currentStoreId();
        $caisses = (new Caisse())->getByStore($storeId);
        jsonResponse(true, '', $caisses);
        break;

    case 'select':
        $caisseId = (int)($_GET['caisse_id'] ?? 0);
        if ($caisseId <= 0) {
            jsonResponse(false, 'Caisse invalide', null, 400);
            break;
        }
        $caisse = (new Caisse())->find($caisseId);
        if (!$caisse || !$caisse['is_active'] || (int)$caisse['store_id'] !== currentStoreId()) {
            jsonResponse(false, 'Caisse introuvable ou non autorisée', null, 404);
            break;
        }
        $_SESSION['caisse_id'] = $caisseId;
        jsonResponse(true, 'Caisse sélectionnée : ' . $caisse['name'], [
            'caisse_id' => $caisseId,
            'caisse_name' => $caisse['name']
        ]);
        break;

    case 'current':
        if (currentCaisseId() === 0) {
            jsonResponse(false, 'Aucune caisse sélectionnée', null, 404);
            break;
        }
        $caisse = (new Caisse())->find(currentCaisseId());
        if (!$caisse) {
            unset($_SESSION['caisse_id']);
            jsonResponse(false, 'Caisse supprimée', null, 404);
            break;
        }
        jsonResponse(true, '', $caisse);
        break;

    default:
        jsonResponse(false, 'Action inconnue', null, 400);
}
