<?php
require_once __DIR__ . '/../../includes/functions.php';
requireLogin();
if (!canSeeModule('caisse') || !hasPermission('caisse', 'saisir')) { flash('danger','Accès refusé.'); header('Location: '.BASE_URL.'/modules/caisse/index.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = getDB();
    $code = strtoupper(trim($_POST['code']));
    $libelle = trim($_POST['libelle']);
    $devise = trim($_POST['devise'] ?: 'FCFA');
    $solde = abs((float)($_POST['solde_initial']??0));
    $agenceId = currentUser()['agence_id'] ?? 1;
    try {
        $db->prepare("INSERT INTO caisses (agence_id,code,libelle,devise,solde_initial,solde_actuel) VALUES (?,?,?,?,?,?)")->execute([$agenceId,$code,$libelle,$devise,$solde,$solde]);
        auditLog('create','caisse','caisses');
        flash('success','Caisse créée avec succès.');
    } catch(PDOException $e) {
        flash('danger','Erreur: code caisse déjà utilisé.');
    }
}
header('Location: index.php'); exit;
