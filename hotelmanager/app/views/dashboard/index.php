<?php $page_title = 'Tableau de bord'; ?>
<?php
// Préparer les données Chart.js
$chart_labels = json_encode($chart['labels']);
$chart_values = json_encode($chart['values']);
?>

<style>
.kpi-grid { display: grid; grid-template-columns: repeat(4,1fr); gap: 14px; margin-bottom: 20px; }
@media(max-width:992px){ .kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px){ .kpi-grid { grid-template-columns: 1fr; } }

.dash-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 16px; margin-bottom: 16px; }
@media(max-width:992px){ .dash-grid { grid-template-columns: 1fr; } }

.occ-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width:768px){ .occ-grid { grid-template-columns: 1fr; } }
</style>

<!-- KPI CARDS -->
<div class="kpi-grid">

  <div class="kpi-card">
    <div>
      <div class="label">Taux d'occupation</div>
      <div class="value"><?= $stats['taux'] ?>%</div>
      <div class="trend trend-up"><i class="bi bi-door-open"></i>
        <?= $stats['occ'] ?>/<?= $stats['total'] ?> chambres occupées
      </div>
    </div>
    <div class="icon" style="background:#dbeafe; color:#1a56db;">
      <i class="bi bi-building"></i>
    </div>
  </div>

  <div class="kpi-card">
    <div>
      <div class="label">Réservations actives</div>
      <div class="value"><?= $stats['checkins'] ?></div>
      <div class="trend trend-up">
        <i class="bi bi-arrow-up-circle"></i>
        <?= $stats['checkin_auj'] ?> check-in · <?= $stats['checkout_auj'] ?> check-out
      </div>
    </div>
    <div class="icon" style="background:#d1fae5; color:#057a55;">
      <i class="bi bi-calendar-check"></i>
    </div>
  </div>

  <div class="kpi-card">
    <div>
      <div class="label">CA du jour</div>
      <div class="value" style="font-size:18px;"><?= format_money($stats['ca_jour']) ?></div>
      <div class="trend" style="color:#6b7280;">
        Mois : <?= format_money($stats['ca_mois']) ?>
      </div>
    </div>
    <div class="icon" style="background:#fef3c7; color:#c27803;">
      <i class="bi bi-cash-stack"></i>
    </div>
  </div>

  <div class="kpi-card">
    <div>
      <div class="label">Chambres libres</div>
      <div class="value"><?= $stats['libres'] ?></div>
      <?php if ($stats['impayees'] > 0): ?>
      <div class="trend trend-down">
        <i class="bi bi-exclamation-circle"></i> <?= $stats['impayees'] ?> facture(s) impayée(s)
      </div>
      <?php else: ?>
      <div class="trend trend-up"><i class="bi bi-check-circle"></i> Aucune facture en attente</div>
      <?php endif; ?>
    </div>
    <div class="icon" style="background:#fce7f3; color:#9d174d;">
      <i class="bi bi-door-closed"></i>
    </div>
  </div>

</div>

<!-- ROW 2: Graphique CA + Réservations récentes -->
<div class="dash-grid">

  <!-- Graphique CA 7 jours -->
  <div class="card">
    <div class="card-header">
      <h5><i class="bi bi-bar-chart-line me-2 text-primary"></i>Chiffre d'affaires — 7 derniers jours</h5>
    </div>
    <div class="card-body">
      <canvas id="caChart" height="120"></canvas>
    </div>
  </div>

  <!-- Occupation par type de chambre -->
  <div class="card">
    <div class="card-header">
      <h5><i class="bi bi-pie-chart me-2 text-success"></i>Occupation par type</h5>
    </div>
    <div class="card-body">
      <canvas id="occChart" height="200"></canvas>
      <div class="mt-3">
        <?php foreach ($occ as $row): ?>
        <div class="d-flex justify-content-between align-items-center mb-2">
          <span class="small fw-semibold"><?= e($row['nom']) ?></span>
          <div class="d-flex align-items-center gap-2">
            <div style="width:80px; height:6px; background:#e5e7eb; border-radius:3px; overflow:hidden;">
              <?php $pct = $row['total'] > 0 ? round(($row['occupees'] / $row['total']) * 100) : 0; ?>
              <div style="width:<?= $pct ?>%; height:100%; background:#1a56db; border-radius:3px;"></div>
            </div>
            <span class="small text-muted"><?= $pct ?>%</span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

<!-- ROW 3: Réservations récentes + Actions rapides -->
<div class="occ-grid">

  <!-- Réservations récentes -->
  <div class="card">
    <div class="card-header">
      <h5><i class="bi bi-clock-history me-2"></i>Réservations récentes</h5>
      <a href="<?= APP_URL ?>/index.php?page=reservations" class="btn btn-sm btn-outline-primary">
        Tout voir
      </a>
    </div>
    <div class="card-body p-0">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Client</th>
            <th>Chambre</th>
            <th>Arrivée</th>
            <th>Statut</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($recent)): ?>
          <tr><td colspan="4" class="text-center text-muted py-3">Aucune réservation</td></tr>
          <?php endif; ?>
          <?php foreach ($recent as $r): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($r['client_nom']) ?></div>
              <div class="small text-muted"><?= e($r['reference']) ?></div>
            </td>
            <td>
              <span class="fw-semibold"><?= e($r['chambre_num']) ?></span>
              <div class="small text-muted"><?= e($r['type_chambre']) ?></div>
            </td>
            <td>
              <div><?= format_date($r['date_arrivee']) ?></div>
              <div class="small text-muted"><?= $r['nb_nuits'] ?> nuit(s)</div>
            </td>
            <td><?= badge_statut_reservation($r['statut']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Actions rapides -->
  <div class="card">
    <div class="card-header">
      <h5><i class="bi bi-lightning me-2 text-warning"></i>Actions rapides</h5>
    </div>
    <div class="card-body d-grid gap-2">
      <a href="<?= APP_URL ?>/index.php?page=reservations&action=create" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>Nouvelle réservation
      </a>
      <a href="<?= APP_URL ?>/index.php?page=clients&action=create" class="btn btn-outline-success">
        <i class="bi bi-person-plus me-2"></i>Nouveau client
      </a>
      <a href="<?= APP_URL ?>/index.php?page=chambres" class="btn btn-outline-secondary">
        <i class="bi bi-door-open me-2"></i>Gérer les chambres
      </a>
      <a href="<?= APP_URL ?>/index.php?page=facturation" class="btn btn-outline-warning">
        <i class="bi bi-receipt me-2"></i>Facturation
      </a>
      <a href="<?= APP_URL ?>/index.php?page=rapports" class="btn btn-outline-dark">
        <i class="bi bi-bar-chart me-2"></i>Rapports & stats
      </a>

      <?php if ($stats['checkin_auj'] > 0): ?>
      <div class="alert alert-info p-2 mb-0 mt-1 small">
        <i class="bi bi-bell me-1"></i>
        <strong><?= $stats['checkin_auj'] ?></strong> check-in(s) attendu(s) aujourd'hui
      </div>
      <?php endif; ?>
      <?php if ($stats['checkout_auj'] > 0): ?>
      <div class="alert alert-warning p-2 mb-0 small">
        <i class="bi bi-bell me-1"></i>
        <strong><?= $stats['checkout_auj'] ?></strong> check-out(s) prévus aujourd'hui
      </div>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script>
document.addEventListener('DOMContentLoaded', function() {

  // Graphique CA
  const caCtx = document.getElementById('caChart');
  if (caCtx) {
    new Chart(caCtx, {
      type: 'bar',
      data: {
        labels: <?= $chart_labels ?>,
        datasets: [{
          label: 'CA (FCFA)',
          data: <?= $chart_values ?>,
          backgroundColor: 'rgba(26,86,219,.75)',
          borderColor: '#1a56db',
          borderWidth: 2,
          borderRadius: 6,
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: {
            callbacks: {
              label: ctx => new Intl.NumberFormat('fr-FR').format(ctx.raw) + ' FCFA'
            }
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: v => (v/1000) + 'K',
              font: { size: 11 }
            },
            grid: { color: '#f3f4f6' }
          },
          x: { ticks: { font: { size: 11 } }, grid: { display: false } }
        }
      }
    });
  }

  // Graphique occupation
  const occCtx = document.getElementById('occChart');
  if (occCtx) {
    const occData = <?= json_encode(array_map(fn($r) => [
      'label' => $r['nom'],
      'val'   => (int)$r['occupees'],
    ], $occ)) ?>;

    new Chart(occCtx, {
      type: 'doughnut',
      data: {
        labels: occData.map(d => d.label),
        datasets: [{
          data: occData.map(d => d.val),
          backgroundColor: ['#1a56db','#057a55','#c27803','#9d174d','#374151'],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        cutout: '68%',
        plugins: {
          legend: { position: 'bottom', labels: { font: { size: 11 }, padding: 8 } }
        }
      }
    });
  }

});
</script>
