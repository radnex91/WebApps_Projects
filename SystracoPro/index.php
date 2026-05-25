<?php
require_once 'includes/config.php';
requireLogin();
$pageTitle = 'Tableau de bord';
$aid = getUserAgenceId();
$today = date('Y-m-d');

// ── Stats du jour ──────────────────────────────────────────
$wA = $aid ? "AND t.agence_id=".intval($aid) : "";
$wAV = $aid ? "AND v.agence_id=".intval($aid) : "";

$stats = [];
$stats['tickets_jour']   = $pdo->query("SELECT COUNT(*) FROM tickets t WHERE DATE(t.date_vente)='$today' AND t.statut='vendu' $wA")->fetchColumn();
$stats['recette_jour']   = $pdo->query("SELECT COALESCE(SUM(t.montant_total),0) FROM tickets t WHERE DATE(t.date_vente)='$today' AND t.statut='vendu' $wA")->fetchColumn();
$stats['voyages_prog']   = $pdo->query("SELECT COUNT(*) FROM voyages v WHERE v.statut='programme' AND DATE(v.date_depart)='$today' $wAV")->fetchColumn();
$stats['voyages_cours']  = $pdo->query("SELECT COUNT(*) FROM voyages v WHERE v.statut='en_cours' $wAV")->fetchColumn();
$stats['reservations']   = $pdo->query("SELECT COUNT(*) FROM reservations r WHERE r.statut='active' ".($aid?"AND r.agence_id=$aid":""))->fetchColumn();
$stats['annulations_jour']= $pdo->query("SELECT COUNT(*) FROM tickets t WHERE DATE(t.date_annulation)='$today' AND t.statut='annule' $wA")->fetchColumn();

// ── Voyages du jour ────────────────────────────────────────
$voyQ=$pdo->query("SELECT v.*,IFNULL(a1.ville,'') as dep,IFNULL(a2.ville,'') as arr,veh.immatriculation,CONCAT(p.prenom,' ',p.nom) as chauffeur,(SELECT COUNT(*) FROM tickets t WHERE t.voyage_id=v.id AND t.statut='vendu') as nb_places_vendues FROM voyages v LEFT JOIN itineraires i ON v.itineraire_id=i.id LEFT JOIN destinations d ON v.destination_id=d.id LEFT JOIN agences a1 ON IFNULL(i.agence_depart,d.agence_depart)=a1.id LEFT JOIN agences a2 ON IFNULL(i.agence_arrivee,d.agence_arrivee)=a2.id LEFT JOIN vehicules veh ON v.vehicule_id=veh.id LEFT JOIN personnel p ON v.chauffeur_id=p.id WHERE DATE(v.date_depart)='$today' ".($aid?"AND v.agence_id=$aid":"")." ORDER BY v.date_depart LIMIT 8");
$voyages=$voyQ->fetchAll();

// ── Recettes 7 derniers jours ──────────────────────────────
$recettes7=$pdo->query("SELECT DATE(date_vente) as jour, SUM(montant_total) as total, COUNT(*) as nb FROM tickets WHERE statut='vendu' ".($aid?"AND agence_id=$aid":"")." AND date_vente>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY jour ORDER BY jour")->fetchAll();

// ── Véhicules en panne ─────────────────────────────────────
$pannes=$pdo->query("SELECT v.*, a.ville FROM vehicules v LEFT JOIN agences a ON v.agence_id=a.id WHERE v.statut='panne' ".($aid?"AND v.agence_id=$aid":"")." LIMIT 5")->fetchAll();

// ── Notifications non lues ────────────────────────────────
$notifs=$pdo->prepare("SELECT * FROM notifications WHERE user_id=? AND lue=0 ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$_SESSION['user_id']]); $notifs=$notifs->fetchAll();

include 'includes/header.php';
?>
<div class="breadcrumb"><i class="fas fa-home"></i><span class="breadcrumb-sep">/</span>Tableau de bord</div>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-ticket-alt"></i></div>
    <div><div class="stat-val"><?= number_format($stats['tickets_jour']) ?></div><div class="stat-lbl">Tickets vendus aujourd'hui</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-money-bill-wave"></i></div>
    <div><div class="stat-val" style="font-size:15px;"><?= number_format($stats['recette_jour'],0,',',' ') ?></div><div class="stat-lbl">Recette du jour (FCFA)</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-route"></i></div>
    <div><div class="stat-val"><?= $stats['voyages_prog'] ?></div><div class="stat-lbl">Voyages programmés</div><div class="stat-sub"><?= $stats['voyages_cours'] ?> en cours</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-bookmark"></i></div>
    <div><div class="stat-val"><?= $stats['reservations'] ?></div><div class="stat-lbl">Réservations actives</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-times-circle"></i></div>
    <div><div class="stat-val"><?= $stats['annulations_jour'] ?></div><div class="stat-lbl">Annulations aujourd'hui</div></div>
  </div>
</div>

<!-- GRAPHIQUE 7 JOURS + VOYAGES DU JOUR -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;">
  <div class="card">
    <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Recettes des 7 derniers jours</h3></div>
    <div class="card-body">
      <?php
      $maxRec = max(1, max(array_column($recettes7,'total') ?: [1]));
      $jours  = ['Lun','Mar','Mer','Jeu','Ven','Sam','Dim'];
      ?>
      <div style="display:flex;align-items:flex-end;gap:8px;height:110px;">
        <?php foreach($recettes7 as $r):
          $h=round($r['total']/$maxRec*100); $dt=new DateTime($r['jour']);
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;">
          <div style="font-size:9px;color:var(--text3);"><?= number_format($r['total']/1000,0) ?>K</div>
          <div style="width:100%;height:<?= $h ?>px;background:var(--primary);border-radius:4px 4px 0 0;min-height:4px;position:relative;" data-tip="<?= money($r['total']) ?>"></div>
          <div style="font-size:10px;color:var(--text3);"><?= $dt->format('d/m') ?></div>
        </div>
        <?php endforeach; ?>
        <?php if(empty($recettes7)): ?><div style="width:100%;text-align:center;color:var(--text3);font-size:12px;">Aucune donnée</div><?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><h3><i class="fas fa-bell"></i> Notifications</h3><a href="<?= BASE_URL ?>modules/notifications.php" class="btn btn-ghost btn-sm">Voir tout</a></div>
    <div class="card-body" style="padding:0;">
      <?php foreach($notifs as $n): ?>
      <div style="padding:9px 14px;border-bottom:1px solid var(--border);display:flex;gap:8px;">
        <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:5px;flex-shrink:0;"></div>
        <div><div style="font-size:12px;font-weight:500;"><?= sanitize($n['titre']) ?></div><div style="font-size:11px;color:var(--text3);"><?= timeAgo($n['created_at']) ?></div></div>
      </div>
      <?php endforeach; ?>
      <?php if(empty($notifs)): ?><div style="padding:20px;text-align:center;font-size:12px;color:var(--text3);">✓ Aucune nouvelle notification</div><?php endif; ?>
    </div>
  </div>
</div>

<!-- VOYAGES DU JOUR -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header">
    <h3><i class="fas fa-route"></i> Voyages du <?= fdate($today) ?></h3>
    <?php if(can('voyages.create')): ?><a href="<?= BASE_URL ?>modules/voyages/ajouter.php" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Nouveau voyage</a><?php endif; ?>
  </div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Numéro</th><th>Trajet</th><th>Véhicule</th><th>Chauffeur</th><th>Départ</th><th>Places</th><th>Statut</th><th>Actions</th></tr></thead>
      <tbody>
        <?php foreach($voyages as $v):
          $pct=$v['places_dispo']>0?min(100,round($v['nb_places_vendues']/$v['places_dispo']*100)):100;
        ?>
        <tr>
          <td><code style="font-size:11px;"><?= sanitize($v['numero']) ?></code></td>
          <td><strong><?= sanitize($v['dep']) ?></strong> → <strong><?= sanitize($v['arr']) ?></strong></td>
          <td><?= sanitize($v['immatriculation']??'—') ?></td>
          <td><?= sanitize($v['chauffeur']??'—') ?></td>
          <td><?= date('H:i',strtotime($v['date_depart'])) ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:6px;min-width:80px;">
              <div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $pct>=90?'var(--danger)':($pct>=70?'var(--warning)':'var(--success)') ?>"></div></div>
              <span style="font-size:10px;"><?= $v['nb_places_vendues'] ?>/<?= $v['places_dispo'] ?></span>
            </div>
          </td>
          <td><span class="tag-statut st-<?= $v['statut'] ?>"><?= statutLabel($v['statut']) ?></span></td>
          <td>
            <div style="display:flex;gap:3px;">
              <?php if(can('tickets.create')): ?><a href="<?= BASE_URL ?>modules/tickets/vente.php?voyage_id=<?= $v['id'] ?>" class="btn btn-xs btn-primary" title="Vendre"><i class="fas fa-ticket-alt"></i></a><?php endif; ?>
              <?php if(can('bordereaux.create')): ?><a href="<?= BASE_URL ?>modules/bordereaux/generer.php?voyage_id=<?= $v['id'] ?>" class="btn btn-xs btn-info" title="Bordereau"><i class="fas fa-file-invoice"></i></a><?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($voyages)): ?><tr><td colspan="8" class="t-empty"><i class="fas fa-route"></i>Aucun voyage programmé</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- VÉHICULES EN PANNE -->
<?php if(!empty($pannes)): ?>
<div class="card">
  <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i> Véhicules en panne</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Immatriculation</th><th>Marque</th><th>Agence</th><th>Statut</th></tr></thead>
      <tbody>
        <?php foreach($pannes as $p): ?>
        <tr>
          <td><strong><?= sanitize($p['immatriculation']) ?></strong></td>
          <td><?= sanitize($p['marque']??'—') ?> <?= sanitize($p['modele']??'') ?></td>
          <td><?= sanitize($p['ville']??'—') ?></td>
          <td><span class="tag-statut st-panne">En panne</span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- ACCÈS RAPIDE -->
<div class="card" style="margin-top:20px;">
  <div class="card-header"><h3><i class="fas fa-bolt"></i> Accès rapide</h3></div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;">
    <?php if(can('tickets.create')): ?><a href="<?= BASE_URL ?>modules/tickets/vente.php" class="btn btn-primary"><i class="fas fa-ticket-alt"></i> Vendre un ticket</a><?php endif; ?>
    <?php if(can('bordereaux.create')): ?><a href="<?= BASE_URL ?>modules/bordereaux/generer.php" class="btn btn-info"><i class="fas fa-file-invoice"></i> Générer bordereau</a><?php endif; ?>
    <?php if(can('rapports.agence')): ?><a href="<?= BASE_URL ?>modules/rapports/agence.php" class="btn btn-success"><i class="fas fa-chart-bar"></i> Rapport journalier</a><?php endif; ?>
    <?php if(can('depenses.create')): ?><a href="<?= BASE_URL ?>modules/depenses/ajouter.php" class="btn btn-warning"><i class="fas fa-plus"></i> Dépense</a><?php endif; ?>
    <?php if(can('versements.create')): ?><a href="<?= BASE_URL ?>modules/versements/ajouter.php" class="btn btn-orange"><i class="fas fa-university"></i> Versement</a><?php endif; ?>
    <?php if(can('voyages.create')): ?><a href="<?= BASE_URL ?>modules/voyages/ajouter.php" class="btn btn-secondary"><i class="fas fa-route"></i> Nouveau voyage</a><?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>