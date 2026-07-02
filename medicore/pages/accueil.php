<?php
$currentPage = 'accueil';
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/dossiers.php';
require_once __DIR__ . '/../includes/accueil.php';
requireLogin();

$flash = null;

// Les POST handlers seront ajoutés en Task 5-8.

require_once __DIR__ . '/../includes/layout.php';
requirePageAccess('accueil');

// --- Filtre statut (GET) ---
$statutFilter = in_whitelist(get_str('statut'), ['', 'arrive', 'constantes_prises', 'en_consultation', 'termine', 'parti'], '');

// --- File d'attente du jour ---
$arrivees = get_arrivees_du_jour($statutFilter ?: null);

// --- Stats du jour ---
$stats = [
    'total'      => 0, 'arrive' => 0, 'constantes' => 0,
    'consultation' => 0, 'termine' => 0,
];
foreach (get_arrivees_du_jour() as $a) {
    $stats['total']++;
    if ($a['statut'] === 'arrive') $stats['arrive']++;
    elseif ($a['statut'] === 'constantes_prises') $stats['constantes']++;
    elseif ($a['statut'] === 'en_consultation') $stats['consultation']++;
    elseif ($a['statut'] === 'termine') $stats['termine']++;
}

// --- Médecins actifs (pour modal orientation, Task 8) ---
$medecins = db_select("SELECT id, CONCAT(prenom,' ',nom) AS nom_complet, specialite FROM utilisateurs WHERE role='medecin' AND statut='actif' ORDER BY nom");

// --- Setting gating souple ---
$verifierConsultation = setting('accueil_verifier_consultation', '1') === '1';

// --- Patients récents (pour check-in existant, Task 6) ---
$patientsRecents = db_select("SELECT id, numero, nom, prenom FROM patients ORDER BY date_creation DESC LIMIT 50");

$statutBadge = [
    'arrive'            => 'badge-blue',
    'constantes_prises' => 'badge-yellow',
    'en_consultation'   => 'badge-purple',
    'termine'           => 'badge-green',
    'parti'             => 'badge-gray',
];
?>

<?php if (!empty($flash)): ?>
<div class="alert alert-<?= $flash[0]==='green'?'green':'red' ?> alert-auto"><?= h($flash[1]) ?></div>
<?php endif; ?>

<div class="page-header-row">
  <div><h2>🏥 Accueil — Salle d'attente</h2><p><?= $stats['total'] ?> patient(s) aujourd'hui · <?= date('d/m/Y') ?></p></div>
  <div style="display:flex;gap:8px">
    <?php if (can('accueil.checkin')): ?>
    <button class="btn btn-blue" onclick="document.getElementById('modal-enregistrer').style.display='flex'">＋ Enregistrer un patient</button>
    <?php endif; ?>
  </div>
</div>

<!-- Stats -->
<div class="stats-grid mb-24">
  <div class="stat-card blue"><div class="stat-icon blue">👥</div><div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total aujourd'hui</div></div>
  <div class="stat-card yellow"><div class="stat-icon yellow">⏳</div><div class="stat-value"><?= $stats['arrive'] ?></div><div class="stat-label">En attente constantes</div></div>
  <div class="stat-card purple"><div class="stat-icon purple">🩺</div><div class="stat-value"><?= $stats['consultation'] ?></div><div class="stat-label">En consultation</div></div>
  <div class="stat-card green"><div class="stat-icon green">✅</div><div class="stat-value"><?= $stats['termine'] ?></div><div class="stat-label">Terminés</div></div>
</div>

<!-- Filtres statut -->
<div style="display:flex;gap:6px;margin-bottom:16px;flex-wrap:wrap">
  <a href="accueil.php" class="btn btn-sm <?= $statutFilter===''?'btn-blue':'btn-ghost' ?>">Tous (<?= $stats['total'] ?>)</a>
  <?php foreach (['arrive'=>'Arrivé','constantes_prises'=>'Constantes prises','en_consultation'=>'En consultation','termine'=>'Terminé'] as $sv=>$sl): ?>
  <a href="accueil.php?statut=<?= $sv ?>" class="btn btn-sm <?= $statutFilter===$sv?'btn-blue':'btn-ghost' ?>"><?= $sl ?></a>
  <?php endforeach; ?>
</div>

<!-- File d'attente -->
<div class="card">
  <div class="card-header"><h3>File d'attente du jour</h3><span style="font-size:12px;color:var(--text2)"><?= count($arrivees) ?> patient(s)</span></div>
  <table>
    <thead><tr><th>Patient</th><th>Arrivée</th><th>Statut</th><th>Constantes</th><th>Médecin</th><th>Accès aux soins</th><th class="col-actions">Actions</th></tr></thead>
    <tbody>
    <?php foreach ($arrivees as $a):
        $age = !empty($a['date_naissance']) ? (int)((time()-strtotime($a['date_naissance']))/31536000) : '?';
        $dossierUrl = secure_url('dossiers.php', (int)$a['patient_id'], 'dossier', ['patient_id' => (int)$a['patient_id']]);
    ?>
      <tr>
        <td>
          <a href="<?= h($dossierUrl) ?>" style="text-decoration:none"><strong><?= h($a['prenom'].' '.$a['nom']) ?></strong></a>
          <div style="font-size:11px;color:var(--text3)"><?= h($a['numero']) ?> · <?= $age ?> ans · <?= h($a['sexe']) ?></div>
        </td>
        <td><?= date('H:i', strtotime($a['date_arrivee'])) ?></td>
        <td><span class="badge <?= $statutBadge[$a['statut']] ?? 'badge-gray' ?>"><?= h(accueil_statut_label($a['statut'])) ?></span></td>
        <td><?= $a['statut']==='arrive' ? '<span class="badge badge-gray">—</span>' : '<span class="badge badge-green">✓ '.date('H:i', strtotime($a['date_constantes'])).'</span>' ?></td>
        <td><?= $a['medecin_nom'] ? h($a['medecin_nom']) : '<span style="color:var(--text3)">—</span>' ?></td>
        <td>
          <?php if ($verifierConsultation): ?>
            <?php if (!$a['a_dossier']): ?><span class="badge badge-yellow" title="Pas de dossier médical">📂 No dossier</span> <?php endif; ?>
            <?php if (!$a['a_consultation']): ?><span class="badge badge-yellow" title="Pas de droit de consultation payé">🧾 No consultation</span><?php endif; ?>
            <?php if ($a['a_dossier'] && $a['a_consultation']): ?><span class="badge badge-green">✓</span><?php endif; ?>
          <?php else: ?>
            <span style="color:var(--text3)">—</span>
          <?php endif; ?>
        </td>
        <td><div class="row-actions"><span class="row-hint" aria-hidden="true">⋯</span>
          <?php if (can('accueil.vitals') && in_array($a['statut'], ['arrive'], true)): ?>
          <button class="btn btn-sm btn-ghost" data-vitals="<?= (int)$a['id'] ?>" data-pid="<?= (int)$a['patient_id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">🌡️ Constantes</button>
          <?php endif; ?>
          <?php if (can('accueil.orienter') && in_array($a['statut'], ['constantes_prises','arrive'], true)): ?>
          <button class="btn btn-sm btn-ghost" data-orienter="<?= (int)$a['id'] ?>" data-pid="<?= (int)$a['patient_id'] ?>" data-nom="<?= h($a['prenom'].' '.$a['nom']) ?>">🩺 Orienter</button>
          <?php endif; ?>
          <?php if (can('accueil.orienter') && in_array($a['statut'], ['en_consultation','constantes_prises','arrive'], true)): ?>
          <form method="POST" style="display:inline" onsubmit="return confirm('Terminer la prise en charge de ce patient ?')">
            <input type="hidden" name="action" value="terminer">
            <input type="hidden" name="arrivee_id" value="<?= (int)$a['id'] ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-green" style="font-size:11px">✓</button>
          </form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (empty($arrivees)): ?>
      <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text3)">Aucun patient dans la file d'attente. Cliquez sur « Enregistrer un patient ».</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require_once __DIR__ . '/../includes/footer.php';