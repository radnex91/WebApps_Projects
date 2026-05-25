<?php
require_once '../../includes/config.php'; requireLogin(); requirePerm('depenses.create');
$pageTitle='Nouvelle Dépense'; $aid=getUserAgenceId();
$agences=$pdo->query("SELECT id,nom FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
$personnels=$pdo->query("SELECT id,CONCAT(prenom,' ',nom) as nom FROM personnel WHERE actif=1 ORDER BY nom")->fetchAll();
$types=['Salaire','Fournitures','Aménagements','Impôts','Fonctionnements','Avance sur salaire','Téléphone','Dépense Direction','Investissement','Transit','SMS','Location VHL','Reliquat','Loyer Agence','Bon Actionnaire','Autres dépenses'];
if($_SERVER['REQUEST_METHOD']==='POST'){
    $ag_id=(int)($_POST['agence_id']??$aid??0); $emp=(int)($_POST['employe_id']??0)?:null;
    $type=$_POST['type_depense']??'Autres dépenses'; $objet=trim($_POST['objet']??'');
    $montant=(float)($_POST['montant']??0); $date=$_POST['date_depense']??date('Y-m-d');
    $mode=$_POST['mode_paiement']??'Espèces';
    if(!$ag_id||!$objet||$montant<=0){flash('Champs obligatoires manquants.','danger');}
    else{
        $pdo->prepare("INSERT INTO depenses (agence_id,employe_id,type_depense,objet,montant,date_depense,mode_paiement,saisie_par,statut) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$ag_id,$emp,$type,$objet,$montant,$date,$mode,$_SESSION['user_id'],isChef()?'approuve':'en_attente']);
        flash('Dépense enregistrée.'); redirect(BASE_URL.'modules/depenses/');
    }
}
include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Dépenses</a><span class="breadcrumb-sep">/</span>Nouvelle</div>
<div class="card" style="max-width:700px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Nouvelle dépense</h3></div>
  <div class="card-body">
    <form method="POST">
      <div class="form-grid" style="margin-bottom:14px;">
        <div class="fg"><label class="flbl">Agence <span class="freq">*</span></label><select name="agence_id" class="fc" <?=$aid?'disabled':''?> required><option value="">—</option><?php foreach($agences as $a): ?><option value="<?=$a['id']?>" <?=$aid==$a['id']?'selected':''?>><?=h($a['nom'])?></option><?php endforeach; ?></select><?php if($aid): ?><input type="hidden" name="agence_id" value="<?=$aid?>"><?php endif; ?></div>
        <div class="fg"><label class="flbl">Type de dépense</label><select name="type_depense" class="fc"><?php foreach($types as $t): ?><option value="<?=h($t)?>"><?=h($t)?></option><?php endforeach; ?></select></div>
        <div class="fg full"><label class="flbl">Objet / Description <span class="freq">*</span></label><input type="text" name="objet" class="fc" required placeholder="Description détaillée de la dépense..."></div>
        <div class="fg"><label class="flbl">Montant (FCFA) <span class="freq">*</span></label><input type="number" name="montant" class="fc" required min="1" step="100"></div>
        <div class="fg"><label class="flbl">Date</label><input type="date" name="date_depense" class="fc" value="<?=date('Y-m-d')?>"></div>
        <div class="fg"><label class="flbl">Ordonnateur / Bénéficiaire</label><select name="employe_id" class="fc"><option value="">— Sélectionner —</option><?php foreach($personnels as $p): ?><option value="<?=$p['id']?>"><?=h($p['nom'])?></option><?php endforeach; ?></select></div>
        <div class="fg"><label class="flbl">Mode de paiement</label><select name="mode_paiement" class="fc"><option value="Espèces">Espèces</option><option value="Chèque">Chèque</option><option value="Virement">Virement</option><option value="Mobile Money">Mobile Money</option></select></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;"><a href="." class="btn btn-secondary">Annuler</a><button type="submit" class="btn btn-warning btn-lg"><i class="fas fa-save"></i> Enregistrer</button></div>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>