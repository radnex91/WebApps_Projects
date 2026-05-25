<?php
function getCurrency() {
    static $currency = null;
    if ($currency === null) {
        try {
            $database = Database::getInstance();
            $pdo = $database->getConnection();
            $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'currency'");
            $stmt->execute();
            $result = $stmt->fetch();
            $currency = $result ? $result['setting_value'] : 'XAF';
        } catch (Exception $e) {
            $currency = 'XAF';
        }
    }
    return $currency;
}

function formatCurrency($amount) {
    $currency = getCurrency();
    $symbols = [
        'XAF' => ' FCFA',
        'EUR' => ' €',
        'USD' => ' $',
    ];
    $symbol = $symbols[$currency] ?? ' ' . $currency;
    return number_format($amount, 2) . $symbol;
}

function applyTimezone() {
    static $timezoneApplied = false;
    if ($timezoneApplied) return;
    
    $timezone = 'Africa/Douala'; // Default
    try {
        $database = Database::getInstance();
        $pdo = $database->getConnection();
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'timezone'");
        $stmt->execute();
        $result = $stmt->fetch();
        if ($result && !empty($result['setting_value'])) {
            $timezone = $result['setting_value'];
        }
    } catch (Exception $e) {
        // Use default
    }
    
    date_default_timezone_set($timezone);
    $timezoneApplied = true;
}

function logMessage($level, $message, $context = []) {
    $logDir = __DIR__ . '/../storage/logs/';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? ' ' . json_encode($context) : '';
    $logLine = "[$timestamp] [$level] $message$contextStr" . PHP_EOL;
    
    file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);
}

function logError($message, $context = []) {
    logMessage('ERROR', $message, $context);
}

function logInfo($message, $context = []) {
    logMessage('INFO', $message, $context);
}

function logWarning($message, $context = []) {
    logMessage('WARNING', $message, $context);
}
