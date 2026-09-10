<?php
$currentPage = 'rh';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('rh');

$staff = db_select(
    "SELECT u.*,
     (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id=u.id AND DATE(r.date_heure)=CURDATE()) AS rdv_today,
     (SELECT COUNT(*) FROM rendez_vous r WHERE r.medecin_id=u.id AND MONTH(r.date_heure)=MONTH(CURDATE())) AS rdv_mois,
     (SELECT COUNT(*) FROM hospitalisations h WHERE h.medecin_id=u.id AND h.statut='en_cours') AS patients_actifs
     FROM utilisateurs u ORDER BY u.statut, u.role, u.nom"
);

$stats = [
    'total'   => (int)db_scalar("SELECT COUNT(*) FROM utilisateurs"),
    'actifs'  => (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE statut='actif'"),
    'conges'  => (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE statut='conge'"),
    'inactifs'=> (int)db_scalar("SELECT COUNT(*) FROM utilisateurs WHERE statut='inactif'"),
];

// Repartition par role (dynamique)
$rolesMap  = get_roles_map();
$byRole    = db_select("SELECT role, COUNT(*) AS nb FROM utilisateurs GROUP BY role ORDER BY nb DESC");
$plannings = db_select("SELECT planning, COUNT(*) AS nb FROM utilisateurs WHERE statut='actif' AND planning IS NOT NULL GROUP BY planning ORDER BY nb DESC");

// Activite RDV ce mois par medecin (top 5)
$topMedecins = db_select(
    "SELECT CONCAT(u.prenom,' ',u.nom) AS nom, COUNT(r.id) AS nb
     FROM utilisateurs u
     LEFT JOIN rendez_vous r ON r.medecin_id=u.id AND MONTH(r.date_heure)=MONTH(CURDATE())
     WHERE u.role='medecin' AND u.statut='actif'
     GROUP BY u.id ORDER BY nb DESC LIMIT 5"
);
$maxRdv = max(array_column($topMedecins,'nb') ?: [1]);

$sBadge = ['actif'=>'badge-green','inactif'=>'badge-red','conge'=>'badge-yellow'];
$sLabel = ['actif'=>'Actif','inactif'=>'Inactif','conge'=>'En cong'];
?>

<div class="page-header-row">
  <div><h2> Ressources Humaines</h2><p><?= $stats['total'] ?> employés  <?= $stats['actifs'] ?> en service  <?= $stats['conges'] ?> en congé</p></div>
  <div style="display:flex;gap:8px">
    <a href="utilisateurs.php?export=csv" class="btn btn-ghost"> Exporter CSV</a>
    <a href="utilisateurs.php" class="btn btn-blue"> Gérer les comptes</a>
  </div>
</div>

<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">👥</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total employés</div></div>
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['actifs'] ?></div><div class="stat-label">En service</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">🏖️</div><div class="stat-value"><?= $stats['conges'] ?></div><div class="stat-label">En cong</div></div>
  <div class="stat-card red"><div class="stat-icon red">⛔</div><div class="stat-value"><?= $stats['inactifs'] ?></div><div class="stat-label">Inactifs</div></div>
</div>

<div class="grid-2 mb-24">

  <!-- Répartition par rôle -->
  <div class="card">
    <div class="card-header"><h3> Répartition par rôle</h3></div>
    <div style="padding:16px 20px;display:flex;flex-direction:column;gap:12px">
      <?php foreach ($byRole as $br):
        $pct   = $stats['total'] > 0 ? round($br['nb']/$stats['total']*100) : 0;
        $col   = $rolesMap[$br['role']]['couleur'] ?? '#3b82f6';
        $label = $rolesMap[$br['role']]['label']   ?? ucfirst($br['role']);
      ?>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:4px">
          <div style="display:flex;align-items:center;gap:8px">
            <span style="width:10px;height:10px;border-radius:50%;background:<?= h($col) ?>"></span>
            <span style="font-size:13px"><?= h($label) ?></span>
          </div>
          <span style="font-size:12px;font-weight:600;color:var(--text2)"><?= (int)$br['nb'] ?> (<?= $pct ?>%)</span>
        </div>
        <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct ?>%;background:<?= h($col) ?>"></div></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div style="display:flex;flex-direction:column;gap:16px">

    <!-- Top mdecins RDV ce mois -->
    <div class="card">
      <div class="card-header"><h3> Top mdecins  RDV ce mois</h3></div>
      <div style="padding:14px 20px;display:flex;flex-direction:column;gap:10px">
        <?php foreach ($topMedecins as $tm):
          $pct2 = $maxRdv > 0 ? round($tm['nb']/$maxRdv*100) : 0;
        ?>
        <div>
          <div style="display:flex;justify-content:space-between;margin-bottom:3px">
            <span style="font-size:12px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($tm['nom']) ?></span>
            <span style="font-size:11px;font-weight:600;color:var(--text2)"><?= (int)$tm['nb'] ?> RDV</span>
          </div>
          <div class="progress-bar"><div class="progress-fill" style="width:<?= $pct2 ?>%;background:var(--accent)"></div></div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($topMedecins)): ?><div style="color:var(--text3);font-size:12px;text-align:center">Aucun RDV ce mois</div><?php endif; ?>
      </div>
    </div>

    <!-- Plannings actifs -->
    <div class="card">
      <div class="card-header"><h3> Plannings en cours</h3></div>
      <?php foreach ($plannings as $pl): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:11px 20px;border-bottom:1px solid var(--border)">
        <span style="font-size:13px"> <?= h($pl['planning'] ?: 'Non défini') ?></span>
        <span style="background:var(--surface2);padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600"><?= (int)$pl['nb'] ?> pers.</span>
      </div>
      <?php endforeach; ?>
      <?php if (empty($plannings)): ?>
      <div style="padding:20px;text-align:center;color:var(--text3);font-size:12px">Aucun planning dfini</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Tableau complet -->
<div class="card">
  <div class="card-header">
    <h3> Liste du personnel</h3>
    <a href="utilisateurs.php" class="btn btn-sm btn-ghost">Gérer </a>
  </div>
  <table>
    <thead>
      <tr><th>Employé</th><th>Rôle</th><th>Spécialité</th><th>Planning</th><th>Patients actifs</th><th>RDV aujourd'hui</th><th>RDV ce mois</th><th>Statut</th><th>Dernière connexion</th></tr>
    </thead>
    <tbody>
    <?php foreach ($staff as $u):
      $rColor = $rolesMap[$u['role']]['couleur'] ?? '#3b82f6';
      $rLabel = $rolesMap[$u['role']]['label']   ?? ucfirst($u['role']);
      [$sBd,$sLb] = [$sBadge[$u['statut']]??'badge-gray', $sLabel[$u['statut']]??$u['statut']];
      $initiales = h($u['avatar_initiales'] ?? strtoupper(mb_substr($u['prenom'],0,1).mb_substr($u['nom'],0,1)));
    ?>
    <tr <?= $u['statut']!=='actif'?'style="opacity:.5"':'' ?>>
      <td>
        <div style="display:flex;align-items:center;gap:9px">
          <div class="user-avatar" style="width:32px;height:32px;font-size:11px;background:<?= h($rColor) ?>22;color:<?= h($rColor) ?>;border:1px solid <?= h($rColor) ?>44"><?= $initiales ?></div>
          <div>
            <div style="font-size:13px;font-weight:600"><?= h($u['prenom'].' '.$u['nom']) ?></div>
            <div class="text-xs text3">@<?= h($u['username']) ?></div>
            <?php if ($u['email']): ?><div class="text-xs text3"><?= h($u['email']) ?></div><?php endif; ?>
          </div>
        </div>
      </td>
      <td><span style="background:<?= h($rColor) ?>22;color:<?= h($rColor) ?>;padding:3px 9px;border-radius:12px;font-size:11px;font-weight:600"><?= h($rLabel) ?></span></td>
      <td style="font-size:12px;color:var(--text2)"><?= h($u['specialite']??'') ?></td>
      <td style="font-size:11px;color:var(--text2)"><?= h($u['planning']??'') ?></td>
      <td style="text-align:center;font-weight:600;color:<?= (int)$u['patients_actifs']>0?'var(--accent2)':'var(--text3)' ?>"><?= (int)$u['patients_actifs'] ?></td>
      <td style="text-align:center"><?= (int)$u['rdv_today'] ?></td>
      <td style="text-align:center"><?= (int)$u['rdv_mois'] ?></td>
      <td><span class="badge <?= $sBd ?>"><?= $sLb ?></span></td>
      <td style="font-size:11px;color:var(--text3)"><?= $u['derniere_connexion']?fmt_date($u['derniere_connexion'],true):'Jamais' ?></td>
    </tr>
    <?php endforeach; ?>
    <?php if (empty($staff)): ?>
    <tr><td colspan="9" style="text-align:center;padding:32px;color:var(--text3)">Aucun employ</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';
