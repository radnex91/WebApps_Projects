<?php
require_once 'includes/config.php';
requireLogin();
$pageTitle = 'Tableau de bord';
$annee = getAnneeActive($pdo);
$aid = $annee['id'] ?? 1;

// Stats globales
$stats = [
    'eleves'      => $pdo->query("SELECT COUNT(*) FROM eleves WHERE statut='actif'")->fetchColumn(),
    'enseignants' => $pdo->query("SELECT COUNT(*) FROM enseignants WHERE statut='actif'")->fetchColumn(),
    'classes'     => $pdo->query("SELECT COUNT(*) FROM classes WHERE annee_id=$aid")->fetchColumn(),
    'inscrits'    => $pdo->query("SELECT COUNT(*) FROM inscriptions WHERE annee_id=$aid AND statut='actif'")->fetchColumn(),
    'garcons'     => $pdo->query("SELECT COUNT(*) FROM eleves WHERE sexe='M' AND statut='actif'")->fetchColumn(),
    'filles'      => $pdo->query("SELECT COUNT(*) FROM eleves WHERE sexe='F' AND statut='actif'")->fetchColumn(),
];
$stats['paiements'] = $pdo->query("SELECT COALESCE(SUM(p.montant),0) FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=$aid AND MONTH(p.date_paiement)=MONTH(CURDATE())")->fetchColumn();

// Par filière
$parFiliere = $pdo->query("SELECT f.nom,f.couleur,COUNT(DISTINCT i.eleve_id) as nb FROM inscriptions i JOIN classes c ON i.classe_id=c.id JOIN filieres f ON c.filiere_id=f.id WHERE i.annee_id=$aid GROUP BY f.id ORDER BY nb DESC")->fetchAll();

// Classes remplissage
$classesInfo = $pdo->query("SELECT c.nom,c.capacite,n.nom as niveau,f.nom as filiere,f.couleur,COUNT(i.id) as inscrits FROM classes c JOIN niveaux n ON c.niveau_id=n.id JOIN filieres f ON c.filiere_id=f.id LEFT JOIN inscriptions i ON i.classe_id=c.id AND i.annee_id=$aid WHERE c.annee_id=$aid GROUP BY c.id ORDER BY n.ordre LIMIT 10")->fetchAll();

// Top élèves
$topEleves = $pdo->query("SELECT e.nom,e.prenom,cl.nom as classe, ROUND(SUM(n.note*mc.coefficient)/NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN mc.coefficient ELSE 0 END),0),2) as moy FROM eleves e JOIN inscriptions i ON i.eleve_id=e.id JOIN classes cl ON i.classe_id=cl.id LEFT JOIN notes n ON n.eleve_id=e.id AND n.annee_id=$aid JOIN matiere_classe mc ON mc.matiere_id=n.matiere_id AND mc.classe_id=n.classe_id WHERE i.annee_id=$aid GROUP BY e.id HAVING moy IS NOT NULL ORDER BY moy DESC LIMIT 5")->fetchAll();

// Activité récente (logs)
$recentLogs = $pdo->query("SELECT l.*,u.nom,u.prenom FROM logs l LEFT JOIN users u ON l.user_id=u.id ORDER BY l.created_at DESC LIMIT 8")->fetchAll();

include 'includes/header.php';
?>

<div class="breadcrumb">
  <i class="fas fa-home"></i> <a href="<?= BASE_URL ?>">Accueil</a>
  <span class="breadcrumb-sep">/</span> Tableau de bord
</div>

<?php if($annee): ?>
<div class="flash-alert flash-info" style="border-radius:var(--radius);margin-bottom:20px;">
  <i class="fas fa-calendar-alt"></i>
  <strong>Année scolaire active :</strong> <?= sanitize($annee['libelle']) ?> &nbsp;·&nbsp;
  <?= formatDate($annee['date_debut']) ?> → <?= formatDate($annee['date_fin']) ?>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-user-graduate"></i></div>
    <div><div class="stat-value"><?= number_format($stats['eleves']) ?></div><div class="stat-label">Élèves actifs</div>
    <div class="stat-change"><i class="fas fa-venus"></i> <?= $stats['filles'] ?> F &nbsp; <i class="fas fa-mars"></i> <?= $stats['garcons'] ?> M</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-chalkboard-teacher"></i></div>
    <div><div class="stat-value"><?= $stats['enseignants'] ?></div><div class="stat-label">Enseignants</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#0891b2,#38bdf8)"><i class="fas fa-chalkboard"></i></div>
    <div><div class="stat-value"><?= $stats['classes'] ?></div><div class="stat-label">Classes</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-file-signature"></i></div>
    <div><div class="stat-value"><?= $stats['inscrits'] ?></div><div class="stat-label">Inscrits cette année</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#d97706,#fbbf24)"><i class="fas fa-money-bill-wave"></i></div>
    <div><div class="stat-value" style="font-size:16px;"><?= number_format($stats['paiements'],0,',',' ') ?></div><div class="stat-label">Paiements ce mois (FCFA)</div></div>
  </div>
</div>

<div class="grid-2" style="gap:20px;margin-bottom:20px;">
<!-- PAR FILIÈRE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-layer-group"></i> Effectifs par filière</h3></div>
  <div class="card-body">
    <?php foreach($parFiliere as $f):
      $pct = $stats['eleves']>0 ? round($f['nb']/$stats['eleves']*100) : 0;
    ?>
    <div style="margin-bottom:12px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:13px;">
        <span style="display:flex;align-items:center;gap:6px;">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= sanitize($f['couleur']) ?>;display:inline-block;"></span>
          <?= sanitize($f['nom']) ?>
        </span>
        <strong><?= $f['nb'] ?> élèves</strong>
      </div>
      <div class="progress"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= sanitize($f['couleur']) ?>"></div></div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($parFiliere)): ?><div class="empty-state"><i class="fas fa-chart-bar"></i><p>Aucune donnée</p></div><?php endif; ?>
  </div>
</div>

<!-- TOP ÉLÈVES -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-trophy"></i> Meilleures moyennes</h3>
    <a href="<?= BASE_URL ?>modules/rapports.php" class="btn btn-ghost btn-sm">Tout voir</a></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Rang</th><th>Élève</th><th>Classe</th><th>Moy.</th></tr></thead>
      <tbody>
        <?php foreach($topEleves as $r => $e): 
          $medals = ['🥇','🥈','🥉'];
          $mention = getMention((float)$e['moy']);
        ?>
        <tr>
          <td><?= $medals[$r] ?? ($r+1) ?></td>
          <td><strong><?= sanitize($e['prenom'].' '.$e['nom']) ?></strong></td>
          <td><?= sanitize($e['classe']) ?></td>
          <td><span class="<?= $mention['class'] ?>"><?= $e['moy'] ?>/20</span></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($topEleves)): ?><tr><td colspan="4" class="table-empty">Aucune note saisie</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div class="grid-2" style="gap:20px;">
<!-- CLASSES -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-chalkboard"></i> Occupation des classes</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Classe</th><th>Filière</th><th>Inscrits</th><th>Taux</th></tr></thead>
      <tbody>
        <?php foreach($classesInfo as $c):
          $pct = $c['capacite']>0 ? min(100,round($c['inscrits']/$c['capacite']*100)) : 0;
          $col = $pct>=90?'var(--danger)':($pct>=70?'var(--warning)':'var(--success)');
        ?>
        <tr>
          <td><strong><?= sanitize($c['nom']) ?></strong></td>
          <td><span class="filiere-badge" style="background:<?= sanitize($c['couleur']) ?>20;color:<?= sanitize($c['couleur']) ?>"><?= sanitize($c['filiere']) ?></span></td>
          <td><?= $c['inscrits'] ?>/<?= $c['capacite'] ?></td>
          <td style="min-width:80px;">
            <div style="display:flex;align-items:center;gap:6px;">
              <div class="progress" style="flex:1;"><div class="progress-bar" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
              <span style="font-size:11px;color:var(--text3);"><?= $pct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ACTIVITÉ RÉCENTE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-history"></i> Activité récente</h3></div>
  <div class="card-body" style="padding:0;">
    <?php foreach($recentLogs as $log): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:9px 16px;border-bottom:1px solid var(--border);">
      <div class="avatar avatar-sm" style="background:var(--primary-bg);color:var(--primary);">
        <i class="fas fa-user" style="font-size:10px;"></i>
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:12px;font-weight:500;"><?= sanitize(($log['prenom']??'').' '.($log['nom']??'Système')) ?></div>
        <div style="font-size:11px;color:var(--text3);"><?= sanitize($log['action']) ?> — <?= sanitize($log['module']) ?></div>
      </div>
      <div style="font-size:11px;color:var(--text3);white-space:nowrap;"><?= timeAgo($log['created_at']) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($recentLogs)): ?><div class="empty-state"><i class="fas fa-history"></i><p>Aucune activité</p></div><?php endif; ?>
  </div>
</div>
</div>

<!-- RACCOURCIS -->
<div class="card" style="margin-top:20px;">
  <div class="card-header"><h3><i class="fas fa-bolt"></i> Accès rapide</h3></div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;">
    <?php if(can('eleves.create')): ?>
    <a href="<?= BASE_URL ?>modules/eleves/ajouter.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Nouvel élève</a>
    <?php endif; ?>
    <?php if(can('eleves.view')): ?>
    <a href="<?= BASE_URL ?>modules/inscription.php" class="btn btn-success"><i class="fas fa-file-signature"></i> Inscrire</a>
    <?php endif; ?>
    <?php if(can('notes.create')): ?>
    <a href="<?= BASE_URL ?>modules/notes/" class="btn btn-warning"><i class="fas fa-star-half-alt"></i> Saisir notes</a>
    <?php endif; ?>
    <?php if(can('bulletins.print')): ?>
    <a href="<?= BASE_URL ?>modules/bulletins/" class="btn btn-info"><i class="fas fa-file-alt"></i> Bulletins</a>
    <?php endif; ?>
    <?php if(can('bulletins.config')): ?>
    <a href="<?= BASE_URL ?>modules/parametres/" class="btn btn-secondary"><i class="fas fa-paint-brush"></i> Config bulletin</a>
    <?php endif; ?>
    <?php if(can('rapports.view')): ?>
    <a href="<?= BASE_URL ?>modules/rapports.php" class="btn btn-outline"><i class="fas fa-chart-bar"></i> Rapports</a>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
