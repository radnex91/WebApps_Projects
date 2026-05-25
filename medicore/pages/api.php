<?php
// ============================================================
//  MediCore ERP - API REST JSON
//  Usage: GET/POST /api.php/{resource}
//  Auth: Header X-API-Key ou ?api_key=
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');

// Preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

require_once __DIR__ . '/../includes/config.php';

// Verifier API activee
$apiEnabled = setting('api_enabled', '1');
if ($apiEnabled !== '1') {
    http_response_code(503);
    echo json_encode(['error'=>'API desactivee','code'=>503]);
    exit;
}

// Verifier cle API
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? get_str('api_key');
$validKey = setting('api_key', 'mc_api_2026_demo_key');
if (empty($apiKey) || !hash_equals($validKey, $apiKey)) {
    http_response_code(401);
    echo json_encode(['error'=>'Cle API invalide','code'=>401]);
    exit;
}

// Rate limiting basique - 100 requetes/minute
rate_limit('api_'.$apiKey, 100, 60);

// Router simple
$uri = $_SERVER['REQUEST_URI'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($uri, PHP_URL_PATH);

// Extraire la ressource de /api/ressource ou /api.php/ressource
$resource = '';
if (($pos = strpos($path, '/api/')) !== false) {
    $resource = substr($path, $pos + 5);
} elseif (($pos = strpos($path, 'api.php/')) !== false) {
    $resource = substr($path, $pos + 8);
}
$resource = trim($resource, '/');

// Router
$parts = explode('/', $resource);
$resource = $parts[0] ?? '';
$id = isset($parts[1]) ? (int)$parts[1] : 0;

try {
    switch ($resource) {
        case 'patients':
            if ($method === 'GET' && $id > 0) api_get_patient($id);
            elseif ($method === 'GET') api_list_patients();
            else api_error(405, 'Methode non supportee');
            break;
        case 'analyses':
            if ($method === 'GET' && $id > 0) api_get_analyse($id);
            elseif ($method === 'GET') api_list_analyses();
            else api_error(405, 'Methode non supportee');
            break;
        case 'ordonnances':
            if ($method === 'GET' && $id > 0) api_get_ordonnance($id);
            elseif ($method === 'GET') api_list_ordonnances();
            else api_error(405, 'Methode non supportee');
            break;
        case 'rendez_vous':
            if ($method === 'GET') api_list_rdv();
            else api_error(405, 'Methode non supportee');
            break;
        case 'hospitalisations':
            if ($method === 'GET') api_list_hosp();
            else api_error(405, 'Methode non supportee');
            break;
        case 'stats':
            if ($method === 'GET') api_stats();
            else api_error(405, 'Methode non supportee');
            break;
        case '':
            api_docs();
            break;
        default:
            api_error(404, 'Ressource non trouvee. Consultez /api.php pour la documentation.');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error'=>'Erreur serveur','message'=>$e->getMessage(),'code'=>500]);
}

// ---- Fonctions API ----

function api_list_patients() {
    $limit = min(get_int('limit', 100), 500);
    $offset = get_int('offset');
    $patients = db_select("SELECT id, numero, nom, prenom, date_naissance, sexe, telephone, assurance, date_creation FROM patients ORDER BY id DESC LIMIT $limit OFFSET $offset");
    $total = (int)db_scalar("SELECT COUNT(*) FROM patients");
    api_json(['total'=>$total, 'limit'=>$limit, 'offset'=>$offset, 'data'=>$patients]);
}

function api_get_patient($id) {
    $p = db_row("SELECT * FROM patients WHERE id=?", [$id]);
    if (!$p) api_error(404, 'Patient non trouve');
    api_json($p);
}

function api_list_analyses() {
    $limit = min(get_int('limit', 100), 500);
    $analyses = db_select("SELECT a.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom FROM analyses a JOIN patients p ON p.id=a.patient_id ORDER BY a.date_creation DESC LIMIT $limit");
    api_json(['total'=>count($analyses),'data'=>$analyses]);
}

function api_get_analyse($id) {
    $a = db_row("SELECT a.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom FROM analyses a JOIN patients p ON p.id=a.patient_id WHERE a.id=?", [$id]);
    if (!$a) api_error(404, 'Analyse non trouvee');
    api_json($a);
}

function api_list_ordonnances() {
    $limit = min(get_int('limit', 100), 500);
    $ordos = db_select("SELECT o.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, CONCAT(u.prenom,' ',u.nom) AS medecin_nom FROM ordonnances o JOIN patients p ON p.id=o.patient_id JOIN utilisateurs u ON u.id=o.medecin_id ORDER BY o.date_prescription DESC LIMIT $limit");
    api_json(['total'=>count($ordos),'data'=>$ordos]);
}

function api_get_ordonnance($id) {
    $o = db_row("SELECT o.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, CONCAT(u.prenom,' ',u.nom) AS medecin_nom FROM ordonnances o JOIN patients p ON p.id=o.patient_id JOIN utilisateurs u ON u.id=o.medecin_id WHERE o.id=?", [$id]);
    if (!$o) api_error(404, 'Ordonnance non trouvee');
    $lignes = db_select("SELECT ol.*, m.nom AS med_nom FROM ordonnance_lignes ol JOIN medicaments m ON m.id=ol.medicament_id WHERE ol.ordonnance_id=?", [$id]);
    $o['lignes'] = $lignes;
    api_json($o);
}

function api_list_rdv() {
    $date = get_str('date') ?: date('Y-m-d');
    $rdvs = db_select("SELECT r.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, CONCAT(u.prenom,' ',u.nom) AS medecin_nom FROM rendez_vous r JOIN patients p ON p.id=r.patient_id JOIN utilisateurs u ON u.id=r.medecin_id WHERE DATE(r.date_heure)=? ORDER BY r.date_heure ASC", [$date]);
    api_json(['date'=>$date, 'total'=>count($rdvs), 'data'=>$rdvs]);
}

function api_list_hosp() {
    $limit = min(get_int('limit', 100), 500);
    $hosp = db_select("SELECT h.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom, d.nom AS dept_nom, l.numero AS lit_numero FROM hospitalisations h JOIN patients p ON p.id=h.patient_id LEFT JOIN departements d ON d.id=h.departement_id LEFT JOIN lits l ON l.id=h.lit_id WHERE h.statut='en_cours' ORDER BY h.date_admission DESC LIMIT $limit");
    api_json(['total'=>count($hosp),'data'=>$hosp]);
}

function api_stats() {
    api_json([
        'patients_total'   => (int)db_scalar("SELECT COUNT(*) FROM patients"),
        'hospitalisations_active' => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'"),
        'rdv_aujourd_hui'  => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=CURDATE()"),
        'ca_jour'          => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE DATE(date_vente)=CURDATE() AND statut='paye'"),
        'meds_critiques'   => (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='critique'"),
    ]);
}

function api_docs() {
    $base = APP_URL . '/api.php';
    api_json([
        'api'=>'MediCore ERP REST API v1.0',
        'authentification'=>'Header X-API-Key ou parametre ?api_key=',
        'endpoints'=>[
            "GET $base/patients"      =>'Liste des patients (limit, offset)',
            "GET $base/patients/{id}" =>'Details d\'un patient',
            "GET $base/analyses"      =>'Liste des analyses',
            "GET $base/analyses/{id}" =>'Details d\'une analyse',
            "GET $base/ordonnances"   =>'Liste des ordonnances',
            "GET $base/ordonnances/{id}"=>'Details ordonnance + lignes',
            "GET $base/rendez_vous"   =>'RDV du jour (?date=YYYY-MM-DD)',
            "GET $base/hospitalisations"=>'Hospitalisations en cours',
            "GET $base/stats"          =>'Statistiques globales',
            "GET $base"                =>'Cette documentation',
        ]
    ]);
}

function api_json($data) {
    http_response_code(200);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function api_error($code, $msg) {
    http_response_code($code);
    echo json_encode(['error'=>$msg, 'code'=>$code], JSON_UNESCAPED_UNICODE);
    exit;
}
