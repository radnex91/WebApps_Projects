<?php
// modules/bordereaux/saisie.php
// Opérateur de saisie — renseigner les infos d'un bordereau reçu physiquement
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.saisie');
$pageTitle = 'Saisie de Bordereau';

$id = (int)($_GET['id'] ?? 0);
$bordereau = null;

if ($id) {
    $stmt = $pdo->prepare("SELECT brd.*,a.nom as agence_nom FROM bordereaux brd LEFT JOIN agences a ON brd.agence_id=a.id WHERE brd.id=?");
    $stmt->execute([$id]);
    $bordereau = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Recherche bordereau par numéro
if (isset($_GET['search_num'])) {
    $num = trim($_GET['search_num'] ?? '');
    if ($num) {
        $stmt = $pdo->prepare("SELECT b.id FROM bordereaux b WHERE b.numero LIKE ?");
        $stmt->execute(["%$num%"]);
        $found = $stmt->fetchColumn();
        if ($found) redirect(BASE_URL."modules/bordereaux/saisie.php?id=$found");
        else flash("Aucun bordereau trouvé avec le numéro : $num", 'warning');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $bordereau) {
	    requireCsrf();
    $carb   = (float)($_POST['montant_carburant'] ?? 0);
    $peage  = (float)($_POST['montant_peage'] ?? 0);
    $avance = (float)($_POST['avance_chauffeur'] ?? 0);
    $autres = (float)($_POST['autres_deductions'] ?? 0);
    $nb_p   = (int)($_POST['nb_passagers'] ?? 0);
    $recette= (float)($_POST['recette_brute'] ?? 0);
    $nette  = $recette - $carb - $peage - $avance - $autres;
    $obs    = trim($_POST['observations'] ?? '');

    $pdo->prepare("UPDATE bordereaux SET montant_carburant=?,montant_peage=?,avance_chauffeur=?,autres_deductions=?,nb_passagers=?,recette_brute=?,recette_nette=?,observations=? WHERE id=?")
        ->execute([$carb,$peage,$avance,$autres,$nb_p,$recette,$nette,$obs,$id]);
    logAction($pdo,'saisie_bordereau','bordereaux',"Bordereau {$bordereau['numero']} mis à jour");
    flash("Bordereau {$bordereau['numero']} mis à jour avec succès.");
    redirect(BASE_URL."modules/bordereaux/saisie.php?id=$id");
}

// Liste bordereaux à saisir
$a_saisir = $pdo->query("SELECT brd.*,a.ville as agence_ville,v.numero as voy_num FROM bordereaux brd LEFT JOIN agences a ON brd.agence_id=a.id LEFT JOIN voyages v ON brd.voyage_id=v.id WHERE brd.statut='genere' ORDER BY brd.created_at DESC LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Bordereaux</a><span class="breadcrumb-sep">/</span>Saisie opérateur</div>

<!-- RECHERCHE -->
<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h3><i class="fas fa-search"></i> Rechercher un bordereau à saisir</h3></div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="search_num" class="fc" placeholder="N° bordereau (ex: BRD-20250414-0001)" style="max-width:340px;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i> Rechercher</button>
    </form>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:18px;">

<!-- FORMULAIRE SAISIE -->
<div>
<?php if ($bordereau): ?>
<div class="card">
  <div class="card-header">
    <h3><i class="fas fa-keyboard"></i> Saisie — <?= sanitize($bordereau['numero']) ?></h3>
    <span class="tag-statut st-<?= $bordereau['statut'] ?>"><?= statutLabel($bordereau['statut']) ?></span>
  </div>
  <div class="card-body">
    <!-- Info actuelles -->
    <div class="fsec" style="margin-bottom:16px;">
      <div class="fsec-t">Informations du bordereau reçu</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:12px;">
        <?php $rows=[['Véhicule',$bordereau['vehicule_immat']??'—'],['Chauffeur',$bordereau['chauffeur_nom']??'—'],['Agence départ',$bordereau['agence_depart']??'—'],['Agence arrivée',$bordereau['agence_arrivee']??'—'],['Date départ',fdatetime($bordereau['date_depart']??'')],['Type',$bordereau['type']]]; foreach($rows as [$k,$v]): ?>
        <div><span style="color:var(--text3);"><?= $k ?> :</span> <strong><?= sanitize($v) ?></strong></div>
        <?php endforeach; ?>
      </div>
    </div>

    <form method="POST">
      <div class="form-grid">
        <div class="fg">
          <label class="flbl">Recette brute (FCFA)</label>
          <input type="number" name="recette_brute" class="fc" value="<?= $bordereau['recette_brute']??0 ?>" min="0" step="100" oninput="calcNet()">
        </div>
        <div class="fg">
          <label class="flbl">Nb passagers</label>
          <input type="number" name="nb_passagers" class="fc" value="<?= $bordereau['nb_passagers']??0 ?>" min="0">
        </div>
        <div class="fg">
          <label class="flbl">Montant carburant (FCFA)</label>
          <input type="number" name="montant_carburant" id="s-carb" class="fc" value="<?= $bordereau['montant_carburant']??0 ?>" min="0" oninput="calcNet()">
        </div>
        <div class="fg">
          <label class="flbl">Montant péages (FCFA)</label>
          <input type="number" name="montant_peage" id="s-peage" class="fc" value="<?= $bordereau['montant_peage']??0 ?>" min="0" oninput="calcNet()">
        </div>
        <div class="fg">
          <label class="flbl">Avance chauffeur (FCFA)</label>
          <input type="number" name="avance_chauffeur" id="s-avance" class="fc" value="<?= $bordereau['avance_chauffeur']??0 ?>" min="0" oninput="calcNet()">
        </div>
        <div class="fg">
          <label class="flbl">Autres déductions (FCFA)</label>
          <input type="number" name="autres_deductions" id="s-autres" class="fc" value="<?= $bordereau['autres_deductions']??0 ?>" min="0" oninput="calcNet()">
        </div>
        <div class="fg full">
          <label class="flbl">Observations / Anomalies constatées</label>
          <textarea name="observations" class="fc" rows="2"><?= sanitize($bordereau['observations']??'') ?></textarea>
        </div>
      </div>

      <div style="background:var(--primary);color:#fff;border-radius:var(--radius);padding:14px 18px;margin:14px 0;display:flex;justify-content:space-between;align-items:center;">
        <span style="font-weight:600;">RECETTE NETTE CALCULÉE :</span>
        <span id="nette-display" style="font-size:20px;font-weight:900;"><?= number_format(($bordereau['recette_nette']??0),0,',',' ') ?> FCFA</span>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="." class="btn btn-secondary">Annuler</a>
        <?php if(can('bordereaux.validate') && $bordereau['statut']==='genere'): ?><a href="valider.php?id=<?= $bordereau['id'] ?>" class="btn btn-primary"><i class="fas fa-check-circle"></i> Valider le bordereau</a><?php endif; ?>
        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-save"></i> Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php else: ?>
<div class="empty card"><i class="fas fa-keyboard"></i><h3 style="margin-top:12px;color:var(--text2);">Sélectionnez un bordereau</h3><p>Recherchez un numéro ou cliquez sur un bordereau dans la liste</p></div>
<?php endif; ?>
</div>

<!-- LISTE À SAISIR -->
<div class="card" style="height:fit-content;">
  <div class="card-header"><h3><i class="fas fa-list"></i> En attente de saisie</h3></div>
  <div class="card-body" style="padding:0;">
    <?php foreach($a_saisir as $brd): ?>
    <a href="?id=<?= $brd['id'] ?>" style="display:block;padding:10px 14px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text);<?= $brd['id']==$id?'background:var(--primary-bg);border-left:3px solid var(--primary);':'' ?>">
      <div style="font-family:monospace;font-size:12px;font-weight:600;color:var(--primary);"><?= sanitize($brd['numero']) ?></div>
      <div style="font-size:12px;"><?= sanitize($brd['agence_ville']??'—') ?> — <?= sanitize($brd['type']) ?></div>
      <div style="font-size:11px;color:var(--text3);"><?= fdatetime($brd['created_at']) ?></div>
      <div style="margin-top:3px;"><span class="tag-statut st-<?= $brd['statut'] ?>" style="font-size:10px;"><?= statutLabel($brd['statut']) ?></span></div>
    </a>
    <?php endforeach; ?>
    <?php if(empty($a_saisir)): ?><div style="padding:20px;text-align:center;color:var(--text3);font-size:12px;"><i class="fas fa-check-circle" style="color:var(--success);"></i> Aucun bordereau en attente</div><?php endif; ?>
  </div>
</div>

</div>

<script>
function calcNet() {
    const recette = parseFloat(document.querySelector('[name=recette_brute]')?.value || 0);
    const carb    = parseFloat(document.getElementById('s-carb')?.value || 0);
    const peage   = parseFloat(document.getElementById('s-peage')?.value || 0);
    const avance  = parseFloat(document.getElementById('s-avance')?.value || 0);
    const autres  = parseFloat(document.getElementById('s-autres')?.value || 0);
    const nette   = recette - carb - peage - avance - autres;
    const el = document.getElementById('nette-display');
    if (el) el.textContent = new Intl.NumberFormat('fr-FR').format(Math.round(nette)) + ' FCFA';
}
</script>
<?php include '../../includes/footer.php'; ?>
