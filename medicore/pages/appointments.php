<?php
$currentPage = 'appointments';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

//  CREER RDV 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'delete_rdv') {
    csrf_verify();
    if (!can('appointments.delete')) { header('Location: '.APP_URL.'/appointments.php'); exit; }
    $id = post_int('rdv_id');
    db_exec("DELETE FROM rendez_vous WHERE id=?", [$id]);
    logActivity("RDV supprimé ID:$id", 'red', 'rendez_vous', $id);
    header('Location: '.APP_URL.'/appointments.php?date='.urlencode(post_str('back_date')));
    exit;
}

//  DONNEES 

//  MODIFIER RDV 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post_str('action') === 'update_rdv' && can('appointments.create')) {
    csrf_verify();
    $id       = post_int('rdv_id');
    $date_str = post_str('date_rdv') . ' ' . post_str('heure_rdv');
    $v = (new Validator())
        ->required('motif','Motif')
        ->whitelist('type',['consultation','suivi','urgence','chirurgie','bilan'],'Type')
        ->whitelist('statut',['planifie','confirme','complete','annule','absent'],'Statut');
    if (!validate_date(post_str('date_rdv'))) { $flash = ['red','Date invalide.']; }
    elseif (!$v->passes()) { $flash = ['red',$v->first_error()]; }
    else {
        db_exec(
            "UPDATE rendez_vous SET date_heure=?,duree_minutes=?,motif=?,type=?,salle=?,statut=?,medecin_id=? WHERE id=?",
            [$date_str,max(15,post_int('duree',30)),$v->get('motif'),$v->get('type'),
             post_str('salle'),$v->get('statut'),post_int('medecin_id'),$id]
        );
        logActivity("RDV modifié ID:$id", 'blue', 'rendez_vous', $id);
        $flash = ['green','Rendez-vous mis à jour.'];
        header('Location: '.APP_URL.'/appointments.php?date='.urlencode(post_str('back_date')).'&saved=1'); exit;
    }
}

// Charger RDV  modifier
$editRdv = null;
if (get_int('edit_id') > 0) {
    $editRdv = db_row(
        "SELECT r.*,CONCAT(p.prenom,' ',p.nom) AS patient_nom
         FROM rendez_vous r JOIN patients p ON p.id=r.patient_id
         WHERE r.id=?", [get_int('edit_id')]
    );
}

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('appointments');

$today    = date('Y-m-d');
$viewDate = get_str('date') ?: $today;
if (!validate_date($viewDate)) $viewDate = $today;
$viewWeek   = get_str('view') === 'week';
$medFilter  = get_int('medecin_id');

if ($viewWeek) {
    $monday   = date('Y-m-d', strtotime('monday this week', strtotime($viewDate)));
    $sunday   = date('Y-m-d', strtotime('sunday this week', strtotime($viewDate)));
    $dateFrom = $monday; $dateTo = $sunday;
} else {
    $dateFrom = $dateTo = $viewDate;
}

$statutFilter = in_whitelist(get_str('statut_filter'),['','planifie','confirme','complete','annule','absent'],'');
$where  = "DATE(r.date_heure) BETWEEN ? AND ?";
$params = [$dateFrom, $dateTo];
if ($medFilter)    { $where .= " AND r.medecin_id=?"; $params[] = $medFilter; }
if ($statutFilter) { $where .= " AND r.statut=?";     $params[] = $statutFilter; }

$rdvs = db_select("SELECT r.*,CONCAT(p.prenom,' ',p.nom) AS patient_nom,p.numero AS patient_num,
    CONCAT(u.prenom,' ',u.nom) AS medecin_nom,u.specialite
    FROM rendez_vous r
    JOIN patients p ON p.id=r.patient_id
    JOIN utilisateurs u ON u.id=r.medecin_id
    WHERE $where ORDER BY r.date_heure ASC", $params);

$prevDate = date('Y-m-d', strtotime($viewDate . ' -1 ' . ($viewWeek ? 'week' : 'day')));
$nextDate = date('Y-m-d', strtotime($viewDate . ' +1 ' . ($viewWeek ? 'week' : 'day')));

$stats = [
    'total'   => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=?", [$today]),
    'complet' => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=? AND statut='complete'", [$today]),
    'annule'  => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=? AND statut IN('annule','absent')", [$today]),
    'mois'    => (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE MONTH(date_heure)=MONTH(CURDATE()) AND YEAR(date_heure)=YEAR(CURDATE())"),
];
$tauxPresence = $stats['total'] > 0 ? round($stats['complet'] / $stats['total'] * 100) : 0;

$médecins = db_select("SELECT id,CONCAT(prenom,' ',nom) AS nom_complet,specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom");
$patients = db_select("SELECT id,CONCAT(prenom,' ',nom) AS nom_complet,numero FROM patients ORDER BY nom LIMIT 300");

$typeColor  = ['consultation'=>'accent','suivi'=>'green','urgence'=>'red','chirurgie'=>'purple','bilan'=>'yellow'];
$statutBadge = ['planifie'=>'badge-blue','confirme'=>'badge-green','complete'=>'badge-green','annule'=>'badge-red','absent'=>'badge-yellow'];
$statutLabel = ['planifie'=>'Planifié','confirme'=>'Confirmé','complete'=>'Complété','annule'=>'Annulé','absent'=>'Absent'];
$typeLabel   = ['consultation'=>'Consultation','suivi'=>'Suivi','urgence'=>'Urgence','chirurgie'=>'Chirurgie','bilan'=>'Bilan'];

$rdvByHour = [];
foreach ($rdvs as $r) { $rdvByHour[date('H:i', strtotime($r['date_heure']))][] = $r; }
ksort($rdvByHour);
?>

<?php if (isset($_GET['ok'])): ?>
<div class="alert alert-green alert-auto">checkmark <?= get_str('ok') === 'deleted' ? 'RDV supprimé.' : 'Mis à jour.' ?></div>
<?php endif; ?>
<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= $flash[0]==='green'?'OK':'ERR' ?> <?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>Rendez-vous</h2><p><?= $stats['total'] ?> RDV aujourd'hui &middot; <?= $stats['complet'] ?> complétés &middot; <?= $stats['mois'] ?> ce mois</p></div>
  <button class="btn btn-blue" onclick="document.getElementById('modal-rdv').style.display='flex'"><?php if (can('appointments.create')): ?>+ Nouveau RDV</button><?php endif; ?>
</div>

<!-- Stats -->
<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">📅</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">RDV aujourd'hui</div></div>
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['complet'] ?></div><div class="stat-label">Complétés</div><div class="stat-delta"><?= $tauxPresence ?>% taux présence</div></div>
  <div class="stat-card red"><div class="stat-icon red">❌</div><div class="stat-value"><?= $stats['annule'] ?></div><div class="stat-label">Annulés / Absents</div></div>
  <div class="stat-card purple"><div class="stat-icon purple">📊</div><div class="stat-value"><?= $stats['mois'] ?></div><div class="stat-label">Total ce mois</div></div>
</div>

<!-- Navigation -->
<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;flex-wrap:wrap">
  <div style="display:flex;gap:6px">
    <a href="?date=<?= h($prevDate) ?>&view=<?= $viewWeek?'week':'' ?>&medecin_id=<?= $medFilter ?>" class="btn btn-ghost btn-sm">&laquo;</a>
    <a href="?date=<?= h($today) ?>&view=<?= $viewWeek?'week':'' ?>&medecin_id=<?= $medFilter ?>" class="btn btn-ghost btn-sm">Aujourd'hui</a>
    <a href="?date=<?= h($nextDate) ?>&view=<?= $viewWeek?'week':'' ?>&medecin_id=<?= $medFilter ?>" class="btn btn-ghost btn-sm">&raquo;</a>
  </div>
  <div style="font-size:15px;font-weight:600;flex:1;text-align:center">
    <?php if ($viewWeek): ?>
      Semaine du <?= date('d/m', strtotime($monday)) ?> au <?= date('d/m/Y', strtotime($sunday)) ?>
    <?php else: ?>
      <?php $jours=['Monday'=>'Lundi','Tuesday'=>'Mardi','Wednesday'=>'Mercredi','Thursday'=>'Jeudi','Friday'=>'Vendredi','Saturday'=>'Samedi','Sunday'=>'Dimanche']; ?>
      <?= $jours[date('l',strtotime($viewDate))]??date('l',strtotime($viewDate)) ?> <?= date('d', strtotime($viewDate)) ?> <?= ['01'=>'janvier','02'=>'f&eacute;vrier','03'=>'mars','04'=>'avril','05'=>'mai','06'=>'juin','07'=>'juillet','08'=>'ao&ucirc;t','09'=>'septembre','10'=>'octobre','11'=>'novembre','12'=>'d&eacute;cembre'][date('m',strtotime($viewDate))] ?> <?= date('Y', strtotime($viewDate)) ?>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:4px">
    <a href="?date=<?= h($viewDate) ?>&medecin_id=<?= $medFilter ?>" class="btn btn-sm <?= !$viewWeek?'btn-blue':'btn-ghost' ?>">Jour</a>
    <a href="?date=<?= h($viewDate) ?>&view=week&medecin_id=<?= $medFilter ?>" class="btn btn-sm <?= $viewWeek?'btn-blue':'btn-ghost' ?>">Semaine</a>
  </div>
  <form method="GET" style="display:flex;gap:6px;flex-wrap:wrap">
    <input type="hidden" name="date" value="<?= h($viewDate) ?>">
    <?php if ($viewWeek): ?><input type="hidden" name="view" value="week"><?php endif; ?>
    <select name="statut_filter" onchange="this.form.submit()" style="padding:7px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
      <option value="">Tous statuts</option>
      <?php foreach(['planifie'=>'Planifié','confirme'=>'Confirmé','complete'=>'Complété','annule'=>'Annulé','absent'=>'Absent'] as $sv=>$sl): ?>
      <option value="<?= $sv ?>" <?= $statutFilter===$sv?'selected':'' ?>><?= $sl ?></option>
      <?php endforeach; ?>
    </select>
    <select name="medecin_id" onchange="this.form.submit()" style="padding:7px 12px;background:var(--surface);border:1px solid var(--border2);border-radius:8px;color:var(--text);font-family:inherit;font-size:13px;outline:none">
      <option value="">Tous les médecins</option>
      <?php foreach ($médecins as $m): ?>
      <option value="<?= (int)$m['id'] ?>" <?= $medFilter===(int)$m['id']?'selected':'' ?>><?= h($m['nom_complet']) ?><?= $m['specialite']?' - '.h($m['specialite']):'' ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($viewWeek): ?>
<!-- VUE SEMAINE -->
<?php
$days = [];
for ($i = 0; $i < 7; $i++) $days[] = date('Y-m-d', strtotime($monday . " +$i days"));
$rdvByDay = [];
foreach ($rdvs as $r) { $rdvByDay[date('Y-m-d', strtotime($r['date_heure']))][] = $r; }
$dn = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
?>
<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:8px">
  <?php foreach ($days as $day):
    $isToday = $day === $today;
    $dayRdvs = $rdvByDay[$day] ?? [];
    $shortDay = $dn[date('D',strtotime($day))] ?? date('D',strtotime($day));
  ?>
  <div style="background:var(--surface);border:1px solid <?= $isToday?'var(--accent)':'var(--border)' ?>;border-radius:10px;overflow:hidden">
    <div style="padding:10px;text-align:center;background:<?= $isToday?'rgba(var(--accent-rgb),.15)':'var(--surface2)' ?>;border-bottom:1px solid var(--border)">
      <div style="font-size:11px;color:var(--text3)"><?= $shortDay ?></div>
      <div style="font-size:18px;font-weight:700;color:<?= $isToday?'var(--accent2)':'var(--text)' ?>"><?= date('d', strtotime($day)) ?></div>
      <div style="font-size:10px;color:var(--text3)"><?= count($dayRdvs) ?> RDV</div>
    </div>
    <div style="padding:8px;display:flex;flex-direction:column;gap:4px;min-height:100px">
      <?php foreach ($dayRdvs as $r):
        $tc = ['consultation'=>'var(--accent)','suivi'=>'var(--green)','urgence'=>'var(--red)','chirurgie'=>'var(--purple)','bilan'=>'var(--yellow)'][$r['type']] ?? 'var(--accent)';
      ?>
      <a href="?date=<?= $day ?>" style="display:block;padding:5px 8px;border-radius:5px;font-size:10px;border-left:3px solid <?= $tc ?>;background:rgba(255,255,255,.03);text-decoration:none" title="<?= h($r['patient_nom']) ?>">
        <div style="font-weight:600;color:var(--text)"><?= date('H:i', strtotime($r['date_heure'])) ?></div>
        <div style="color:var(--text2);overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h(mb_substr($r['patient_nom'],0,14)) ?></div>
      </a>
      <?php endforeach; ?>
      <?php if (empty($dayRdvs)): ?><div style="text-align:center;color:var(--text3);font-size:11px;padding-top:12px">-</div><?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php else: ?>
<!-- VUE JOUR -->
<div class="grid-2">
  <div class="card">
    <div class="card-header">
      <h3>Agenda &mdash; <?= date('d/m/Y', strtotime($viewDate)) ?></h3>
      <span style="font-size:12px;color:var(--text2)"><?= count($rdvs) ?> RDV</span>
    </div>
    <?php if (empty($rdvs)): ?>
    <div style="padding:40px;text-align:center;color:var(--text3)">
      <div style="font-size:28px;margin-bottom:8px"></div>
      <div>Aucun rendez-vous ce jour.</div>
      <button class="btn btn-blue btn-sm" style="margin-top:12px" onclick="document.getElementById('modal-rdv').style.display='flex'">+ Créer un RDV</button>
    </div>
    <?php else: ?>
    <?php foreach ($rdvByHour as $heure => $heureRdvs): ?>
      <?php foreach ($heureRdvs as $r):
        $tcv = ['consultation'=>'var(--accent)','suivi'=>'var(--green)','urgence'=>'var(--red)','chirurgie'=>'var(--purple)','bilan'=>'var(--yellow)'][$r['type']] ?? 'var(--accent)';
      ?>
      <div style="display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border);align-items:flex-start">
        <div style="font-size:12px;font-weight:600;color:var(--text3);width:42px;flex-shrink:0;margin-top:4px"><?= date('H:i', strtotime($r['date_heure'])) ?></div>
        <div style="flex:1;padding:10px 12px;border-radius:7px;border-left:3px solid <?= $tcv ?>;background:rgba(255,255,255,.03)">
          <div style="display:flex;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:4px">
            <strong style="font-size:13px"><?= h($r['patient_nom']) ?></strong>
            <div style="display:flex;gap:4px">
              <span style="font-size:10px;background:var(--surface2);padding:2px 7px;border-radius:10px;color:var(--text2)"><?= $typeLabel[$r['type']]??$r['type'] ?></span>
              <span class="badge <?= $statutBadge[$r['statut']]??'badge-gray' ?>" style="font-size:10px"><?= $statutLabel[$r['statut']]??$r['statut'] ?></span>
            </div>
          </div>
          <div style="font-size:12px;color:var(--text2);margin-bottom:3px"><?= h($r['motif']) ?></div>
          <div style="font-size:11px;color:var(--text3)">
            Dr. <?= h($r['medecin_nom']) ?>
            <?= $r['salle'] ? ' &middot; ' . h($r['salle']) : '' ?>
            &middot; <?= (int)$r['duree_minutes'] ?> min
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:4px;flex-shrink:0">
          <form method="POST" style="margin:0">
            <input type="hidden" name="action" value="update_statut">
            <input type="hidden" name="rdv_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="back_date" value="<?= h($viewDate) ?>">
            <?= csrf_field() ?>
            <select name="statut" onchange="this.form.submit()" style="padding:4px 6px;background:var(--bg);border:1px solid var(--border2);border-radius:5px;color:var(--text);font-size:11px;outline:none;cursor:pointer">
              <?php foreach (['planifie'=>'Planifié','confirme'=>'Confirmé','complete'=>'Complété','annule'=>'Annulé','absent'=>'Absent'] as $sv=>$sl): ?>
              <option value="<?= $sv ?>" <?= $r['statut']===$sv?'selected':'' ?>><?= $sl ?></option>
              <?php endforeach; ?>
            </select>
          </form>
          <?php if (can('appointments.delete')): ?>
          <?php if (can('appointments.create')): ?>
          <a href="appointments.php?edit_id=<?= (int)$r['id'] ?>&date=<?= h($viewDate) ?>" class="btn btn-sm btn-ghost" title="Modifier">✏️</a>
          <?php endif; ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Supprimer ce RDV ?')">
            <input type="hidden" name="action" value="delete_rdv">
            <input type="hidden" name="rdv_id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="back_date" value="<?= h($viewDate) ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-red" style="font-size:10px;padding:3px 8px">🗑</button>
          </form>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">
    <!-- Répartition par type -->
    <div class="card">
      <div class="card-header"><h3>Répartition par type aujourd'hui</h3></div>
      <div style="padding:16px 20px;display:flex;flex-direction:column;gap:10px">
      <?php
      $typeStats = db_select("SELECT type,COUNT(*) AS nb FROM rendez_vous WHERE DATE(date_heure)=? GROUP BY type ORDER BY nb DESC", [$today]);
      $totalJour = array_sum(array_column($typeStats, 'nb'));
      $tcMap = ['consultation'=>'var(--accent)','suivi'=>'var(--green)','urgence'=>'var(--red)','chirurgie'=>'var(--purple)','bilan'=>'var(--yellow)'];
      foreach ($typeStats as $ts):
        $pct = $totalJour > 0 ? round($ts['nb']/$totalJour*100) : 0;
        $col = $tcMap[$ts['type']] ?? 'var(--accent)';
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <span style="font-size:12px"><?= $typeLabel[$ts['type']]??$ts['type'] ?></span>
          <span style="font-size:11px;font-weight:600;color:var(--text2)"><?= $ts['nb'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= $col ?>"></div></div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($typeStats)): ?><div style="color:var(--text3);font-size:12px;text-align:center">Aucun RDV aujourd'hui</div><?php endif; ?>
      </div>
    </div>

    <!-- Prochains 7 jours -->
    <div class="card">
      <div class="card-header"><h3>Prochains 7 jours</h3></div>
      <?php
      $moisFr = ['01'=>'jan','02'=>'fev','03'=>'mar','04'=>'avr','05'=>'mai','06'=>'jun','07'=>'jul','08'=>'aou','09'=>'sep','10'=>'oct','11'=>'nov','12'=>'dec'];
      $jFr    = ['Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mer','Thu'=>'Jeu','Fri'=>'Ven','Sat'=>'Sam','Sun'=>'Dim'];
      for ($i = 0; $i < 7; $i++):
        $d  = date('Y-m-d', strtotime("+$i days"));
        $nb = (int)db_scalar("SELECT COUNT(*) FROM rendez_vous WHERE DATE(date_heure)=?", [$d]);
        $isTd = $d === $today;
        $sDay = $jFr[date('D',strtotime($d))] ?? date('D',strtotime($d));
      ?>
      <a href="?date=<?= $d ?>" style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid var(--border);text-decoration:none;background:<?= $isTd?'rgba(var(--accent-rgb),.06)':'' ?>">
        <span style="font-size:13px;color:<?= $isTd?'var(--accent2)':'var(--text2)' ?>">
          <strong><?= $sDay ?></strong> <?= date('d', strtotime($d)) ?> <?= $moisFr[date('m',strtotime($d))]??'' ?>
          <?php if ($isTd): ?><small style="color:var(--accent2)"> (aujourd'hui)</small><?php endif; ?>
        </span>
        <span style="background:<?= $nb>0?'rgba(var(--accent-rgb),.15)':'var(--surface2)' ?>;color:<?= $nb>0?'var(--accent2)':'var(--text3)' ?>;padding:2px 10px;border-radius:10px;font-size:12px;font-weight:600"><?= $nb ?></span>
      </a>
      <?php endfor; ?>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- MODAL CREER RDV -->
<div id="modal-rdv" class="modal-overlay" style="display:none;z-index:200;align-items:flex-start;justify-content:center;padding:20px;overflow-y:auto" role="dialog" aria-modal="true" onclick="if(event.target===this)this.style.display='none'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(600px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7);margin:auto">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;position:sticky;top:0;background:var(--surface);border-radius:16px 16px 0 0">
      <h3>Nouveau rendez-vous</h3>
      <button type="button" class="modal-close" onclick="document.getElementById('modal-rdv').style.display='none'" aria-label="Fermer" style="font-size:18px;color:var(--text2)">X</button>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="create_rdv"><?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group form-full"><label for="inp-patient_id">Patient *</label>
          <select name="patient_id" id="inp-patient_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="">-- Selectionner --</option>
            <?php foreach ($patients as $p): ?><option value="<?= (int)$p['id'] ?>"><?= h($p['nom_complet']) ?> (<?= h($p['numero']) ?>)</option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group form-full"><label for="inp-medecin_id">Medecin *</label>
          <select name="medecin_id" id="inp-medecin_id" required style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="">-- Selectionner --</option>
            <?php foreach ($médecins as $m): ?><option value="<?= (int)$m['id'] ?>" <?= $medFilter===(int)$m['id']?'selected':'' ?>><?= h($m['nom_complet']) ?><?= $m['specialite']?' - '.h($m['specialite']):'' ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="inp-date_rdv">Date *</label><input type="date" name="date_rdv" id="inp-date_rdv" required value="<?= h($viewDate) ?>" min="<?= $today ?>"></div>
        <div class="form-group"><label for="inp-heure">Heure *</label><input type="time" name="heure" id="inp-heure" required value="09:00" step="900"></div>
        <div class="form-group"><label for="inp-duree_minutes">Durée</label>
          <select name="duree_minutes" id="inp-duree_minutes" style="padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="15">15 min</option><option value="30" selected>30 min</option><option value="45">45 min</option><option value="60">1 heure</option><option value="90">1h30</option>
          </select>
        </div>
        <div class="form-group"><label for="inp-type">Type</label>
          <select name="type" id="inp-type" style="padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="consultation">Consultation</option><option value="suivi">Suivi</option><option value="urgence">Urgence</option><option value="chirurgie">Chirurgie</option><option value="bilan">Bilan</option>
          </select>
        </div>
        <div class="form-group form-full"><label for="inp-motif">Motif *</label><input type="text" name="motif" id="inp-motif" required maxlength="255"></div>
        <div class="form-group"><label for="inp-salle">Salle</label><input type="text" name="salle" id="inp-salle" maxlength="50" placeholder="ex: Salle A3"></div>
        <div class="form-group"><label for="inp-statut">Statut initial</label>
          <select name="statut" id="inp-statut" style="padding:9px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <option value="planifie">📅 Planifié</option><option value="confirme">✅ Confirmé</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;padding-top:16px;border-top:1px solid var(--border)">
        <button type="button" class="btn btn-ghost" onclick="document.getElementById('modal-rdv').style.display='none'">Annulér</button>
        <button type="submit" class="btn btn-blue">Créer le RDV</button>
      </div>
    </form>
  </div>
</div>

<?php $__médecins = db_select("SELECT id,CONCAT(prenom,' ',nom) AS nom,specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom"); ?>
<!-- MODAL MODIFIER RDV -->
<?php if ($editRdv): ?>
<div id="modal-edit-rdv" class="modal-overlay" style="display:flex;z-index:200;align-items:center;justify-content:center;padding:20px" role="dialog" aria-modal="true" onclick="if(event.target===this)location.href='appointments.php?date=<?= h($viewDate) ?>'">
  <div style="background:var(--surface);border:1px solid var(--border2);border-radius:16px;width:min(580px,95vw);box-shadow:0 24px 60px rgba(0,0,0,.7)">
    <div style="padding:18px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <h3>✏️ Modifier RDV — <?= h($editRdv['patient_nom']) ?></h3>
      <a href="appointments.php?date=<?= h($viewDate) ?>" style="color:var(--text2);text-decoration:none;font-size:18px"></a>
    </div>
    <form method="POST" style="padding:24px">
      <input type="hidden" name="action" value="update_rdv">
      <input type="hidden" name="rdv_id" value="<?= (int)$editRdv['id'] ?>">
      <input type="hidden" name="back_date" value="<?= h($viewDate) ?>">
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group"><label for="inp-edit-date_rdv">Date *</label>
          <input type="date" name="date_rdv" id="inp-edit-date_rdv" value="<?= substr($editRdv['date_heure'],0,10) ?>" required>
        </div>
        <div class="form-group"><label for="inp-edit-heure_rdv">Heure *</label>
          <input type="time" name="heure_rdv" id="inp-edit-heure_rdv" value="<?= substr($editRdv['date_heure'],11,5) ?>" required>
        </div>
        <div class="form-group"><label for="inp-edit-duree">Durée (min)</label>
          <input type="number" name="duree" id="inp-edit-duree" value="<?= (int)$editRdv['duree_minutes'] ?>" min="15" step="15">
        </div>
        <div class="form-group"><label for="inp-edit-type">Type</label>
          <select name="type" id="inp-edit-type" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach(['consultation'=>'Consultation','suivi'=>'Suivi','urgence'=>'Urgence','chirurgie'=>'Chirurgie','bilan'=>'Bilan'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $editRdv['type']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="inp-edit-medecin_id">Médecin</label>
          <select name="medecin_id" id="inp-edit-medecin_id" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach($__médecins as $md): ?>
            <option value="<?= (int)$md['id'] ?>" <?= $editRdv['medecin_id']===$md['id']?'selected':'' ?>>Dr. <?= h($md['nom']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label for="inp-edit-salle">Salle</label>
          <input type="text" name="salle" id="inp-edit-salle" value="<?= h($editRdv['salle']??'') ?>" maxlength="50">
        </div>
        <div class="form-group form-full"><label for="inp-edit-motif">Motif *</label>
          <input type="text" name="motif" id="inp-edit-motif" value="<?= h($editRdv['motif']) ?>" required maxlength="200">
        </div>
        <div class="form-group"><label for="inp-edit-statut">Statut</label>
          <select name="statut" id="inp-edit-statut" style="padding:9px 12px;background:var(--bg);border:1px solid var(--border2);border-radius:7px;color:var(--text);font-family:inherit;font-size:13px;outline:none;width:100%">
            <?php foreach(['planifie'=>'Planifi','confirme'=>'Confirm','complete'=>'Complt','annule'=>'Annul','absent'=>'Absent'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= $editRdv['statut']===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;padding-top:16px;border-top:1px solid var(--border)">
        <a href="appointments.php?date=<?= h($viewDate) ?>" class="btn btn-ghost">Annulér</a>
        <button type="submit" class="btn btn-blue">💾 Enregistrer</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php';
