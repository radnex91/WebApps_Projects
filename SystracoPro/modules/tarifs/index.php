<?php // modules/tarifs/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('tarifs.manage');
$pageTitle='Tarifs des voyages';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $id=(int)($_POST['id']??0); $did=(int)($_POST['destination_id']??0); $itinId=(int)($_POST['itineraire_id']??0)?:null;
    $escDep=(int)($_POST['escale_depart_id']??0)?:null; $escArr=(int)($_POST['escale_arrivee_id']??0)?:null;
    $classe=$_POST['classe']??'cla'; $prix=(float)($_POST['prix']??0); $bag=(float)($_POST['bagages_inclus']??0); $actif=isset($_POST['actif'])?1:0;
    if(!$did||$prix<=0){flash('Destination et prix obligatoires.','danger');}
    else{
        // Vérifier doublon destination+classe (+itineraire+escales si segment)
        $ckParams=[$did,$classe,$id];
        if($itinId){
            $ck=$pdo->prepare("SELECT id FROM tarifs WHERE destination_id=? AND classe=? AND itineraire_id=? AND IFNULL(escale_depart_id,0)=IFNULL(?,0) AND IFNULL(escale_arrivee_id,0)=IFNULL(?,0) AND id!=? LIMIT 1");
            $ckParams=[$did,$classe,$itinId,$escDep,$escArr,$id];
        } else {
            $ck=$pdo->prepare("SELECT id FROM tarifs WHERE destination_id=? AND classe=? AND itineraire_id IS NULL AND id!=? LIMIT 1");
        }
        $ck->execute($ckParams);
        if($ck->fetch()){flash('Un tarif existe déjà pour ce trajet et cette classe.','danger');}
        else{
            if($id){$pdo->prepare("UPDATE tarifs SET destination_id=?,itineraire_id=?,escale_depart_id=?,escale_arrivee_id=?,classe=?,prix=?,bagages_inclus=?,actif=? WHERE id=?")->execute([$did,$itinId,$escDep,$escArr,$classe,$prix,$bag,$actif,$id]);flash('Tarif modifié.');}
            else{$pdo->prepare("INSERT INTO tarifs (destination_id,itineraire_id,escale_depart_id,escale_arrivee_id,classe,prix,bagages_inclus,actif,created_by) VALUES (?,?,?,?,?,?,?,?,?)")->execute([$did,$itinId,$escDep,$escArr,$classe,$prix,$bag,$actif,$_SESSION['user_id']]);flash('Tarif créé.');}
            logAction($pdo,'manage_tarif','tarifs',"Destination $did — $classe — $prix FCFA");
            redirect(BASE_URL.'modules/tarifs/index.php');
        }
    }
}
if(isset($_GET['toggle'])){$pdo->prepare("UPDATE tarifs SET actif=NOT actif WHERE id=?")->execute([$_GET['toggle']]);flash('Statut modifié.');redirect(BASE_URL.'modules/tarifs/index.php');}
if(isset($_GET['del'])&&isSuperAdmin()){$pdo->prepare("DELETE FROM tarifs WHERE id=?")->execute([$_GET['del']]);flash('Tarif supprimé.','warning');redirect(BASE_URL.'modules/tarifs/index.php');}
// AJAX: charger escales d'un itinéraire
if(isset($_GET['ajax_escales'])&&$_GET['itineraire_id']){
    header('Content-Type: application/json');ob_clean();
    $itinId=(int)$_GET['itineraire_id'];
    $es=$pdo->prepare("SELECT e.id,e.ordre,e.agence_id,a.ville FROM itineraire_escales e JOIN agences a ON e.agence_id=a.id WHERE e.itineraire_id=? ORDER BY e.ordre");
    $es->execute([$itinId]);
    echo json_encode($es->fetchAll(PDO::FETCH_ASSOC));exit;
}
$wA = getUserAgenceId() ? "AND d.agence_depart=".intval(getUserAgenceId()) : "";
// Tarifs avec infos trajet (destination + segment itinéraire)
$tarifs=$pdo->query("SELECT t.*,a1.ville as dep,a1.code as dep_code,a2.ville as arr,a2.code as arr_code,IFNULL(i.nom,CONCAT(a1.ville,' → ',a2.ville)) as trajet_nom,esc1.ville as esc_dep_ville,esc2.ville as esc_arr_ville FROM tarifs t JOIN destinations d ON t.destination_id=d.id JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id LEFT JOIN itineraires i ON t.itineraire_id=i.id LEFT JOIN itineraire_escales ie1 ON t.escale_depart_id=ie1.id LEFT JOIN agences esc1 ON ie1.agence_id=esc1.id LEFT JOIN itineraire_escales ie2 ON t.escale_arrivee_id=ie2.id LEFT JOIN agences esc2 ON ie2.agence_id=esc2.id WHERE 1=1 $wA ORDER BY a1.ville,a2.ville,t.classe")->fetchAll();
$dests=$pdo->query("SELECT d.*,a1.ville as dep,a2.ville as arr FROM destinations d JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE d.actif=1 $wA ORDER BY a1.ville,a2.ville")->fetchAll();
// Itinéraires avec leurs destinations pour les segments
$itins=$pdo->query("SELECT i.*,a1.ville as dep,a2.ville as arr,d.id as dest_id FROM itineraires i LEFT JOIN agences a1 ON i.agence_depart=a1.id LEFT JOIN agences a2 ON i.agence_arrivee=a2.id LEFT JOIN destinations d ON d.agence_depart=i.agence_depart AND d.agence_arrivee=i.agence_arrivee ORDER BY a1.ville,a2.ville")->fetchAll();
include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Tarifs</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-tags"></i> Tarifs (<?= count($tarifs) ?>)</h3><button class="btn btn-primary btn-sm" onclick="resetTForm();openModal('tm')"><i class="fas fa-plus"></i> Nouveau tarif</button></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap"><table data-no-filter>
      <thead><tr><th>Trajet</th><th>Segment</th><th>Classe</th><th>Prix (FCFA)</th><th>Bagages inclus</th><th>Actif</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($tarifs as $t):
          $segment='';
          if($t['esc_dep_ville']||$t['esc_arr_ville']){
            $segment=($t['esc_dep_ville']??$t['dep']).' → '.($t['esc_arr_ville']??$t['arr']);
          }
        ?>
        <tr>
          <td><strong><?= sanitize($t['dep'].' → '.$t['arr']) ?></strong><span style="font-size:10px;color:var(--text3);"> (<?= sanitize($t['dep_code'].' → '.$t['arr_code']) ?>)</span></td>
          <td><?= $segment?'<span style="background:#e0e7ff;color:#1e3a8a;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;">'.sanitize($segment).'</span>':'<span style="color:var(--text3);font-size:12px;">Trajet complet</span>' ?></td>
          <td><span class="badge <?= $t['classe']==='vip'?'badge-purple':($t['classe']==='spc'?'badge-teal':'badge-blue') ?>"><?= statutLabel($t['classe']) ?></span></td>
          <td style="font-weight:700;font-size:14px;"><?= number_format($t['prix'],0,',',' ') ?></td>
          <td><?= $t['bagages_inclus'] ?> kg</td>
          <td><?= $t['actif']?'<span class="badge badge-green">Actif</span>':'<span class="badge badge-red">Inactif</span>' ?></td>
          <td><div style="display:flex;gap:3px;"><button class="btn btn-xs btn-warning" onclick='editT(<?= htmlspecialchars(json_encode($t),ENT_QUOTES) ?>)'><i class="fas fa-edit"></i></button><a href="?toggle=<?= $t['id'] ?>" class="btn btn-xs btn-ghost" onclick="return confirm('Changer le statut ?')"><i class="fas fa-toggle-<?= $t['actif']?'on':'off' ?>"></i></a><?php if(isSuperAdmin()): ?><a href="?del=<?= $t['id'] ?>" class="btn btn-xs btn-danger" onclick="return confirm('Supprimer ce tarif ?')"><i class="fas fa-trash"></i></a><?php endif; ?></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($tarifs)): ?><tr><td colspan="7" class="t-empty"><i class="fas fa-tags"></i> Aucun tarif</td></tr><?php endif; ?>
      </tbody>
    </table></div>
  </div>
</div>
<div class="modal-over" id="tm"><div class="modal modal-sm">
  <div class="modal-head"><h3><i class="fas fa-tag"></i> Tarif</h3><button class="modal-x" onclick="closeModal('tm')">✕</button></div>
  <form method="POST" id="tForm">
      <?= csrfField() ?>
      <div class="modal-body">
    <input type="hidden" name="id" id="t-id">
    <input type="hidden" name="itineraire_id" id="t-itin" value="">
    <input type="hidden" name="escale_depart_id" id="t-esc-dep" value="">
    <input type="hidden" name="escale_arrivee_id" id="t-esc-arr" value="">
    <div class="form-grid">
      <div class="fg full"><label class="flbl">Destination <span class="freq">*</span></label><select name="destination_id" id="t-dest" class="fc" required onchange="onDestChange()"><option value="">—</option><?php foreach($dests as $d): ?><option value="<?= $d['id'] ?>" data-itin="<?= htmlspecialchars(json_encode(array_filter($itins,function($i)use($d){return $i['dest_id']==$d['id'];})),ENT_QUOTES) ?>"><?= sanitize($d['dep'].' → '.$d['arr']) ?></option><?php endforeach; ?></select></div>
      <div class="fg full" id="t-seg-wrap" style="display:none;"><label class="flbl">Itinéraire</label><select id="t-seg" class="fc" onchange="onSegChange()"><option value="">Trajet complet</option></select></div>
      <div class="fg" id="t-esc-dep-wrap" style="display:none;"><label class="flbl">Escale départ</label><select id="t-esc-dep-sel" class="fc" onchange="onEscaleChange()"></select></div>
      <div class="fg" id="t-esc-arr-wrap" style="display:none;"><label class="flbl">Escale arrivée</label><select id="t-esc-arr-sel" class="fc" onchange="onEscaleChange()"></select></div>
      <div class="fg"><label class="flbl">Classe</label><select name="classe" id="t-cl" class="fc"><?= ticketClassOptions() ?></select></div>
      <div class="fg"><label class="flbl">Prix (FCFA) <span class="freq">*</span></label><input type="number" name="prix" id="t-px" class="fc" required min="0" step="100"></div>
      <div class="fg"><label class="flbl">Bagages inclus (kg)</label><input type="number" name="bagages_inclus" id="t-bag" class="fc" min="0" step="5" value="20"></div>
      <div class="fg"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;margin-top:20px;"><input type="checkbox" name="actif" id="t-actif" checked> <span class="flbl">Actif</span></label></div>
    </div>
  </div>
  <div class="modal-foot"><button type="button" class="btn btn-secondary" onclick="closeModal('tm')">Annuler</button><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer</button></div>
  </form>
</div></div>
<script>
const itinsData=<?= json_encode(array_values(array_map(function($i){return['id'=>$i['id'],'dest_id'=>$i['dest_id'],'nom'=>$i['nom'],'dep'=>$i['dep'],'arr'=>$i['arr']];},$itins))) ?>;
let escalesCache={};
function onDestChange(){
  const did=document.getElementById('t-dest').value;
  const segWrap=document.getElementById('t-seg-wrap');
  const segSel=document.getElementById('t-seg');
  document.getElementById('t-itin').value='';
  document.getElementById('t-esc-dep').value='';
  document.getElementById('t-esc-arr').value='';
  hideEscales();
  if(!did){segWrap.style.display='none';return;}
  const matching=itinsData.filter(i=>i.dest_id==did);
  if(matching.length===0){segWrap.style.display='none';return;}
  segWrap.style.display='';
  segSel.innerHTML='<option value="">Trajet complet (sans segment)</option>';
  matching.forEach(i=>{
    const o=document.createElement('option');
    o.value=i.id; o.textContent=i.nom+' ('+i.dep+' → '+i.arr+')';
    segSel.appendChild(o);
  });
}
function hideEscales(){
  document.getElementById('t-esc-dep-wrap').style.display='none';
  document.getElementById('t-esc-arr-wrap').style.display='none';
}
function showEscales(escales){
  const depWrap=document.getElementById('t-esc-dep-wrap');
  const arrWrap=document.getElementById('t-esc-arr-wrap');
  const depSel=document.getElementById('t-esc-dep-sel');
  const arrSel=document.getElementById('t-esc-arr-sel');
  depWrap.style.display='';
  arrWrap.style.display='';
  depSel.innerHTML='<option value="">— Début du trajet —</option>';
  arrSel.innerHTML='<option value="">— Fin du trajet —</option>';
  escales.forEach(e=>{
    const o1=document.createElement('option'); o1.value=e.id; o1.textContent=e.ville; depSel.appendChild(o1);
    const o2=document.createElement('option'); o2.value=e.id; o2.textContent=e.ville; arrSel.appendChild(o2);
  });
  if(escales.length>=2){
    depSel.value=escales[0].id;
    arrSel.value=escales[escales.length-1].id;
    onEscaleChange();
  }
}
function onSegChange(){
  const itinId=document.getElementById('t-seg').value;
  document.getElementById('t-itin').value=itinId;
  document.getElementById('t-esc-dep').value='';
  document.getElementById('t-esc-arr').value='';
  if(!itinId){hideEscales();return;}
  if(escalesCache[itinId]){showEscales(escalesCache[itinId]);return;}
  fetch('?ajax_escales=1&itineraire_id='+itinId).then(r=>r.json()).then(escales=>{
    escalesCache[itinId]=escales;
    showEscales(escales);
  });
}
function onEscaleChange(){
  const depId=document.getElementById('t-esc-dep-sel').value;
  const arrId=document.getElementById('t-esc-arr-sel').value;
  document.getElementById('t-esc-dep').value=depId;
  document.getElementById('t-esc-arr').value=arrId;
}
function resetTForm(){
  document.getElementById('t-id').value='';
  document.getElementById('t-dest').value='';
  document.getElementById('t-itin').value='';
  document.getElementById('t-esc-dep').value='';
  document.getElementById('t-esc-arr').value='';
  document.getElementById('t-cl').value=document.getElementById('t-cl').options[0]?.value||'';
  document.getElementById('t-px').value='';
  document.getElementById('t-bag').value='20';
  document.getElementById('t-actif').checked=true;
  document.getElementById('t-seg-wrap').style.display='none';
  hideEscales();
}
function editT(t){
  document.getElementById('t-id').value=t.id;
  document.getElementById('t-dest').value=t.destination_id;
  document.getElementById('t-cl').value=t.classe||'';
  document.getElementById('t-px').value=t.prix;
  document.getElementById('t-bag').value=t.bagages_inclus||0;
  document.getElementById('t-actif').checked=!!parseInt(t.actif);
  onDestChange();
  if(t.itineraire_id){
    document.getElementById('t-seg').value=t.itineraire_id;
    document.getElementById('t-itin').value=t.itineraire_id;
    onSegChange();
    // After escales load, set saved values
    setTimeout(()=>{
      if(t.escale_depart_id) document.getElementById('t-esc-dep-sel').value=t.escale_depart_id;
      if(t.escale_arrivee_id) document.getElementById('t-esc-arr-sel').value=t.escale_arrivee_id;
      document.getElementById('t-esc-dep').value=t.escale_depart_id||'';
      document.getElementById('t-esc-arr').value=t.escale_arrivee_id||'';
    },200);
  }
  openModal('tm');
}
</script>
<?php include '../../includes/footer.php'; ?>
