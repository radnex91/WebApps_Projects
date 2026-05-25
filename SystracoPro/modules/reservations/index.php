<?php // modules/reservations/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('reservations.manage');
$pageTitle='Réservations'; $aid=getUserAgenceId();
if(isset($_GET['annul'])){$pdo->prepare("UPDATE reservations SET statut='annulee' WHERE id=?")->execute([$_GET['annul']]);flash('Réservation annulée.','warning');redirect(BASE_URL.'modules/reservations/index.php');}
if(isset($_GET['confirm'])){ // Confirmer et créer ticket
    $r=$pdo->prepare("SELECT * FROM reservations WHERE id=?"); $r->execute([$_GET['confirm']]); $res=$r->fetch();
    if($res&&$res['statut']==='active'){
        $num=genNumero($pdo,'tickets','numero','T',getUserAgenceCode());
        $pdo->prepare("INSERT INTO tickets (numero,voyage_id,passager_nom,passager_tel,passager_cni,siege,classe,montant,montant_total,statut,mode_paiement,agence_id,guichetier_id) VALUES (?,?,?,?,?,?,?,?,?,'vendu','especes',?,?)")
            ->execute([$num,$res['voyage_id'],$res['passager_nom'],$res['passager_tel'],$res['passager_cni'],$res['siege'],$res['classe'],$res['montant'],$res['montant'],$res['agence_id'],$_SESSION['user_id']]);
        $tid=$pdo->lastInsertId();
        $pdo->prepare("UPDATE reservations SET statut='confirmee',ticket_id=? WHERE id=?")->execute([$tid,$_GET['confirm']]);
        flash("Réservation confirmée — Ticket $num créé.");
        redirect(BASE_URL."modules/tickets/imprimer.php?id=$tid");
    }
}
$wA=$aid?"AND r.agence_id=$aid":"";
$ress=$pdo->query("SELECT r.*,v.date_depart,a1.ville as dep,a2.ville as arr FROM reservations r JOIN voyages v ON r.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id WHERE 1=1 $wA ORDER BY r.created_at DESC LIMIT 60")->fetchAll();
$voyages=$pdo->query("SELECT v.*,a1.ville as dep,a2.ville as arr FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id WHERE v.statut='programme' AND v.date_depart>NOW() ".($aid?"AND v.agence_id=$aid":"")." ORDER BY v.date_depart LIMIT 20")->fetchAll();
$tarifs=$pdo->query("SELECT t.*,a1.ville as dep,a2.ville as arr FROM tarifs t JOIN destinations d ON t.destination_id=d.id JOIN agences a1 ON d.agence_depart=a1.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE t.actif=1 ORDER BY a1.ville,t.classe")->fetchAll();
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['reserver'])){
    $vid=(int)$_POST['voyage_id']; $nom=trim($_POST['passager_nom']??''); $tel=trim($_POST['passager_tel']??''); $cni=trim($_POST['passager_cni']??''); $siege=trim($_POST['siege']??''); $classe=$_POST['classe']??'normale'; $tarif_id=(int)($_POST['tarif_id']??0); $montant=(float)($_POST['montant']??0);
    if($nom&&$vid&&$montant>0){
        $num=genNumero($pdo,'reservations','numero','RSV'); $exp=date('Y-m-d H:i:s',strtotime('+'.(int)getParam('delai_reservation','48').' hours'));
        $pdo->prepare("INSERT INTO reservations (numero,voyage_id,passager_nom,passager_tel,passager_cni,siege,classe,montant,statut,date_expiration,agence_id,guichetier_id) VALUES (?,?,?,?,?,?,?,?,'active',?,?,?)")
            ->execute([$num,$vid,$nom,$tel,$cni,$siege,$classe,$montant,$exp,$aid??0,$_SESSION['user_id']]);
        flash("Réservation $num enregistrée (valable jusqu'au ".fdatetime($exp).').');
    }else{flash('Données incomplètes.','danger');}
    redirect(BASE_URL.'modules/reservations/index.php');
}
include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Réservations</div>
<div style="display:grid;grid-template-columns:1fr 320px;gap:16px;">
<div class="card">
  <div class="card-header"><h3><i class="fas fa-bookmark"></i> Réservations (<?= count($ress) ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Numéro</th><th>Passager</th><th>Voyage</th><th>Siège</th><th>Montant</th><th>Expiration</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($ress as $r): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($r['numero']) ?></code></td>
          <td><strong><?= sanitize($r['passager_nom']) ?></strong><br><span style="font-size:11px;"><?= sanitize($r['passager_tel']??'') ?></span></td>
          <td><?= sanitize($r['dep'].'→'.$r['arr']) ?><br><span style="font-size:11px;color:var(--text3);"><?= fdatetime($r['date_depart']) ?></span></td>
          <td><?= sanitize($r['siege']??'—') ?></td>
          <td style="font-weight:600;"><?= number_format($r['montant'],0,',',' ') ?></td>
          <td style="font-size:11px;<?= $r['date_expiration']<date('Y-m-d H:i:s')?'color:var(--danger);':'' ?>"><?= fdatetime($r['date_expiration']??'') ?></td>
          <td><span class="tag-statut st-<?= $r['statut']==='active'?'programme':($r['statut']==='confirmee'?'arrive':'annule') ?>"><?= statutLabel($r['statut']) ?></span></td>
          <td><div style="display:flex;gap:3px;">
            <?php if($r['statut']==='active'): ?>
            <a href="?confirm=<?= $r['id'] ?>" class="btn btn-xs btn-success" title="Confirmer & émettre ticket" onclick="return confirm('Confirmer cette réservation et créer le ticket ?')"><i class="fas fa-check"></i></a>
            <a href="?annul=<?= $r['id'] ?>" class="btn btn-xs btn-danger" title="Annuler" onclick="return confirm('Annuler cette réservation ?')"><i class="fas fa-times"></i></a>
            <?php endif; ?>
          </div></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($ress)): ?><tr><td colspan="8" class="t-empty"><i class="fas fa-bookmark"></i>Aucune réservation</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<div class="card" style="height:fit-content;">
  <div class="card-header"><h3><i class="fas fa-plus"></i> Nouvelle réservation</h3></div>
  <div class="card-body">
    <form method="POST">
      <?= csrfField() ?>
      <div class="fg" style="margin-bottom:10px;"><label class="flbl">Voyage <span class="freq">*</span></label><select name="voyage_id" class="fc" required><option value="">—</option><?php foreach($voyages as $v): ?><option value="<?= $v['id'] ?>"><?= sanitize($v['dep'].'→'.$v['arr'].' '.date('d/m H:i',strtotime($v['date_depart']))) ?></option><?php endforeach; ?></select></div>
      <div class="fg" style="margin-bottom:10px;"><label class="flbl">Passager <span class="freq">*</span></label><input type="text" name="passager_nom" class="fc" required style="text-transform:uppercase;"></div>
      <div class="fg" style="margin-bottom:10px;"><label class="flbl">Téléphone</label><input type="tel" name="passager_tel" class="fc"></div>
      <div class="fg" style="margin-bottom:10px;"><label class="flbl">CNI</label><input type="text" name="passager_cni" class="fc"></div>
      <div class="fg" style="margin-bottom:10px;"><label class="flbl">Siège</label><input type="text" name="siege" class="fc" maxlength="5"></div>
      <div class="fg" style="margin-bottom:10px;"><label class="flbl">Classe</label><select name="classe" class="fc"><?= ticketClassOptions() ?></select></div>
      <div class="fg" style="margin-bottom:14px;"><label class="flbl">Montant (FCFA)</label><input type="number" name="montant" class="fc" min="0" step="100"></div>
      <button type="submit" name="reserver" value="1" class="btn btn-primary btn-block"><i class="fas fa-bookmark"></i> Réserver</button>
    </form>
  </div>
</div>
</div>
<?php include '../../includes/footer.php'; ?>
