<?php
require_once __DIR__ . '/../../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Non autorisé']);
    exit;
}
if (!canSeeModule('radnex')) {
    http_response_code(403);
    echo json_encode(['error' => 'Accès refusé']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Méthode non autorisée']);
    exit;
}

// Load config
$configFile = __DIR__ . '/config.php';
$config = file_exists($configFile) ? require $configFile : null;
if (!$config) {
    echo json_encode(['error' => 'RADNEX n\'est pas configuré. Allez dans Administration > IA RADNEX.']);
    exit;
}

// Resolve provider settings
$provider = $config['provider'] ?? ($config['use_ollama'] ?? false ? 'ollama' : 'openrouter');
$apiUrl = '';
$apiKey = '';
$model = '';
$timeout = (int)($config['timeout'] ?? 30);

switch ($provider) {
    case 'ollama':
        $apiUrl = $config['ollama_url'] ?? 'http://localhost:11434/v1/chat/completions';
        $model  = $config['ollama_model'] ?? 'llama3.1:8b';
        break;
    case 'openai':
        $apiUrl = 'https://api.openai.com/v1/chat/completions';
        $apiKey = $config['openai_key'] ?? '';
        $model  = $config['openai_model'] ?? 'gpt-4o-mini';
        break;
    case 'openrouter':
        $apiUrl = 'https://openrouter.ai/api/v1/chat/completions';
        $apiKey = $config['openrouter_key'] ?? '';
        $model  = $config['openrouter_model'] ?? 'openrouter/free';
        break;
    case 'custom':
        $apiUrl = $config['custom_url'] ?? '';
        $apiKey = $config['custom_key'] ?? '';
        $model  = $config['custom_model'] ?? '';
        break;
    default:
        echo json_encode(['error' => 'Fournisseur IA invalide']);
        exit;
}

if (empty($apiUrl)) {
    echo json_encode(['error' => 'URL API non configurée. Allez dans Administration > IA RADNEX.']);
    exit;
}

// Parse input
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) {
    echo json_encode(['error' => 'Entrée invalide']);
    exit;
}

$message = trim($input['message'] ?? '');
if ($message === '') {
    echo json_encode(['error' => 'Message vide']);
    exit;
}

// Build messages
$systemPrompt = $config['system_prompt'] ?? 'Tu es RADNEX, assistant IA spécialisé en finance. Réponds en français.';
$messages = [['role' => 'system', 'content' => $systemPrompt]];

$history = $input['history'] ?? [];
if (is_array($history)) {
    foreach ($history as $h) {
        $messages[] = [
            'role' => $h['role'] ?? 'user',
            'content' => $h['content'] ?? ''
        ];
    }
}
$messages[] = ['role' => 'user', 'content' => $message];

// Build request payload
$payload = json_encode([
    'model' => $model,
    'messages' => $messages,
    'stream' => false
], JSON_UNESCAPED_UNICODE);

// Build headers
$headers = ['Content-Type: application/json'];
if (!empty($apiKey)) {
    $headers[] = 'Authorization: Bearer ' . $apiKey;
}
if ($provider === 'openrouter') {
    $headers[] = 'HTTP-Referer: ' . BASE_URL;
    $headers[] = 'X-Title: BrenFinance RADNEX';
}

// Call API
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_TIMEOUT => $timeout,
    CURLOPT_SSL_VERIFYPEER => true,
]);

$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err = curl_error($ch);
curl_close($ch);

if ($err !== '') {
    $providerName = ['ollama' => 'Ollama', 'openai' => 'OpenAI', 'openrouter' => 'OpenRouter', 'custom' => 'API personnalisée'];
    $name = $providerName[$provider] ?? 'API';
    if ($provider === 'ollama') {
        echo json_encode(['error' => 'Erreur de connexion Ollama : ' . $err . '. Vérifiez qu\'Ollama est lancé.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['error' => 'Erreur de connexion ' . $name . ' : ' . $err], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

$data = json_decode($res, true);

// Check for API errors
if (isset($data['error'])) {
    $errMsg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
    echo json_encode(['error' => 'Erreur API : ' . $errMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($data['choices'][0]['message']['content'])) {
    $reply = $data['choices'][0]['message']['content'];
} elseif (isset($data['message']['content'])) {
    // Native Ollama format
    $reply = $data['message']['content'];
} else {
    $reply = 'Aucune réponse de l\'IA. Réponse brute : ' . mb_substr($res, 0, 200);
}

echo json_encode(['reply' => $reply, 'sources' => []], JSON_UNESCAPED_UNICODE);