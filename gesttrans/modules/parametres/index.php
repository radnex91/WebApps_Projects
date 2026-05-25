<?php
// modules/parametres/index.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('parametres.manage');
$pageTitle = 'Paramètres Système';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $k => $v) {
        if (strpos($k,'p_') === 0) {
            $key = substr($k, 2);
            $pdo->prepare("INSERT INTO parametres (cle,valeur) VALUES (?,?) ON DUPLICATE KEY UPDATE valeur=?")->execute([$key, trim($v), trim($v)]);
        }
    }
    logAction($pdo,'update_params','parametres','Paramètres mis à jour');
    flash('Paramètres sauvegardés avec succès.');
    redirect(BASE_URL.'modules/parametres/');
}

$params = $pdo->query("SELECT * FROM parametres ORDER BY cle")->fetchAll();
$pmap = []; foreach($params as $p) $pmap[$p['cle']] = $p['valeur'];

$fields = [
    'Entreprise' => [
        'nom_entreprise'  => ['Nom de l\'entreprise', 'text', 'DANAY EXPRESS SARL'],
        'adresse_siege'   => ['Adresse du siège', 'text', 'B.P. 178 Yagoua, Cameroun'],
        'telephone_siege' => ['Téléphone', 'text', '+237 578 44 01'],
        'email'           => ['Email', 'email', ''],
        'monnaie'         => ['Monnaie', 'text', 'FCFA'],
    ],
    'Interface' => [
        'couleur_primaire'=> ['Couleur principale', 'color', '#1e40af'],
    ],
];

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Paramètres</div>

<form method="POST">
<div class="card" style="max-width:720px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-cog"></i> Paramètres généraux du système</h3></div>
  <div class="card-body">
    <?php foreach($fields as $section => $items): ?>
    <div class="fsec" style="margin-bottom:18px;">
      <div class="fsec-t"><i class="fas fa-folder"></i> <?= h($section) ?></div>
      <div class="form-grid">
        <?php foreach($items as $key => [$label, $type, $default]): $val=$pmap[$key]??$default; ?>
        <div class="fg">
          <label class="flbl"><?= h($label) ?></label>
          <input type="<?= $type ?>" name="p_<?= $key ?>" class="fc" value="<?= h($val) ?>" <?= $type==='color'?'style="height:38px;cursor:pointer;"':'' ?>>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <div class="fsec">
      <div class="fsec-t"><i class="fas fa-info-circle"></i> Informations système</div>
      <table style="width:100%;font-size:12px;border-collapse:collapse;">
        <?php $infos=[['Version', APP_VER],['Base de données', DB_NAME],['Serveur PHP', PHP_VERSION],['Fuseau horaire', date_default_timezone_get()]]; foreach($infos as [$k,$v]): ?>
        <tr><td style="padding:5px 10px;border-bottom:1px solid var(--border);color:var(--text2);width:160px;"><?= $k ?></td><td style="padding:5px 10px;border-bottom:1px solid var(--border);"><code><?= h($v) ?></code></td></tr>
        <?php endforeach; ?>
      </table>
    </div>

    <div style="display:flex;justify-content:flex-end;">
      <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Sauvegarder les paramètres</button>
    </div>
  </div>
</div>
</form>
<?php include '../../includes/footer.php'; ?>
