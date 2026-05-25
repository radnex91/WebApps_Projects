<?php
// modules/itineraires/escales_ajax.php — Retourne les escales d'un itinéraire en JSON
require_once '../../includes/config.php';
requireLogin();

$itineraire_id = (int)($_GET['itineraire_id'] ?? 0);
header('Content-Type: application/json');

if (!$itineraire_id) { echo json_encode([]); exit; }

$escales = $pdo->prepare("SELECT e.*, a.ville, a.code FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
$escales->execute([$itineraire_id]);
echo json_encode($escales->fetchAll(PDO::FETCH_ASSOC));