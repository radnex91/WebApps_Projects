<?php
// modules/rapports.php
require_once '../includes/config.php';
requireLogin(); requirePerm('rapports.view');
$pageTitle = 'Rapports & Statistiques';
$annee = getAnneeActive($pdo);
$aid   = $annee['id'] ?? 1;

// Stats globales
$stats = [
    'total_eleves'    => $pdo->query("SELECT COUNT(*) FROM eleves WHERE statut='actif'")->fetchColumn(),
    'total_inscrits'  => $pdo->query("SELECT COUNT(*) FROM inscriptions WHERE annee_id=$aid AND statut='actif'")->fetchColumn(),
    'total_classes'   => $pdo->query("SELECT COUNT(*) FROM classes WHERE annee_id=$aid")->fetchColumn(),
    'total_ens'       => $pdo->query("SELECT COUNT(*) FROM enseignants WHERE statut='actif'")->fetchColumn(),
    'total_paiements' => $pdo->query("SELECT COALESCE(SUM(p.montant),0) FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=$aid")->fetchColumn(),
    'total_attendu'   => $pdo->query("SELECT COALESCE(SUM(frais_scolarite),0) FROM inscriptions WHERE annee_id=$aid")->fetchColumn(),
    'garcons'         => $pdo->query("SELECT COUNT(*) FROM eleves WHERE sexe='M' AND statut='actif'")->fetchColumn(),
    'filles'          => $pdo->query("SELECT COUNT(*) FROM eleves WHERE sexe='F' AND statut='actif'")->fetchColumn(),
];
$stats['taux_paiement'] = $stats['total_attendu']>0 ? round($stats['total_paiements']/$stats['total_attendu']*100, 1) : 0;
$stats['reste_paiement']= $stats['total_attendu'] - $stats['total_paiements'];

// Effectifs par filière
$parFiliere = $pdo->query("SELECT f.nom, f.couleur, COUNT(DISTINCT i.eleve_id) as nb_eleves, COUNT(DISTINCT c.id) as nb_classes FROM filieres f JOIN classes c ON c.filiere_id=f.id LEFT JOIN inscriptions i ON i.classe_id=c.id AND i.annee_id=$aid WHERE c.annee_id=$aid GROUP BY f.id ORDER BY nb_eleves DESC")->fetchAll();

// Effectifs par niveau
$parNiveau = $pdo->query("SELECT n.nom, n.cycle, COUNT(DISTINCT i.eleve_id) as nb FROM niveaux n JOIN classes c ON c.niveau_id=n.id LEFT JOIN inscriptions i ON i.classe_id=c.id AND i.annee_id=$aid WHERE c.annee_id=$aid GROUP BY n.id ORDER BY n.ordre")->fetchAll();

// Top 10 élèves (toutes périodes)
$topEleves = $pdo->query("SELECT e.nom, e.prenom, e.matricule, cl.nom as classe, f.nom as filiere, f.couleur,
    ROUND(SUM(n.note*mc.coefficient)/NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN mc.coefficient ELSE 0 END),0),2) as moy
    FROM eleves e JOIN inscriptions i ON i.eleve_id=e.id AND i.annee_id=$aid
    JOIN classes cl ON i.classe_id=cl.id JOIN filieres f ON cl.filiere_id=f.id
    LEFT JOIN notes n ON n.eleve_id=e.id AND n.annee_id=$aid
    JOIN matiere_classe mc ON mc.matiere_id=n.matiere_id AND mc.classe_id=n.classe_id
    GROUP BY e.id HAVING moy IS NOT NULL ORDER BY moy DESC LIMIT 10")->fetchAll();

// Paiements par mois
$parMois = $pdo->query("SELECT DATE_FORMAT(p.date_paiement,'%Y-%m') as mois, DATE_FORMAT(p.date_paiement,'%b %Y') as mois_label, SUM(p.montant) as total FROM paiements p JOIN inscriptions i ON p.inscription_id=i.id WHERE i.annee_id=$aid GROUP BY mois ORDER BY mois")->fetchAll();

// Élèves en difficultés (moy < 10)
$enDifficulte = $pdo->query("SELECT e.nom, e.prenom, cl.nom as classe,
    ROUND(SUM(n.note*mc.coefficient)/NULLIF(SUM(CASE WHEN n.note IS NOT NULL THEN mc.coefficient ELSE 0 END),0),2) as moy
    FROM eleves e JOIN inscriptions i ON i.eleve_id=e.id AND i.annee_id=$aid
    JOIN classes cl ON i.classe_id=cl.id
    LEFT JOIN notes n ON n.eleve_id=e.id AND n.annee_id=$aid
    JOIN matiere_classe mc ON mc.matiere_id=n.matiere_id AND mc.classe_id=n.classe_id
    GROUP BY e.id HAVING moy IS NOT NULL AND moy < 10 ORDER BY moy LIMIT 10")->fetchAll();

include '../includes/header.php';
?>

<div class="breadcrumb"><a href="<?= BASE_URL ?>"><i class="fas fa-home"></i></a><span class="breadcrumb-sep">/</span>Rapports</div>

<div class="flash-alert flash-info" style="border-radius:var(--radius);margin-bottom:20px;">
  <i class="fas fa-calendar-alt"></i> Rapport pour l'année scolaire <strong><?= sanitize($annee['libelle']??'') ?></strong>
  <button onclick="window.print()" class="btn btn-primary btn-sm" style="margin-left:auto;"><i class="fas fa-print"></i> Imprimer</button>
</div>

<!-- STATS CARDS -->
<div class="stats-grid" style="margin-bottom:24px;">
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#1d4ed8,#60a5fa)"><i class="fas fa-user-graduate"></i></div>
    <div><div class="stat-value"><?= $stats['total_inscrits'] ?></div><div class="stat-label">Élèves inscrits</div>
    <div class="stat-change">👦 <?= $stats['garcons'] ?> garçons · 👧 <?= $stats['filles'] ?> filles</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#7c3aed,#a78bfa)"><i class="fas fa-chalkboard"></i></div>
    <div><div class="stat-value"><?= $stats['total_classes'] ?></div><div class="stat-label">Classes</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#16a34a,#4ade80)"><i class="fas fa-money-bill-wave"></i></div>
    <div><div class="stat-value" style="font-size:15px;"><?= number_format($stats['total_paiements'],0,',',' ') ?></div>
    <div class="stat-label">Encaissé (FCFA)</div>
    <div class="stat-change">Taux : <?= $stats['taux_paiement'] ?>%</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:linear-gradient(135deg,#dc2626,#f87171)"><i class="fas fa-exclamation-circle"></i></div>
    <div><div class="stat-value" style="font-size:15px;"><?= number_format($stats['reste_paiement'],0,',',' ') ?></div>
    <div class="stat-label">Reste à payer (FCFA)</div></div>
  </div>
</div>

<div class="grid-2" style="gap:20px;margin-bottom:20px;">

<!-- EFFECTIFS PAR FILIÈRE -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-layer-group"></i> Effectifs par filière</h3></div>
  <div class="card-body">
    <?php foreach($parFiliere as $f):
      $pct = $stats['total_inscrits']>0 ? round($f['nb_eleves']/$stats['total_inscrits']*100) : 0;
    ?>
    <div style="margin-bottom:14px;">
      <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
        <span style="display:flex;align-items:center;gap:7px;">
          <span style="width:12px;height:12px;border-radius:50%;background:<?= sanitize($f['couleur']) ?>;display:inline-block;flex-shrink:0;"></span>
          <strong><?= sanitize($f['nom']) ?></strong>
          <span style="font-size:11px;color:var(--text3);">(<?= $f['nb_classes'] ?> classe<?= $f['nb_classes']>1?'s':'' ?>)</span>
        </span>
        <span style="font-weight:700;"><?= $f['nb_eleves'] ?> &nbsp;<span style="font-size:11px;color:var(--text3);"><?= $pct ?>%</span></span>
      </div>
      <div class="progress">
        <div class="progress-bar" style="width:<?= $pct ?>%;background:<?= sanitize($f['couleur']) ?>"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if(empty($parFiliere)): ?><div class="empty-state"><p>Aucune donnée</p></div><?php endif; ?>
  </div>
</div>

<!-- EFFECTIFS PAR NIVEAU -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-stairs"></i> Effectifs par niveau</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Niveau</th><th>Cycle</th><th>Élèves</th></tr></thead>
      <tbody>
        <?php foreach($parNiveau as $n): ?>
        <tr>
          <td><strong><?= sanitize($n['nom']) ?></strong></td>
          <td><span class="badge <?= $n['cycle']==='BTS'?'badge-secondary':'badge-primary' ?>"><?= sanitize($n['cycle']) ?></span></td>
          <td><strong><?= $n['nb'] ?></strong></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($parNiveau)): ?><tr><td colspan="3" class="table-empty">Aucun niveau</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<div class="grid-2" style="gap:20px;margin-bottom:20px;">

<!-- TOP ÉLÈVES -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-trophy"></i> Top 10 élèves (moyenne générale)</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Rang</th><th>Élève</th><th>Classe</th><th>Moy.</th><th>Mention</th></tr></thead>
      <tbody>
        <?php foreach($topEleves as $r=>$e):
          $mention = getMention((float)$e['moy']);
          $medals  = ['🥇','🥈','🥉'];
        ?>
        <tr>
          <td style="text-align:center;"><?= $medals[$r] ?? ($r+1) ?></td>
          <td>
            <div style="font-weight:600;"><?= sanitize($e['nom'].' '.$e['prenom']) ?></div>
            <div style="font-size:11px;color:var(--text3);"><?= sanitize($e['matricule']) ?></div>
          </td>
          <td><span class="filiere-badge" style="background:<?= sanitize($e['couleur']) ?>20;color:<?= sanitize($e['couleur']) ?>"><?= sanitize($e['classe']) ?></span></td>
          <td style="font-size:15px;font-weight:800;color:<?= $mention['color'] ?>"><?= $e['moy'] ?>/20</td>
          <td><span class="<?= $mention['class'] ?>"><?= $mention['label'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($topEleves)): ?><tr><td colspan="5" class="table-empty">Aucune note saisie</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ÉLÈVES EN DIFFICULTÉ -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-exclamation-triangle" style="color:var(--danger)"></i> Élèves en difficulté (moy. &lt; 10)</h3></div>
  <div class="card-body" style="padding:0;">
    <table>
      <thead><tr><th>Élève</th><th>Classe</th><th>Moy.</th></tr></thead>
      <tbody>
        <?php foreach($enDifficulte as $e): ?>
        <tr>
          <td><strong><?= sanitize($e['nom'].' '.$e['prenom']) ?></strong></td>
          <td><?= sanitize($e['classe']) ?></td>
          <td><span class="mention-insuf"><?= $e['moy'] ?>/20</span></td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($enDifficulte)): ?><tr><td colspan="3" style="text-align:center;padding:20px;color:var(--success);font-weight:500;"><i class="fas fa-check-circle"></i> Aucun élève en difficulté !</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
</div>

<!-- PAIEMENTS PAR MOIS -->
<?php if(!empty($parMois)): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><h3><i class="fas fa-chart-bar"></i> Paiements mensuels</h3></div>
  <div class="card-body">
    <?php $maxMois = max(array_column($parMois,'total')); ?>
    <div style="display:flex;align-items:flex-end;gap:8px;height:120px;padding-bottom:8px;">
      <?php foreach($parMois as $m):
        $h = $maxMois>0 ? round($m['total']/$maxMois*100) : 0;
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;">
        <div style="font-size:9px;color:var(--text3);text-align:center;"><?= number_format($m['total']/1000,0) ?>K</div>
        <div style="width:100%;height:<?= $h ?>px;background:var(--primary);border-radius:4px 4px 0 0;min-height:4px;"></div>
        <div style="font-size:9px;color:var(--text3);text-align:center;white-space:nowrap;"><?= sanitize($m['mois_label']) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- TAUX DE PAIEMENT -->
<div class="card">
  <div class="card-header"><h3><i class="fas fa-percent"></i> État financier — Taux de recouvrement</h3></div>
  <div class="card-body">
    <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
      <div style="flex:1;">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
          <span style="font-size:13px;font-weight:500;">Taux de paiement global</span>
          <strong style="color:<?= $stats['taux_paiement']>=70?'var(--success)':($stats['taux_paiement']>=40?'var(--warning)':'var(--danger)') ?>"><?= $stats['taux_paiement'] ?>%</strong>
        </div>
        <div class="progress" style="height:14px;">
          <div class="progress-bar" style="width:<?= $stats['taux_paiement'] ?>%;background:<?= $stats['taux_paiement']>=70?'var(--success)':($stats['taux_paiement']>=40?'var(--warning)':'var(--danger)') ?>;"></div>
        </div>
      </div>
      <div style="text-align:right;">
        <div style="font-size:12px;color:var(--text3);">Attendu :</div>
        <div style="font-weight:700;"><?= formatMoney($stats['total_attendu']) ?></div>
        <div style="font-size:12px;color:var(--text3);margin-top:4px;">Reçu :</div>
        <div style="font-weight:700;color:var(--success);"><?= formatMoney($stats['total_paiements']) ?></div>
      </div>
    </div>
  </div>
</div>

<?php include '../includes/footer.php'; ?>
