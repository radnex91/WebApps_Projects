<?php
$currentPage = 'pharmacie';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

$msg = ''; $msgType = '';

//  CRER médicament
if (can('medicaments.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_med') {
    csrf_verify();
    $v = (new Validator())
        ->required('nom', 'Nom')
        ->int_range('stock_actuel',  0, 9999999, 'Stock actuel')
        ->int_range('stock_minimum', 0, 9999999, 'Seuil minimum');
    if (!$v->passes()) { $msg = $v->first_error(); $msgType = 'red'; }
    else {
        $stock   = post_int('stock_actuel');
        $minimum = post_int('stock_minimum');
        $statut  = $stock <= $minimum * 0.5 ? 'critique' : ($stock <= $minimum ? 'bas' : 'normal');
        db_exec(
            "INSERT INTO medicaments (nom,categorie,dosage,unite,stock_actuel,stock_minimum,fournisseur,prix_unitaire,statut) VALUES (?,?,?,?,?,?,?,?,?)",
            [$v->get('nom'), post_str('categorie'), post_str('dosage'), post_str('unite'),
             $stock, $minimum, post_str('fournisseur'), post_float('prix_unitaire'), $statut]
        );
        logActivity('Médicament ajouté: ' . $v->get('nom'), 'blue', 'medicament');
        header('Location: ' . APP_URL . '/pharmacie.php?tab=stock&ok=1'); exit;
    }
}

//  CRER ordonnance
if (can('ordonnances.create') && $_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'create_ordonnance') {
    csrf_verify();
    $patient_id  = post_int('patient_id');
    $medecin_id  = post_int('medecin_id');
    $med_ids     = $_POST['med_id']    ?? [];
    $dosages     = $_POST['dosage']    ?? [];
    $frequences  = $_POST['frequence'] ?? [];
    $durees      = $_POST['duree']     ?? [];

    if (!$patient_id || !$medecin_id || empty($med_ids)) {
        $msg = 'Patient, médecin et au moins un médicament sont obligatoires.'; $msgType = 'red';
    } else {
        $ord_id = db_exec("INSERT INTO ordonnances (patient_id, medecin_id, statut) VALUES (?,?,'active')", [$patient_id, $medecin_id]);
        foreach ($med_ids as $i => $mid) {
            $mid = (int)$mid;
            if (!$mid) continue;
            db_exec(
                "INSERT INTO ordonnance_lignes (ordonnance_id, medicament_id, dosage, frequence, duree) VALUES (?,?,?,?,?)",
                [$ord_id, $mid, post_str('dosage')  /* reuse */ , $frequences[$i] ?? '', $durees[$i] ?? '']
            );
            // Use indexed values properly
            db_exec(
                "UPDATE ordonnance_lignes SET dosage=?, frequence=?, duree=? WHERE ordonnance_id=? AND medicament_id=? ORDER BY id DESC LIMIT 1",
                [$dosages[$i] ?? '', $frequences[$i] ?? '', $durees[$i] ?? '', $ord_id, $mid]
            );
        }
        logActivity("Ordonnance créée — patient ID $patient_id", 'blue', 'ordonnance', $ord_id);
        $msg = "Ordonnance créée. Vous pouvez l'encaisser directement."; $msgType = 'green';
    }
}

//  DONNES
//  Layout inclus ici  aprs toute logique PHP

//  MODIFIER MDICAMENT 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_medicament' && can('medicaments.create')) {
    csrf_verify();
    $id  = post_int('med_id');
    $nom = trim(post_str('nom'));
    if (!$nom) { $flash = ['red','Nom obligatoire.']; }
    else {
        $cats   = ['antibiotique','analgesique','antihypertenseur','antipaludeen','antidiabetique','vitamines','autre'];
        $cat    = in_whitelist(post_str('categorie'), $cats, 'autre');
        $stock  = max(0, post_int('stock_actuel', 0));
        $stockMin = max(0, post_int('stock_minimum', 0));
        $prix   = max(0, (float)($_POST['prix_unitaire'] ?? 0));
        $statut = $stock <= 0 ? 'critique' : ($stock <= $stockMin ? 'bas' : 'normal');
        db_exec(
            "UPDATE medicaments SET nom=?,categorie=?,dosage=?,unite=?,stock_actuel=?,stock_minimum=?,fournisseur=?,prix_unitaire=?,statut=? WHERE id=?",
            [$nom,$cat,post_str('dosage'),post_str('unite'),$stock,$stockMin,post_str('fournisseur'),$prix,$statut,$id]
        );
        logActivity("Médicament modifié: $nom", 'blue', 'medicament', $id);
        $flash = ['green','Médicament mis à jour.'];
        header('Location: '.APP_URL.'/pharmacie.php?tab=stock&saved=1'); exit;
    }
}

// Charger médicament  modifier
$editMed = null;
if (get_int('edit_med') > 0 && can('medicaments.create')) {
    $editMed = db_row("SELECT * FROM medicaments WHERE id=?", [get_int('edit_med')]);
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('pharmacie');
$onglet = in_whitelist(get_str('tab'), ['stock', 'ordonnances'], 'stock');

$searchMed = get_str('search_med');
$searchFiltre = in_whitelist(get_str('filtre_stock'), ['','normal','bas','critique'], '');
$medWhere = '1=1'; $medParams = [];
if ($searchMed) { $medWhere .= " AND (nom LIKE ? OR categorie LIKE ? OR fournisseur LIKE ?)"; $like="%$searchMed%"; $medParams=[$like,$like,$like]; }
if ($searchFiltre) { $medWhere .= " AND statut=?"; $medParams[]=$searchFiltre; }
$meds = db_select("SELECT * FROM medicaments WHERE $medWhere ORDER BY statut DESC, nom ASC", $medParams);

// Ordonnances actives + info ticket si déjà encaissée
$ordonnances = db_select(
    "SELECT o.*,
            CONCAT(p.prenom,' ',p.nom) AS patient_nom, p.numero AS patient_num,
            CONCAT(u.prenom,' ',u.nom) AS medecin_nom,
            -- Ticket lié (si encaissée)
            cv.numero_ticket           AS ticket_numero,
            cv.montant_total           AS ticket_total,
            cv.statut                  AS ticket_statut,
            cv.id                      AS ticket_id,
            cv.date_vente              AS ticket_date
     FROM ordonnances o
     JOIN patients p    ON p.id = o.patient_id
     JOIN utilisateurs u ON u.id = o.medecin_id
     LEFT JOIN caisse_ventes cv ON cv.ordonnance_id = o.id
     ORDER BY o.date_prescription DESC
     LIMIT 100"
);

// Lignes de chaque ordonnance
foreach ($ordonnances as &$ord) {
    $ord['lignes'] = db_select(
        "SELECT ol.*, m.nom AS med_nom, m.dosage AS med_dosage, m.prix_unitaire, m.stock_actuel, m.unite
         FROM ordonnance_lignes ol
         JOIN medicaments m ON m.id = ol.medicament_id
         WHERE ol.ordonnance_id = ?", [$ord['id']]
    );
    $ord['total_estime'] = array_sum(array_column($ord['lignes'], 'prix_unitaire'));
}
unset($ord);

$patients = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, numero FROM patients ORDER BY nom ASC");
$medecins = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom ASC");

// Compteurs
$critiques  = (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='critique'");
$bas        = (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='bas'");
$total_meds = (int)db_scalar("SELECT COUNT(*) FROM medicaments");
$valeur_stock = (float)db_scalar("SELECT COALESCE(SUM(stock_actuel*prix_unitaire),0) FROM medicaments");
$nb_ord_actives   = (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'");
$nb_ord_terminees = (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='terminee'");

$statutBadge = ['normal'=>'badge-green','bas'=>'badge-yellow','critique'=>'badge-red','expire'=>'badge-red'];
?>

<?php if (get_str('ok')): ?><div class="alert alert-green alert-auto"> Médicament ajouté avec succès.</div><?php endif; ?>
<?php if ($msg): ?><div class="alert alert-<?= $msgType==='green'?'green':'red' ?> alert-auto"><?= $msgType==='green'?'':'' ?> <?= h($msg) ?></div><?php endif; ?>

<div class="page-header-row">
  <div>
    <h2> Pharmacie</h2>
    <p>Stocks, ordonnances et encaissement — <a href="caisse.php" style="color:var(--accent2)"> Caisse</a></p>
  </div>
  <div style="display:flex;gap:8px">
    <?php if ($onglet === 'stock'): ?>
    <button class="btn btn-ghost" <?php if (can('medicaments.create')): ?>onclick="openModal('modal-med')">+ Médicament</button><?php endif; ?>
    <?php else: ?>
    <button class="btn btn-ghost" <?php if (can('ordonnances.create')): ?>onclick="openModal('modal-ordonnance')">+ Ordonnance</button><?php endif; ?>
    <?php endif; ?>
    <a href="caisse.php" class="btn btn-blue"> Ouvrir la caisse</a>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-24">
  <div class="stat-card green">
    <div class="stat-icon green">💊</div>
    <div class="stat-value"><?= $total_meds ?></div>
    <div class="stat-label">Références actives</div>
    <div class="stat-delta"><?= fmt_money($valeur_stock) ?> en stock</div>
  </div>
  <div class="stat-card red">
    <div class="stat-icon red">🚨</div>
    <div class="stat-value"><?= $critiques ?></div>
    <div class="stat-label">Stocks critiques</div>
  </div>
  <div class="stat-card yellow">
    <div class="stat-icon yellow">🧾</div>
    <div class="stat-value"><?= $nb_ord_actives ?></div>
    <div class="stat-label">Ordonnances à encaisser</div>
    <?php if ($nb_ord_actives > 0): ?>
    <div class="stat-delta"><a href="?tab=ordonnances" style="color:var(--yellow)"> Voir les ordonnances</a></div>
    <?php endif; ?>
  </div>
  <div class="stat-card blue">
    <div class="stat-icon blue">✅</div>
    <div class="stat-value"><?= $nb_ord_terminees ?></div>
    <div class="stat-label">Ordonnances encaissées</div>
  </div>
</div>

<!-- Tabs -->
<div class="pill-tabs">
  <a href="?tab=stock"       class="pill-tab <?= $onglet==='stock'?'active':'' ?>"> Stock médicaments (<?= $total_meds ?>)</a>
  <a href="?tab=ordonnances" class="pill-tab <?= $onglet==='ordonnances'?'active':'' ?>">
     Ordonnances
    <?php if ($nb_ord_actives > 0): ?><span style="background:var(--yellow);color:#000;border-radius:10px;padding:1px 6px;font-size:10px;margin-left:4px"><?= $nb_ord_actives ?> en attente</span><?php endif; ?>
  </a>
</div>

<!--  ONGLET STOCK  -->
<?php if ($onglet === 'stock'): ?>
<form method="GET" style="display:flex;gap:8px;margin-bottom:16px;flex-wrap:wrap">
  <input type="hidden" name="tab" value="stock">
  <input type="text" name="search_med" value="<?= h($searchMed) ?>" placeholder=" Médicament, catégorie, fournisseur..."
    style="flex:1;min-width:200px;padding:9px 14px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-size:13px;outline:none">
  <?php foreach ([''=>'Tous','normal'=>' Normal','bas'=>' Bas','critique'=>' Critique'] as $v=>$l): ?>
  <label style="display:flex;align-items:center;padding:8px 12px;background:var(--surface);border:1px solid <?= $searchFiltre===$v?'var(--accent)':'var(--border)' ?>;border-radius:8px;cursor:pointer;font-size:12px;color:<?= $searchFiltre===$v?'var(--accent2)':'var(--text2)' ?>">
    <input type="radio" name="filtre_stock" value="<?= $v ?>" <?= $searchFiltre===$v?'checked':'' ?> onchange="this.form.submit()" style="display:none"><?= $l ?>
  </label>
  <?php endforeach; ?>
  <button type="submit" class="btn btn-blue btn-sm">Chercher</button>
  <?php if ($searchMed||$searchFiltre): ?><a href="pharmacie.php?tab=stock" class="btn btn-ghost btn-sm">✕</a><?php endif; ?>
</form>
<?php if ($critiques > 0): ?>
<div class="alert alert-red mb-24"> <strong><?= $critiques ?> médicament(s) en stock critique</strong>  <a href="caisse.php" style="color:#fca5a5;text-decoration:underline">Voir la caisse</a> pour les ventes récentes.</div>
<?php endif; ?>

<div class="card">
  <div class="card-header">
    <h3> Inventaire</h3>
    <span style="font-size:12px;color:var(--text2)"><?= $total_meds ?> références  <?= $critiques + $bas ?> alertes</span>
  </div>
  <table>
    <thead>
      <tr><th>Médicament</th><th>Catégorie</th><th>Dosage</th><th>Stock</th><th>Min.</th><th>Fournisseur</th><th>Prix unit.</th><th>Statut</th><th>Actions</th></tr>
    </thead>
    <tbody>
      <?php foreach ($meds as $m):
        $rowBg = $m['statut'] === 'critique' ? 'background:rgba(var(--red-rgb),.05)' : ($m['statut'] === 'bas' ? 'background:rgba(var(--yellow-rgb),.04)' : '');
      ?>
      <tr style="<?= $rowBg ?>">
        <td><strong><?= h($m['nom']) ?></strong></td>
        <td><?= h($m['categorie'] ?? '') ?></td>
        <td><?= h($m['dosage'] ?? '') ?></td>
        <td>
          <span style="font-weight:600;color:<?= $m['statut']==='critique'?'var(--red)':($m['statut']==='bas'?'var(--yellow)':'var(--text)') ?>">
            <?= number_format((int)$m['stock_actuel']) ?>
          </span>
          <span style="font-size:11px;color:var(--text3)"> <?= h($m['unite'] ?? '') ?></span>
        </td>
        <td><?= number_format((int)$m['stock_minimum']) ?></td>
        <td><?= h($m['fournisseur'] ?? '') ?></td>
        <td><?= fmt_money((float)$m['prix_unitaire']) ?></td>
        <td><span class="badge <?= $statutBadge[$m['statut']] ?? 'badge-gray' ?>"><?= ($m['statut']==='critique'?'🔴 ':($m['statut']==='bas'?'🟡 ':($m['statut']==='expire'?'⚫ ':'🟢 '))).ucfirst($m['statut']) ?></span></td>
        <td style="display:flex;gap:4px">
          <?php if (can('medicaments.create')): ?>
          <a href="pharmacie.php?tab=stock&edit_med=<?= (int)$m['id'] ?>" class="btn btn-sm btn-ghost" title="Modifier">✏️</a>
          <?php endif; ?>
          <!--  LIEN DIRECT vers la caisse avec ce médicament prrempli -->
          <a href="caisse.php?prefill_med=<?= (int)$m['id'] ?>"
             class="btn btn-sm btn-blue"
             title="Vendre ce médicament en caisse"
             <?= $m['stock_actuel'] <= 0 ? 'style="opacity:.4;pointer-events:none"' : '' ?>>
             Caisse
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!--  ONGLET ORDONNANCES  -->
<?php if ($onglet === 'ordonnances'): ?>
<div style="display:flex;flex-direction:column;gap:12px">
  <?php foreach ($ordonnances as $ord):
    $estEncaisséee  = !empty($ord['ticket_numero']);
    $borderColor   = $estEncaisséee ? 'var(--green)' : 'var(--border)';
    $totalEstime   = array_sum(array_column($ord['lignes'], 'prix_unitaire'));
  ?>
  <div class="card" style="border-color:<?= $borderColor ?>">
    <div style="padding:16px 20px;display:flex;align-items:flex-start;justify-content:space-between;gap:12px;flex-wrap:wrap">

      <!-- Infos ordonnance -->
      <div style="flex:1;min-width:200px">
        <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
          <strong style="font-size:15px">Ordonnance #<?= (int)$ord['id'] ?></strong>
          <?php if ($ord['statut'] === 'active'): ?>
            <span class="badge badge-yellow">En attente d'encaisséement</span>
          <?php elseif ($ord['statut'] === 'terminee'): ?>
            <span class="badge badge-green"> Encaissée</span>
          <?php else: ?>
            <span class="badge badge-gray"><?= h($ord['statut']) ?></span>
          <?php endif; ?>
        </div>
        <div style="font-size:13px;color:var(--text2);margin-bottom:4px">
           <strong style="color:var(--text)"><?= h($ord['patient_nom']) ?></strong>
          <span style="color:var(--text3)">  <?= h($ord['patient_num']) ?></span>
        </div>
        <div style="font-size:12px;color:var(--text3)">
           <?= h($ord['medecin_nom']) ?>  <?= fmt_date($ord['date_prescription'], true) ?>
        </div>
      </div>

      <!-- Montant & actions -->
      <div style="text-align:right;flex-shrink:0">
        <div style="font-size:11px;color:var(--text3);margin-bottom:4px">Total estim</div>
        <div style="font-size:20px;font-weight:700;color:var(--green);margin-bottom:10px"><?= fmt_money($totalEstime) ?></div>

        <?php if ($estEncaisséee): ?>
          <!--  TICKET LI  affich directement dans la pharmacie -->
          <div style="background:rgba(var(--green-rgb),.1);border:1px solid rgba(var(--green-rgb),.25);border-radius:8px;padding:8px 12px;font-size:12px;color:var(--green);margin-bottom:6px">
             Ticket : <strong><?= h($ord['ticket_numero']) ?></strong><br>
            <span style="font-size:11px;color:var(--text2)"><?= fmt_money((float)$ord['ticket_total']) ?>  <?= fmt_date($ord['ticket_date'], true) ?></span>
          </div>
          <a href="caisse.php?ticket=<?= (int)$ord['ticket_id'] ?>" class="btn btn-sm btn-ghost"> Voir le ticket</a>
        <?php else: ?>
          <!--  BOUTON PRINCIPAL : Encaisséer  la caisse -->
          <a href="caisse.php?ordonnance_id=<?= (int)$ord['id'] ?>"
             class="btn btn-blue"
             style="font-size:13px;padding:9px 18px">
             Encaisséer  la caisse
          </a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Lignes médicaments -->
    <?php if (!empty($ord['lignes'])): ?>
    <div style="border-top:1px solid var(--border);padding:12px 20px">
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Médicaments prescrits</div>
      <div style="display:flex;flex-direction:column;gap:4px">
        <?php foreach ($ord['lignes'] as $l): ?>
        <div style="display:flex;align-items:center;gap:12px;font-size:13px">
          <span style="flex:1"><strong><?= h($l['med_nom']) ?></strong> <?= h($l['med_dosage'] ?? '') ?></span>
          <span style="color:var(--text2);font-size:12px"><?= h($l['dosage']) ?>  <?= h($l['frequence']) ?><?= $l['duree'] ? '  ' . h($l['duree']) : '' ?></span>
          <!-- Stock en temps rel -->
          <span style="font-size:11px;color:<?= $l['stock_actuel'] <= 0 ? 'var(--red)' : ($l['stock_actuel'] < 10 ? 'var(--yellow)' : 'var(--text3)') ?>">
            Stock: <?= (int)$l['stock_actuel'] ?> <?= h($l['unite'] ?? '') ?>
          </span>
          <span style="color:var(--green);font-size:12px;font-weight:600;min-width:60px;text-align:right"><?= fmt_money((float)$l['prix_unitaire']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if (empty($ordonnances)): ?>
  <div style="text-align:center;padding:48px;color:var(--text3)">
    <div style="font-size:32px;margin-bottom:12px"></div>
    <div>Aucune ordonnance enregistrée.</div>
    <button class="btn btn-blue" style="margin-top:16px" onclick="openModal('modal-ordonnance')">+ Créer une ordonnance</button>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!--  MODAL NOUVEAU MDICAMENT  -->
<div id="modal-med" class="modal-overlay" style="display:none;z-index:200;align-items:center;justify-content:center" role="dialog" aria-modal="true"
     onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(560px,95vw);max-height:90vh;overflow-y:auto;box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface)">
      <h3> Ajouter un médicament</h3>
      <button type="button" class="modal-close" onclick="this.closest('[id]').style.display='none'" aria-label="Fermer"></button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_med">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label for="inp-nom">Nom *</label><input type="text" name="nom" id="inp-nom" required maxlength="200" ></div>
        <div class="form-group"><label for="inp-categorie">Catégorie</label><input type="text" name="categorie" id="inp-categorie" maxlength="100"></div>
        <div class="form-group"><label for="inp-dosage">Dosage</label><input type="text" name="dosage" id="inp-dosage" maxlength="50" placeholder="ex: 500mg"></div>
        <div class="form-group"><label for="inp-unite">Unité</label><input type="text" name="unite" id="inp-unite" maxlength="20" placeholder="ex: comprim"></div>
        <div class="form-group"><label for="inp-fournisseur">Fournisseur</label><input type="text" name="fournisseur" id="inp-fournisseur" maxlength="100"></div>
        <div class="form-group"><label for="inp-stock_actuel">Stock actuel *</label><input type="number" name="stock_actuel" id="inp-stock_actuel" value="0" min="0" required></div>
        <div class="form-group"><label for="inp-stock_minimum">Seuil minimum *</label><input type="number" name="stock_minimum" id="inp-stock_minimum" value="100" min="0" required></div>
        <div class="form-group"><label for="inp-prix_unitaire">Prix unitaire</label><input type="number" name="prix_unitaire" id="inp-prix_unitaire" step="0.01" min="0" value="0.00"></div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-med').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue"> Ajouter</button>
      </div>
    </form>
  </div>
</div>

<!--  MODAL NOUVELLE ORDONNANCE  -->
<div id="modal-ordonnance" class="modal-overlay" style="display:none;z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" role="dialog" aria-modal="true"
     onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(660px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);margin:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0">
      <h3> Nouvelle ordonnance</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-ordonnance').style.display='none'" aria-label="Fermer"></button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_ordonnance">
      <?= csrf_field() ?>
      <div class="form-grid" style="margin-bottom:16px">
        <div class="form-group">
          <label for="inp-patient_id">Patient *</label>
          <select name="patient_id" id="inp-patient_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value=""> Sélectionner </option>
            <?php foreach ($patients as $p): ?>
            <option value="<?= (int)$p['id'] ?>"><?= h($p['nom_complet']) ?> (<?= h($p['numero']) ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="inp-medecin_id">Médecin prescripteur *</label>
          <select name="medecin_id" id="inp-medecin_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value=""> Sélectionner </option>
            <?php foreach ($medecins as $m): ?>
            <option value="<?= (int)$m['id'] ?>"><?= h($m['nom_complet']) ?><?= $m['specialite'] ? '  ' . h($m['specialite']) : '' ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <!-- Lignes de médicaments -->
      <div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
          <label style="font-size:11px;font-weight:600;color:var(--text2);text-transform:uppercase;letter-spacing:.04em">Médicaments prescrits</label>
          <button type="button" class="btn btn-sm btn-ghost" onclick="addOrdLigne()">+ Ajouter</button>
        </div>
        <div style="display:grid;grid-template-columns:1fr 100px 120px 80px 24px;gap:6px;padding:0 2px;margin-bottom:6px">
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Médicament</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Dosage</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Fréquence</span>
          <span style="font-size:10px;color:var(--text3);font-weight:600;text-transform:uppercase">Durée</span>
          <span></span>
        </div>
        <div id="ord-lignes"></div>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-ordonnance').style.display='none'">Annuler</button>
        <button type="submit" class="btn btn-blue"> Créer l'ordonnance</button>
      </div>
    </form>
  </div>
</div>

<script>
const MEDS_LIST = <?= json_encode(array_map(fn($m) => [
    'id'  => (int)$m['id'],
    'nom' => $m['nom'] . ($m['dosage'] ? ' ' . $m['dosage'] : ''),
], $meds)) ?>;

function openModal(id) { document.getElementById(id).style.display = 'flex'; }

let ordLineCount = 0;
function addOrdLigne() {
    const c   = document.getElementById('ord-lignes');
    const idx = ordLineCount++;
    let opts  = '<option value=""> Médicament </option>';
    MEDS_LIST.forEach(m => { opts += `<option value="${m.id}">${m.nom}</option>`; });

    const inp = (name, ph) => `<input type="text" name="${name}" placeholder="${ph}" style="padding:8px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none;width:100%">`;

    const div = document.createElement('div');
    div.id    = 'ord-ligne-' + idx;
    div.style.cssText = 'display:grid;grid-template-columns:1fr 100px 120px 80px 24px;gap:6px;margin-bottom:6px';
    div.innerHTML = `
      <select name="med_id[]" style="padding:8px;background:var(--bg);border:1px solid var(--border2);border-radius:6px;color:var(--text);font-size:12px;outline:none">${opts}</select>
      ${inp('dosage[]', '500mg 2x')}
      ${inp('frequence[]', '2x/jour')}
      ${inp('duree[]', '7 jours')}
      <button type="button" onclick="document.getElementById('ord-ligne-${idx}').remove()" style="min-width:44px;min-height:44px;background:rgba(var(--red-rgb),.15);border:none;border-radius:6px;color:var(--red);cursor:pointer;font-size:14px"></button>
    `;
    c.appendChild(div);
}

// Ajouter une ligne au dmarrage
document.addEventListener('DOMContentLoaded', addOrdLigne);
</script>


<!-- MODAL DITION MDICAMENT -->
<?php if ($editMed && can('medicaments.create')): ?>
<div id="modal-edit-med" class="modal-overlay" style="display:flex;z-index:200;align-items:center;justify-content:center;padding:20px" role="dialog" aria-modal="true" onclick="if(event.target===this)location.href='pharmacie.php?tab=stock'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(560px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>✏️ Modifier — <?= h($editMed['nom']) ?></h3>
      <a href="pharmacie.php?tab=stock" style="color:var(--text2);text-decoration:none;font-size:18px"></a>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_medicament">
      <input type="hidden" name="med_id" value="<?= (int)$editMed['id'] ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label for="inp-edit-nom">Nom du médicament *</label>
          <input type="text" name="nom" id="inp-edit-nom" value="<?= h($editMed['nom']) ?>" required maxlength="200"></div>
        <div class="form-group"><label for="inp-edit-categorie">Catégorie</label>
          <select name="categorie" id="inp-edit-categorie" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach (['antibiotique'=>'Antibiotique','analgesique'=>'Analgsique','antihypertenseur'=>'Antihypertenseur','antipaludeen'=>'Antipaludéen','antidiabetique'=>'Antidiabétique','vitamines'=>'Vitamines','autre'=>'Autre'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $editMed['categorie']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="inp-edit-dosage">Dosage</label>
          <input type="text" name="dosage" id="inp-edit-dosage" value="<?= h($editMed['dosage']??'') ?>" maxlength="50" placeholder="500mg, 10ml...">
        </div>
        <div class="form-group"><label for="inp-edit-unite">Unité</label>
          <input type="text" name="unite" id="inp-edit-unite" value="<?= h($editMed['unite']??'') ?>" maxlength="20" placeholder="comprim, flacon...">
        </div>
        <div class="form-group"><label for="inp-edit-stock_actuel">Stock actuel *</label>
          <input type="number" name="stock_actuel" id="inp-edit-stock_actuel" value="<?= (int)$editMed['stock_actuel'] ?>" min="0" required>
        </div>
        <div class="form-group"><label for="inp-edit-stock_minimum">Stock minimum (alerte)</label>
          <input type="number" name="stock_minimum" id="inp-edit-stock_minimum" value="<?= (int)$editMed['stock_minimum'] ?>" min="0">
        </div>
        <div class="form-group"><label for="inp-edit-prix_unitaire">Prix unitaire (<?= h(setting('currency_symbol','FCFA')) ?>)</label>
          <input type="number" name="prix_unitaire" id="inp-edit-prix_unitaire" value="<?= (float)$editMed['prix_unitaire'] ?>" min="0" step="0.01">
        </div>
        <div class="form-group"><label for="inp-edit-fournisseur">Fournisseur</label>
          <input type="text" name="fournisseur" id="inp-edit-fournisseur" value="<?= h($editMed['fournisseur']??'') ?>" maxlength="200">
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="pharmacie.php?tab=stock" class="btn btn-ghost">Annuler</a>
        <button type="submit" class="btn btn-blue">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php';
