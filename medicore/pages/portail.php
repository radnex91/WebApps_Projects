<?php
// ============================================================
//  MediCore ERP - Portail Patient
//  Interface de consultation pour les patients
// ============================================================
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/security.php';

$flash = null;
$patientAuth = null;

// --- POST : Connexion patient ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'login_patient') {
    $numero  = post_str('numero');
    $code    = post_str('code');

    if ($numero && $code) {
        $patient = db_row("SELECT p.*, cp.code_secret, cp.id AS compte_id, cp.statut AS compte_statut, cp.tentatives_echouees
            FROM patients p
            JOIN comptes_patients cp ON cp.patient_id=p.id
            WHERE p.numero=? AND cp.statut='actif'", [$numero]);

        if ($patient && password_verify($code, $patient['code_secret'])) {
            db_exec("UPDATE comptes_patients SET derniere_connexion=NOW(), tentatives_echouees=0 WHERE id=?", [$patient['compte_id']]);
            $_SESSION['patient_id'] = $patient['id'];
            $_SESSION['patient_nom'] = $patient['prenom'].' '.$patient['nom'];
            $_SESSION['patient_num'] = $patient['numero'];
            header('Location: '.APP_URL.'/portail.php'); exit;
        } else {
            $flash = ['red', 'Numero de dossier ou code secret incorrect.'];
            if ($patient) db_exec("UPDATE comptes_patients SET tentatives_echouees=tentatives_echouees+1 WHERE id=?", [$patient['compte_id']]);
        }
    }
}

// --- POST : Deconnexion ---
if (get_str('action') === 'logout') {
    unset($_SESSION['patient_id'], $_SESSION['patient_nom'], $_SESSION['patient_num']);
    header('Location: '.APP_URL.'/portail.php'); exit;
}

$isPatient = isset($_SESSION['patient_id']);

// --- Donnees patient connecte ---
$patientData = null;
$hospitalisations = [];
$ordonnances = [];
$analyses = [];
$rdvs = [];

if ($isPatient) {
    $pid = (int)$_SESSION['patient_id'];
    $patientData = db_row("SELECT * FROM patients WHERE id=?", [$pid]);
    $hospitalisations = db_select("SELECT h.*, d.nom AS dept_nom FROM hospitalisations h LEFT JOIN departements d ON d.id=h.departement_id WHERE h.patient_id=? ORDER BY h.date_admission DESC LIMIT 20", [$pid]);
    $ordonnances = db_select("SELECT o.*, CONCAT(u.prenom,' ',u.nom) AS med_nom FROM ordonnances o LEFT JOIN utilisateurs u ON u.id=o.medecin_id WHERE o.patient_id=? ORDER BY o.date_prescription DESC LIMIT 20", [$pid]);
    $analyses = db_select("SELECT * FROM analyses WHERE patient_id=? ORDER BY date_creation DESC LIMIT 30", [$pid]);
    $rdvs = db_select("SELECT r.*, CONCAT(u.prenom,' ',u.nom) AS med_nom FROM rendez_vous r LEFT JOIN utilisateurs u ON u.id=r.medecin_id WHERE r.patient_id=? ORDER BY r.date_heure DESC LIMIT 20", [$pid]);
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Portail Patient - MediCore</title>
<?php
$_s = get_settings();
$_fontBody = $_s['font_body'] ?? 'DM Sans';
$gf = ['DM Sans'=>'DM+Sans:wght@300;400;500;600;700','Inter'=>'Inter:wght@300;400;500;600;700'];
$gfStr = $gf[$_fontBody] ?? 'Inter:wght@300;400;500;600;700';
?>
<link href="https://fonts.googleapis.com/css2?family=<?= $gfStr ?>&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/main.css">

<?php if (!$isPatient): ?>
<!-- Page de connexion -->
</head><body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg)">
<div id="auth-wrap" style="position:static"><div class="auth-card fade-in">
  <div class="auth-logo"><div class="logo-icon"></div><span><?= h(($_s['app_name']??'MediCore ERP')) ?></span></div>
  <div class="auth-title">Portail Patient</div>
  <div class="auth-sub">Connectez-vous avec votre numéro de dossier et votre code secret</div>
  <?php if ($flash): ?><div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?>"><?= h($flash[1]) ?></div><?php endif; ?>
  <form method="POST">
    <input type="hidden" name="action" value="login_patient">
    <div class="form-group"><label>Numéro de dossier</label><input type="text" name="numero" placeholder="Ex: P-2025-0001" required></div>
    <div class="form-group"><label>Code secret</label><input type="password" name="code" placeholder="Votre code secret" required></div>
    <button type="submit" class="btn-primary" style="width:100%">Accéder à mon dossier</button>
  </form>
  <div class="demo-creds" style="font-size:11px;margin-top:16px">
    <p style="color:var(--text3)">Pour créer un compte patient, utilisez le module Utilisateurs > Portail Patient en back-office.</p>
  </div>
  <div style="text-align:center;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
    <a href="<?= APP_URL ?>/index.php" class="btn btn-ghost" style="font-size:12px">Retour au MediCore ERP</a>
  </div>
</div></div>

<?php else: ?>
<!-- Page dossier patient -->
<style>
.portail-header{background:var(--surface);border-bottom:1px solid var(--border);padding:16px 24px;display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.portail-tab{padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:500;border:1px solid var(--border);background:var(--bg);color:var(--text2);transition:all 0.2s}
.portail-tab.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.portail-content{max-width:1000px;margin:0 auto;padding:24px}
</style>
</head><body style="background:var(--bg);min-height:100vh">

<div class="portail-header">
  <div><strong style="font-size:18px">🏥 Portail Patient</strong>
    <div style="font-size:11px;color:var(--text3)"><?= h(($_s['app_name']??'MediCore ERP')) ?></div></div>
  <div style="margin-left:auto;text-align:right">
    <div style="font-weight:600"><?= h($_SESSION['patient_nom']) ?></div>
    <div style="font-size:11px;color:var(--text3)"><?= h($_SESSION['patient_num']) ?></div>
  </div>
  <a href="<?= APP_URL ?>/index.php" class="btn btn-sm btn-ghost">Retour ERP</a>
	  <a href="?action=logout" class="btn btn-sm btn-ghost" style="color:var(--red)">Déconnexion</a>
</div>

<div class="portail-content">
  <?php if (!empty($patientData)): ?>
  <div class="card" style="margin-bottom:24px;border-left:4px solid var(--accent)">
    <div style="padding:16px 20px">
      <h3 style="margin-bottom:12px">Informations personnelles</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px 24px;font-size:13px">
        <div><span style="color:var(--text3)">Nom :</span> <strong><?= h($patientData['prenom'].' '.$patientData['nom']) ?></strong></div>
        <div><span style="color:var(--text3)">N° Dossier :</span> <strong><?= h($patientData['numero']) ?></strong></div>
        <div><span style="color:var(--text3)">Date naiss. :</span> <?= !empty($patientData['date_naissance'])?fmt_date($patientData['date_naissance']):'N/C' ?></div>
        <div><span style="color:var(--text3)">Sexe :</span> <?= h($patientData['sexe']??'N/C') ?></div>
        <div><span style="color:var(--text3)">Groupe sang. :</span> <?= h($patientData['groupe_sanguin']??'N/C') ?></div>
        <div><span style="color:var(--text3)">Assurance :</span> <?= h($patientData['assurance']??'N/C') ?></div>
        <?php if ($patientData['allergies']): ?><div style="color:var(--red);grid-column:1/-1">⚠️ Allergies : <?= h($patientData['allergies']) ?></div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Analyses de laboratoire -->
  <div class="card" style="margin-bottom:24px"><div class="card-header"><h3>🧪 Resultats d'analyses (<?= count($analyses) ?>)</h3></div>
  <table><thead><tr><th>Date</th><th>Type</th><th>Statut</th><th>Resultat</th></tr></thead><tbody>
  <?php foreach ($analyses as $a): ?>
  <tr><td><?= fmt_date($a['date_creation']) ?></td><td><?= h($a['type_examen']) ?></td>
    <td><span class="badge <?= $a['statut']==='disponible'?'badge-green':'badge-yellow' ?>"><?= h($a['statut']) ?></span></td>
    <td><?= !empty($a['resultat'])?nl2br(h($a['resultat'])):'<span style="color:var(--text3)">En attente</span>' ?></td></tr>
  <?php endforeach; ?>
  <?php if (empty($analyses)): ?><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text3)">Aucune analyse disponible.</td></tr><?php endif; ?>
  </tbody></table></div>

  <!-- Rendez-vous -->
  <div class="card" style="margin-bottom:24px"><div class="card-header"><h3>📅 Rendez-vous (<?= count($rdvs) ?>)</h3></div>
  <table><thead><tr><th>Date</th><th>Medecin</th><th>Type</th><th>Statut</th></tr></thead><tbody>
  <?php foreach ($rdvs as $r): ?>
  <tr><td><?= fmt_date($r['date_heure'], true) ?></td><td><?= h($r['med_nom']??'N/C') ?></td>
    <td><?= h($r['type']??'N/C') ?></td>
    <td><span class="badge <?= $r['statut']==='confirme'?'badge-green':($r['statut']==='annule'?'badge-red':'badge-blue') ?>"><?= h($r['statut']) ?></span></td></tr>
  <?php endforeach; ?>
  <?php if (empty($rdvs)): ?><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text3)">Aucun rendez-vous.</td></tr><?php endif; ?>
  </tbody></table></div>

  <!-- Ordonnances -->
  <div class="card" style="margin-bottom:24px"><div class="card-header"><h3>💊 Ordonnances (<?= count($ordonnances) ?>)</h3></div>
  <table><thead><tr><th>Date</th><th>Medecin</th><th>Statut</th></tr></thead><tbody>
  <?php foreach ($ordonnances as $o): ?>
  <tr><td><?= fmt_date($o['date_prescription']) ?></td><td><?= h($o['med_nom']??'N/C') ?></td>
    <td><span class="badge <?= $o['statut']==='active'?'badge-green':'badge-gray' ?>"><?= h($o['statut']??'N/C') ?></span></td></tr>
  <?php endforeach; ?>
  <?php if (empty($ordonnances)): ?><tr><td colspan="3" style="text-align:center;padding:24px;color:var(--text3)">Aucune ordonnance.</td></tr><?php endif; ?>
  </tbody></table></div>

  <!-- Hospitalisations -->
  <div class="card"><div class="card-header"><h3>🏥 Hospitalisations (<?= count($hospitalisations) ?>)</h3></div>
  <table><thead><tr><th>Admission</th><th>Departement</th><th>Priorite</th><th>Statut</th></tr></thead><tbody>
  <?php foreach ($hospitalisations as $h): ?>
  <tr><td><?= fmt_date($h['date_admission'], true) ?></td><td><?= h($h['dept_nom']??'N/C') ?></td>
    <td><?= h($h['priorite']??'N/C') ?></td>
    <td><span class="badge <?= $h['statut']==='en_cours'?'badge-yellow':($h['statut']==='sorti'?'badge-green':'badge-blue') ?>"><?= h($h['statut']??'N/C') ?></span></td></tr>
  <?php endforeach; ?>
  <?php if (empty($hospitalisations)): ?><tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text3)">Aucune hospitalisation.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
</body></html>
