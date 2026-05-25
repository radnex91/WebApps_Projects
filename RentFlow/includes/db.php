<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'rentflow');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Erreur de connexion: " . $e->getMessage());
}

$params = [];
try {
    $stmt = $pdo->query("SELECT cle, valeur FROM parametres");
    while ($row = $stmt->fetch()) {
        $params[$row['cle']] = $row['valeur'];
    }
    if (empty($params)) {
        $params = [
            'devise' => 'FCFA',
            'devise_position' => 'droite',
            'date_format' => 'd/m/Y',
            'jour_avance' => '5',
            'jour_retard' => '10',
            'nom_entreprise' => 'RentFlow',
            'pays' => 'Afrique Centrale',
            'theme' => 'blue',
            'police' => 'system'
        ];
    }
} catch (Exception $e) {
    $params = [
        'devise' => 'FCFA',
        'devise_position' => 'droite',
        'date_format' => 'd/m/Y',
        'jour_avance' => '5',
        'jour_retard' => '10',
        'nom_entreprise' => 'RentFlow',
        'pays' => 'Afrique Centrale'
    ];
}

function getParam($cle, $defaut = '') {
    global $params;
    return $params[$cle] ?? $defaut;
}

function formatPrix($montant) {
    $devise = getParam('devise', 'FCFA');
    $position = getParam('devise_position', 'droite');
    $montant = number_format($montant, 0, ',', ' ');
    return $position === 'gauche' ? $devise . ' ' . $montant : $montant . ' ' . $devise;
}

function formatDate($date) {
    if (!$date) return '-';
    $format = getParam('date_format', 'd/m/Y');
    return date($format, strtotime($date));
}

function formatDateTime($datetime) {
    if (!$datetime) return '-';
    $format = getParam('date_format', 'd/m/Y') . ' H:i';
    return date($format, strtotime($datetime));
}

function getStatutPaiement($date_paiement) {
    if (!$date_paiement) return 'retard';
    $jour = (int)date('j', strtotime($date_paiement));
    $jour_avance = (int)getParam('jour_avance', 5);
    $jour_retard = (int)getParam('jour_retard', 10);
    if ($jour <= $jour_avance) return 'avance';
    if ($jour <= $jour_retard) return 'normal';
    return 'retard';
}

function getThemeColor() {
    $theme = getParam('theme', 'blue');
    $colors = [
        'blue' => '#2563eb',
        'indigo' => '#6366f1',
        'purple' => '#8b5cf6',
        'pink' => '#ec4899',
        'red' => '#ef4444',
        'orange' => '#f97316',
        'yellow' => '#eab308',
        'green' => '#22c55e',
        'teal' => '#14b8a6',
        'cyan' => '#06b6d4',
        'dark' => '#1e293b'
    ];
    return $colors[$theme] ?? '#2563eb';
}

function getThemeRGB() {
    $theme = getParam('theme', 'blue');
    $rgb = [
        'blue' => '37, 99, 235',
        'indigo' => '99, 102, 241',
        'purple' => '139, 92, 246',
        'pink' => '236, 72, 153',
        'red' => '239, 68, 68',
        'orange' => '249, 115, 22',
        'yellow' => '234, 179, 8',
        'green' => '34, 197, 94',
        'teal' => '20, 184, 166',
        'cyan' => '6, 182, 212',
        'dark' => '30, 41, 59'
    ];
    return $rgb[$theme] ?? '37, 99, 235';
}

function paginate($total, $per_page = 20) {
    $page = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    return ['page' => $page, 'offset' => $offset, 'per_page' => $per_page, 'total' => $total_pages];
}

function renderPagination($total, $per_page, $base_url) {
    $p = paginate($total, $per_page);
    if ($p['total'] <= 1) return '';
    $html = '<nav><ul class="pagination">';
    $query_string = preg_replace('/[?&]p=\d+/', '', $_SERVER['QUERY_STRING']);
    $query_string = $query_string ? $query_string . '&p=' : '?p=';
    
    if ($p['page'] > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . $query_string . ($p['page'] - 1) . '">Preccedent</a></li>';
    }
    
    for ($i = 1; $i <= $p['total']; $i++) {
        $html .= '<li class="page-item ' . ($i == $p['page'] ? 'active' : '') . '"><a class="page-link" href="' . $base_url . $query_string . $i . '">' . $i . '</a></li>';
    }
    
    if ($p['page'] < $p['total']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $base_url . $query_string . ($p['page'] + 1) . '">Suivant</a></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}