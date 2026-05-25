<?php
// index.php
require_once 'includes/config.php';
requireLogin();
$pageTitle = 'Tableau de bord';

$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

$stats = [];
$stats['eleves']      = $pdo->query("SELECT COUNT(*) FROM eleves WHERE statut='actif'")->fetchColumn();
$stats['enseignants'] = $pdo->query("SELECT COUNT(*) FROM enseignants WHERE statut='actif'")->fetchColumn();
$stats['classes']     = $pdo->query("SELECT COUNT(*) FROM classes WHERE annee_id=$annee_id")->fetchColumn();
$stats['parents']     = $pdo->query("SELECT COUNT(*) FROM parents")->fetchColumn();
$stats['inscriptions']= $pdo->query("SELECT COUNT(*) FROM inscriptions WHERE annee_id=$annee_id")->fetchColumn();

// Paiements du mois
$stats['paiements']   = $pdo->query("SELECT COALESCE(SUM(p.montant),0) FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE MONTH(p.date_paiement)=MONTH(CURDATE()) AND YEAR(p.date_paiement)=YEAR(CURDATE())")->fetchColumn();

// Répartition par cycle
$cycles = $pdo->query("SELECT n.cycle, COUNT(DISTINCT i.eleve_id) as nb FROM inscriptions i JOIN classes c ON i.classe_id=c.id JOIN niveaux n ON c.niveau_id=n.id WHERE i.annee_id=$annee_id GROUP BY n.cycle ORDER BY MIN(n.ordre)")->fetchAll();

// Derniers élèves inscrits
$recents = $pdo->query("SELECT e.nom, e.prenom, e.matricule, n.nom as niveau, n.cycle, i.date_inscription FROM inscriptions i JOIN eleves e ON i.eleve_id=e.id JOIN classes c ON i.classe_id=c.id JOIN niveaux n ON c.niveau_id=n.id WHERE i.annee_id=$annee_id ORDER BY i.date_inscription DESC LIMIT 8")->fetchAll();

include 'includes/header.php';
?>

<div class="breadcrumb">
  <i class="fas fa-home"></i> <a href="<?= BASE_URL ?>">Accueil</a>
  <span class="breadcrumb-sep"></span> Tableau de bord
</div>

<?php if($annee): ?>
<div class="alert alert-info" style="margin-bottom:16px;">
  <i class="fas fa-calendar-alt"></i>
  Année scolaire active : <strong><?= sanitize($annee['libelle']) ?></strong>
  &nbsp;|&nbsp; <?= date('d/m/Y', strtotime($annee['date_debut'])) ?> → <?= date('d/m/Y', strtotime($annee['date_fin'])) ?>
</div>
<?php endif; ?>

<!-- STATS -->
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
    <div>
      <div class="stat-value"><?= number_format($stats['eleves']) ?></div>
      <div class="stat-label">Élèves actifs</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon purple"><i class="fas fa-chalkboard-teacher"></i></div>
    <div>
      <div class="stat-value"><?= number_format($stats['enseignants']) ?></div>
      <div class="stat-label">Enseignants</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon orange"><i class="fas fa-chalkboard"></i></div>
    <div>
      <div class="stat-value"><?= number_format($stats['classes']) ?></div>
      <div class="stat-label">Classes</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon teal"><i class="fas fa-users"></i></div>
    <div>
      <div class="stat-value"><?= number_format($stats['parents']) ?></div>
      <div class="stat-label">Parents</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i class="fas fa-file-signature"></i></div>
    <div>
      <div class="stat-value"><?= number_format($stats['inscriptions']) ?></div>
      <div class="stat-label">Inscrits cette année</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon red"><i class="fas fa-money-bill-wave"></i></div>
    <div>
      <div class="stat-value"><?= number_format($stats['paiements'], 0, ',', ' ') ?></div>
      <div class="stat-label">Paiements ce mois (FCFA)</div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
<!-- CYCLES -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-layer-group"></i> Effectifs par cycle</h2>
  </div>
  <div class="card-body">
    <?php foreach($cycles as $c): 
      $badge = strtolower(str_replace(['é','è','ê'],'e', $c['cycle']));
    ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
      <span class="badge cycle-<?= $badge ?>"><?= sanitize($c['cycle']) ?></span>
      <div style="flex:1;margin:0 12px;background:#f1f5f9;border-radius:20px;height:10px;overflow:hidden;">
        <div style="height:100%;background:var(--primary);border-radius:20px;width:<?= min(100, ($c['nb']/$stats['eleves'])*100) ?>%"></div>
      </div>
      <strong><?= $c['nb'] ?> élèves</strong>
    </div>
    <?php endforeach; ?>
    <?php if(empty($cycles)): ?>
    <div class="empty-state"><i class="fas fa-inbox"></i><p>Aucune donnée</p></div>
    <?php endif; ?>
  </div>
</div>

<!-- DERNIÈRES INSCRIPTIONS -->
<div class="card">
  <div class="card-header">
    <h2><i class="fas fa-clock"></i> Dernières inscriptions</h2>
    <a href="<?= BASE_URL ?>modules/inscription/" class="btn btn-sm btn-secondary">Voir tout</a>
  </div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Élève</th><th>Niveau</th><th>Date</th></tr></thead>
      <tbody>
      <?php foreach($recents as $r): 
        $badge = strtolower(str_replace(['é','è','ê'],'e', $r['cycle']));
      ?>
      <tr>
        <td>
          <div class="avatar" style="display:inline-flex;margin-right:8px;font-size:11px;width:30px;height:30px;"><?= strtoupper(substr($r['prenom'],0,1).substr($r['nom'],0,1)) ?></div>
          <?= sanitize($r['prenom'].' '.$r['nom']) ?>
        </td>
        <td><span class="badge cycle-<?= $badge ?>"><?= sanitize($r['niveau']) ?></span></td>
        <td style="color:var(--text-muted);font-size:12px;"><?= date('d/m/Y', strtotime($r['date_inscription'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(empty($recents)): ?>
      <tr><td colspan="3" class="empty-state"><i class="fas fa-inbox"></i><p>Aucune inscription</p></td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- RACCOURCIS -->
<div class="card" style="margin-top:0;">
  <div class="card-header"><h2><i class="fas fa-bolt"></i> Accès rapide</h2></div>
  <div class="card-body" style="display:flex;flex-wrap:wrap;gap:10px;">
    <a href="<?= BASE_URL ?>modules/eleves/ajouter.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nouvel élève</a>
    <a href="<?= BASE_URL ?>modules/inscription/" class="btn btn-success"><i class="fas fa-file-signature"></i> Inscrire un élève</a>
    <a href="<?= BASE_URL ?>modules/notes/" class="btn btn-warning"><i class="fas fa-star-half-alt"></i> Saisir des notes</a>
    <a href="<?= BASE_URL ?>modules/bulletins/" class="btn btn-secondary"><i class="fas fa-print"></i> Imprimer bulletins</a>
    <a href="<?= BASE_URL ?>modules/enseignants/ajouter.php" class="btn btn-outline btn-primary"><i class="fas fa-plus"></i> Nouvel enseignant</a>
    <a href="<?= BASE_URL ?>modules/rapports/" class="btn btn-outline btn-secondary"><i class="fas fa-chart-bar"></i> Rapports</a>
  </div>
</div>

<?php include 'includes/footer.php'; ?>
