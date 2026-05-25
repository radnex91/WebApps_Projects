<?php // modules/voyages/index.php
require_once '../../includes/config.php'; requireLogin(); requirePerm('voyages.create');
$pageTitle='Voyages'; $aid=getUserAgenceId(); $wA=$aid?"AND (v.agence_id=$aid OR EXISTS(SELECT 1 FROM bordereaux b JOIN bordereau_escales be ON b.id=be.bordereau_id WHERE b.voyage_id=v.id AND b.statut='en_cours' AND be.agence_id=$aid))":"";
// Annulation voyage (POST only)
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['cancel_voyage']) && can('voyages.cancel')){
    requireCsrf();
    $vid=(int)$_POST['cancel_voyage'];
    $pdo->prepare("UPDATE voyages SET statut='annule' WHERE id=?")->execute([$vid]);
    $pdo->prepare("UPDATE bordereaux SET statut='annule' WHERE voyage_id=? AND statut IN ('genere','en_cours')")->execute([$vid]);
    logAction($pdo,'annulation_voyage','voyages','Voyage '.$vid.' annulé');
    flash('Voyage annulé.','warning');
    redirect(BASE_URL.'modules/voyages/index.php');
}
// Confirmation départ voyage
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['depart_voyage']) && can('voyages.create')){
    requireCsrf();
    $vid=(int)$_POST['depart_voyage'];
    $pdo->prepare("UPDATE voyages SET statut='en_cours' WHERE id=? AND statut='programme'")->execute([$vid]);
    // Passer les bordereaux genere en_cours et creer les escales si absentes
    $brds=$pdo->prepare("SELECT b.id, v.itineraire_id FROM bordereaux b JOIN voyages v ON b.voyage_id=v.id WHERE b.voyage_id=? AND b.statut='genere'");
    $brds->execute([$vid]);
    foreach($brds->fetchAll(PDO::FETCH_ASSOC) as $brd){
        $pdo->prepare("UPDATE bordereaux SET statut='en_cours' WHERE id=?")->execute([$brd['id']]);
        // Creer les escales si absentes
        $hasEscale=$pdo->prepare("SELECT 1 FROM bordereau_escales WHERE bordereau_id=? LIMIT 1");
        $hasEscale->execute([$brd['id']]);
        if(!$hasEscale->fetchColumn() && $brd['itineraire_id']){
            $es=$pdo->prepare("SELECT agence_id,ordre FROM itineraire_escales WHERE itineraire_id=? ORDER BY ordre");
            $es->execute([$brd['itineraire_id']]);
            foreach($es->fetchAll(PDO::FETCH_ASSOC) as $i=>$esc){
                $pdo->prepare("INSERT INTO bordereau_escales (bordereau_id,agence_id,ordre,statut) VALUES (?,?,?,?)")
                    ->execute([$brd['id'],$esc['agence_id'],$esc['ordre'],$i===0?'confirme':'en_attente']);
            }
        }
    }
    logAction($pdo,'depart_voyage','voyages','Voyage '.$vid.' — départ confirmé');
    flash('Départ confirmé — voyage en cours.','success');
    redirect(BASE_URL.'modules/voyages/index.php');
}
$stmt=$pdo->query("SELECT v.*,a1.ville as dep,a2.ville as arr,veh.immatriculation,veh.capacite,CONCAT(p.prenom,' ',p.nom) as chauffeur,(SELECT COUNT(DISTINCT t.id) FROM tickets t WHERE (t.voyage_id=v.id OR t.bordereau_id IN (SELECT id FROM bordereaux WHERE voyage_id=v.id)) AND t.statut='vendu') as nb_tks FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE 1=1 $wA ORDER BY v.date_depart DESC LIMIT 50");
$voyages=$stmt->fetchAll();
include '../../includes/header.php'; ?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Voyages</div>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-route"></i> Voyages (<?= count($voyages) ?>)</h3><a href="ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouveau voyage</a></div>
  <div class="card-body" style="padding:0;">
    <table data-no-filter>
      <thead><tr><th>Numéro</th><th>Trajet</th><th>Véhicule</th><th>Chauffeur</th><th>Départ</th><th>Occupation</th><th>Remplissage</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($voyages as $v): $capBus=!empty($v['capacite'])?(int)$v['capacite']:(int)$v['places_dispo']; $pct=$capBus>0?min(100,round($v['nb_tks']/$capBus*100)):0;
        $detailUrl = 'voir.php?id='.$v['id'];
        ?>
        <tr style="cursor:pointer;" onclick="if(!event.target.closest('a,button,form')){window.location.href='<?= $detailUrl ?>'}">
          <td><code style="font-size:11px;"><?= sanitize($v['numero']) ?></code></td>
          <td><strong><?= sanitize($v['dep']) ?> → <?= sanitize($v['arr']) ?></strong></td>
          <td><?= sanitize($v['immatriculation']??'—') ?></td>
          <td><?= sanitize($v['chauffeur']??'—') ?></td>
          <td><?= fdatetime($v['date_depart']) ?></td>
          <td><?= $v['nb_tks'] ?>/<?= $capBus ?></td>
          <td><div class="progress" style="width:60px;display:inline-block;"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=90?'var(--danger)':'var(--success)' ?>"></div></div></td>
          <td><span class="tag-statut st-<?= $v['statut'] ?>"><?= statutLabel($v['statut']) ?></span></td>
          <td onclick="event.stopPropagation();">
            <div style="display:flex;gap:3px;">
              <?php if(can('tickets.create')): ?><a href="../tickets/vente.php?voyage_id=<?= $v['id'] ?>" class="btn btn-xs btn-primary" title="Vendre ticket"><i class="fas fa-ticket-alt"></i></a><?php endif; ?>
              <?php if(can('bordereaux.create') && can('voyages.create') && in_array($v['statut'],['programme','en_cours'])): ?>
              <button type="button" class="btn btn-xs btn-success" title="Gérer le départ" onclick="ouvrirDepartModal(<?= $v['id'] ?>)"><i class="fas fa-bus"></i> Départ</button>
              <?php endif; ?>
              <?php if($v['statut']==='programme' && can('voyages.cancel')): ?><form method="POST" style="display:inline" onsubmit="return confirm('Annuler ce voyage ?')"><input type="hidden" name="_csrf" value="<?= csrfToken() ?>"><input type="hidden" name="cancel_voyage" value="<?= $v['id'] ?>"><button type="submit" class="btn btn-xs btn-danger" title="Annuler"><i class="fas fa-times"></i></button></form><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php include 'depart_modal.php'; ?>
<?php include '../../includes/footer.php'; ?>
