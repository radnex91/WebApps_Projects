<?php
// modules/depenses/ajouter.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('depenses.create');
$pageTitle = 'Nouvelle Dépense';
$aid = getUserAgenceId();

$voyages = $pdo->query("SELECT v.id,v.numero,a1.ville as dep,a2.ville as arr FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id WHERE v.statut IN ('programme','en_cours','arrive') ORDER BY v.date_depart DESC LIMIT 30")->fetchAll();
$vehicules = $pdo->query("SELECT * FROM vehicules ORDER BY immatriculation")->fetchAll();
$agences = $pdo->query("SELECT * FROM agences WHERE actif=1 ORDER BY ville")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $agence_id  = (int)($_POST['agence_id'] ?? $aid ?? 0);
    $cat        = $_POST['categorie'] ?? 'autre';
    $libelle    = trim($_POST['libelle'] ?? '');
    $montant    = (float)($_POST['montant'] ?? 0);
    $date_d     = $_POST['date_depense'] ?? date('Y-m-d');
    $voy_id     = (int)($_POST['voyage_id'] ?? 0) ?: null;
    $veh_id     = (int)($_POST['vehicule_id'] ?? 0) ?: null;
    $benef      = trim($_POST['beneficiaire'] ?? '');
    $obs        = trim($_POST['observations'] ?? '');
    $auto_appr  = isset($_POST['auto_approuve']) && isChefAgence();

    if (!$libelle || $montant <= 0 || !$agence_id) {
        flash('Libellé, montant et agence obligatoires.', 'danger');
    } else {
        // Vérifier limite chef guichet
        $limite = (float)getParam('limite_depense', '10000');
        if (isChefGuichet() && !isChefAgence() && $montant > $limite) {
            flash("En tant que Chef de Guichet, vous ne pouvez imputer que des dépenses ≤ ".number_format($limite,0,',',' ')." FCFA.", 'danger');
        } else {
            $statut = ($auto_appr || isChefAgence()) ? 'approuve' : 'en_attente';
            $num = genNumero($pdo, 'depenses', 'numero', 'DEP');
            $pdo->prepare("INSERT INTO depenses (numero,agence_id,categorie,libelle,montant,date_depense,voyage_id,vehicule_id,beneficiaire,observations,statut,impute_par) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")
                ->execute([$num,$agence_id,$cat,$libelle,$montant,$date_d,$voy_id,$veh_id,$benef,$obs,$statut,$_SESSION['user_id']]);
            logAction($pdo,'create_depense','depenses',"$libelle — ".money($montant));
            flash("Dépense $num enregistrée.".($statut==='en_attente'?' En attente d\'approbation.':''));
            redirect(BASE_URL.'modules/depenses/index.php');
        }
    }
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Dépenses</a><span class="breadcrumb-sep">/</span>Nouvelle</div>

<div class="card" style="max-width:720px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Enregistrer une dépense</h3></div>
  <div class="card-body">
    <?php $limite=(float)getParam('limite_depense','10000'); if(isChefGuichet()&&!isChefAgence()): ?>
    <div class="flash flash-info" style="border-radius:var(--radius);margin-bottom:14px;">
      <i class="fas fa-info-circle"></i> En tant que Chef de Guichet, vous pouvez imputer des dépenses jusqu'à <strong><?= number_format($limite,0,',',' ') ?> FCFA</strong>. Au-delà, contactez le Chef d'Agence.
    </div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-grid" style="margin-bottom:14px;">
        <div class="fg">
          <label class="flbl">Agence <span class="freq">*</span></label>
          <select name="agence_id" class="fc" <?= $aid?'disabled':'' ?>>
            <?php foreach($agences as $a): ?>
            <option value="<?= $a['id'] ?>" <?= ($aid==$a['id']||(!$aid&&!$a['id']))?'selected':'' ?>><?= sanitize($a['nom']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if($aid): ?><input type="hidden" name="agence_id" value="<?= $aid ?>"><?php endif; ?>
        </div>
        <div class="fg">
          <label class="flbl">Catégorie</label>
          <select name="categorie" class="fc">
            <?php foreach(['reparation'=>'🔧 Réparation/Panne','carburant'=>'⛽ Carburant','salaire'=>'💰 Salaire','loyer'=>'🏢 Loyer','fourniture'=>'📦 Fourniture','peage'=>'🛣️ Péage','autre'=>'📝 Autre'] as $k=>$v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="fg full">
          <label class="flbl">Libellé de la dépense <span class="freq">*</span></label>
          <input type="text" name="libelle" class="fc" required placeholder="Ex: Réparation crevaison VH-001, Carburant voyage...">
        </div>
        <div class="fg">
          <label class="flbl">Montant (FCFA) <span class="freq">*</span></label>
          <input type="number" name="montant" class="fc" required min="1" step="100">
        </div>
        <div class="fg">
          <label class="flbl">Date</label>
          <input type="date" name="date_depense" class="fc" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="fg">
          <label class="flbl">Voyage concerné</label>
          <select name="voyage_id" class="fc">
            <option value="">— Aucun —</option>
            <?php foreach($voyages as $v): ?><option value="<?= $v['id'] ?>"><?= sanitize($v['numero'].' — '.$v['dep'].'→'.$v['arr']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Véhicule concerné</label>
          <select name="vehicule_id" class="fc">
            <option value="">— Aucun —</option>
            <?php foreach($vehicules as $v): ?><option value="<?= $v['id'] ?>"><?= sanitize($v['immatriculation']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="fg">
          <label class="flbl">Bénéficiaire / Fournisseur</label>
          <input type="text" name="beneficiaire" class="fc" placeholder="Nom du bénéficiaire">
        </div>
        <div class="fg full">
          <label class="flbl">Observations</label>
          <textarea name="observations" class="fc" rows="2"></textarea>
        </div>
        <?php if(isChefAgence()): ?>
        <div class="fg full">
          <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
            <input type="checkbox" name="auto_approuve" checked> <span>Approuver immédiatement</span>
          </label>
        </div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="index.php" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-warning btn-lg"><i class="fas fa-save"></i> Enregistrer la dépense</button>
      </div>
    </form>
  </div>
</div>
<?php include '../../includes/footer.php'; ?>
