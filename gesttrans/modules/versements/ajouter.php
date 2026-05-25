<?php
require_once '../../includes/config.php'; requireLogin(); requirePerm('versements.create');
$pageTitle='Nouveau Versement'; $aid=getUserAgenceId();
$agences=$pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'){
    $ag_id=(int)($_POST['agence_id']??$aid??0); $ref=trim($_POST['ref_versement']??''); $date=$_POST['date']??date('Y-m-d');
    $vers=(float)($_POST['versement_agence']??0); $dec=(float)($_POST['decaissement_agence']??0); $rec=(float)($_POST['recette_agence']??0); $quit=trim($_POST['quittance']??'');
    if(!$ag_id||$vers<=0){flash('Agence et montant obligatoires.','danger');}
    else{
        $pdo->prepare("INSERT INTO versements (ref_versement,agence_id,date,versement_agence,decaissement_agence,recette_agence,quittance,saisie_par) VALUES (?,?,?,?,?,?,?,?)")->execute([$ref,$ag_id,$date,$vers,$dec,$rec,$quit,$_SESSION['user_id']]);
        logAction($pdo,'create_versement','versements',money($vers)); flash('Versement enregistré.');
        redirect(BASE_URL.'modules/versements/');
    }
}
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Versements</a><span class="breadcrumb-sep">/</span>Nouveau</div>
<div class="card" style="max-width:680px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-university"></i> Nouveau versement bancaire</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-grid" style="margin-bottom:14px;">
        <div class="fg"><label class="flbl">Agence <span class="freq">*</span></label><select name="agence_id" class="fc" <?= $aid?'disabled':'' ?> required><option value="">—</option><?php foreach($agences as $a): ?><option value="<?= $a['id'] ?>" <?= $aid==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option><?php endforeach; ?></select><?php if($aid): ?><input type="hidden" name="agence_id" value="<?= $aid ?>"><?php endif; ?></div>
        <div class="fg"><label class="flbl">Référence versement</label><input type="text" name="ref_versement" class="fc" placeholder="VER-2023-001"></div>
        <div class="fg"><label class="flbl">Date <span class="freq">*</span></label><input type="date" name="date" class="fc" value="<?= date('Y-m-d') ?>"></div>
        <div class="fg"><label class="flbl">Versement agence (FCFA) <span class="freq">*</span></label><input type="number" name="versement_agence" class="fc" min="0" step="100" required></div>
        <div class="fg"><label class="flbl">Décaissement agence (FCFA)</label><input type="number" name="decaissement_agence" class="fc" min="0" step="100" value="0"></div>
        <div class="fg"><label class="flbl">Recette agence (FCFA)</label><input type="number" name="recette_agence" class="fc" min="0" step="100" value="0"></div>
        <div class="fg"><label class="flbl">N° Quittance</label><input type="text" name="quittance" class="fc" placeholder="QTT-001"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;"><a href="." class="btn btn-secondary">Annuler</a><button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
