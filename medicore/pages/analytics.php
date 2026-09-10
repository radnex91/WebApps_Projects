<?php
$currentPage = 'analytics';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

//  EXPORT CSV 
if (get_str('export') === 'csv') {
    if (ob_get_level() > 0) ob_end_clean();
    $month = date('m'); $year = date('Y');
    $data_export = [
        'kpis' => [
            ['Métrique','Valeur'],
            ['Patients hospitalisés actifs', db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'")],
            ['Taux occupation lits (%)',     db_scalar("SELECT ROUND(SUM(statut='occupe')/COUNT(*)*100,1) FROM lits")],
            ['RDV ce mois',                  db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE MONTH(date_heure)=? AND YEAR(date_heure)=?", [$month,$year])],
            ['Admissions ce mois',           db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE MONTH(date_admission)=? AND YEAR(date_admission)=?", [$month,$year])],
            ['CA caisse ce mois',            db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE MONTH(date_vente)=? AND YEAR(date_vente)=? AND statut='paye'", [$month,$year])],
            ['Factures en attente',          db_scalar("SELECT COUNT(*) FROM factures WHERE statut='en_attente'")],
        ],
        'admissions_mois' => db_select("SELECT DATE_FORMAT(date_admission,'%Y-%m') AS mois, COUNT(*) AS nb FROM hospitalisations GROUP BY mois ORDER BY mois DESC LIMIT 12"),
    ];
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="analytics_'.date('Y-m-d').'.csv"');
    $out = fopen('php://output','w');
    fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($out, ['=== INDICATEURS CLÉS ==='], ';');
    foreach ($data_export['kpis'] as $row) fputcsv($out, $row, ';');
    fputcsv($out, [], ';');
    fputcsv($out, ['=== ADMISSIONS PAR MOIS ==='], ';');
    fputcsv($out, ['Mois','Nombre admissions'], ';');
    foreach ($data_export['admissions_mois'] as $row) fputcsv($out, [$row['mois'], $row['nb']], ';');
    fclose($out); exit;
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('analytics');

$today = date('Y-m-d');
$year  = date('Y');
$month = date('m');

//  KPIs principaux 
$kpis = [
    'patients_total'     => (int)db_scalar("SELECT COUNT(*) FROM patients"),
    'hosps_actives'      => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'"),
    'hosps_mois'         => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE MONTH(date_admission)=? AND YEAR(date_admission)=?", [$month,$year]),
    'hosps_sortis_mois'  => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE MONTH(date_sortie)=? AND YEAR(date_sortie)=? AND statut='sorti'", [$month,$year]),
    'rdv_mois'           => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE MONTH(date_heure)=? AND YEAR(date_heure)=?", [$month,$year]),
    'rdv_complete_mois'  => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE MONTH(date_heure)=? AND YEAR(date_heure)=? AND statut='complete'", [$month,$year]),
    'taux_occ_lits'      => db_scalar("SELECT ROUND(SUM(statut='occupe')/COUNT(*)*100,1) FROM lits"),
    'ca_mois'            => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM factures WHERE MONTH(date_emission)=? AND YEAR(date_emission)=? AND statut='reglee'", [$month,$year]),
    'ca_caisse_mois'     => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE MONTH(date_vente)=? AND YEAR(date_vente)=? AND statut='paye'", [$month,$year]),
    'meds_critiques'     => (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='critique'"),
    'analyses_dispo'     => (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='disponible'"),
    'ordos_attente'      => (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'"),
];
$tauxRdv = $kpis['rdv_mois'] > 0 ? round($kpis['rdv_complete_mois']/$kpis['rdv_mois']*100) : 0;
$dms = db_scalar("SELECT ROUND(AVG(DATEDIFF(COALESCE(date_sortie,NOW()),date_admission)),1) FROM hospitalisations WHERE MONTH(date_admission)=? AND YEAR(date_admission)=?", [$month,$year]);

//  Admissions 12 derniers mois 
$admParMois = db_select("SELECT DATE_FORMAT(date_admission,'%Y-%m') AS mois, COUNT(*) AS nb
    FROM hospitalisations
    WHERE date_admission >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY mois ORDER BY mois ASC");

//  Admissions 7 derniers jours 
$adm7j = db_select("SELECT DATE(date_admission) AS jour, COUNT(*) AS nb
    FROM hospitalisations
    WHERE date_admission >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY jour ORDER BY jour ASC");

// Remplir les 7 jours manquants
$adm7jMap = [];
foreach ($adm7j as $a) $adm7jMap[$a['jour']] = (int)$a['nb'];
$adm7jFull = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $adm7jFull[] = ['jour' => $d, 'nb' => $adm7jMap[$d] ?? 0];
}

//  Repartition par departement 
$parDept = db_select("SELECT d.nom, COUNT(h.id) AS nb
    FROM departements d
    LEFT JOIN hospitalisations h ON h.departement_id=d.id AND MONTH(h.date_admission)=? AND YEAR(h.date_admission)=?
    GROUP BY d.id ORDER BY nb DESC", [$month,$year]);
$totalDept = array_sum(array_column($parDept,'nb')) ?: 1;

//  RDV par médecin
$rdvMed = db_select("SELECT CONCAT(u.prenom,' ',u.nom) AS nom, COUNT(r.id) AS nb
    FROM utilisateurs u
    LEFT JOIN rendez_vous r ON r.medecin_id=u.id AND MONTH(r.date_heure)=? AND YEAR(r.date_heure)=?
    WHERE u.role='medecin' AND u.statut='actif'
    GROUP BY u.id ORDER BY nb DESC LIMIT 8", [$month,$year]);
$maxRdv = max(array_column($rdvMed,'nb') ?: [1]);

//  Factures par statut 
$facStats = db_select("SELECT statut, COUNT(*) AS nb, COALESCE(SUM(montant_total),0) AS total
    FROM factures WHERE MONTH(date_emission)=? AND YEAR(date_emission)=?
    GROUP BY statut", [$month,$year]);

//  Top medicaments vendus 
$topMeds = db_select("SELECT m.nom, SUM(cl.quantite) AS total_vendu
    FROM caisse_lignes cl
    JOIN medicaments m ON m.id=cl.medicament_id
    JOIN caisse_ventes cv ON cv.id=cl.vente_id
    WHERE MONTH(cv.date_vente)=? AND cv.statut='paye'
    GROUP BY m.id ORDER BY total_vendu DESC LIMIT 6", [$month]);

$moisFrLong = ['01'=>'Janvier','02'=>'Fevrier','03'=>'Mars','04'=>'Avril','05'=>'Mai','06'=>'Juin',
               '07'=>'Juillet','08'=>'Aout','09'=>'Septembre','10'=>'Octobre','11'=>'Novembre','12'=>'Decembre'];
$moisActuel = ($moisFrLong[$month]??$month) . ' ' . $year;

$deptColors = ['#3b82f6','#10b981','#8b5cf6','#f59e0b','#ef4444','#06b6d4'];
$facBadge   = ['en_attente'=>'badge-yellow','reglee'=>'badge-green','impayee'=>'badge-red','annulee'=>'badge-gray','partielle'=>'badge-blue'];
$facLabel   = ['en_attente'=>'En attente','reglee'=>'Réglée','impayee'=>'Impayée','annulee'=>'Annulée','partielle'=>'Partielle'];
?>

<div class="page-header-row">
  <div><h2>Analytiques</h2><p>Indicateurs de performance &mdash; <?= $moisActuel ?></p></div>
  <a href="rapports.php" class="btn btn-ghost">Rapports complets &rarr;</a>
</div>

<!-- KPIs principaux -->
<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">🏥</div><div class="stat-value"><?= $kpis['hosps_actives'] ?></div><div class="stat-label">Hospitalises actifs</div></div>
  <div class="stat-card green"><div class="stat-icon green">📥</div><div class="stat-value"><?= $kpis['hosps_mois'] ?></div><div class="stat-label">Admissions ce mois</div><div class="stat-delta"><?= $kpis['hosps_sortis_mois'] ?> sorties</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">⏱️</div><div class="stat-value"><?= $dms ?: '' ?> j</div><div class="stat-label">Duree moy. sejour</div></div>
  <div class="stat-card purple"><div class="stat-icon purple">🛏️</div><div class="stat-value"><?= $kpis['taux_occ_lits'] ?>%</div><div class="stat-label">Taux occupation lits</div></div>
</div>

<div class="grid-2 mb-24">

  <!-- Admissions 7 jours -->
  <div class="card">
    <div class="card-header"><h3>Admissions  7 derniers jours</h3></div>
    <div style="padding:20px">
      <div style="display:flex;align-items:flex-end;gap:8px;height:120px;margin-bottom:8px">
        <?php $maxAdm = max(array_column($adm7jFull,'nb') ?: [1]); ?>
        <?php foreach ($adm7jFull as $a):
          $h = $maxAdm > 0 ? round($a['nb']/$maxAdm*100) : 0;
          $isToday2 = $a['jour'] === $today;
          $jn = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
          $dn2 = $jn[date('D',strtotime($a['jour']))] ?? date('D',strtotime($a['jour']));
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:4px">
          <span style="font-size:11px;font-weight:600;color:var(--text2)"><?= $a['nb'] ?></span>
          <div style="width:100%;background:<?= $isToday2?'var(--accent)':'rgba(var(--accent-rgb),.3)' ?>;border-radius:4px 4px 0 0;height:<?= max(4,$h) ?>%;transition:height .3s" title="<?= $a['jour'] ?>: <?= $a['nb'] ?>"></div>
          <span style="font-size:10px;color:var(--text3)"><?= $dn2 ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-top:4px">
        <span>Total 7j: <strong style="color:var(--text)"><?= array_sum(array_column($adm7jFull,'nb')) ?></strong></span>
        <span>Moy/j: <strong style="color:var(--text)"><?= round(array_sum(array_column($adm7jFull,'nb'))/7,1) ?></strong></span>
      </div>
    </div>
  </div>

  <!-- RDV par médecin -->
  <div class="card">
    <div class="card-header"><h3>RDV par médecin ce mois</h3></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:10px">
      <?php foreach ($rdvMed as $rm):
        $pct = $maxRdv > 0 ? round($rm['nb']/$maxRdv*100) : 0;
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:180px" title="<?= h($rm['nom']) ?>"><?= h($rm['nom']) ?></span>
          <span style="font-size:11px;font-weight:600;color:var(--text2)"><?= (int)$rm['nb'] ?></span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:var(--accent)"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($rdvMed)): ?><div style="color:var(--text3);text-align:center;font-size:12px">Aucun RDV ce mois</div><?php endif; ?>
      <div style="padding-top:8px;border-top:1px solid var(--border);display:flex;justify-content:space-between;font-size:12px">
        <span style="color:var(--text2)">Total mois</span>
        <strong><?= $kpis['rdv_mois'] ?> RDV &mdash; <?= $tauxRdv ?>% de présence</strong>
      </div>
    </div>
  </div>

</div>

<div class="grid-2 mb-24">

  <!-- Répartition par département -->
  <div class="card">
    <div class="card-header"><h3>Admissions par département &mdash; <?= $moisActuel ?></h3></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:12px">
      <?php foreach ($parDept as $i => $d):
        $pct = round($d['nb']/$totalDept*100);
        $col = $deptColors[$i % count($deptColors)];
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="font-size:13px;font-weight:500"><?= h($d['nom']) ?></span>
          <span style="font-size:12px;color:var(--text2)"><?= (int)$d['nb'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Financier -->
  <div class="card">
    <div class="card-header"><h3>Performance financiere &mdash; <?= $moisActuel ?></h3></div>
    <div style="padding:20px">
      <div style="margin-bottom:20px">
        <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Chiffre d'affaires total</div>
        <div style="font-size:32px;font-weight:700;color:var(--green)"><?= fmt_money($kpis['ca_mois'] + $kpis['ca_caisse_mois']) ?></div>
        <div style="display:flex;gap:16px;margin-top:8px;font-size:12px">
          <span style="color:var(--text2)">Facturation: <strong style="color:var(--text)"><?= fmt_money($kpis['ca_mois']) ?></strong></span>
          <span style="color:var(--text2)">Caisse: <strong style="color:var(--text)"><?= fmt_money($kpis['ca_caisse_mois']) ?></strong></span>
        </div>
      </div>

      <!-- Factures par statut -->
      <div style="font-size:11px;color:var(--text3);text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px">Factures par statut</div>
      <?php foreach ($facStats as $fs): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border)">
        <span class="badge <?= $facBadge[$fs['statut']]??'badge-gray' ?>"><?= $facLabel[$fs['statut']]??$fs['statut'] ?></span>
        <div style="text-align:right">
          <div style="font-size:13px;font-weight:600"><?= fmt_money((float)$fs['total']) ?></div>
          <div style="font-size:11px;color:var(--text3)"><?= (int)$fs['nb'] ?> facture(s)</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>

<div class="grid-2 mb-24">

  <!-- Alertes stock -->
  <div class="card">
    <div class="card-header">
      <h3>Alertes stock &amp; activite</h3>
      <a href="pharmacie.php" class="btn btn-sm btn-ghost">Voir pharmacie</a>
    </div>
    <div style="padding:0">
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid var(--border)">
        <span style="font-size:13px">Medicaments critiques</span>
        <span style="font-weight:700;color:var(--red)"><?= $kpis['meds_critiques'] ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid var(--border)">
        <span style="font-size:13px">Analyses disponibles non consultees</span>
        <span style="font-weight:700;color:var(--green)"><?= $kpis['analyses_dispo'] ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px;border-bottom:1px solid var(--border)">
        <span style="font-size:13px">Ordonnances en attente d'encaissement</span>
        <span style="font-weight:700;color:var(--yellow)"><?= $kpis['ordos_attente'] ?></span>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:12px 20px">
        <span style="font-size:13px">Total patients enregistrés</span>
        <span style="font-weight:700;color:var(--accent2)"><?= $kpis['patients_total'] ?></span>
      </div>
    </div>
  </div>

  <!-- Top medicaments vendus -->
  <div class="card">
    <div class="card-header"><h3>Top medicaments &mdash; caisse ce mois</h3></div>
    <div style="padding:20px;display:flex;flex-direction:column;gap:10px">
      <?php if ($topMeds):
        $maxMed = max(array_column($topMeds,'total_vendu') ?: [1]);
        foreach ($topMeds as $i => $tm):
          $pct = round($tm['total_vendu']/$maxMed*100);
          $col = $deptColors[$i % count($deptColors)];
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="font-size:12px;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= h($tm['nom']) ?>"><?= h($tm['nom']) ?></span>
          <span style="font-size:11px;font-weight:600;color:var(--text2)"><?= (int)$tm['total_vendu'] ?> unites</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      </div>
      <?php endforeach;
      else: ?><div style="text-align:center;color:var(--text3);font-size:12px;padding:16px">Aucune vente ce mois</div><?php endif; ?>
    </div>
  </div>

</div>

<!-- Admissions 12 mois -->
<div class="card mb-24">
  <div class="card-header"><h3>Tendance admissions &mdash; 12 derniers mois</h3></div>
  <div style="padding:20px">
    <?php if ($admParMois):
      $maxMoisAdm = max(array_column($admParMois,'nb') ?: [1]);
      $moisAbrev  = ['01'=>'Jan','02'=>'Fev','03'=>'Mar','04'=>'Avr','05'=>'Mai','06'=>'Jun','07'=>'Jul','08'=>'Aou','09'=>'Sep','10'=>'Oct','11'=>'Nov','12'=>'Dec'];
    ?>
    <div style="display:flex;align-items:flex-end;gap:6px;height:100px;margin-bottom:8px">
      <?php foreach ($admParMois as $a):
        $h2 = round($a['nb']/$maxMoisAdm*100);
        [$yr,$mo] = explode('-',$a['mois']);
        $isCurrentMonth = $a['mois'] === "$year-$month";
      ?>
      <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
        <span style="font-size:10px;color:var(--text2)"><?= $a['nb'] ?></span>
        <div style="width:100%;border-radius:3px 3px 0 0;height:<?= max(4,$h2) ?>%;background:<?= $isCurrentMonth?'var(--accent)':'rgba(var(--accent-rgb),.35)' ?>"></div>
        <span style="font-size:9px;color:var(--text3)"><?= ($moisAbrev[$mo]??$mo).' '.substr($yr,2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <div style="font-size:11px;color:var(--text3);text-align:right">
      Total 12 mois : <strong style="color:var(--text)"><?= array_sum(array_column($admParMois,'nb')) ?></strong> admissions
    </div>
    <?php else: ?>
    <div style="text-align:center;color:var(--text3);font-size:13px">Aucune donnee disponible</div>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
