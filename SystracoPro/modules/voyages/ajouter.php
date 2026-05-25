<?php
// modules/voyages/ajouter.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('voyages.create');
$pageTitle = 'Nouveau Voyage';
$aid = getUserAgenceId();

$vehicules  = $pdo->query("SELECT * FROM vehicules WHERE statut='actif' ORDER BY immatriculation")->fetchAll();
$chauffeurs = $pdo->query("SELECT id, nom, prenom FROM personnel WHERE statut='actif' AND fonction='chauffeur' ORDER BY nom")->fetchAll();
// Itineraires actifs
$wA = $aid ? "AND i.agence_depart=".intval($aid) : "";
$itineraires = $pdo->query("SELECT i.*, a1.ville as dep, a2.ville as arr FROM itineraires i JOIN agences a1 ON i.agence_depart=a1.id JOIN agences a2 ON i.agence_arrivee=a2.id WHERE i.actif=1 $wA ORDER BY i.nom")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $itin_id  = (int)($_POST['itineraire_id'] ?? 0);
    $veh_id   = (int)($_POST['vehicule_id'] ?? 0) ?: null;
    $chauf_id = (int)($_POST['chauffeur_id'] ?? 0) ?: null;
    $conv_nom = trim($_POST['convoyeur_nom'] ?? '');
    $chef_piste = trim($_POST['chef_depiste'] ?? '');
    $date_dep = $_POST['date_depart'] ?? '';
    $classe   = $_POST['classe_voyage'] ?? 'cla';
    $places   = (int)($_POST['places_dispo'] ?? 0);
    $carb     = (float)($_POST['montant_carburant'] ?? 0);
    $peage    = (float)($_POST['montant_peage'] ?? 0);

    if (!$itin_id || !$date_dep) {
        flash('Le trajet et la date de depart sont obligatoires.', 'danger');
    } else {
        if ($veh_id && !$places) {
            $vc = $pdo->prepare("SELECT capacite FROM vehicules WHERE id=?"); $vc->execute([$veh_id]);
            $places = (int)$vc->fetchColumn();
        }
        $num = genNumero($pdo, 'voyages', 'numero', getParam('prefix_voyage','VOY'));

        $itInfo = $pdo->prepare("SELECT agence_depart, agence_arrivee FROM itineraires WHERE id=?"); $itInfo->execute([$itin_id]);
        $itData = $itInfo->fetch(PDO::FETCH_ASSOC);
        $agence_id = $aid ?: $itData['agence_depart'];
        $destFind = $pdo->prepare("SELECT id FROM destinations WHERE agence_depart=? AND agence_arrivee=? LIMIT 1");
        $destFind->execute([$itData['agence_depart'], $itData['agence_arrivee']]);
        $dest_id = (int)$destFind->fetchColumn() ?: null;

        $pdo->prepare("INSERT INTO voyages (numero,vehicule_id,chauffeur_id,convoyeur_nom,chef_depiste,destination_id,itineraire_id,agence_id,date_depart,classe_voyage,places_dispo,montant_carburant,montant_peage,created_by) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$num,$veh_id,$chauf_id,$conv_nom,$chef_piste,$dest_id,$itin_id,$agence_id,$date_dep,$classe,$places,$carb,$peage,$_SESSION['user_id']]);
        $vid = $pdo->lastInsertId();
        logAction($pdo,'create_voyage','voyages',"Voyage $num");
        flash("Voyage $num programme avec succes.");
        redirect(BASE_URL."modules/voyages/index.php");
    }
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Voyages</a><span class="breadcrumb-sep">/</span>Nouveau</div>

<div class="card" style="max-width:820px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Programmation de voyage</h3></div>
  <div class="card-body">
    <form method="POST" id="progForm">
      <?= csrfField() ?>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-calendar-alt"></i> Informations du depart</div>
        <div class="form-grid">
          <div class="fg">
            <label class="flbl">Date et heure de depart <span class="freq">*</span></label>
            <input type="datetime-local" name="date_depart" class="fc" value="<?= date('Y-m-d\TH:i') ?>" required>
          </div>
          <div class="fg">
            <label class="flbl">Classe de voyage <span class="freq">*</span></label>
            <select name="classe_voyage" class="fc" required>
              <option value="cla">Classique</option>
              <option value="vip">VIP</option>
              <option value="spc">Super Classique</option>
            </select>
          </div>
          <div class="fg" style="grid-column: 1 / -1;">
            <label class="flbl">Trajet <span class="freq">*</span></label>
            <select name="itineraire_id" id="itin-sel" class="fc" onchange="showEscalePreview(this)" required>
              <option value="">— Selectionner un trajet —</option>
              <?php foreach($itineraires as $i): ?>
              <option value="<?= $i['id'] ?>" data-dep="<?= sanitize($i['dep']) ?>" data-arr="<?= sanitize($i['arr']) ?>" data-dist="<?= $i['distance_km'] ?>" data-duree="<?= $i['duree_minutes'] ?>">
                <?= sanitize($i['dep']) ?> &rarr; <?= sanitize($i['arr']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div id="escale-preview" style="display:none;margin-top:12px;padding:10px;background:var(--bg);border-radius:var(--radius);font-size:13px;"></div>
      </div>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-bus"></i> Vehicule et equipage</div>
        <div class="form-grid">
          <div class="fg">
            <label class="flbl">Immatriculation</label>
            <select name="vehicule_id" class="fc" onchange="setCapacity(this)">
              <option value="">— Selectionner —</option>
              <?php foreach($vehicules as $v): ?>
              <option value="<?= $v['id'] ?>" data-cap="<?= $v['capacite'] ?>"><?= sanitize($v['immatriculation']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Nombre de places</label>
            <input type="number" name="places_dispo" id="places-inp" class="fc" min="1" max="200" placeholder="Auto depuis vehicule" readonly style="background:#f3f4f6;">
          </div>
          <div class="fg">
            <label class="flbl">Nom du chauffeur</label>
            <select name="chauffeur_id" class="fc">
              <option value="">— Selectionner —</option>
              <?php foreach($chauffeurs as $p): ?>
              <option value="<?= $p['id'] ?>"><?= sanitize($p['nom'].' '.$p['prenom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Convoyeur</label>
            <input type="text" name="convoyeur_nom" class="fc" placeholder="Nom du convoyeur">
          </div>
          <div class="fg">
            <label class="flbl">Chef de piste</label>
            <input type="text" name="chef_depiste" class="fc" placeholder="Nom du chef de piste">
          </div>
        </div>
      </div>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-gas-pump"></i> Frais previsionnels</div>
        <div class="form-grid-3">
          <div class="fg">
            <label class="flbl">Carburant alloue (FCFA)</label>
            <input type="number" name="montant_carburant" class="fc" min="0" step="500" placeholder="0">
          </div>
          <div class="fg">
            <label class="flbl">Peages previsionnels (FCFA)</label>
            <input type="number" name="montant_peage" class="fc" min="0" step="100" placeholder="0">
          </div>
        </div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:24px;padding-top:20px;border-top:1px solid var(--border);">
        <a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Annuler</a>
        <button type="submit" class="btn btn-success btn-lg"><i class="fas fa-check"></i> Creer le voyage</button>
      </div>
    </form>
  </div>
</div>

<script>
function setCapacity(sel) {
    const cap = sel.options[sel.selectedIndex]?.dataset?.cap;
    if (cap) document.getElementById('places-inp').value = cap;
}

async function showEscalePreview(sel) {
    const id = sel.value;
    const preview = document.getElementById('escale-preview');
    if (!id) { preview.style.display='none'; return; }
    try {
        const r = await fetch('<?= BASE_URL ?>modules/itineraires/escales_ajax.php?itineraire_id=' + id);
        const data = await r.json();
        if (!data.length) { preview.style.display='none'; return; }
        let html = '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';
        data.forEach((e, i) => {
            const bg = i===0 ? 'var(--success)' : i===data.length-1 ? 'var(--danger)' : 'var(--warning)';
            html += `<span style="background:${bg};color:#fff;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600;">${e.ville}</span>`;
            if (i < data.length-1) html += '<i class="fas fa-long-arrow-alt-right" style="color:var(--text3);font-size:11px;"></i>';
        });
        html += '</div>';
        preview.innerHTML = html;
        preview.style.display = '';
    } catch(e) { preview.style.display='none'; }
}
</script>
<?php include '../../includes/footer.php'; ?>
