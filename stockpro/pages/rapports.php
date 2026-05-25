<?php
$page_title = 'Rapports';
$page_id = 'rapports';
require_once '../includes/header.php';
requireAuth();
if (!canDo('rapport_view')) { echo '<div class="alert alert-danger">Accès refusé.</div>'; require_once '../includes/footer.php'; exit; }
$db = getDB();
$ent = getEntreprise();

// Filtre mois
$mois_select = $_GET['mois'] ?? date('Y-m');
$debut_mois = $mois_select . '-01';
$fin_mois = date('Y-m-t', strtotime($debut_mois));

// Liste des mois disponibles (6 derniers)
$mois_disponibles = $db->query("
  SELECT DISTINCT DATE_FORMAT(created_at,'%Y-%m') as mois
  FROM mouvements
  ORDER BY mois DESC LIMIT 6
")->fetchAll(PDO::FETCH_COLUMN);

// Si le mois sélectionné n'est pas dans la liste, ajouter le mois courant
if (!in_array(date('Y-m'), $mois_disponibles)) {
  array_unshift($mois_disponibles, date('Y-m'));
}

// Stats globales (toujours)
$val_achat = $db->query("SELECT SUM(quantite*prix_achat) FROM produits WHERE actif=1")->fetchColumn() ?: 0;
$val_vente  = $db->query("SELECT SUM(quantite*prix_vente) FROM produits WHERE actif=1")->fetchColumn() ?: 0;
$marge      = $val_vente - $val_achat;

// Stats du mois sélectionné
$entrees_mois = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM mouvements WHERE type='entree' AND created_at BETWEEN ? AND ?")->fetchColumn();
$st = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM mouvements WHERE type='entree' AND created_at BETWEEN ? AND ?");
$st->execute([$debut_mois, $fin_mois]);
$entrees_mois = $st->fetchColumn();

$st = $db->prepare("SELECT COALESCE(SUM(quantite),0) FROM mouvements WHERE type='sortie' AND created_at BETWEEN ? AND ?");
$st->execute([$debut_mois, $fin_mois]);
$sorties_mois = $st->fetchColumn();

$st = $db->prepare("SELECT COUNT(*) FROM mouvements WHERE created_at BETWEEN ? AND ?");
$st->execute([$debut_mois, $fin_mois]);
$nb_mouvements_mois = $st->fetchColumn();

// Mouvements du mois sélectionné
$st = $db->prepare("
  SELECT m.*, p.nom as produit_nom, p.reference, p.unite, u.prenom, u.nom as user_nom
  FROM mouvements m
  JOIN produits p ON m.produit_id=p.id
  JOIN utilisateurs u ON m.utilisateur_id=u.id
  WHERE m.created_at BETWEEN ? AND ?
  ORDER BY m.created_at DESC
");
$st->execute([$debut_mois, $fin_mois]);
$mouvements_mois = $st->fetchAll();

// Par catégorie
$par_cat = $db->query("
  SELECT c.nom, c.couleur, COUNT(p.id) as nb_produits, SUM(p.quantite*p.prix_vente) as valeur
  FROM categories c
  LEFT JOIN produits p ON p.categorie_id=c.id AND p.actif=1
  GROUP BY c.id ORDER BY valeur DESC LIMIT 8
")->fetchAll();
$total_valeur = array_sum(array_column($par_cat,'valeur')) ?: 1;

// Top sorties (30 jours)
$top_sorties = $db->query("
  SELECT p.nom, p.reference, SUM(m.quantite) as total_sorti, p.unite
  FROM mouvements m
  JOIN produits p ON m.produit_id=p.id
  WHERE m.type='sortie' AND m.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  GROUP BY m.produit_id ORDER BY total_sorti DESC LIMIT 8
")->fetchAll();

// Mouvements par mois (6 derniers mois) pour le graphique
$mvt_par_mois = $db->query("
  SELECT DATE_FORMAT(created_at,'%Y-%m') as mois,
         SUM(CASE WHEN type='entree' THEN quantite ELSE 0 END) as entrees,
         SUM(CASE WHEN type='sortie' THEN quantite ELSE 0 END) as sorties
  FROM mouvements
  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
  GROUP BY mois ORDER BY mois
")->fetchAll();

$nom_mois = [
  '01'=>'Janvier','02'=>'Février','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
  '07'=>'Juillet','08'=>'Août','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Décembre'
];
$mois_label = ($nom_mois[substr($mois_select,5,2)] ?? '') . ' ' . substr($mois_select,0,4);
?>

<!-- Filtre mois -->
<div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;flex-wrap:wrap">
  <form method="GET" style="display:flex;align-items:center;gap:10px">
    <label class="form-label" style="margin:0;white-space:nowrap">📅 Période :</label>
    <select name="mois" class="form-control form-select" style="width:auto;min-width:180px" onchange="this.form.submit()">
      <?php foreach ($mois_disponibles as $m): ?>
      <option value="<?= $m ?>" <?= $m===$mois_select?'selected':'' ?>><?= ($nom_mois[substr($m,5,2)] ?? substr($m,5,2)) . ' ' . substr($m,0,4) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <div style="font-family:var(--font-heading);font-weight:700;font-size:18px;color:var(--text)"><?= $mois_label ?></div>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-icon" style="background:#dcfce7"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    <div><div class="stat-val" style="font-size:17px"><?= formatMoney($val_achat) ?></div><div class="stat-label">Valeur achat stock</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#dbeafe"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></div>
    <div><div class="stat-val" style="font-size:17px"><?= formatMoney($val_vente) ?></div><div class="stat-label">Valeur vente stock</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:<?= $marge>=0?'#dcfce7':'#fee2e2' ?>"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="<?= $marge>=0?'#16a34a':'#dc2626' ?>" stroke-width="2"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg></div>
    <div><div class="stat-val" style="font-size:17px;color:<?= $marge>=0?'var(--success)':'var(--danger)' ?>"><?= formatMoney($marge) ?></div><div class="stat-label">Marge potentielle</div></div>
  </div>
  <div class="stat-card">
    <div class="stat-icon" style="background:#fef3c7"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg></div>
    <div><div class="stat-val" style="font-size:17px"><?= $nb_mouvements_mois ?></div><div class="stat-label">Mouvements ce mois</div></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <!-- Répartition par catégorie -->
  <div class="card">
    <div class="card-header"><div class="card-title">Répartition par catégorie</div></div>
    <div class="card-body">
      <?php foreach ($par_cat as $c): ?>
      <?php if (!$c['valeur']) continue; $pct=round($c['valeur']/$total_valeur*100); ?>
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;margin-bottom:5px">
          <span style="font-size:13px;font-weight:500;display:flex;align-items:center;gap:6px">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= $c['couleur']??'var(--primary)' ?>;display:inline-block"></span>
            <?= htmlspecialchars($c['nom']) ?>
          </span>
          <span style="font-size:12px;color:var(--muted)"><?= $pct ?>% · <?= formatMoney($c['valeur']) ?></span>
        </div>
        <div class="progress"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $c['couleur']??'var(--primary)' ?>"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top sorties -->
  <div class="card">
    <div class="card-header"><div class="card-title">Top sorties (30 jours)</div></div>
    <div style="overflow-x:auto">
      <?php if (empty($top_sorties)): ?>
      <div class="empty-state" style="padding:30px"><p>Aucune sortie ces 30 derniers jours</p></div>
      <?php else: ?>
      <table>
        <thead><tr><th>#</th><th>Produit</th><th>Qté sortie</th></tr></thead>
        <tbody>
        <?php foreach ($top_sorties as $i=>$p): ?>
        <tr>
          <td style="color:var(--muted);font-size:13px"><?= $i+1 ?></td>
          <td>
            <div style="font-weight:600;font-size:13px"><?= htmlspecialchars(mb_substr($p['nom'],0,30,'UTF-8')) ?></div>
            <div style="font-size:11px;color:var(--muted)"><?= $p['reference'] ?></div>
          </td>
          <td style="font-weight:700;color:var(--danger)"><?= $p['total_sorti'] ?> <small style="font-weight:400;color:var(--muted)"><?= $p['unite'] ?></small></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!empty($mvt_par_mois)): ?>
<div class="card" style="margin-top:20px">
  <div class="card-header"><div class="card-title">Mouvements — 6 derniers mois</div></div>
  <div class="card-body">
    <div style="display:flex;gap:20px;flex-wrap:wrap">
      <?php
      $max_mvt = max(array_merge(array_column($mvt_par_mois,'entrees'), array_column($mvt_par_mois,'sorties'))) ?: 1;
      foreach ($mvt_par_mois as $m):
        $h_e=round($m['entrees']/$max_mvt*120); $h_s=round($m['sorties']/$max_mvt*120);
      ?>
      <div style="flex:1;min-width:80px;text-align:center">
        <div style="display:flex;gap:4px;align-items:flex-end;justify-content:center;height:130px">
          <div style="width:20px;background:var(--success);opacity:.8;border-radius:4px 4px 0 0;height:<?= $h_e ?>px;transition:height .4s" title="Entrées: <?= $m['entrees'] ?>"></div>
          <div style="width:20px;background:var(--danger);opacity:.8;border-radius:4px 4px 0 0;height:<?= $h_s ?>px;transition:height .4s" title="Sorties: <?= $m['sorties'] ?>"></div>
        </div>
        <div style="font-size:11px;color:var(--muted);margin-top:4px"><?= substr($m['mois'],5) ?>/<?= substr($m['mois'],2,2) ?></div>
        <div style="font-size:10px;color:var(--success)">+<?= $m['entrees'] ?></div>
        <div style="font-size:10px;color:var(--danger)">-<?= $m['sorties'] ?></div>
      </div>
      <?php endforeach; ?>
      <div style="display:flex;align-items:center;gap:16px;margin-left:20px;align-self:center">
        <div style="display:flex;align-items:center;gap:6px;font-size:12px"><div style="width:12px;height:12px;background:var(--success);border-radius:3px"></div>Entrées</div>
        <div style="display:flex;align-items:center;gap:6px;font-size:12px"><div style="width:12px;height:12px;background:var(--danger);border-radius:3px"></div>Sorties</div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Tableau des mouvements du mois -->
<div class="card" style="margin-top:20px">
  <div class="card-header">
    <div class="card-title">📋 Détail des mouvements — <?= $mois_label ?></div>
    <?php if (!empty($mouvements_mois)): ?>
    <span class="badge badge-blue"><?= count($mouvements_mois) ?> mouvements</span>
    <?php endif; ?>
  </div>
  <div style="overflow-x:auto">
    <?php if (empty($mouvements_mois)): ?>
    <div class="empty-state">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
      <h4>Aucun mouvement en <?= $mois_label ?></h4>
      <p>Aucun mouvement de stock n'a été enregistré pour cette période.</p>
    </div>
    <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Date</th>
          <th>Produit</th>
          <th>Type</th>
          <th>Quantité</th>
          <th>Avant → Après</th>
          <th>Motif</th>
          <th>Utilisateur</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($mouvements_mois as $mvt):
        $type_badge = match($mvt['type']) {
          'entree'     => '<span class="badge badge-green">Entrée</span>',
          'sortie'     => '<span class="badge badge-red">Sortie</span>',
          'ajustement' => '<span class="badge badge-orange">Ajustement</span>',
          'retour'     => '<span class="badge badge-blue">Retour</span>',
          default       => '<span class="badge badge-gray">'.$mvt['type'].'</span>',
        };
      ?>
      <tr>
        <td style="white-space:nowrap;font-size:13px"><?= date('d/m/Y H:i', strtotime($mvt['created_at'])) ?></td>
        <td>
          <div style="font-weight:600;font-size:13px"><?= htmlspecialchars($mvt['produit_nom']) ?></div>
          <div style="font-size:11px;color:var(--muted)"><?= $mvt['reference'] ?></div>
        </td>
        <td><?= $type_badge ?></td>
        <td style="font-weight:700"><?= $mvt['quantite'] ?> <small style="font-weight:400;color:var(--muted)"><?= $mvt['unite'] ?></small></td>
        <td style="font-size:13px"><?= $mvt['quantite_avant'] ?> → <?= $mvt['quantite_apres'] ?></td>
        <td style="font-size:13px;color:var(--text-2)"><?= htmlspecialchars($mvt['motif'] ?? '—') ?></td>
        <td style="font-size:13px"><?= htmlspecialchars($mvt['prenom'].' '.$mvt['user_nom']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>