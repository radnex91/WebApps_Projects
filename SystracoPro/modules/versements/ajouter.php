<?php
// modules/versements/ajouter.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('versements.create');
$pageTitle = 'Nouveau Versement';
$aid = getUserAgenceId();
$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agence_id = (int)($_POST['agence_id'] ?? $aid ?? 0);
    $type      = $_POST['type'] ?? 'banque';
    $montant   = (float)($_POST['montant'] ?? 0);
    $date_v    = $_POST['date_versement'] ?? date('Y-m-d');
    $ref       = trim($_POST['reference'] ?? '');
    $banque    = trim($_POST['banque'] ?? '');
    $compte    = trim($_POST['compte'] ?? '');
    $obs       = trim($_POST['observations'] ?? '');
    $auto_conf = isset($_POST['auto_confirme']) && isChefAgence();

    if (!$montant || !$agence_id) {
        flash('Montant et agence obligatoires.','danger');
    } else {
        $statut = $auto_conf ? 'confirme' : 'en_attente';
        $num = genNumero($pdo,'versements','numero','VER');
        $pdo->prepare("INSERT INTO versements (numero,agence_id,type,montant,date_versement,reference,banque,compte,statut,saisi_par,observations) VALUES (?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$num,$agence_id,$type,$montant,$date_v,$ref,$banque,$compte,$statut,$_SESSION['user_id'],$obs]);
        if ($auto_conf) $pdo->prepare("UPDATE versements SET confirme_par=?,date_confirmation=NOW() WHERE numero=?")->execute([$_SESSION['user_id'],$num]);
        logAction($pdo,'create_versement','versements',"$num — ".money($montant)." via $type");
        flash("Versement $num enregistré.".($statut==='en_attente'?' En attente de confirmation.':''));
        redirect(BASE_URL.'modules/versements/index.php');
    }
}
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Versements</a><span class="breadcrumb-sep">/</span>Nouveau</div>
<div class="card" style="max-width:680px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-university"></i> Nouveau versement bancaire / Mobile Money</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-grid" style="margin-bottom:14px;">
        <div class="fg">
          <label class="flbl">Agence <span class="freq">*</span></label>
          <select name="agence_id" class="fc" <?= $aid?'disabled':'' ?>>
            <?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= ($aid==$a['id'])?'selected':'' ?>><?= sanitize($a['nom']) ?></option><?php endforeach; ?>
          </select>
          <?php if($aid): ?><input type="hidden" name="agence_id" value="<?= $aid ?>"><?php endif; ?>
        </div>
        <div class="fg">
          <label class="flbl">Type de versement</label>
          <select name="type" class="fc" onchange="toggleBanqueFields(this.value)">
            <option value="banque">🏦 Versement bancaire</option>
            <option value="om">📱 Orange Money</option>
            <option value="momo">📱 MTN MoMo</option>
            <option value="autre">📝 Autre</option>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Montant (FCFA) <span class="freq">*</span></label>
          <input type="number" name="montant" class="fc" required min="1" step="1000" autofocus>
        </div>
        <div class="fg">
          <label class="flbl">Date du versement</label>
          <input type="date" name="date_versement" class="fc" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="fg" id="field-banque">
          <label class="flbl">Banque / Opérateur</label>
          <input type="text" name="banque" class="fc" placeholder="Afriland, UBA, Orange, MTN...">
        </div>
        <div class="fg" id="field-compte">
          <label class="flbl">N° Compte</label>
          <input type="text" name="compte" class="fc" placeholder="Numéro de compte">
        </div>
        <div class="fg">
          <label class="flbl">N° Référence / Reçu</label>
          <input type="text" name="reference" class="fc" placeholder="N° transaction, reçu...">
        </div>
        <div class="fg full">
          <label class="flbl">Observations</label>
          <textarea name="observations" class="fc" rows="2"></textarea>
        </div>
        <?php if(isChefAgence()): ?>
        <div class="fg full"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;"><input type="checkbox" name="auto_confirme" checked> <span>Confirmer immédiatement</span></label></div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="index.php" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-orange btn-lg"><i class="fas fa-save"></i> Enregistrer le versement</button>
      </div>
    </form>
  </div>
</div>
<script>
function toggleBanqueFields(v){
    const b=document.getElementById('field-banque'),c=document.getElementById('field-compte');
    b.style.display=c.style.display=(v==='banque')?'':'none';
}
</script>
<?php include '../../includes/footer.php'; ?>
