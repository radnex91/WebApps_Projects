<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
requireLogin();

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id   = (int)($data['id'] ?? 0);

if (!$id) { json_response(['success'=>false,'message'=>'ID invalide']); }

$mvt = queryOne("SELECT * FROM mouvements WHERE id=?", [$id]);
if (!$mvt) { json_response(['success'=>false,'message'=>'Mouvement introuvable']); }

execute("DELETE FROM mouvements WHERE id=?", [$id]);
calculerCMUPACE($mvt['article_id'], $mvt['date_mouvement']);
updateAnalyseMensuelle($mvt['article_id'], $mvt['annee'], $mvt['mois']);

json_response(['success'=>true]);
