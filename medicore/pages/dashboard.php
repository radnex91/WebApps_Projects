<?php
$currentPage = 'dashboard';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/notifier.php';
requirePageAccess('dashboard');

$today = date('Y-m-d');
$month = date('m');
$year  = date('Y');

//  KPIs temps réel 
$kpis = [
    'patients_actifs'  => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'"),
    'critiques'        => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours' AND priorite='critique'"),
    'lits_libres'      => (int)db_scalar("SELECT COUNT(*) FROM lits WHERE statut='libre'"),
    'lits_total'       => (int)db_scalar("SELECT COUNT(*) FROM lits"),
    'rdv_aujourd_hui'  => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=?", [$today]),
    'rdv_en_attente'   => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=? AND statut='planifie'", [$today]),
    'urgences_actives' => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE statut='en_cours'"),
    'meds_critiques'   => (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='critique'"),
    'meds_bas'         => (int)db_scalar("SELECT COUNT(*) FROM medicaments WHERE statut='bas'"),
    'ordos_attente'    => (int)db_scalar("SELECT COUNT(*) FROM ordonnances WHERE statut='active'"),
    'analyses_dispo'   => (int)db_scalar("SELECT COUNT(*) FROM analyses WHERE statut='disponible'"),
    'ca_jour'          => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE DATE(date_vente)=? AND statut='paye'", [$today]),
    'ca_mois'          => (float)db_scalar("SELECT COALESCE(SUM(montant_total),0) FROM caisse_ventes WHERE MONTH(date_vente)=? AND YEAR(date_vente)=? AND statut='paye'", [$month,$year]),
    'factures_attente' => (float)db_scalar("SELECT COALESCE(SUM(montant_patient),0) FROM factures WHERE statut='en_attente'"),
    'admissions_mois'  => (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE MONTH(date_admission)=? AND YEAR(date_admission)=?", [$month,$year]),
    'patients_total'   => (int)db_scalar("SELECT COUNT(*) FROM patients"),
    // Nouveaux KPIs - modules Phase 1-3
    'observations_jour'=> (int)db_scalar("SELECT COUNT(*) FROM observations_infirmieres WHERE DATE(date_observation)=?", [$today]),
    'mar_en_attente'   => (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE DATE(heure_prevue)=? AND statut='prevue'", [$today]),
    'mar_admin'        => (int)db_scalar("SELECT COUNT(*) FROM administration_medicaments WHERE DATE(date_administration)=? AND statut='administre'", [$today]),
    'chirurgies_actives'=> (int)db_scalar("SELECT COUNT(*) FROM interventions_chirurgicales WHERE statut IN ('planifiee','en_preparation','en_cours')"),
    'imagerie_attente' => (int)db_scalar("SELECT COUNT(*) FROM examens_imagerie WHERE statut IN ('prescrit','planifie')"),
    'deces_mois'       => (int)db_scalar("SELECT COUNT(*) FROM certificats_deces WHERE MONTH(date_creation)=? AND YEAR(date_creation)=?", [$month,$year]),
    'notifications_non_lues'=> get_unread_count(),
];
$taux_occ = $kpis['lits_total'] > 0 ? round(($kpis['lits_total']-$kpis['lits_libres'])/$kpis['lits_total']*100) : 0;

//  Alertes critiques (sidebar uniquement)
$alertes = [];

//  Planning du jour 
$rdvJour = db_select(
    "SELECT r.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom,
            CONCAT(u.prenom,' ',u.nom) AS medecin_nom, u.specialite
     FROM rendez_vous r
     JOIN patients p    ON p.id = r.patient_id
     JOIN utilisateurs u ON u.id = r.medecin_id
     WHERE DATE(r.date_heure) = ?
     ORDER BY r.date_heure ASC LIMIT 8", [$today]
);

//  Hospitalisations actives 
$hospsActifs = db_select(
    "SELECT h.*, CONCAT(p.prenom,' ',p.nom) AS patient_nom,
            p.date_naissance, d.nom AS dept_nom, l.numero AS lit_num
     FROM hospitalisations h
     JOIN patients p     ON p.id = h.patient_id
     JOIN departements d ON d.id = h.departement_id
     JOIN lits l         ON l.id = h.lit_id
     WHERE h.statut = 'en_cours'
     ORDER BY FIELD(h.priorite,'critique','urgent','normal'), h.date_admission DESC
     LIMIT 6"
);

//  Admissions 7 derniers jours 
$adm7j = [];
for ($i = 6; $i >= 0; $i--) {
    $d   = date('Y-m-d', strtotime("-$i days"));
    $nb  = (int)db_scalar("SELECT COUNT(*) FROM hospitalisations WHERE DATE(date_admission)=?", [$d]);
    $adm7j[] = ['d'=>$d,'nb'=>$nb,'label'=>date('d/m', strtotime($d))];
}
$maxAdm = max(array_column($adm7j,'nb') ?: [1]);

//  Occupation par dpartement 
$occupation = db_select(
    "SELECT d.nom, d.couleur,
            COUNT(l.id) AS total,
            SUM(l.statut='occupe') AS occupes
     FROM departements d
     LEFT JOIN lits l ON l.departement_id = d.id
     GROUP BY d.id ORDER BY occupes DESC"
);

//  Journal d'activit 
$activite = db_select(
    "SELECT a.*, CONCAT(u.prenom,' ',u.nom) AS user_nom
     FROM activite_log a
     LEFT JOIN utilisateurs u ON u.id = a.utilisateur_id
     ORDER BY a.date_action DESC LIMIT 10"
);

//  Info rôle utilisateur connecté
$_rmap      = get_roles_map();
$userRole   = $_SESSION['user_role'] ?? '';
$roleColor  = $_rmap[$userRole]['couleur']  ?? '#3b82f6';
$roleLabel  = $_rmap[$userRole]['label']    ?? ucfirst($userRole);
$nbModules  = count(getAccessibleNav());

$icones = ['blue'=>'','green'=>'','red'=>'','yellow'=>'','purple'=>''];
$colors = [
    'blue'  =>'rgba(59,130,246,.12)','green'=>'rgba(16,185,129,.12)',
    'red'   =>'rgba(239,68,68,.12)','yellow'=>'rgba(245,158,11,.12)',
    'purple'=>'rgba(139,92,246,.12)',
];
$prioColor = ['critique'=>'var(--red)','urgent'=>'var(--yellow)','normal'=>'var(--accent)'];
$prioBadge = ['critique'=>'badge-red','urgent'=>'badge-yellow','normal'=>'badge-blue'];
$typeColor = ['consultation'=>'var(--accent)','suivi'=>'var(--green)','urgence'=>'var(--red)','chirurgie'=>'var(--purple)','bilan'=>'var(--yellow)'];
$moisFr = ['01'=>'jan','02'=>'fé','03'=>'mar','04'=>'avr','05'=>'mai','06'=>'jun','07'=>'jul','08'=>'ao','09'=>'sep','10'=>'oct','11'=>'nov','12'=>'dé'];
?>

<!-- Badge rôle + accès rapides -->
<div style="background:var(--surface2);border:1px solid var(--border);border-radius:10px;padding:11px 18px;margin-bottom:20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap">
  <div style="width:10px;height:10px;border-radius:50%;background:<?= h($roleColor) ?>;flex-shrink:0"></div>
  <span style="font-size:13px;color:var(--text2)">
    Connecté en tant que <strong style="color:var(--text)"><?= h($_SESSION['user_prenom'].' '.$_SESSION['user_nom']) ?></strong>
    <span style="margin-left:6px;font-size:11px;background:<?= h($roleColor) ?>22;color:<?= h($roleColor) ?>;padding:2px 8px;border-radius:12px;font-weight:600"><?= h($roleLabel) ?></span>
  </span>
  <span style="margin-left:auto;font-size:12px;color:var(--text3)"><?= $nbModules ?> modules  <?= date('d/m/Y H:i') ?></span>
</div>

<!-- STATS PRINCIPALES -->
<div class="page-header-row" style="margin-bottom:20px">
  <div><h2>Tableau de bord</h2><p>Vue en temps réel  <?= date('l d F Y', strtotime($today)) ?></p></div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php if (canAccessPage('appointments')): ?>
    <a href="appointments.php" class="btn btn-ghost btn-sm"> 📅 Agenda</a>
    <?php endif; ?>
    <?php if (canAccessPage('urgences')): ?>
    <a href="urgences.php" class="btn btn-ghost btn-sm"> 🚨 Urgences</a>
    <?php endif; ?>
    <?php if (canAccessPage('analytics')): ?>
    <a href="analytics.php" class="btn btn-ghost btn-sm"> 📈 Analytiques</a>
    <?php endif; ?>
  </div>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card blue">
    <div class="stat-icon blue">🏥</div>
    <div class="stat-value" id="stat-patients-actifs"><?= $kpis['patients_actifs'] ?></div>
    <div class="stat-label">Hospitalisés actifs</div>
  </div>
  <div class="stat-card <?= $taux_occ >= 90 ? 'red' : ($taux_occ >= 70 ? 'yellow' : 'green') ?>">
    <div class="stat-icon <?= $taux_occ >= 90 ? 'red' : ($taux_occ >= 70 ? 'yellow' : 'green') ?>">🛏️</div>
    <div class="stat-value" id="stat-occupation"><?= $taux_occ ?>%</div>
    <div class="stat-label">Occupation lits</div>
    <div class="stat-delta" id="stat-lits-info"><?= $kpis['lits_libres'] ?> libre(s) / <?= $kpis['lits_total'] ?></div>
  </div>
  <div class="stat-card green">
    <div class="stat-icon green">✅</div>
    <div class="stat-value" id="stat-rdv-today"><?= $kpis['rdv_aujourd_hui'] ?></div>
    <div class="stat-label">RDV aujourd'hui</div>
    <div class="stat-delta" id="stat-rdv-attente"><?= $kpis['rdv_en_attente'] ?> en attente</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon purple">💰</div>
    <div class="stat-value" id="stat-ca-jour"><?= fmt_money($kpis['ca_jour']) ?></div>
    <div class="stat-label">CA caisse aujourd'hui</div>
    <div class="stat-delta" id="stat-ca-mois">Mois : <?= fmt_money($kpis['ca_mois']) ?></div>
  </div>
</div>

<!-- KPIs cliniques (nouveaux modules) -->
<div class="stats-grid mb-24">
  <div class="stat-card blue">
    <div class="stat-icon">🌡️</div>
    <div class="stat-value"><?= $kpis['observations_jour'] ?></div>
    <div class="stat-label">Observations aujourd'hui</div>
  </div>
  <div class="stat-card <?= $kpis['mar_en_attente'] > 5 ? 'yellow' : 'green' ?>">
    <div class="stat-icon">💉</div>
    <div class="stat-value"><?= $kpis['mar_admin'] ?>/<?= $kpis['mar_admin'] + $kpis['mar_en_attente'] ?></div>
    <div class="stat-label">Médicaments administrés</div>
    <div class="stat-delta"><?= $kpis['mar_en_attente'] ?> en attente</div>
  </div>
  <div class="stat-card <?= $kpis['notifications_non_lues'] > 0 ? 'red' : 'green' ?>">
    <div class="stat-icon">🔔</div>
    <div class="stat-value"><?= $kpis['notifications_non_lues'] ?></div>
    <div class="stat-label">Notifications non lues</div>
  </div>
  <div class="stat-card purple">
    <div class="stat-icon">🔪</div>
    <div class="stat-value"><?= $kpis['chirurgies_actives'] ?></div>
    <div class="stat-label">Chirurgies actives</div>
    <div class="stat-delta"><?= $kpis['imagerie_attente'] ?> imageries en attente</div>
  </div>
</div>

<!-- LIGNE 1 : Planning + Hospitalisations -->
<div class="grid-2 mb-24">

  <!-- Planning du jour -->
  <div class="card">
    <div class="card-header">
      <h3> Planning du jour</h3>
      <?php if (canAccessPage('appointments')): ?>
      <a href="appointments.php" class="btn btn-sm btn-ghost">Tout voir </a>
      <?php endif; ?>
    </div>
    <?php if (empty($rdvJour)): ?>
    <div style="padding:32px;text-align:center;color:var(--text3)">
      <div style="font-size:28px;margin-bottom:8px"></div>
      <div style="font-size:13px">Aucun rendez-vous aujourd'hui</div>
      <?php if (canAccessPage('appointments')): ?>
      <a href="appointments.php" class="btn btn-blue btn-sm" style="margin-top:12px">+ Créer un RDV</a>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <?php foreach ($rdvJour as $r):
      $tc = $typeColor[$r['type']] ?? 'var(--accent)';
    ?>
    <div style="display:flex;gap:10px;padding:10px 16px;border-bottom:1px solid var(--border);align-items:center">
      <div style="font-size:12px;font-weight:600;color:var(--text3);width:38px;flex-shrink:0"><?= date('H:i', strtotime($r['date_heure'])) ?></div>
      <div style="width:3px;border-radius:2px;align-self:stretch;background:<?= $tc ?>;flex-shrink:0"></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($r['patient_nom']) ?></div>
        <div style="font-size:11px;color:var(--text2)">Dr. <?= h($r['medecin_nom']) ?>  <?= h($r['motif']) ?></div>
      </div>
      <span class="badge <?= $r['statut']==='complete'?'badge-green':($r['statut']==='annule'?'badge-red':'badge-blue') ?>" style="font-size:10px;flex-shrink:0"><?= ucfirst($r['statut']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Hospitalisations actives -->
  <div class="card">
    <div class="card-header">
      <h3> Patients hospitalisés</h3>
      <?php if (canAccessPage('urgences')): ?>
      <a href="urgences.php" class="btn btn-sm btn-ghost">Tout voir </a>
      <?php endif; ?>
    </div>
    <?php if (empty($hospsActifs)): ?>
    <div style="padding:32px;text-align:center;color:var(--text3)">
      <div style="font-size:28px;margin-bottom:8px"></div>
      <div style="font-size:13px">Aucune hospitalisation active</div>
    </div>
    <?php else: ?>
    <?php foreach ($hospsActifs as $h):
      $age    = date_diff(date_create($h['date_naissance']), date_create())->y;
      $dureeH = round((time()-strtotime($h['date_admission']))/3600);
      $duree  = $dureeH < 24 ? $dureeH.'h' : round($dureeH/24).'j';
    ?>
    <div style="padding:10px 16px;border-bottom:1px solid var(--border);display:flex;gap:10px;align-items:center">
      <div style="width:8px;height:8px;border-radius:50%;background:<?= $prioColor[$h['priorite']]??'var(--accent)' ?>;flex-shrink:0;margin-top:2px"></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:13px;font-weight:600"><?= h($h['patient_nom']) ?> <span style="font-size:11px;font-weight:400;color:var(--text2)"><?= $age ?> ans</span></div>
        <div style="font-size:11px;color:var(--text2)"><?= h($h['dept_nom']) ?>  Lit <?= h($h['lit_num']) ?>  <?= $duree ?></div>
      </div>
      <span class="badge <?= $prioBadge[$h['priorite']]??'badge-blue' ?>" style="font-size:10px;flex-shrink:0"><?= ucfirst($h['priorite']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if ($kpis['patients_actifs'] > 6): ?>
    <div style="padding:10px 16px;text-align:center;font-size:12px;color:var(--text3)">+ <?= $kpis['patients_actifs'] - 6 ?> autre(s) hospitalisé(s)</div>
    <?php endif; ?>
    <?php endif; ?>
  </div>

</div>

<!-- LIGNE 2 : Graphique admissions + Occupation depts + Journal -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px">

  <!-- Admissions 7 jours -->
  <div class="card">
    <div class="card-header"><h3> Admissions  7 jours</h3></div>
    <div style="padding:20px">
      <div style="display:flex;align-items:flex-end;gap:6px;height:80px;margin-bottom:8px">
        <?php foreach ($adm7j as $a):
          $h2 = $maxAdm > 0 ? round($a['nb']/$maxAdm*100) : 0;
          $isToday2 = $a['d'] === $today;
        ?>
        <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:3px">
          <span style="font-size:10px;color:var(--text2);font-weight:600"><?= $a['nb'] ?></span>
          <div style="width:100%;border-radius:3px 3px 0 0;min-height:4px;height:<?= max(4,$h2) ?>%;background:<?= $isToday2?'var(--accent)':'rgba(59,130,246,.3)' ?>" title="<?= $a['d'] ?>"></div>
          <span style="font-size:9px;color:var(--text3)"><?= date('d/m', strtotime($a['d'])) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:space-between;font-size:11px;color:var(--text3);margin-top:4px">
        <span>Total: <strong style="color:var(--text)"><?= array_sum(array_column($adm7j,'nb')) ?></strong></span>
        <span>Ce mois: <strong style="color:var(--text)"><?= $kpis['admissions_mois'] ?></strong></span>
      </div>
    </div>
  </div>

  <!-- Occupation par dpartement -->
  <div class="card">
    <div class="card-header"><h3> Occupation depts</h3></div>
    <div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">
      <?php foreach (array_slice($occupation,0,5) as $d):
        $pct = $d['total'] > 0 ? round($d['occupes']/$d['total']*100) : 0;
        $col = $pct>=90?'var(--red)':($pct>=70?'var(--yellow)':'var(--green)');
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:3px">
          <span style="font-size:12px;max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($d['nom']) ?></span>
          <span style="font-size:11px;color:var(--text2)"><?= (int)$d['occupes'] ?>/<?= (int)$d['total'] ?></span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($occupation)): ?>
      <div style="color:var(--text3);font-size:12px;text-align:center">Aucun département</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Journal d'activit -->
  <div class="card">
    <div class="card-header"><h3> Activité récente</h3></div>
    <div>
    <?php
    $icAct  = ['blue'=>'','green'=>'','red'=>'','yellow'=>'','purple'=>''];
    $bgAct  = ['blue'=>'rgba(59,130,246,.1)','green'=>'rgba(16,185,129,.1)','red'=>'rgba(239,68,68,.1)','yellow'=>'rgba(245,158,11,.1)','purple'=>'rgba(139,92,246,.1)'];
    foreach (array_slice($activite,0,7) as $log):
    ?>
    <div style="display:flex;gap:8px;padding:8px 14px;border-bottom:1px solid var(--border);align-items:flex-start">
      <div style="width:22px;height:22px;border-radius:6px;background:<?= $bgAct[$log['couleur']]??'rgba(59,130,246,.1)' ?>;display:flex;align-items:center;justify-content:center;font-size:11px;flex-shrink:0;margin-top:1px"><?= $icAct[$log['couleur']]??'' ?></div>
      <div style="flex:1;min-width:0">
        <div style="font-size:11px;color:var(--text);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($log['action']) ?></div>
        <div style="font-size:10px;color:var(--text3)"><?= $log['user_nom']?h($log['user_nom']):'Système' ?>  <?= fmt_date($log['date_action'],true) ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($activite)): ?>
    <div style="padding:24px;text-align:center;color:var(--text3);font-size:12px">Aucune activit</div>
    <?php endif; ?>
    </div>
  </div>

</div>

<!-- LIGNE 3 : Actions rapides selon rôle -->
<div class="card">
  <div class="card-header"><h3> Actions rapides</h3></div>
  <div style="padding:16px 20px;display:flex;gap:10px;flex-wrap:wrap">

    <?php if (can('patients.create') && canAccessPage('patients')): ?>
    <a href="patients.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span></span><span>Nouveau patient</span>
    </a>
    <?php endif; ?>

    <?php if (can('appointments.create') && canAccessPage('appointments')): ?>
    <a href="appointments.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span></span><span>Nouveau RDV</span>
    </a>
    <?php endif; ?>

    <?php if (can('ordonnances.create') && canAccessPage('pharmacie')): ?>
    <a href="pharmacie.php?tab=ordonnances" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span></span><span>Ordonnance</span>
    </a>
    <?php endif; ?>

    <?php if (can('caisse.create_vente') && canAccessPage('caisse')): ?>
    <a href="caisse.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span></span><span>Caisse</span>
    </a>
    <?php endif; ?>

    <?php if (can('analyses.create') && canAccessPage('laboratoire')): ?>
    <a href="laboratoire.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span></span><span>Prescrire analyse</span>
    </a>
    <?php endif; ?>

    <?php if (can('factures.create') && canAccessPage('facturation')): ?>
    <a href="facturation.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span></span><span>Nouvelle facture</span>
    </a>
    <?php endif; ?>

    <?php if (canAccessPage('rapports')): ?>
    <a href="rapports.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span></span><span>Rapports</span>
    </a>
    <?php endif; ?>

    <?php if (canAccessPage('observations')): ?>
    <a href="observations.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span>🌡️</span><span>Signes vitaux</span>
    </a>
    <?php endif; ?>

    <?php if (canAccessPage('mar')): ?>
    <a href="mar.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span>💉</span><span>Admin. médicaments</span>
    </a>
    <?php endif; ?>

    <?php if (canAccessPage('notes')): ?>
    <a href="notes.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span>📝</span><span>Notes cliniques</span>
    </a>
    <?php endif; ?>

    <?php if (canAccessPage('chirurgie')): ?>
    <a href="chirurgie.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span>🔪</span><span>Bloc opératoire</span>
    </a>
    <?php endif; ?>

    <?php if (canAccessPage('maternite')): ?>
    <a href="maternite.php" class="btn btn-ghost" style="display:flex;align-items:center;gap:6px">
      <span>🤰</span><span>Maternité</span>
    </a>
    <?php endif; ?>


  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
