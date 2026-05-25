<?php
// modules/tickets/imprimer.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('tickets.print');
$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL.'modules/tickets/liste.php');

$stmt = $pdo->prepare("SELECT t.*,v.numero as voy_num,v.date_depart,IFNULL(ad.ville,a1.ville) as dep,IFNULL(ad.nom,a1.nom) as dep_nom,IFNULL(aa.ville,a2.ville) as arr,IFNULL(aa.nom,a2.nom) as arr_nom,veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauffeur,CONCAT(u.prenom,' ',u.nom) as guichetier,u.username as guichetier_user,ag.nom as agence_nom,ag.telephone as agence_tel,brd.numero as brd_num FROM tickets t LEFT JOIN voyages v ON t.voyage_id=v.id LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN agences ad ON t.agence_depart_id=ad.id LEFT JOIN agences aa ON t.agence_arrivee_id=aa.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id LEFT JOIN utilisateurs u ON t.guichetier_id=u.id LEFT JOIN agences ag ON t.agence_id=ag.id LEFT JOIN bordereaux brd ON t.bordereau_id=brd.id WHERE t.id=?");
$stmt->execute([$id]); $t = $stmt->fetch();
if (!$t) { flash('Ticket introuvable.','danger'); redirect(BASE_URL.'modules/tickets/liste.php'); }
if ($t['statut'] === 'annule') { flash('Ce ticket est annulé et ne peut être imprimé.','danger'); redirect(BASE_URL.'modules/tickets/liste.php'); }

$appName = getParam('nom_entreprise', APP_NAME);
$isLibre = empty($t['voyage_id']);
$pageTitle = 'Ticket '.$t['numero'];
include '../../includes/header.php';
?>
<div class="breadcrumb no-print"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="liste.php">Tickets</a><span class="breadcrumb-sep">/</span>Imprimer</div>

<div class="no-print" style="display:flex;gap:8px;margin-bottom:16px;">
  <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer</button>
  <a href="liste.php?vente=1" class="btn btn-success"><i class="fas fa-plus"></i> Nouveau ticket</a>
  <a href="liste.php" class="btn btn-secondary"><i class="fas fa-list"></i> Liste</a>
</div>

<link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
body { font-family: 'Open Sans', 'Segoe UI', Arial, sans-serif; }

@media print {
  @page { size: landscape; margin: 0; }
  body { margin: 0; padding: 0; }
  .no-print, .sidebar, .topbar, .breadcrumb, .page > *:not(#ticket-zone) { display: none !important; }
  .page { padding: 0 !important; margin: 0 !important; }
  #ticket-zone { margin: 0 !important; padding: 0 !important; }
}

#ticket-zone { font-family: 'Open Sans', sans-serif; }

.ticket-wrapper {
  width: 842px; height: 595px; background: #fff; position: relative; overflow: hidden;
  margin: 0 auto; box-sizing: border-box;
  font-size: 10px; line-height: 1.15; font-weight: 500;
}

.ticket-border {
  position: absolute; top: 6px; left: 6px; right: 6px; bottom: 6px;
  border: 1px solid #aaa; pointer-events: none;
}

@media screen {
  .ticket-wrapper { box-shadow: 0 2px 12px rgba(0,0,0,0.1); margin: 10px auto; }
}

/* ── LEFT: Class label (large) ── */
.tkt-left {
  position: absolute; left: 323px; top: 305px; width: 80px;
  text-align: center;
}
.tkt-left .cl {
  font-size: 14px; font-weight: 800; text-transform: uppercase; color: #111;
  letter-spacing: 2px;
}
.tkt-left .tkt-qr {
  position: absolute; left: -32px; top: 40px; width: 64px; height: 64px;
}
.tkt-left .tkt-qr svg { width: 64px; height: 64px; display: block; }

/* ── MIDDLE: Main ticket body ── */
.tkt-main {
  position: absolute;
  left: 414px; top: 0; width: 270px;
}

.tkt-main .tkt-num {
  position: absolute; left: 97px; top: 242px;
  font-size: 10px; color: #555;
}
.tkt-main .pass {
  position: absolute; left: 90px; top: 256px;
  font-size: 10px; font-weight: 800; color: #000; text-transform: uppercase;
}
.tkt-main .cni {
  position: absolute; left: 88px; top: 271px;
  font-size: 10px; color: #555;
}
.tkt-main .route {
  position: absolute; left: 67px; top: 286px;
  font-size: 10px; color: #222;
}
.tkt-main .price {
  position: absolute; left: 67px; top: 302px;
  font-size: 10px; font-weight: 800; color: #111;
}
.tkt-main .dt {
  position: absolute; left: 54px; top: 317px;
  font-size: 10px; color: #333;
}
.tkt-main .tm {
  position: absolute; left: 189px; top: 316px;
  font-size: 10px; color: #333;
}
.tkt-main .tel {
  position: absolute; left: 169px; top: 330px;
  font-size: 10px; color: #333;
}
.tkt-main .type {
  position: absolute; left: 90px; top: 331px;
  font-size: 10px; color: #333;
}
.tkt-main .bag {
  position: absolute; left: 67px; top: 347px;
  font-size: 10px; color: #333;
}
.tkt-main .vendu {
  position: absolute; left: 0; top: 378px;
  font-size: 10px; color: #777;
}
.tkt-main .vendu span { color: #333; font-weight: 700; }

/* ── RIGHT: Stub ── */
.tkt-right {
  position: absolute;
  left: 688px; top: 0; width: 150px;
}

.tkt-right .cl {
  position: absolute; left: 0; top: 260px;
  font-size: 14px; font-weight: 800; text-transform: uppercase; color: #111;
  letter-spacing: 2px;
}
.tkt-right .st-name {
  position: absolute; left: 32px; top: 282px;
  font-size: 10px; font-weight: 700; color: #000; text-transform: uppercase;
}
.tkt-right .st-price {
  position: absolute; left: 39px; top: 295px;
  font-size: 10px; font-weight: 800; color: #111;
}
.tkt-right .st-route {
  position: absolute; left: 35px; top: 309px;
  font-size: 10px; color: #222;
}
.tkt-right .st-date {
  position: absolute; left: 19px; top: 324px;
  font-size: 10px; color: #333;
}
.tkt-right .st-time {
  position: absolute; left: 105px; top: 324px;
  font-size: 10px; color: #333;
}
.tkt-right .st-type {
  position: absolute; left: 57px; top: 335px;
  font-size: 10px; color: #333;
}
.tkt-right .st-bag {
  position: absolute; left: 32px; top: 349px;
  font-size: 10px; color: #333;
}
.tkt-right .st-tnum {
  position: absolute; left: 3px; top: 363px;
  font-size: 9px; color: #555;
}
</style>

<div id="ticket-zone">
<?php
$dateVente = date('d/m/Y', strtotime($t['date_vente']));
$heureVente = date('H:i', strtotime($t['date_vente']));
$venduParUser = sanitize($t['guichetier_user'] ?? $t['guichetier'] ?? '—');

$classeLabel = 'Classique';
if (($t['classe'] ?? 'cla') === 'vip') $classeLabel = 'VIP';
elseif (($t['classe'] ?? 'cla') === 'spc') $classeLabel = 'Spécial';

$ticketNum = sanitize($t['numero']);
$passNom = sanitize(strtoupper($t['passager_nom']));
$reference = sanitize($t['passager_cni'] ?? '—');
$prix = number_format($t['montant_total'], 0, ',', ' ') . ' FCFA';
$typeLabel = ($t['type_passager'] ?? 'adulte') === 'enfant' ? 'Enfant' : 'Adulte';
$bagages = (int)($t['bagages_kg'] ?? 0);
$tel = sanitize($t['passager_tel'] ?? $t['agence_tel'] ?? '—');
$trajetLabel = sanitize(($t['dep'] ?? '—') . ' - ' . ($t['arr'] ?? '—'));
$trajetDe = sanitize($t['dep'] ?? '—');
$trajetArr = sanitize($t['arr'] ?? '—');

require_once '../../includes/phpqrcode.php';
$qrTmp = __DIR__ . '/../../data/tmp/qr_' . $id . '.svg';
QRcode::svg($ticketNum, $qrTmp, QR_ECLEVEL_L, 3, 1);
$qrSvg = file_get_contents($qrTmp);
@unlink($qrTmp);
// Make SVG responsive: remove hardcoded w/h and let CSS control it
$qrSvg = preg_replace('~<\?xml.*?\?>~i', '', $qrSvg);
$qrSvg = preg_replace('~<!DOCTYPE.*?>~i', '', $qrSvg);
$qrSvg = preg_replace('~\s*(?:width|height)="[^"]*"\s*~', ' ', $qrSvg);
?>
<div class="ticket-wrapper">
  <div class="ticket-border"></div>

  <!-- ── GAUCHE : label classe ── -->
  <div class="tkt-left">
    <div class="cl"><?= $classeLabel ?></div>
    <div class="tkt-qr"><?= $qrSvg ?></div>
  </div>

  <!-- ── CENTRE : corps du ticket ── -->
  <div class="tkt-main">
    <div class="tkt-num"><?= $ticketNum ?></div>
    <div class="pass"><?= $passNom ?></div>
    <div class="cni"><?= $reference ?></div>
    <div class="route">De <?= $trajetDe ?> &agrave; <?= $trajetArr ?></div>
    <div class="price"><?= $prix ?></div>
    <div class="dt"><?= $dateVente ?></div>
    <div class="tm"><?= $heureVente ?></div>
    <div class="tel">Tel. <?= $tel ?></div>
    <div class="type"><?= $typeLabel ?></div>
    <div class="bag"><?= $bagages ?></div>
    <?php if (($t['reliquat'] ?? 0) > 0): ?>
    <div style="position:absolute;left:0;top:362px;font-size:10px;color:#d97706;">
      Reliquat : <?= number_format($t['reliquat'], 0, ',', ' ') ?> FCFA
    </div>
    <?php endif; ?>
    <div class="vendu">Vendu par : <span><?= $venduParUser ?></span></div>
  </div>

  <!-- ── DROITE : talon ── -->
  <div class="tkt-right">
    <div class="cl"><?= $classeLabel ?></div>
    <div class="st-name"><?= $passNom ?></div>
    <div class="st-price"><?= $prix ?></div>
    <div class="st-route"><?= $trajetLabel ?></div>
    <div class="st-date"><?= $dateVente ?></div>
    <div class="st-time"><?= $heureVente ?></div>
    <div class="st-type"><?= $typeLabel ?></div>
    <div class="st-bag"><?= $bagages ?></div>
    <div class="st-tnum">Ticket N&deg; : <?= $ticketNum ?></div>
  </div>

</div>
</div>

<?php include '../../includes/footer.php'; ?>
