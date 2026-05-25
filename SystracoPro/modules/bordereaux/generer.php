<?php
// modules/bordereaux/generer.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.create');
$pageTitle = 'Générer un Bordereau';
$aid = getUserAgenceId();
$wA = $aid ? "AND v.agence_id=$aid" : "";
$voyages = $pdo->query("SELECT v.id,v.numero,v.date_depart,v.statut,v.places_dispo,IFNULL(i.nom,CONCAT(a1.ville,' → ',a2.ville)) as trajet,a1.ville as dep,a2.ville as arr,veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauffeur FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE v.statut IN ('programme','en_cours') AND v.id NOT IN (SELECT voyage_id FROM bordereaux WHERE voyage_id IS NOT NULL AND statut='en_cours') $wA ORDER BY v.date_depart LIMIT 40")->fetchAll();
$sel_voy=(int)($_GET['voyage_id']??0);
$voyage=null; $tickets_voyage=[];
$existingBrd=null;
if($sel_voy){
    $s=$pdo->prepare("SELECT v.*,IFNULL(i.nom,CONCAT(a1.ville,' → ',a2.ville)) as trajet,a1.ville as dep,a1.nom as dep_nom,a2.ville as arr,a2.nom as arr_nom,veh.immatriculation,veh.marque,CONCAT(p.prenom,' ',p.nom) as chauf_full,p.permis as chauf_permis,v.convoyeur_nom FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE v.id=?");
    $s->execute([$sel_voy]); $voyage=$s->fetch();
    if($voyage){
        // Si le voyage est en_cours, rediriger vers l'impression du bordereau existant
        if ($voyage['statut'] === 'en_cours') {
            $eb=$pdo->prepare("SELECT id FROM bordereaux WHERE voyage_id=? ORDER BY created_at DESC LIMIT 1");
            $eb->execute([$sel_voy]); $existingBrd=$eb->fetchColumn();
            if ($existingBrd) {
                redirect(BASE_URL."modules/bordereaux/imprimer.php?id=".$existingBrd);
            }
        }
        // Tickets rattachés au voyage + tickets libres de l'agence
        $agId = $aid ?? $voyage['agence_id'];
        $ts=$pdo->prepare("SELECT t.*,IFNULL(aa.ville,a2.ville) as dest, IF(t.voyage_id IS NULL,'Libre','Rattaché') as type_vente FROM tickets t LEFT JOIN voyages vv ON t.voyage_id=vv.id LEFT JOIN itineraires i ON vv.itineraire_id=i.id LEFT JOIN destinations d ON vv.destination_id=d.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE (t.voyage_id=? OR (t.voyage_id IS NULL AND t.agence_id=?)) AND t.statut='vendu' AND t.bordereau_id IS NULL ORDER BY t.voyage_id IS NULL, t.siege");
        $ts->execute([$sel_voy,$agId]); $tickets_voyage=$ts->fetchAll();

        // Escales du voyage (pour filtrage transit)
        $escales = [];
        if ($voyage['itineraire_id']) {
            $es=$pdo->prepare("SELECT a.id,a.ville,a.nom FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
            $es->execute([$voyage['itineraire_id']]); $escales=$es->fetchAll();
        }
    }
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    requireCsrf();
    $vid=(int)$_POST['voyage_id'];
    $type=$_POST['type']??'chauffeur';
    $carb=(float)($_POST['montant_carburant']??0);
    $peage=(float)($_POST['montant_peage']??0);
    $avance=(float)($_POST['avance_chauffeur']??0);
    $autres=(float)($_POST['autres_deductions']??0);
    $obs=trim($_POST['observations']??'');
    $selected_ids=array_map('intval',$_POST['ticket_ids']??[]);

    if(empty($selected_ids)){flash('Sélectionnez au moins un ticket.','danger');redirect(BASE_URL.'modules/bordereaux/generer.php?voyage_id='.$vid);}

    $sv=$pdo->prepare("SELECT v.*,a1.ville as dep,a1.nom as dep_nom,a2.ville as arr,a2.nom as arr_nom,veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauf_full,p.permis as chauf_permis,v.convoyeur_nom FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE v.id=?");
    $sv->execute([$vid]); $svData=$sv->fetch();
    if(!$svData){ flash('Voyage introuvable.','danger'); redirect(BASE_URL.'modules/bordereaux/generer.php'); }

    $in=implode(',',array_map('intval',$selected_ids));
    $ts2=$pdo->query("SELECT SUM(montant_total) as r,COUNT(*) as nb FROM tickets WHERE id IN ($in) AND statut='vendu'")->fetch();
    $nb_pass=$ts2['nb']??0; $recette=$ts2['r']??0;
    $nette=$recette-$carb-$peage-$avance-$autres;
    $num=genNumero($pdo,'bordereaux','numero',getParam('prefix_bordereau','BRD'));
    $pdo->prepare("INSERT INTO bordereaux (numero,voyage_id,parent_id,segment_ordre,agence_id,type,vehicule_immat,chauffeur_nom,chauffeur_permis,convoyeur_nom,agence_depart,agence_arrivee,date_depart,nb_passagers,recette_brute,montant_carburant,montant_peage,avance_chauffeur,autres_deductions,recette_nette,created_by,observations) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$num,$vid,null,1,$aid??$svData['agence_id'],$type,$svData['immatriculation'],$svData['chauf_full'],$svData['chauf_permis'],$svData['convoyeur_nom']??'',$svData['dep_nom'],$svData['arr_nom'],$svData['date_depart'],$nb_pass,$recette,$carb,$peage,$avance,$autres,$nette,$_SESSION['user_id'],$obs]);
    $brd_id=$pdo->lastInsertId();
    $tkts=$pdo->query("SELECT t.*,IFNULL(aa.ville,a2.ville) as dest FROM tickets t LEFT JOIN voyages vv ON t.voyage_id=vv.id LEFT JOIN itineraires i ON vv.itineraire_id=i.id LEFT JOIN destinations d ON vv.destination_id=d.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id WHERE t.id IN ($in) ORDER BY t.siege");
    foreach($tkts->fetchAll() as $tk){
        $pdo->prepare("INSERT INTO bordereau_lignes (bordereau_id,ticket_id,passager_nom,siege,destination,montant,classe) VALUES (?,?,?,?,?,?,?)")->execute([$brd_id,$tk['id'],$tk['passager_nom'],$tk['siege'],$tk['dest'],$tk['montant_total'],$tk['classe']]);
        $pdo->prepare("UPDATE tickets SET bordereau_id=? WHERE id=?")->execute([$brd_id,$tk['id']]);
    }
    // Copier les escales du voyage vers le bordereau (pour le filtrage transit)
    foreach ($escales as $i => $esc) {
        $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut) VALUES (?,?,?,'en_attente')")
            ->execute([$brd_id, $esc['id'], $i + 1]);
    }
    logAction($pdo,'generer_bordereau','bordereaux',"Bordereau $num — Voyage $vid — $nb_pass tickets");
    flash("Bordereau $num généré avec succès.");
    redirect(BASE_URL."modules/bordereaux/imprimer.php?id=$brd_id");
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href=".">Bordereaux</a><span class="breadcrumb-sep">/</span>Générer</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h3><i class="fas fa-route"></i> Sélection du voyage</h3></div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <select name="voyage_id" class="fc" style="flex:1;max-width:500px;" onchange="this.form.submit()">
        <option value="">— Sélectionner un voyage —</option>
        <?php foreach($voyages as $v): ?>
        <option value="<?= $v['id'] ?>" <?= $sel_voy==$v['id']?'selected':'' ?>><?= sanitize($v['dep'].' → '.$v['arr'].' | '.date('d/m H:i',strtotime($v['date_depart'])).' | '.$v['immatriculation']??'') ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-arrow-right"></i></button>
    </form>
    <?php if($voyage): ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:8px;margin-top:10px;background:var(--bg);border-radius:var(--radius);padding:10px;font-size:12px;">
      <div><strong><?= sanitize($voyage['dep']) ?> → <?= sanitize($voyage['arr']) ?></strong></div>
      <div><?= sanitize($voyage['immatriculation']??'—') ?></div>
      <div><?= sanitize($voyage['chauf_full']??'') ?></div>
      <div><?= date('d/m/Y H:i',strtotime($voyage['date_depart'])) ?></div>
      <div><strong><?= count($tickets_voyage) ?></strong> tickets disponibles</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php if($voyage): ?>
<form method="POST" id="brd-form">
  <?= csrfField() ?>
  <input type="hidden" name="voyage_id" value="<?= $sel_voy ?>">

  <!-- LISTE DES TICKETS À COCHER -->
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header">
      <h3><i class="fas fa-ticket-alt"></i> Tickets disponibles <span id="ticket-count"><?= count($tickets_voyage) ?></span></h3>
      <div style="display:flex;gap:8px;align-items:center;">
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleAllTickets(true)"><i class="fas fa-check-double"></i> Tout cocher</button>
        <button type="button" class="btn btn-xs btn-ghost" onclick="toggleAllTickets(false)"><i class="fas fa-times"></i> Tout décocher</button>
        <span style="font-size:12px;color:var(--text2);margin-left:8px;">Sélectionnés : <strong id="sel-count">0</strong></span>
        <span style="font-size:12px;color:var(--success);margin-left:8px;">Total : <strong id="sel-total">0</strong> FCFA</span>
      </div>
    </div>
    <div class="card-body" style="padding:0;">
      <?php if(empty($tickets_voyage)): ?>
      <div style="padding:20px;text-align:center;color:var(--text3);"><i class="fas fa-ticket-alt" style="font-size:24px;"></i><br>Aucun ticket disponible pour ce voyage</div>
      <?php else: ?>
      <div class="table-wrap">
      <table data-no-filter>
        <thead><tr><th style="width:40px;"><input type="checkbox" id="check-all" onchange="toggleAllTickets(this.checked)"></th><th>N° Ticket</th><th>Passager</th><th>Téléphone</th><th>Siège</th><th>Classe</th><th>Destination</th><th>Montant</th><th>Mode</th><th>Type</th></tr></thead>
        <tbody>
          <?php foreach($tickets_voyage as $tk): ?>
          <tr data-dest="<?= mb_strtolower(trim(sanitize($tk['dest']??''))) ?>">
            <td><input type="checkbox" name="ticket_ids[]" value="<?= $tk['id'] ?>" class="tk-cb" data-montant="<?= $tk['montant_total'] ?>" onchange="updateBrdSummary()"></td>
            <td><code style="font-size:11px;"><?= sanitize($tk['numero']) ?></code></td>
            <td><strong><?= sanitize($tk['passager_nom']) ?></strong></td>
            <td style="font-size:12px;"><?= sanitize($tk['passager_tel']??'—') ?></td>
            <td style="text-align:center;font-weight:700;"><?= sanitize($tk['siege']??'—') ?></td>
            <td><span class="badge <?= $tk['classe']==='vip'?'badge-purple':'badge-blue' ?>"><?= strtoupper($tk['classe']) ?></span></td>
            <td><?= sanitize($tk['dest']??'—') ?></td>
            <td style="font-weight:700;"><?= number_format($tk['montant_total'],0,',',' ') ?></td>
            <td style="font-size:11px;"><?= ['especes'=>'💵','om'=>'📱OM','momo'=>'📱MOMO','carte'=>'💳'][$tk['mode_paiement']]??$tk['mode_paiement'] ?></td>
            <td style="font-size:11px;"><?= ($tk['type_vente']??'')==='Libre'?'<span class="badge badge-amber">Libre</span>':'<span class="badge badge-blue">Rattaché</span>' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 1fr;gap:18px;">
    <div class="card">
      <div class="card-header"><h3><i class="fas fa-file-invoice"></i> Informations du bordereau</h3></div>
      <div class="card-body">
        <div class="fsec">
          <div class="fsec-t">Véhicule & Équipage</div>
          <div class="form-grid">
            <div class="fg"><label class="flbl">Type de bordereau</label><select name="type" class="fc"><option value="chauffeur">Chauffeur</option><option value="comptabilite">Comptabilité</option><option value="transit">Transit</option><option value="direction">Direction</option></select></div>
            <div class="fg"><label class="flbl">Immatriculation</label><input type="text" class="fc" value="<?= sanitize($voyage['immatriculation']??'') ?>" readonly></div>
            <div class="fg"><label class="flbl">Chauffeur</label><input type="text" class="fc" value="<?= sanitize($voyage['chauf_full']??'') ?>" readonly></div>
            <div class="fg"><label class="flbl">Convoyeur</label><input type="text" class="fc" value="<?= sanitize($voyage['convoyeur_nom']??'') ?>" readonly></div>
            <div class="fg"><label class="flbl">Agence départ</label><input type="text" class="fc" value="<?= sanitize($voyage['dep_nom']) ?>" readonly></div>
            <div class="fg"><label class="flbl">Agence arrivée</label><input type="text" class="fc" value="<?= sanitize($voyage['arr_nom']) ?>" readonly></div>
          </div>
        </div>
        <div class="fsec">
          <div class="fsec-t">Détails financiers</div>
          <div class="form-grid">
            <div class="fg"><label class="flbl">Montant carburant (FCFA)</label><input type="number" name="montant_carburant" class="fc" value="<?= $voyage['montant_carburant'] ?>" min="0" oninput="calcBrd()"></div>
            <div class="fg"><label class="flbl">Montant péages (FCFA)</label><input type="number" name="montant_peage" class="fc" value="<?= $voyage['montant_peage'] ?>" min="0" oninput="calcBrd()"></div>
            <div class="fg"><label class="flbl">Avance chauffeur (FCFA)</label><input type="number" name="avance_chauffeur" class="fc" value="0" min="0" oninput="calcBrd()"></div>
            <div class="fg"><label class="flbl">Autres déductions (FCFA)</label><input type="number" name="autres_deductions" class="fc" value="0" min="0" oninput="calcBrd()"></div>
          </div>
        </div>
        <div class="fg"><label class="flbl">Observations</label><textarea name="observations" class="fc" rows="2"></textarea></div>
      </div>
    </div>

    <div class="card">
      <div class="card-header"><h3><i class="fas fa-calculator"></i> Récapitulatif</h3></div>
      <div class="card-body">
        <div style="background:var(--bg);border-radius:var(--radius);padding:14px;margin-bottom:14px;">
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>Tickets sélectionnés</span><strong id="d-nb">0</strong></div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>Recette brute</span><strong style="color:var(--success);" id="d-brute">0 FCFA</strong></div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Carburant</span><span id="d-carb">0 FCFA</span></div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Péages</span><span id="d-peage">0 FCFA</span></div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Avance chauffeur</span><span id="d-avance">0 FCFA</span></div>
          <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px;"><span>(-) Autres</span><span id="d-autres">0 FCFA</span></div>
          <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:15px;font-weight:800;color:var(--primary);">
            <span>RECETTE NETTE</span><span id="d-nette">0 FCFA</span>
          </div>
        </div>
      </div>
      <div class="card-footer" style="display:flex;gap:8px;">
        <button type="submit" id="brd-submit" class="btn btn-success btn-block" disabled><i class="fas fa-check"></i> Générer & Imprimer le bordereau</button>
      </div>
    </div>
  </div>
</form>
<?php else: ?>
<div class="empty card"><i class="fas fa-file-invoice"></i><h3 style="margin-top:12px;">Sélectionnez un voyage</h3></div>
<?php endif; ?>

<script>
function f(n){return new Intl.NumberFormat('fr-FR').format(Math.round(n));}

function updateBrdSummary(){
    const cbs=document.querySelectorAll('.tk-cb:not(:disabled)');
    let count=0, total=0;
    cbs.forEach(cb=>{if(cb.checked){count++;total+=parseFloat(cb.dataset.montant||0);}});
    document.getElementById('sel-count').textContent=count;
    document.getElementById('sel-total').textContent=f(total);
    document.getElementById('d-nb').textContent=count;
    document.getElementById('d-brute').textContent=f(total)+' FCFA';
    document.getElementById('check-all').checked=cbs.length>0 && count===cbs.length;
    document.getElementById('brd-submit').disabled=count===0;
    calcBrd();
}

function toggleAllTickets(state){
    document.querySelectorAll('.tk-cb:not(:disabled)').forEach(cb=>cb.checked=state);
    const allCbs=document.querySelectorAll('.tk-cb:not(:disabled)');
    document.getElementById('check-all').checked=state && allCbs.length>0;
    updateBrdSummary();
}

function calcBrd(){
    const carb=parseFloat(document.querySelector('[name=montant_carburant]')?.value||0);
    const peage=parseFloat(document.querySelector('[name=montant_peage]')?.value||0);
    const avance=parseFloat(document.querySelector('[name=avance_chauffeur]')?.value||0);
    const autres=parseFloat(document.querySelector('[name=autres_deductions]')?.value||0);
    const brute=parseFloat(document.getElementById('sel-total')?.textContent?.replace(/\s/g,'')||0);
    document.getElementById('d-carb').textContent=f(carb)+' FCFA';
    document.getElementById('d-peage').textContent=f(peage)+' FCFA';
    document.getElementById('d-avance').textContent=f(avance)+' FCFA';
    document.getElementById('d-autres').textContent=f(autres)+' FCFA';
    document.getElementById('d-nette').textContent=f(brute-carb-peage-avance-autres)+' FCFA';
}

<?php if($voyage): ?>
// ── Filtrage transit ──────────────────────────────────────
const routeVilles = [
  '<?= mb_strtolower(trim(sanitize($voyage['dep']??''))) ?>',
  '<?= mb_strtolower(trim(sanitize($voyage['arr']??''))) ?>',
  <?php foreach($escales as $e): ?>
  '<?= mb_strtolower(trim(sanitize($e['ville']??$e['nom']??''))) ?>',
  <?php endforeach; ?>
].filter(Boolean);

function filtrerTransit() {
  const type = document.querySelector('[name=type]').value;
  const rows = document.querySelectorAll('.tk-cb').length > 0
    ? document.querySelectorAll('[data-dest]')
    : document.querySelectorAll('tr[data-dest]');

  document.querySelectorAll('[data-dest]').forEach(function(tr){
    const dest = (tr.dataset.dest || '').trim();
    if (type === 'transit' && dest) {
      const surTrajet = routeVilles.some(function(v){ return dest === v || dest.indexOf(v) !== -1 || v.indexOf(dest) !== -1; });
      tr.style.display = surTrajet ? 'none' : '';
      const cb = tr.querySelector('.tk-cb');
      if (cb && surTrajet) cb.checked = false;
    } else {
      tr.style.display = '';
    }
  });
  // Mise à jour du compteur de tickets visibles
  var visibles = 0;
  document.querySelectorAll('[data-dest]').forEach(function(tr){ if (tr.style.display !== 'none') visibles++; });
  document.getElementById('ticket-count').textContent = visibles;
  document.querySelectorAll('.tk-cb').forEach(function(cb){ cb.disabled = cb.closest('tr')?.style.display === 'none'; });
  document.getElementById('check-all').checked = false;
  updateBrdSummary();
}

document.querySelector('[name=type]')?.addEventListener('change', filtrerTransit);
filtrerTransit();
<?php endif; ?>

// Empêcher la soumission si aucun ticket sélectionné
document.getElementById('brd-form')?.addEventListener('submit',function(e){
    const checked=document.querySelectorAll('.tk-cb:checked');
    if(!checked.length){e.preventDefault();alert('Sélectionnez au moins un ticket.');}
});
</script>
<?php include '../../includes/footer.php'; ?>