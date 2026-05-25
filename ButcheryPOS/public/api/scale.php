<?php
// ButcheryPOS - Scale API Endpoint
// GET: Return latest scale reading (polled by frontend)
// POST: Accept new reading from Python bridge

require __DIR__ . '/../../config/bootstrap.php';

use App\Middleware\ApiAuthMiddleware;

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Return latest scale reading
    $stmt = $pdo->prepare("SELECT * FROM scale_readings ORDER BY captured_at DESC LIMIT 1");
    $stmt->execute();
    $reading = $stmt->fetch();

    if ($reading) {
        echo json_encode([
            'weight_value' => (float)$reading['weight_value'],
            'unit' => $reading['unit'],
            'device_code' => $reading['device_code'],
            'captured_at' => $reading['captured_at'],
        ]);
    } else {
        echo json_encode(['weight_value' => 0, 'unit' => 'kg', 'device_code' => 'none', 'captured_at' => null]);
    }
    exit;
}

if ($method === 'POST') {
    // Accept reading from Python bridge — requires API key
    $apiKey = $appSettings['scale_api_key'] ?? 'CHANGE_ME_SCALE_KEY';

    if (!ApiAuthMiddleware::handle($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $weight = (float)($input['weight_value'] ?? 0);
    $deviceCode = $input['device_code'] ?? 'default-scale';
    $rawPayload = $input['raw_payload'] ?? null;
    $unit = $input['unit'] ?? 'kg';

    if ($weight <= 0) {
        echo json_encode(['error' => 'Invalid weight']);
        exit;
    }

    $stmt = $pdo->prepare(
        "INSERT INTO scale_readings (weight_value, unit, device_code, raw_payload, pos_session_id)
         VALUES (:weight, :unit, :device, :raw, :session_id)"
    );
    $stmt->execute([
        'weight' => $weight,
        'unit' => $unit,
        'device' => $deviceCode,
        'raw' => $rawPayload,
        'session_id' => $_SESSION['pos_session_id'] ?? null,
    ]);

    echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
    exit;
}

// Method not allowed
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);