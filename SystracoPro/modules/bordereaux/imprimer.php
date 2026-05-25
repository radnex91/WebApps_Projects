<?php
// modules/bordereaux/imprimer.php
require_once '../../includes/config.php';
requireLogin(); requirePerm('bordereaux.print');
$id=(int)($_GET['id']??0);
if(!$id) redirect(BASE_URL.'modules/bordereaux/');
$stmt=$pdo->prepare("SELECT brd.*,brd.parent_id,brd.segment_ordre,ag.nom as agence_nom,ag.telephone as agence_tel,ag.adresse as agence_adr FROM bordereaux brd LEFT JOIN agences ag ON brd.agence_id=ag.id WHERE brd.id=?");
$stmt->execute([$id]); $bordereau=$stmt->fetch(PDO::FETCH_ASSOC);
if(!$bordereau||!is_array($bordereau)){flash('Bordereau introuvable.','danger');redirect(BASE_URL.'modules/bordereaux/');}
$lignes=$pdo->prepare("SELECT bl.*,t.passager_tel,t.passager_cni,t.mode_paiement,t.escale_montee_id,t.escale_descente_id,t.agence_arrivee_id FROM bordereau_lignes bl LEFT JOIN tickets t ON bl.ticket_id=t.id WHERE bl.bordereau_id=? ORDER BY bl.siege"); $lignes->execute([$id]); $lignes=$lignes->fetchAll(PDO::FETCH_ASSOC);
$escales=$pdo->prepare("SELECT be.*,a.nom as agence_nom,a.ville as agence_ville FROM bordereau_escales be LEFT JOIN agences a ON be.agence_id=a.id WHERE be.bordereau_id=? ORDER BY be.ordre"); $escales->execute([$id]); $escales=$escales->fetchAll(PDO::FETCH_ASSOC);
$appName=getParam('nom_entreprise',APP_NAME);
// Si l'utilisateur est à une escale, exclure les passagers arrivés à cette escale
$aid = getUserAgenceId();
$escaleDestNames = [];
$currentAgenceLabel = '';
if ($aid) {
    // Noms/villes de l'agence de l'utilisateur pour le filtrage
    $myAg = $pdo->prepare("SELECT nom,ville,telephone,adresse FROM agences WHERE id=?"); $myAg->execute([$aid]); $myAgData = $myAg->fetch(PDO::FETCH_ASSOC);
    if ($myAgData) {
        $escaleDestNames = [mb_strtolower(trim($myAgData['ville'])), mb_strtolower(trim($myAgData['nom']))];
        $currentAgenceLabel = sanitize($myAgData['ville']) . ' — ' . sanitize($myAgData['nom']);
    }
    // Aussi exclure les destinations des escales déjà confirmées ou dépassées
    if (!empty($escales)) {
        // Trouver l'ordre de l'escale de l'utilisateur
        $myOrdre = 0;
        foreach ($escales as $esc) {
            if ((int)$esc['agence_id'] === (int)$aid) { $myOrdre = (int)$esc['ordre']; break; }
        }
        foreach ($escales as $esc) {
            $escOrdre = (int)$esc['ordre'];
            $escVille = mb_strtolower(trim($esc['agence_ville'] ?? $esc['agence_nom']));
            if ($esc['statut'] === 'confirme' || ($myOrdre > 0 && $escOrdre < $myOrdre)) {
                $escaleDestNames[] = $escVille;
                $escaleDestNames[] = mb_strtolower(trim($esc['agence_nom']));
            }
        }
    }
    $escaleDestNames = array_filter(array_unique($escaleDestNames));
}
// Marquer imprimé
$pdo->prepare("UPDATE bordereaux SET imprime=1 WHERE id=?")->execute([$id]);
$pageTitle='Bordereau '.$bordereau['numero'];
include '../../includes/header.php';
?>
<div class="no-print" style="display:flex;gap:8px;margin-bottom:16px;">
  <button onclick="window.print()" class="btn btn-primary"><i class="fas fa-print"></i> Imprimer les 3 exemplaires</button>
  <a href="./" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Retour</a>
</div>

<?php
$typesLabels=['chauffeur'=>'Bordereau CHAUFFEUR','comptabilite'=>'Bordereau COMPTABILITÉ','transit'=>'Bordereau TRANSIT','direction'=>'Bordereau DIRECTION'];
$types=['chauffeur','comptabilite','transit'];
// Villes du trajet pour filtrer les passagers transit
$escaleVilles = array_map(function($e) { return mb_strtolower(trim($e['agence_ville'] ?? $e['agence_nom'])); }, $escales);
$escaleVilles[] = mb_strtolower(trim($bordereau['agence_depart'] ?? ''));
$escaleVilles[] = mb_strtolower(trim($bordereau['agence_arrivee'] ?? ''));
$escaleVilles = array_filter($escaleVilles);
foreach($types as $type):
  // Filtrer les passagers arrivés à cette escale ou aux escales déjà confirmées
  $lignesFiltrees = $lignes;
  if (!empty($escaleDestNames)) {
    $lignesFiltrees = array_filter($lignes, function($l) use ($escaleDestNames) {
      $dest = mb_strtolower(trim($l['destination'] ?? ''));
      if (!$dest) return true;
      foreach ($escaleDestNames as $ev) {
        if ($ev && ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false)) return false;
      }
      return true;
    });
    $lignesFiltrees = array_values($lignesFiltrees);
  }
  // Transit : ne montrer que les passagers dont la destination n'est pas sur le trajet
  if ($type === 'transit') {
    $lignesFiltrees = array_filter($lignesFiltrees, function($l) use ($escaleVilles) {
      $dest = mb_strtolower(trim($l['destination'] ?? ''));
      if (!$dest) return true;
      foreach ($escaleVilles as $ev) {
        if ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false) return false;
      }
      return true;
    });
    $lignesFiltrees = array_values($lignesFiltrees);
  }
?>
<!-- ═══ BORDEREAU : <?= strtoupper($type) ?> ═══ -->
<div class="brd-page" style="border:2px solid #1e3a8a;margin-bottom:20px;page-break-after:always;">
  <!-- EN-TÊTE -->
  <div class="brd-head">
    <div>
      <div style="font-size:14px;font-weight:900;color:#1e3a8a;"><?= sanitize($appName) ?></div>
      <div style="font-size:11px;color:#555;"><?= sanitize($bordereau['agence_nom']) ?><?php if($currentAgenceLabel && $currentAgenceLabel !== sanitize($bordereau['agence_nom'])): ?> <span style="color:#d97706;font-weight:600;">→ Agence actuelle : <?= $currentAgenceLabel ?></span><?php endif; ?></div>
      <div style="font-size:10px;color:#888;"><?= sanitize($bordereau['agence_adr']??'') ?> | Tél: <?= sanitize($bordereau['agence_tel']??'') ?></div>
    </div>
    <div style="text-align:right;">
      <div style="font-size:10px;font-weight:700;background:#1e3a8a;color:#fff;padding:3px 10px;border-radius:4px;margin-bottom:4px;"><?= $typesLabels[$type]??'' ?></div>
      <div style="font-size:11px;color:#555;">Date: <?= date('d/m/Y H:i') ?></div>
    </div>
  </div>

  <div class="brd-title">BORDEREAU DE VOYAGE N° <?= sanitize($bordereau['numero']) ?></div>
  <?php
  $brdParentId = $bordereau['parent_id'] ?? null;
  if ($brdParentId) {
      $parentBrd = $pdo->prepare("SELECT numero FROM bordereaux WHERE id=?");
      $parentBrd->execute([$brdParentId]);
      $parentNumero = $parentBrd->fetchColumn();
      if ($parentNumero) echo '<div style="font-size:10px;color:#666;margin-bottom:4px;">Suite du bordereau '.sanitize($parentNumero).'</div>';
  }
  ?>

  <!-- INFO VOYAGE -->
  <table data-no-filter class="brd-table" style="margin-bottom:8px;">
    <tr>
      <td style="font-weight:700;background:#f0f7ff;width:150px;">Véhicule</td><td><?= sanitize($bordereau['vehicule_immat']??'—') ?></td>
      <td style="font-weight:700;background:#f0f7ff;width:130px;">Date départ</td><td><strong><?= fdatetime($bordereau['date_depart']??'') ?></strong></td>
    </tr>
    <tr>
      <td style="font-weight:700;background:#f0f7ff;">Chauffeur</td><td><?= sanitize($bordereau['chauffeur_nom']??'—') ?></td>
      <td style="font-weight:700;background:#f0f7ff;">Convoyeur</td><td><?= sanitize($bordereau['convoyeur_nom']??'—') ?></td>
    </tr>
    <tr>
      <td style="font-weight:700;background:#f0f7ff;">Trajet</td><td colspan="3"><strong><?= sanitize($bordereau['agence_depart']??'') ?> → <?= sanitize($bordereau['agence_arrivee']??'') ?></strong></td>
    </tr>
    <tr>
      <td style="font-weight:700;background:#f0f7ff;">Passagers</td><td><strong><?= count($lignesFiltrees) ?></strong></td>
      <td style="font-weight:700;background:#f0f7ff;">Observations</td><td style="font-size:11px;"><?= sanitize($bordereau['observations']??'—') ?></td>
    </tr>
  </table>

  <!-- LISTE PASSAGERS -->
  <div style="font-size:10px;font-weight:700;text-transform:uppercase;color:#1e3a8a;margin-bottom:4px;">LISTE DES PASSAGERS</div>
  <table data-no-filter class="brd-table">
    <thead><tr><th>#</th><th>Nom du Passager</th><th>Tél.</th><th>CNI</th><th>Destination</th><th>Montant</th></tr></thead>
    <tbody>
      <?php foreach($lignesFiltrees as $i=>$l): ?>
      <tr>
        <td><?= $i+1 ?></td>
        <td style="font-weight:500;"><?= sanitize($l['passager_nom']) ?></td>
        <td style="font-size:10px;"><?= sanitize($l['passager_tel']??'—') ?></td>
        <td style="font-size:10px;"><?= sanitize($l['passager_cni']??'—') ?></td>
        <td><?= sanitize($l['destination']??'—') ?></td>
        <td style="text-align:right;font-weight:600;"><?= number_format($l['montant'],0,',',' ') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($lignesFiltrees)): ?><tr><td colspan="6" style="text-align:center;color:#9ca3af;font-style:italic;">Aucun passager en transit</td></tr><?php endif; ?>
    </tbody>
  </table>

  <!-- SYNTHÈSE FINANCIÈRE -->
  <?php
  $brutAffiche = array_sum(array_column($lignesFiltrees,'montant'));
  // Calcul du montant transit (passagers dont la destination n'est pas sur le trajet)
  $montantTransit = 0;
  $lignesTransit = array_filter($lignesFiltrees, function($l) use ($escaleVilles) {
      $dest = mb_strtolower(trim($l['destination'] ?? ''));
      if (!$dest) return false;
      foreach ($escaleVilles as $ev) {
          if ($dest === $ev || strpos($ev, $dest) !== false || strpos($dest, $ev) !== false) return false;
      }
      return true;
  });
  $montantTransit = array_sum(array_column($lignesTransit, 'montant'));
  $montantLocal = $brutAffiche - $montantTransit;
  $netteAffiche = $brutAffiche - ($bordereau['montant_carburant']??0) - ($bordereau['montant_peage']??0) - ($bordereau['avance_chauffeur']??0) - ($bordereau['autres_deductions']??0);
  ?>
  <table data-no-filter class="brd-table" style="margin-top:8px;width:50%;float:right;">
    <tr><td>Recette brute :</td><td style="text-align:right;font-weight:700;"><?= number_format($brutAffiche,0,',',' ') ?> FCFA</td></tr>
    <?php if($montantTransit > 0): ?>
    <tr><td style="color:#7c3aed;">Dont transit :</td><td style="text-align:right;color:#7c3aed;font-weight:600;"><?= number_format($montantTransit,0,',',' ') ?> FCFA</td></tr>
    <tr><td>Dont local :</td><td style="text-align:right;"><?= number_format($montantLocal,0,',',' ') ?> FCFA</td></tr>
    <?php endif; ?>
    <tr><td>(-) Carburant :</td><td style="text-align:right;"><?= number_format($bordereau['montant_carburant'],0,',',' ') ?> FCFA</td></tr>
    <tr><td>(-) Péages :</td><td style="text-align:right;"><?= number_format($bordereau['montant_peage'],0,',',' ') ?> FCFA</td></tr>
    <tr><td>(-) Avance chauffeur :</td><td style="text-align:right;"><?= number_format($bordereau['avance_chauffeur'],0,',',' ') ?> FCFA</td></tr>
    <?php if($bordereau['autres_deductions']>0): ?><tr><td>(-) Autres déductions :</td><td style="text-align:right;"><?= number_format($bordereau['autres_deductions'],0,',',' ') ?> FCFA</td></tr><?php endif; ?>
    <tr style="background:#1e3a8a;color:#fff;font-weight:700;font-size:13px;">
      <td>RECETTE NETTE :</td><td style="text-align:right;"><?= number_format($netteAffiche,0,',',' ') ?> FCFA</td>
    </tr>
  </table>
  <div style="clear:both;"></div>

  <?php if(!empty($escales)): ?>
  <!-- ESCALES -->
  <div style="margin-top:8px;font-size:10px;font-weight:700;text-transform:uppercase;color:#1e3a8a;">ESCALES & VALIDATIONS</div>
  <div style="display:flex;align-items:center;gap:4px;margin-top:4px;flex-wrap:wrap;">
    <?php foreach($escales as $i=>$esc): ?>
    <span style="background:<?= $esc['statut']==='confirme'?'#dcfce7':'#f3f4f6' ?>;color:<?= $esc['statut']==='confirme'?'#14532d':'#666' ?>;padding:2px 8px;border-radius:12px;font-size:9px;font-weight:600;"><?= sanitize($esc['agence_ville']??$esc['agence_nom']) ?> <?= $esc['statut']==='confirme'?'✓':'' ?></span>
    <?php if($i<count($escales)-1): ?><span style="font-size:9px;color:#999;">→</span><?php endif; ?>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- SIGNATURES -->
  <div class="brd-sign" style="margin-top:16px;">
    <div class="brd-sign-box"><div>Chauffeur</div><div class="brd-sign-line"></div><small><?= sanitize($bordereau['chauffeur_nom']??'') ?></small></div>
    <div class="brd-sign-box"><div>Chef d'Agence</div><div class="brd-sign-line"></div><small>Nom & Cachet</small></div>
    <div class="brd-sign-box"><div><?= $type==='chauffeur'?'Convoyeur':($type==='comptabilite'?'Comptable':'Agent Transit') ?></div><div class="brd-sign-line"></div><small>Signature</small></div>
  </div>
</div>
<?php endforeach; ?>

<?php include '../../includes/footer.php'; ?>
