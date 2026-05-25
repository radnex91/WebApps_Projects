<?php
// modules/rapports/index.php
require_once '../../includes/config.php';
requireLogin();
$pageTitle = 'Rapports & Statistiques';
$annee = getAnneeActive($pdo);
$annee_id = $annee['id'] ?? 1;

// Stats globales
$nb_eleves   = $pdo->query("SELECT COUNT(*) FROM eleves WHERE statut='actif'")->fetchColumn();
$nb_filles   = $pdo->query("SELECT COUNT(*) FROM eleves WHERE statut='actif' AND sexe='F'")->fetchColumn();
$nb_garcons  = $nb_eleves - $nb_filles;
$nb_inscrits = $pdo->query("SELECT COUNT(*) FROM inscriptions WHERE annee_id=$annee_id")->fetchColumn();
$nb_classes  = $pdo->query("SELECT COUNT(*) FROM classes WHERE annee_id=$annee_id")->fetchColumn();
$nb_ens      = $pdo->query("SELECT COUNT(*) FROM enseignants WHERE statut='actif'")->fetchColumn();
$total_paye  = $pdo->query("SELECT COALESCE(SUM(p.montant),0) FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=$annee_id")->fetchColumn();
$total_frais = $pdo->query("SELECT COALESCE(SUM(frais_scolarite),0) FROM inscriptions WHERE annee_id=$annee_id")->fetchColumn();

// Répartition par cycle
$par_cycle = $pdo->query("SELECT n.cycle, COUNT(DISTINCT i.eleve_id) as nb FROM inscriptions i JOIN classes c ON i.classe_id=c.id JOIN niveaux n ON c.niveau_id=n.id WHERE i.annee_id=$annee_id GROUP BY n.cycle ORDER BY MIN(n.ordre)")->fetchAll();

// Répartition par classe
$par_classe = $pdo->query("SELECT c.nom as classe, n.nom as niveau, n.cycle, COUNT(i.id) as nb FROM inscriptions i JOIN classes c ON i.classe_id=c.id JOIN niveaux n ON c.niveau_id=n.id WHERE i.annee_id=$annee_id GROUP BY c.id ORDER BY n.ordre, c.nom")->fetchAll();

// Top élèves par moyenne
$top_eleves = $pdo->query("SELECT e.nom, e.prenom, cl.nom as classe, ROUND(SUM(n.note*m.coefficient)/NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN m.coefficient ELSE 0 END),0),2) as moy_gen FROM eleves e JOIN inscriptions i ON i.eleve_id=e.id JOIN classes cl ON i.classe_id=cl.id LEFT JOIN notes n ON n.eleve_id=e.id AND n.annee_id=$annee_id LEFT JOIN matieres m ON n.matiere_id=m.id WHERE i.annee_id=$annee_id GROUP BY e.id HAVING moy_gen IS NOT NULL ORDER BY moy_gen DESC LIMIT 10")->fetchAll();

// Paiements par mois
$paiements_mois = $pdo->query("SELECT MONTH(p.date_paiement) as mois, MONTHNAME(p.date_paiement) as nom_mois, SUM(p.montant) as total FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=$annee_id AND YEAR(p.date_paiement)=YEAR(CURDATE()) GROUP BY MONTH(p.date_paiement) ORDER BY mois")->fetchAll();

// Absences par classe
$abs_classe = $pdo->query("SELECT cl.nom as classe, COUNT(a.id) as nb_abs, SUM(a.justifie) as justif FROM absences a JOIN inscriptions i ON i.eleve_id=a.eleve_id AND i.annee_id=$annee_id JOIN classes cl ON i.classe_id=cl.id WHERE a.annee_id=$annee_id GROUP BY cl.id ORDER BY nb_abs DESC LIMIT 10")->fetchAll();

include '../../includes/header.php';
?>
<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep"></span>Rapports</div>

<div style="display:flex;justify-content:flex-end;margin-bottom:12px;" class="no-print">
  <button onclick="window.print()" class="btn btn-primary btn-sm"><i class="fas fa-print"></i> Imprimer le rapport</button>
</div>

<!-- STATS GLOBALES -->
<div class="stats-grid">
  <div class="stat-card"><div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div><div><div class="stat-value"><?= $nb_eleves ?></div><div class="stat-label">Élèves actifs</div></div></div>
  <div class="stat-card"><div class="stat-icon purple"><i class="fas fa-venus"></i></div><div><div class="stat-value"><?= $nb_filles ?> <small style="font-size:14px;">(<?= $nb_eleves>0?round($nb_filles/$nb_eleves*100).'%':'—' ?>)</small></div><div class="stat-label">Filles</div></div></div>
  <div class="stat-card"><div class="stat-icon teal"><i class="fas fa-mars"></i></div><div><div class="stat-value"><?= $nb_garcons ?> <small style="font-size:14px;">(<?= $nb_eleves>0?round($nb_garcons/$nb_eleves*100).'%':'—' ?>)</small></div><div class="stat-label">Garçons</div></div></div>
  <div class="stat-card"><div class="stat-icon orange"><i class="fas fa-chalkboard"></i></div><div><div class="stat-value"><?= $nb_classes ?></div><div class="stat-label">Classes</div></div></div>
  <div class="stat-card"><div class="stat-icon green"><i class="fas fa-money-bill-wave"></i></div><div><div class="stat-value" style="font-size:18px;"><?= number_format($total_paye,0,',',' ') ?></div><div class="stat-label">Encaissé (FCFA)</div></div></div>
  <div class="stat-card"><div class="stat-icon red"><i class="fas fa-exclamation-circle"></i></div><div><div class="stat-value" style="font-size:18px;"><?= number_format($total_frais-$total_paye,0,',',' ') ?></div><div class="stat-label">Reste à percevoir</div></div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
<!-- RÉPARTITION CYCLES -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-layer-group"></i> Effectifs par cycle</h2></div>
  <div class="card-body">
    <?php foreach($par_cycle as $c):
      $pct = $nb_eleves > 0 ? round($c['nb']/$nb_eleves*100) : 0;
      $cycle_cls = strtolower(str_replace(['é','è','ê'],'e',$c['cycle']));
    ?>
    <div style="margin-bottom:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span class="badge cycle-<?= $cycle_cls ?>"><?= sanitize($c['cycle']) ?></span>
        <strong><?= $c['nb'] ?> élèves (<?= $pct ?>%)</strong>
      </div>
      <div style="background:#f1f5f9;border-radius:20px;height:12px;overflow:hidden;">
        <div style="width:<?= $pct ?>%;height:100%;background:var(--primary);border-radius:20px;transition:width .5s;"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<!-- TOP ÉLÈVES -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-trophy"></i> Top 10 des meilleures moyennes</h2></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Rang</th><th>Élève</th><th>Classe</th><th>Moy.</th></tr></thead>
      <tbody>
        <?php foreach($top_eleves as $rang => $e): ?>
        <tr>
          <td><?= $rang<3 ? '<i class="fas fa-medal" style="color:'.['gold','silver','#cd7f32'][$rang].'"></i> '.($rang+1) : $rang+1 ?></td>
          <td><strong><?= sanitize($e['nom'].' '.$e['prenom']) ?></strong></td>
          <td><?= sanitize($e['classe']) ?></td>
          <td><strong style="color:<?= $e['moy_gen']>=10?'var(--success)':'var(--danger)' ?>"><?= $e['moy_gen'] ?>/20</strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($top_eleves)): ?><tr><td colspan="4" style="text-align:center;padding:20px;color:var(--text-muted)">Aucune note enregistrée</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
<!-- EFFECTIFS PAR CLASSE -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-chalkboard"></i> Effectifs par classe</h2></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Classe</th><th>Niveau</th><th>Cycle</th><th>Élèves</th></tr></thead>
      <tbody>
        <?php foreach($par_classe as $c):
          $cc = strtolower(str_replace(['é','è','ê'],'e',$c['cycle']));
        ?>
        <tr>
          <td><strong><?= sanitize($c['classe']) ?></strong></td>
          <td><?= sanitize($c['niveau']) ?></td>
          <td><span class="badge cycle-<?= $cc ?>"><?= sanitize($c['cycle']) ?></span></td>
          <td><strong><?= $c['nb'] ?></strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($par_classe)): ?><tr><td colspan="4" class="empty-state">Aucune donnée</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- PAIEMENTS PAR MOIS -->
<div class="card">
  <div class="card-header"><h2><i class="fas fa-chart-line"></i> Paiements par mois</h2></div>
  <div class="card-body">
    <?php 
    $max_mois = $paiements_mois ? max(array_column($paiements_mois,'total')) : 1;
    foreach($paiements_mois as $pm):
      $pct = $max_mois > 0 ? round($pm['total']/$max_mois*100) : 0;
    ?>
    <div style="margin-bottom:10px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:3px;font-size:13px;">
        <span><?= sanitize($pm['nom_mois']) ?></span>
        <strong><?= number_format($pm['total'],0,',',' ') ?> FCFA</strong>
      </div>
      <div style="background:#f1f5f9;border-radius:20px;height:10px;overflow:hidden;">
        <div style="width:<?= $pct ?>%;height:100%;background:var(--success);border-radius:20px;"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($paiements_mois)): ?><div class="empty-state"><i class="fas fa-chart-line"></i><p>Aucun paiement enregistré</p></div><?php endif; ?>
  </div>
</div>
</div>

<!-- ABSENCES -->
<?php if(!empty($abs_classe)): ?>
<div class="card" style="margin-top:20px;">
  <div class="card-header"><h2><i class="fas fa-user-clock"></i> Absences par classe</h2></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Classe</th><th>Total absences</th><th>Justifiées</th><th>Non justifiées</th></tr></thead>
      <tbody>
        <?php foreach($abs_classe as $a): ?>
        <tr>
          <td><?= sanitize($a['classe']) ?></td>
          <td><strong><?= $a['nb_abs'] ?></strong></td>
          <td><span class="badge badge-success"><?= $a['justif']??0 ?></span></td>
          <td><span class="badge badge-danger"><?= $a['nb_abs']-($a['justif']??0) ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>
