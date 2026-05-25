<?php
// modules/bordereaux/ajouter.php — SAISIE BORDEREAU (module central)
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.create');
$pageTitle = 'Saisir un Bordereau';
$aid = getUserAgenceId();

$vehicules = $pdo->query("SELECT v.*,g.nom as groupe_nom FROM vehicules v LEFT JOIN groupes g ON v.groupe_id=g.id WHERE v.actif=1 ORDER BY v.immatriculation")->fetchAll();
$agences   = $pdo->query("SELECT id,nom,code,ville FROM agences WHERE actif=1 ORDER BY nom")->fetchAll();
$isEdit    = isset($_GET['id']);
$brd       = null;

if ($isEdit && can('bordereaux.edit')) {
    $s=$pdo->prepare("SELECT * FROM bordereaux WHERE id=?"); $s->execute([$_GET['id']]); $brd=$s->fetch();
    if (!$brd) { flash('Bordereau introuvable.','danger'); redirect(BASE_URL.'modules/bordereaux/'); }
    $pageTitle = 'Modifier le bordereau N°'.$brd['num_bordereau'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id         = (int)($_POST['id'] ?? 0);
    $num        = (int)($_POST['num_bordereau'] ?? 0);
    $code       = trim($_POST['code_bordereau'] ?? '');
    $veh_id     = (int)($_POST['vehicule_id'] ?? 0) ?: null;
    $date       = $_POST['date'] ?? date('Y-m-d');
    $ag_dep     = (int)($_POST['agence_depart_id'] ?? 0) ?: null;
    $ag_arr     = (int)($_POST['agence_arrivee_id'] ?? 0) ?: null;
    $nb_pass    = (int)($_POST['nb_passagers'] ?? 0);
    $nb_grat    = (int)($_POST['nb_billets_gratuits'] ?? 0);
    $recette    = (float)($_POST['recette_totale'] ?? 0);
    $carb       = (float)($_POST['carburant'] ?? 0);
    $peage      = (float)($_POST['peage_total'] ?? 0);
    $retenue    = (float)($_POST['retenue_agence'] ?? 0);
    $ration     = (float)($_POST['ration_chauffeur'] ?? 0);
    $autres     = (float)($_POST['autres_depenses'] ?? 0);
    $libelle    = trim($_POST['libelle'] ?? '');

    if (!$num || !$date) {
        flash('N° bordereau et date obligatoires.','danger');
    } else {
        $fields = [$num, $code, $veh_id, $date, $ag_dep, $ag_arr, $nb_pass, $nb_grat, $recette, $carb, $peage, $retenue, $ration, $autres, $libelle];
        if ($id) {
            $pdo->prepare("UPDATE bordereaux SET num_bordereau=?,code_bordereau=?,vehicule_id=?,date=?,agence_depart_id=?,agence_arrivee_id=?,nb_passagers=?,nb_billets_gratuits=?,recette_totale=?,carburant=?,peage_total=?,retenue_agence=?,ration_chauffeur=?,autres_depenses=?,libelle=? WHERE id=?")->execute([...$fields,$id]);
            flash("Bordereau N°$num modifié.");
            logAction($pdo,'modif_bordereau','bordereaux',"BRD #$id N°$num");
        } else {
            $pdo->prepare("INSERT INTO bordereaux (num_bordereau,code_bordereau,vehicule_id,date,agence_depart_id,agence_arrivee_id,nb_passagers,nb_billets_gratuits,recette_totale,carburant,peage_total,retenue_agence,ration_chauffeur,autres_depenses,libelle,saisie_par) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([...$fields,$_SESSION['user_id']]);
            $newId = $pdo->lastInsertId();
            flash("Bordereau N°$num enregistré avec succès.");
            logAction($pdo,'create_bordereau','bordereaux',"BRD N°$num");
            redirect(BASE_URL."modules/bordereaux/imprimer.php?id=$newId");
        }
        redirect(BASE_URL.'modules/bordereaux/');
    }
}

// Prochain numéro suggéré
$lastNum = $pdo->query("SELECT COALESCE(MAX(num_bordereau),0) FROM bordereaux")->fetchColumn();
$nextNum = $lastNum + 1;

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Bordereaux</a><span class="breadcrumb-sep">/</span><?= $isEdit?'Modifier':'Saisir' ?></div>

<div style="display:grid;grid-template-columns:1fr 300px;gap:18px;">
<div class="card">
  <div class="card-header"><h3><i class="fas fa-file-invoice"></i> <?= h($pageTitle) ?></h3></div>
  <div class="card-body">
    <form method="POST" oninput="calcRecetteNette()">
      <input type="hidden" name="id" value="<?= $brd['id']??'' ?>">

      <!-- IDENTIFICATION -->
      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-hashtag"></i> Identification</div>
        <div class="form-grid">
          <div class="fg">
            <label class="flbl">N° Bordereau <span class="freq">*</span></label>
            <input type="number" name="num_bordereau" class="fc" value="<?= $brd['num_bordereau']??$nextNum ?>" required min="1" autofocus>
          </div>
          <div class="fg">
            <label class="flbl">Code bordereau</label>
            <input type="text" name="code_bordereau" class="fc" value="<?= h($brd['code_bordereau']??'') ?>" placeholder="GB-2023-1001">
          </div>
          <div class="fg">
            <label class="flbl">Date du voyage <span class="freq">*</span></label>
            <input type="date" name="date" class="fc" value="<?= h($brd['date']??date('Y-m-d')) ?>" required>
          </div>
        </div>
      </div>

      <!-- VÉHICULE & TRAJET -->
      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-bus"></i> Véhicule & Trajet</div>
        <div class="form-grid">
          <div class="fg">
            <label class="flbl">Immatriculation véhicule</label>
            <select name="vehicule_id" class="fc">
              <option value="">— Sélectionner —</option>
              <?php foreach($vehicules as $v): ?>
              <option value="<?= $v['id'] ?>" <?= ($brd['vehicule_id']??null)==$v['id']?'selected':'' ?>><?= h($v['immatriculation']) ?> (<?= h($v['groupe_nom']??'—') ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Agence de départ</label>
            <select name="agence_depart_id" class="fc">
              <option value="">— Sélectionner —</option>
              <?php foreach($agences as $a): ?>
              <option value="<?= $a['id'] ?>" <?= ($brd['agence_depart_id']??($aid??null))==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="fg">
            <label class="flbl">Agence d'arrivée</label>
            <select name="agence_arrivee_id" class="fc">
              <option value="">— Sélectionner —</option>
              <?php foreach($agences as $a): ?>
              <option value="<?= $a['id'] ?>" <?= ($brd['agence_arrivee_id']??null)==$a['id']?'selected':'' ?>><?= h($a['nom']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- PASSAGERS -->
      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-users"></i> Passagers</div>
        <div class="form-grid">
          <div class="fg">
            <label class="flbl">Nombre de passagers</label>
            <input type="number" name="nb_passagers" id="nb_passagers" class="fc" value="<?= $brd['nb_passagers']??0 ?>" min="0" max="200">
            <div class="fhint">Entre 0 et 70 (ou capacité du véhicule)</div>
          </div>
          <div class="fg">
            <label class="flbl">Billets gratuits</label>
            <input type="number" name="nb_billets_gratuits" class="fc" value="<?= $brd['nb_billets_gratuits']??0 ?>" min="0">
            <div class="fhint">Saisir 0 s'il n'y en a pas</div>
          </div>
        </div>
      </div>

      <!-- FINANCIER -->
      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-coins"></i> Données financières</div>
        <div class="form-grid">
          <div class="fg">
            <label class="flbl">Recette totale (FCFA) <span class="freq">*</span></label>
            <input type="number" name="recette_totale" id="recette_totale" class="fc" value="<?= $brd['recette_totale']??0 ?>" min="0" step="100" oninput="calcRecetteNette()" required>
          </div>
          <div class="fg">
            <label class="flbl">Carburant (FCFA)</label>
            <input type="number" name="carburant" id="carburant" class="fc" value="<?= $brd['carburant']??0 ?>" min="0" step="100" oninput="calcRecetteNette()">
          </div>
          <div class="fg">
            <label class="flbl">Péages (FCFA)</label>
            <input type="number" name="peage_total" id="peage_total" class="fc" value="<?= $brd['peage_total']??0 ?>" min="0" step="100" oninput="calcRecetteNette()">
          </div>
          <div class="fg">
            <label class="flbl">Retenue agence (FCFA)</label>
            <input type="number" name="retenue_agence" id="retenue_agence" class="fc" value="<?= $brd['retenue_agence']??0 ?>" min="0" step="100" oninput="calcRecetteNette()">
          </div>
          <div class="fg">
            <label class="flbl">Ration chauffeur (FCFA)</label>
            <input type="number" name="ration_chauffeur" id="ration_chauffeur" class="fc" value="<?= $brd['ration_chauffeur']??0 ?>" min="0" step="100" oninput="calcRecetteNette()">
          </div>
          <div class="fg">
            <label class="flbl">Autres dépenses (FCFA)</label>
            <input type="number" name="autres_depenses" id="autres_depenses" class="fc" value="<?= $brd['autres_depenses']??0 ?>" min="0" step="100" oninput="calcRecetteNette()">
          </div>
        </div>
      </div>

      <div class="fg" style="margin-bottom:16px;">
        <label class="flbl">Libellé / Observations</label>
        <textarea name="libelle" class="fc" rows="2" placeholder="Notes complémentaires..."><?= h($brd['libelle']??'') ?></textarea>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="." class="btn btn-secondary btn-lg"><i class="fas fa-arrow-left"></i> Annuler</a>
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> <?= $isEdit?'Modifier':'Enregistrer' ?> le bordereau</button>
      </div>
    </form>
  </div>
</div>

<!-- PANNEAU CALCUL -->
<div>
  <div class="card" style="position:sticky;top:20px;">
    <div class="card-header"><h3><i class="fas fa-calculator"></i> Calcul automatique</h3></div>
    <div class="card-body" style="padding:14px;">
      <div class="recette-calc">
        <div class="label">RECETTE NETTE</div>
        <div class="value" id="recette_nette_display"><?= moneyRaw(($brd['recette_nette']??0)) ?> FCFA</div>
        <div style="font-size:10px;opacity:.7;margin-top:4px;">Recette − Carburant − Péages − Retenue − Ration − Autres</div>
      </div>

      <div style="background:var(--bg);border-radius:var(--radius);padding:12px;font-size:12px;">
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed var(--border);"><span>Recette brute :</span><span id="d-recette" style="font-weight:600;"><?= moneyRaw($brd['recette_totale']??0) ?> FCFA</span></div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed var(--border);color:var(--danger);"><span>(−) Carburant :</span><span id="d-carb"><?= moneyRaw($brd['carburant']??0) ?> FCFA</span></div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed var(--border);color:var(--danger);"><span>(−) Péages :</span><span id="d-peage"><?= moneyRaw($brd['peage_total']??0) ?> FCFA</span></div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed var(--border);color:var(--danger);"><span>(−) Retenue :</span><span id="d-retenue"><?= moneyRaw($brd['retenue_agence']??0) ?> FCFA</span></div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px dashed var(--border);color:var(--danger);"><span>(−) Ration chauf. :</span><span id="d-ration"><?= moneyRaw($brd['ration_chauffeur']??0) ?> FCFA</span></div>
        <div style="display:flex;justify-content:space-between;padding:4px 0;color:var(--danger);"><span>(−) Autres :</span><span id="d-autres"><?= moneyRaw($brd['autres_depenses']??0) ?> FCFA</span></div>
      </div>

      <div style="margin-top:12px;font-size:11px;color:var(--text3);text-align:center;">
        Le calcul se met à jour automatiquement lors de la saisie
      </div>
    </div>
  </div>
</div>
</div>

<?php include '../../includes/footer.php'; ?>
