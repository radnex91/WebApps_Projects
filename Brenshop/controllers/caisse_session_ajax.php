<?php
// controllers/caisse_session_ajax.php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();

$action = $_GET['action'] ?? '';

switch ($action) {

    // ---- OPEN ----
    case 'open':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Méthode non autorisée', null, 405);
            break;
        }
        $caisseId = (int)($_POST['caisse_id'] ?? 0);
        $openingBalance = (float)($_POST['opening_balance'] ?? 0);

        if ($caisseId <= 0) {
            jsonResponse(false, 'Veuillez sélectionner une caisse', null, 400);
            break;
        }
        if ($openingBalance < 0) {
            jsonResponse(false, 'Le fond de caisse ne peut pas être négatif', null, 400);
            break;
        }

        $caisse = (new Caisse())->find($caisseId);
        if (!$caisse || !$caisse['is_active'] || (int)$caisse['store_id'] !== currentStoreId()) {
            jsonResponse(false, 'Caisse introuvable ou non autorisée', null, 404);
            break;
        }

        // Vérifier qu'aucune session n'est déjà ouverte sur cette caisse
        $sessionModel = new CaisseSession();
        $existing = $sessionModel->getActiveSession($caisseId);
        if ($existing) {
            // Si la session appartient au même utilisateur, la retourner (reprise)
            if ((int)$existing['user_id'] === (int)$_SESSION['user_id']) {
                $_SESSION['caisse_id'] = $caisseId;
                jsonResponse(true, 'Session reprise', [
                    'session_id' => $existing['id'],
                    'caisse_name' => $caisse['name']
                ]);
                break;
            }
            // Sinon, quelqu'un d'autre utilise cette caisse
            jsonResponse(false, 'Cette caisse est déjà utilisée par ' . $existing['user_name'], null, 409);
            break;
        }

        // Créer la session
        $sessionId = $sessionModel->open(
            $caisseId,
            currentStoreId(),
            (int)$_SESSION['user_id'],
            $openingBalance
        );

        $_SESSION['caisse_id'] = $caisseId;
        jsonResponse(true, 'Caisse ouverte avec succès', [
            'session_id'  => $sessionId,
            'caisse_name' => $caisse['name']
        ]);
        break;

    // ---- CLOSE ----
    case 'close':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Méthode non autorisée', null, 405);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $caisseId = (int)($data['caisse_id'] ?? currentCaisseId());
        $actualBalance = (float)($data['closing_balance_actual'] ?? 0);
        $notes = sanitize($data['notes'] ?? '');

        $sessionModel = new CaisseSession();
        $activeSession = $sessionModel->getActiveSession($caisseId);

        if (!$activeSession) {
            jsonResponse(false, 'Aucune session ouverte pour cette caisse', null, 404);
            break;
        }

        // Vérifier permissions : le caissier qui a ouvert, ou un admin/manager
        $userRole = $_SESSION['user_role'] ?? '';
        $userId = (int)$_SESSION['user_id'];
        if ((int)$activeSession['user_id'] !== $userId && !in_array($userRole, ['admin', 'manager'])) {
            jsonResponse(false, 'Vous n\'avez pas la permission de fermer cette caisse', null, 403);
            break;
        }

        if ($actualBalance < 0) {
            jsonResponse(false, 'Le montant compté ne peut pas être négatif', null, 400);
            break;
        }

        $sessionModel->close(
            (int)$activeSession['id'],
            $actualBalance,
            $userId,
            $notes ?: null
        );

        // Nettoyer la session
        unset($_SESSION['caisse_id']);
        jsonResponse(true, 'Caisse fermée avec succès', [
            'session_id' => $activeSession['id']
        ]);
        break;

    // ---- STATUS ----
    case 'status':
        $sessionModel = new CaisseSession();
        $activeSession = $sessionModel->getActiveSessionForUser(
            (int)$_SESSION['user_id'],
            currentStoreId()
        );

        if (!$activeSession) {
            jsonResponse(true, '', [
                'has_active_session' => false,
                'session' => null,
                'summary' => null
            ]);
            break;
        }

        $summary = $sessionModel->getSessionSummary((int)$activeSession['id']);
        jsonResponse(true, '', [
            'has_active_session' => true,
            'session' => $activeSession,
            'summary' => $summary
        ]);
        break;

    // ---- SUMMARY ----
    case 'summary':
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if ($sessionId <= 0) {
            jsonResponse(false, 'Session invalide', null, 400);
            break;
        }
        $sessionModel = new CaisseSession();
        $summary = $sessionModel->getSessionSummary($sessionId);
        $session = $sessionModel->find($sessionId);
        jsonResponse(true, '', [
            'session' => $session,
            'summary' => $summary
        ]);
        break;

    // ---- REPORT (Z-reader data) ----
    case 'report':
        $sessionId = (int)($_GET['session_id'] ?? 0);
        if ($sessionId <= 0) {
            jsonResponse(false, 'Session invalide', null, 400);
            break;
        }
        $sessionModel = new CaisseSession();
        $reportData = $sessionModel->getReportData($sessionId);
        jsonResponse(true, '', $reportData);
        break;

    // ---- ADD OPERATION (deposit/withdrawal) ----
    case 'add_operation':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            jsonResponse(false, 'Méthode non autorisée', null, 405);
            break;
        }
        $data = json_decode(file_get_contents('php://input'), true);
        $sessionId = (int)($data['session_id'] ?? 0);
        $type = $data['type'] ?? '';
        $amount = (float)($data['amount'] ?? 0);
        $reason = sanitize(trim($data['reason'] ?? ''));

        if ($sessionId <= 0) {
            jsonResponse(false, 'Session invalide', null, 400);
            break;
        }
        if (!in_array($type, ['deposit', 'withdrawal'])) {
            jsonResponse(false, 'Type d\'opération invalide', null, 400);
            break;
        }
        if ($amount <= 0) {
            jsonResponse(false, 'Le montant doit être supérieur à 0', null, 400);
            break;
        }
        if (empty($reason)) {
            jsonResponse(false, 'Le motif est obligatoire', null, 400);
            break;
        }

        // Vérifier que la session est bien ouverte
        $sessionModel = new CaisseSession();
        $session = $sessionModel->find($sessionId);
        if (!$session || $session['status'] !== 'open') {
            jsonResponse(false, 'La session n\'est pas ouverte', null, 400);
            break;
        }

        $opModel = new CaisseOperation();
        if ($type === 'deposit') {
            $opModel->addDeposit($sessionId, $amount, $reason, (int)$_SESSION['user_id']);
        } else {
            $opModel->addWithdrawal($sessionId, $amount, $reason, (int)$_SESSION['user_id']);
        }

        jsonResponse(true, 'Opération enregistrée');
        break;

    // ---- HISTORY ----
    case 'history':
        $storeId = currentStoreId();
        $date = sanitize($_GET['date'] ?? '');
        $sessionModel = new CaisseSession();
        $history = $sessionModel->getHistory($storeId, $date);
        jsonResponse(true, '', $history);
        break;

    // ---- LIST CAISSES (for opening screen) ----
    case 'list_caisses':
        $storeId = currentStoreId();
        $caisses = (new Caisse())->getByStore($storeId);
        jsonResponse(true, '', $caisses);
        break;

    default:
        jsonResponse(false, 'Action inconnue', null, 400);
}
