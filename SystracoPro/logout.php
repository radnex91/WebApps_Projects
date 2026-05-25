<?php
require_once 'includes/config.php';

// Garde caisse : guichetier avec caisse ouverte
if (isLoggedIn() && isGuichetier()) {
    $ca = caisseOuverte();
    if ($ca) {
        $action = $_POST['logout_action'] ?? $_GET['logout_action'] ?? '';
        if ($action === 'pause') {
            logAction($pdo, 'deconnexion_pause', 'auth', "Caisse {$ca['id']} reste ouverte (pause)");
            session_destroy();
            redirect(BASE_URL . 'login.php');
        } elseif ($action === 'cloture') {
            flash('Veuillez d\'abord cloturer votre caisse avant de vous deconnecter.', 'warning');
            redirect(BASE_URL . 'modules/caisse/index.php');
        }
        $appNom = sanitize(getParam('nom_entreprise', 'TransportManager'));
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Deconnexion — '.$appNom.'</title>
        <link rel="stylesheet" href="'.BASE_URL.'css/app.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <style>.lo-wrap{display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg);}.lo-card{background:#fff;border-radius:var(--radius-lg);padding:40px;text-align:center;max-width:420px;width:90%;box-shadow:var(--shadow-lg);}</style></head><body>
        <div class="lo-wrap"><div class="lo-card">
        <i class="fas fa-cash-register" style="font-size:48px;color:#1e3a8a;margin-bottom:12px;"></i>
        <h3 style="margin-bottom:4px;">Caisse ouverte</h3>
        <p style="color:var(--text3);font-size:13px;margin-bottom:20px;">Votre caisse <strong>'.sanitize($ca['numero']).'</strong> est encore ouverte. Que souhaitez-vous faire ?</p>
        <form method="POST" style="display:flex;gap:10px;justify-content:center;">
        <input type="hidden" name="_csrf" value="'.csrfToken().'">
        <button type="submit" name="logout_action" value="pause" class="btn btn-info btn-sm"><i class="fas fa-coffee"></i> C\'est une pause</button>
        <button type="submit" name="logout_action" value="cloture" class="btn btn-warning btn-sm"><i class="fas fa-lock"></i> Cloture</button>
        </form>
        <p style="font-size:10px;color:var(--text3);margin-top:12px;">Pause = vous revenez plus tard, la caisse reste ouverte.<br>Cloture = fin de journee, vous devez cloturer votre caisse.</p>
        </div></div></body></html>';
        exit;
    }
}

// Deconnexion normale
session_destroy();
redirect(BASE_URL . 'login.php');
