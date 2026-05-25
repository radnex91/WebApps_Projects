<?php
// modules/reservations/ajouter.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('reservations.manage');
$pageTitle = 'Nouvelle Réservation';
$aid = getUserAgenceId();
$voyage_id = (int)($_GET['voyage_id'] ?? 0);

$wA = $aid ? "AND v.agence_id=$aid" : "";
$voyages = $pdo->query("SELECT v.*,a1.ville as dep,a2.ville as arr,(SELECT COUNT(*) FROM tickets t WHERE t.voyage_id=v.id AND t.statut IN ('vendu','reserve')) as prises FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id WHERE v.statut='programme' AND v.date_depart>NOW() $wA ORDER BY v.date_depart LIMIT 30")->fetchAll();

$voyage = null; $tarifs = [];
if ($voyage_id) {
    $s=$pdo->prepare("SELECT v.*,a1.ville as dep,a2.ville as arr,veh.immatriculation FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id WHERE v.id=?");
    $s->execute([$voyage_id]); $voyage=$s->fetch();
    if ($voyage) {
        $ts=$pdo->prepare("SELECT t.*,a2.ville as arr FROM tarifs t JOIN destinations d ON t.destination_id=d.id JOIN agences a2 ON d.agence_arrivee=a2.id WHERE d.agence_depart=(SELECT agence_depart FROM destinations WHERE id=?) AND t.actif=1 ORDER BY t.classe");
        $ts->execute([$voyage['destination_id']]); $tarifs=$ts->fetchAll();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vid     = (int)$_POST['voyage_id'];
    $nom     = trim($_POST['passager_nom'] ?? '');
    $tel     = trim($_POST['passager_tel'] ?? '');
    $cni     = trim($_POST['passager_cni'] ?? '');
    $siege   = trim($_POST['siege'] ?? '');
    $classe  = $_POST['classe'] ?? 'normale';
    $montant = (float)($_POST['montant'] ?? 0);
    $acompte = (float)($_POST['acompte'] ?? 0);

    if (!$nom || !$vid || $montant <= 0) {
        flash('Nom passager, voyage et montant obligatoires.', 'danger');
    } else {
        $num = genNumero($pdo,'reservations','numero','RSV');
        $exp = date('Y-m-d H:i:s', strtotime('+'.(int)getParam('delai_reservation','48').' hours'));
        $pdo->prepare("INSERT INTO reservations (numero,voyage_id,passager_nom,passager_tel,passager_cni,siege,classe,montant,acompte,statut,date_expiration,agence_id,guichetier_id) VALUES (?,?,?,?,?,?,?,?,?,'active',?,?,?)")
            ->execute([$num,$vid,$nom,$tel,$cni,$siege,$classe,$montant,$acompte,$exp,$aid??0,$_SESSION['user_id']]);
        logAction($pdo,'create_reservation','reservations',"$num — $nom");
        flash("Réservation $num créée. Valable jusqu'au ".fdatetime($exp).'.');
        redirect(BASE_URL.'modules/reservations/index.php');
    }
}

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span><a href="index.php">Réservations</a><span class="breadcrumb-sep">/</span>Nouvelle</div>

<div class="card" style="max-width:700px;margin:0 auto;">
  <div class="card-header"><h3><i class="fas fa-bookmark"></i> Nouvelle réservation</h3></div>
  <div class="card-body">
    <form method="POST">
      <?= csrfField() ?>
        <div class="fsec-t"><i class="fas fa-route"></i> Voyage</div>
        <div class="fg">
          <label class="flbl">Voyage <span class="freq">*</span></label>
          <select name="voyage_id" class="fc" required onchange="window.location='?voyage_id='+this.value">
            <option value="">— Sélectionner —</option>
            <?php foreach($voyages as $v): $dispo=$v['places_dispo']-$v['prises']; ?>
            <option value="<?= $v['id'] ?>" <?= $voyage_id==$v['id']?'selected':'' ?>>
              <?= sanitize($v['dep'].' → '.$v['arr'].' | '.date('d/m H:i',strtotime($v['date_depart']))) ?> (<?= $dispo ?> places)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php if($voyage): ?>
        <div style="margin-top:8px;background:var(--bg);border-radius:var(--radius);padding:10px;font-size:12px;display:flex;gap:16px;flex-wrap:wrap;">
          <span>📅 <?= fdatetime($voyage['date_depart']) ?></span>
          <span>🚌 <?= sanitize($voyage['immatriculation']??'—') ?></span>
          <span>💺 <?= $voyage['places_dispo'] ?> places</span>
        </div>
        <?php endif; ?>
      </div>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-user"></i> Passager</div>
        <div class="form-grid">
          <div class="fg full"><label class="flbl">Nom complet <span class="freq">*</span></label><input type="text" name="passager_nom" class="fc" required style="text-transform:uppercase;" autofocus></div>
          <div class="fg"><label class="flbl">Téléphone</label><input type="tel" name="passager_tel" class="fc"></div>
          <div class="fg"><label class="flbl">N° CNI</label><input type="text" name="passager_cni" class="fc"></div>
          <div class="fg"><label class="flbl">Siège souhaité</label><input type="text" name="siege" class="fc" maxlength="5" placeholder="A1, B2..."></div>
          <div class="fg">
            <label class="flbl">Classe & Tarif</label>
            <select name="classe" id="cls-sel" class="fc" onchange="setTarif(this)">
              <?php foreach($tarifs as $t): ?>
              <option value="<?= $t['classe'] ?>" data-prix="<?= $t['prix'] ?>"><?= strtoupper($t['classe']) ?> — <?= number_format($t['prix'],0,',',' ') ?> FCFA</option>
              <?php endforeach; ?>
              <?php if(empty($tarifs)): ?><?= ticketClassOptions() ?><?php endif; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="fsec">
        <div class="fsec-t"><i class="fas fa-money-bill"></i> Montant</div>
        <div class="form-grid-3">
          <div class="fg"><label class="flbl">Montant total (FCFA) <span class="freq">*</span></label><input type="number" name="montant" id="rsv-montant" class="fc" required min="0" step="100"></div>
          <div class="fg"><label class="flbl">Acompte versé (FCFA)</label><input type="number" name="acompte" class="fc" min="0" step="100" value="0"></div>
        </div>
      </div>

      <div style="background:var(--warning-bg);border-radius:var(--radius);padding:10px 14px;margin-bottom:14px;font-size:12px;color:var(--warning);">
        <i class="fas fa-clock"></i> La réservation expirera automatiquement après <strong><?= getParam('delai_reservation','48') ?> heures</strong> si non confirmée.
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <a href="index.php" class="btn btn-secondary">Annuler</a>
        <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-bookmark"></i> Créer la réservation</button>
      </div>
    </form>
  </div>
</div>

<script>
function setTarif(sel) {
    const prix = sel.options[sel.selectedIndex]?.dataset?.prix;
    if (prix) document.getElementById('rsv-montant').value = prix;
}
// Initialiser le tarif au chargement
document.addEventListener('DOMContentLoaded', () => {
    const sel = document.getElementById('cls-sel');
    if (sel) setTarif(sel);
});
</script>
<?php include '../../includes/footer.php'; ?>
