<?php
// modules/tickets/gestion.php — Gestion des tickets (statuts, actions)
require_once '../../includes/config.php';
requireLogin(); requirePerm('tickets.view');
$pageTitle = 'Gestion des Tickets';
$aid = getUserAgenceId();

// ── ACTIONS POST ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST') {
    requireCsrf();

    // Annulation
    if (isset($_POST['cancel_ticket']) && can('tickets.cancel')) {
        $cid = (int)$_POST['cancel_ticket'];
        $motif = trim($_POST['motif'] ?? 'Annulé par chef de guichet');
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND statut='vendu'");
        $stmt->execute([$cid]); $ck = $stmt->fetch();
        if ($ck) {
            $pdo->prepare("UPDATE tickets SET statut='annule',date_annulation=NOW(),motif_annulation=?,annule_par=? WHERE id=?")->execute([$motif,$_SESSION['user_id'],$cid]);
            logAction($pdo,'annulation_ticket','tickets',"Ticket $cid annulé: $motif");
            flash('Ticket annulé avec succès.','warning');
        }
        redirect(BASE_URL.'modules/tickets/gestion.php');
    }

    // Marquer utilisé
    if (isset($_POST['use_ticket']) && can('tickets.view')) {
        $uid = (int)$_POST['use_ticket'];
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND statut='vendu'");
        $stmt->execute([$uid]); $tk = $stmt->fetch();
        if ($tk) {
            $pdo->prepare("UPDATE tickets SET statut='utilise' WHERE id=?")->execute([$uid]);
            logAction($pdo,'ticket_utilise','tickets',"Ticket $uid marqué utilisé");
            flash('Ticket marqué comme utilisé.','success');
        }
        redirect(BASE_URL.'modules/tickets/gestion.php');
    }

    // Restaurer ticket annulé
    if (isset($_POST['restore_ticket']) && can('tickets.cancel')) {
        $rid = (int)$_POST['restore_ticket'];
        $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id=? AND statut='annule'");
        $stmt->execute([$rid]); $tk = $stmt->fetch();
        if ($tk) {
            $pdo->prepare("UPDATE tickets SET statut='vendu',date_annulation=NULL,motif_annulation=NULL,annule_par=NULL WHERE id=?")->execute([$rid]);
            logAction($pdo,'ticket_restored','tickets',"Ticket $rid restauré");
            flash('Ticket restauré avec succès.','success');
        }
        redirect(BASE_URL.'modules/tickets/gestion.php');
    }
}

// ── FILTRES ───────────────────────────────────────────────
$search = trim($_GET['q'] ?? ''); $statut = $_GET['statut'] ?? ''; $mode = $_GET['mode'] ?? '';
$date_d = $_GET['date_d'] ?? ''; $date_f = $_GET['date_f'] ?? '';
$page = max(1,(int)($_GET['page']??1)); $perPage=30;

$where=['1=1']; $params=[];
if ($aid) { $where[]="t.agence_id=?"; $params[]=$aid; }
if ($search) { $where[]="(t.numero LIKE ? OR t.passager_nom LIKE ? OR t.passager_tel LIKE ?)"; $params=array_merge($params,["%$search%","%$search%","%$search%"]); }
if ($statut) { $where[]="t.statut=?"; $params[]=$statut; }
if ($mode)   { $where[]="t.mode_paiement=?"; $params[]=$mode; }
if ($date_d) { $where[]="DATE(t.date_vente)>=?"; $params[]=$date_d; }
if ($date_f) { $where[]="DATE(t.date_vente)<=?"; $params[]=$date_f; }
$ws=implode(' AND ',$where);
$total=$pdo->prepare("SELECT COUNT(*) FROM tickets t WHERE $ws"); $total->execute($params);
$totalRows=$total->fetchColumn(); $totalPages=ceil($totalRows/$perPage);
$stmt=$pdo->prepare("SELECT t.*,IFNULL(ad.ville,a1.ville) as dep,IFNULL(aa.ville,a2.ville) as arr,v.numero as voy_num,v.date_depart,CONCAT(u.prenom,' ',u.nom) as guichetier,brd.numero as brd_num FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences ad ON t.agence_depart_id=ad.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id LEFT JOIN utilisateurs u ON t.guichetier_id=u.id LEFT JOIN bordereaux brd ON t.bordereau_id=brd.id WHERE $ws ORDER BY t.date_vente DESC LIMIT $perPage OFFSET ".(($page-1)*$perPage));
$stmt->execute($params); $tickets=$stmt->fetchAll();

$tot=$pdo->prepare("SELECT SUM(montant_total) as total,COUNT(*) as nb FROM tickets t WHERE $ws AND t.statut='vendu'"); $tot->execute($params); $totals=$tot->fetch();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Gestion tickets</div>

<div class="card" style="margin-bottom:16px;">
  <div class="card-header"><h3><i class="fas fa-filter"></i> Filtres</h3></div>
  <div class="card-body">
    <form method="GET" class="filter-bar">
      <input type="text" name="q" class="fc" placeholder="🔍 N° ticket, passager, téléphone..." value="<?= sanitize($search) ?>" style="max-width:260px;">
      <select name="statut" class="fc" style="width:auto;"><option value="">Tous statuts</option><?php foreach(['vendu'=>'Vendu','utilise'=>'Utilisé','annule'=>'Annulé','reserve'=>'Réservé'] as $k=>$v): ?><option value="<?= $k ?>" <?= $statut===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select>
      <select name="mode" class="fc" style="width:auto;"><option value="">Tous modes</option><?php foreach(['especes'=>'Espèces','om'=>'Orange Money','momo'=>'MTN MoMo','carte'=>'Carte'] as $k=>$v): ?><option value="<?= $k ?>" <?= $mode===$k?'selected':'' ?>><?= $v ?></option><?php endforeach; ?></select>
      <input type="date" name="date_d" class="fc" value="<?= sanitize($date_d) ?>" style="width:auto;">
      <input type="date" name="date_f" class="fc" value="<?= sanitize($date_f) ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
      <a href="?" class="btn btn-ghost btn-sm"><i class="fas fa-times"></i></a>
    </form>
    <div style="display:flex;gap:16px;font-size:13px;margin-top:8px;">
      <span>Total : <strong><?= $totalRows ?> ticket(s)</strong></span>
      <span style="color:var(--success);">Recette filtrée : <strong><?= number_format($totals['total']??0,0,',',' ') ?> FCFA</strong></span>
      <span>Validés : <strong><?= $totals['nb'] ?></strong></span>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header"><h3><i class="fas fa-list-alt"></i> Gestion des tickets (<?= $totalRows ?>)</h3></div>
  <div class="card-body" style="padding:0;">
    <div class="table-wrap">
    <table data-no-filter>
      <thead><tr><th>Numéro</th><th>Bordereau</th><th>Passager</th><th>Trajet</th><th>Classe</th><th>Montant</th><th>Mode</th><th>Guichetier</th><th>Date/Heure</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($tickets as $t): ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($t['numero']) ?></code><?= empty($t['voyage_id']) ? '<br><span class="badge badge-amber" style="font-size:9px;">Libre</span>' : '' ?></td>
          <td style="font-size:11px;"><?= $t['brd_num'] ? '<a href="'.BASE_URL.'modules/bordereaux/voir.php?id='.$t['bordereau_id'].'" title="Voir le bordereau"><code>'.sanitize($t['brd_num']).'</code></a>' : '—' ?></td>
          <td><strong><?= sanitize($t['passager_nom']) ?></strong><br><span style="font-size:11px;color:var(--text3);"><?= sanitize($t['passager_tel']??'') ?></span></td>
          <td><?= sanitize($t['dep']??'—') ?> → <?= sanitize($t['arr']??'—') ?></td>
          <td><span class="tag-statut <?= $t['classe']==='vip'?'badge-purple':'badge-blue' ?>"><?= strtoupper($t['classe']) ?></span></td>
          <td style="font-weight:700;color:<?= $t['statut']==='vendu'?'var(--success)':'var(--text2)' ?>;"><?= number_format($t['montant_total'],0,',',' ') ?></td>
          <td style="font-size:11px;"><?= ['especes'=>'💵','om'=>'📱OM','momo'=>'📱MOMO','carte'=>'💳'][$t['mode_paiement']]??$t['mode_paiement'] ?></td>
          <td style="font-size:12px;"><?= sanitize($t['guichetier']??'—') ?></td>
          <td style="font-size:11px;"><?= date('d/m H:i',strtotime($t['date_vente'])) ?></td>
          <td><span class="tag-statut st-<?= $t['statut'] ?>"><?= statutLabel($t['statut']) ?></span></td>
          <td>
            <div style="display:flex;gap:3px;">
              <?php if($t['statut']!=='annule'): ?>
              <a href="imprimer.php?id=<?= $t['id'] ?>" class="btn btn-xs btn-primary" title="Imprimer"><i class="fas fa-print"></i></a>
              <?php endif; ?>
              <?php if($t['statut']==='vendu' && can('tickets.cancel') && !$t['bordereau_id']): ?>
              <button onclick="cancelTicket(<?= $t['id'] ?>,'<?= sanitize($t['numero']) ?>')" class="btn btn-xs btn-danger" title="Annuler"><i class="fas fa-times"></i></button>
              <?php endif; ?>
              <?php if($t['statut']==='vendu' && !$t['bordereau_id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Marquer ce ticket comme utilisé ?')"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="use_ticket" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-xs btn-success" title="Marquer utilisé"><i class="fas fa-check"></i></button></form>
              <?php endif; ?>
              <?php if($t['statut']==='annule' && can('tickets.cancel') && !$t['bordereau_id']): ?>
              <form method="POST" style="display:inline" onsubmit="return confirm('Restaurer ce ticket ?')"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="restore_ticket" value="<?= $t['id'] ?>"><button type="submit" class="btn btn-xs btn-warning" title="Restaurer"><i class="fas fa-undo"></i></button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($tickets)): ?><tr><td colspan="10" class="t-empty"><i class="fas fa-ticket-alt"></i>Aucun ticket trouvé</td></tr><?php endif; ?>
      </tbody>
    </table>
    </div>
    <?php if($totalPages>1): ?><div class="pagination"><?php for($i=1;$i<=$totalPages;$i++): ?><a href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&statut=<?= urlencode($statut) ?>&date_d=<?= $date_d ?>&date_f=<?= $date_f ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
  </div>
</div>

<!-- MODAL ANNULATION -->
<div class="modal-over" id="cancel-modal">
  <div class="modal modal-sm">
    <div class="modal-head"><h3><i class="fas fa-times-circle"></i> Annuler le ticket</h3><button class="modal-x" onclick="closeModal('cancel-modal')">✕</button></div>
    <div class="modal-body">
      <p style="margin-bottom:12px;color:var(--text2);">Ticket : <strong id="cancel-num"></strong></p>
      <div class="fg"><label class="flbl">Motif d'annulation <span class="freq">*</span></label>
        <select id="cancel-motif" class="fc">
          <option value="Annulation à la demande du passager">Annulation passager</option>
          <option value="Voyage annulé">Voyage annulé</option>
          <option value="Erreur de saisie">Erreur de saisie</option>
          <option value="">Autre (préciser)</option>
        </select>
        <input type="text" id="cancel-motif-txt" class="fc" style="margin-top:6px;display:none;" placeholder="Préciser le motif...">
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn btn-secondary" onclick="closeModal('cancel-modal')">Fermer</button>
      <button class="btn btn-danger" onclick="confirmCancel()"><i class="fas fa-times"></i> Confirmer l'annulation</button>
    </div>
  </div>
</div>
<input type="hidden" id="cancel-id">

<script>
function cancelTicket(id,num){
    document.getElementById('cancel-id').value=id;
    document.getElementById('cancel-num').textContent=num;
    openModal('cancel-modal');
}
document.getElementById('cancel-motif')?.addEventListener('change',function(){
    document.getElementById('cancel-motif-txt').style.display=this.value?'none':'';
});
function confirmCancel(){
    const id=document.getElementById('cancel-id').value;
    const sel=document.getElementById('cancel-motif').value;
    const txt=document.getElementById('cancel-motif-txt').value;
    const motif=sel||txt;
    if(!motif){alert('Motif requis');return;}
    const form=document.createElement('form');
    form.method='POST';
    form.action='';
    form.innerHTML='<input type="hidden" name="_csrf" value="'+document.getElementById('csrf-token').dataset.token+'"><input type="hidden" name="cancel_ticket" value="'+id+'"><input type="hidden" name="motif" value="'+motif.replace(/"/g,'&quot;')+'">';
    document.body.appendChild(form);
    form.submit();
}
</script>
<?php include '../../includes/footer.php'; ?>