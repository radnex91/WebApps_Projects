<?php $page_title = 'Rapports & Statistiques'; ?>

<!-- Filtres période -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="rapports">
      <div class="col-sm-2">
        <select class="form-select form-select-sm" name="periode" id="periodeSelect">
          <option value="jour"    <?= $periode==='jour'    ? 'selected':'' ?>>Aujourd'hui</option>
          <option value="semaine" <?= $periode==='semaine' ? 'selected':'' ?>>Cette semaine</option>
          <option value="mois"    <?= $periode==='mois'    ? 'selected':'' ?>>Ce mois</option>
          <option value="custom"  <?= $periode==='custom'  ? 'selected':'' ?>>Personnalisé</option>
        </select>
      </div>
      <div class="col-sm-2">
        <input type="date" class="form-control form-control-sm" name="date_from"
               value="<?= e($date_from) ?>">
      </div>
      <div class="col-sm-2">
        <input type="date" class="form-control form-control-sm" name="date_to"
               value="<?= e($date_to) ?>">
      </div>
      <div class="col-sm-2">
        <button type="submit" class="btn btn-primary btn-sm">Actualiser</button>
      </div>
    </form>
  </div>
</div>

<!-- KPIs période -->
<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div>
        <div class="label">CA de la période</div>
        <div class="value" style="font-size:17px;"><?= format_money($ca_total) ?></div>
      </div>
      <div class="icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-cash-stack"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div>
        <div class="label">Réservations</div>
        <div class="value"><?= $nb_reservations ?></div>
      </div>
      <div class="icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-calendar-check"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div>
        <div class="label">Clients uniques</div>
        <div class="value"><?= $nb_clients_uniques ?></div>
      </div>
      <div class="icon bg-success bg-opacity-10 text-success"><i class="bi bi-people"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="kpi-card">
      <div>
        <div class="label">Taux d'occupation moy.</div>
        <div class="value"><?= $taux_occ ?>%</div>
      </div>
      <div class="icon bg-info bg-opacity-10 text-info"><i class="bi bi-building"></i></div>
    </div>
  </div>
</div>

<!-- Graphiques -->
<div class="row g-3 mb-4">
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header"><h5><i class="bi bi-graph-up me-2"></i>Évolution du CA</h5></div>
      <div class="card-body">
        <canvas id="evolutionChart" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header"><h5><i class="bi bi-bar-chart-horizontal me-2"></i>CA par type de chambre</h5></div>
      <div class="card-body">
        <?php foreach ($par_type as $t): ?>
        <?php $max_ca = max(array_column($par_type,'ca')) ?: 1; $pct = round($t['ca']/$max_ca*100); ?>
        <div class="mb-3">
          <div class="d-flex justify-content-between small mb-1">
            <span class="fw-semibold"><?= e($t['nom']) ?></span>
            <span class="text-muted"><?= format_money($t['ca']) ?> (<?= $t['nb_res'] ?> rés.)</span>
          </div>
          <div style="height:8px;background:#f3f4f6;border-radius:4px;overflow:hidden;">
            <div style="height:100%;width:<?= $pct ?>%;background:#1a56db;border-radius:4px;"></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($par_type)): ?>
        <p class="text-muted small text-center py-3">Aucune donnée pour cette période.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Top clients + Journal -->
<div class="row g-3">
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header"><h5><i class="bi bi-trophy me-2 text-warning"></i>Top 10 clients</h5></div>
      <div class="card-body p-0">
        <table class="table mb-0 table-sm">
          <thead><tr><th>#</th><th>Client</th><th class="text-center">Séjours</th><th class="text-end">Dépenses</th></tr></thead>
          <tbody>
            <?php if (empty($top_clients)): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">Aucune donnée.</td></tr>
            <?php endif; ?>
            <?php foreach ($top_clients as $i => $c): ?>
            <tr>
              <td>
                <?php if ($i < 3): ?>
                <span style="font-size:16px;"><?= ['🥇','🥈','🥉'][$i] ?></span>
                <?php else: ?>
                <span class="text-muted small"><?= $i+1 ?></span>
                <?php endif; ?>
              </td>
              <td class="fw-semibold small"><?= e($c['client_nom']) ?></td>
              <td class="text-center small"><?= $c['nb_sejours'] ?></td>
              <td class="text-end fw-semibold small text-primary"><?= format_money($c['total_depense']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card">
      <div class="card-header">
        <h5><i class="bi bi-shield-lock me-2 text-secondary"></i>Journal d'activité récent</h5>
      </div>
      <div class="card-body p-0" style="max-height:350px;overflow-y:auto;">
        <table class="table table-sm mb-0">
          <thead><tr><th>Date</th><th>Utilisateur</th><th>Action</th><th>Module</th></tr></thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
            <tr>
              <td class="small text-muted"><?= format_datetime($l['created_at']) ?></td>
              <td class="small"><?= e($l['user_nom'] ?? 'Système') ?></td>
              <td class="small"><code><?= e($l['action']) ?></code></td>
              <td><span class="badge bg-light text-dark border small"><?= e($l['module']) ?></span></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const evolutionData = <?= json_encode(array_map(fn($r) => [
    'jour' => date('d/m', strtotime($r['jour'])),
    'ca'   => (float)$r['ca']
  ], $evolution)) ?>;

  const ctx = document.getElementById('evolutionChart');
  if (ctx && evolutionData.length > 0) {
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: evolutionData.map(d => d.jour),
        datasets: [{
          label: 'CA (FCFA)',
          data: evolutionData.map(d => d.ca),
          borderColor: '#1a56db',
          backgroundColor: 'rgba(26,86,219,.08)',
          borderWidth: 2.5,
          fill: true,
          tension: 0.4,
          pointRadius: 4,
          pointBackgroundColor: '#1a56db',
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: ctx => new Intl.NumberFormat('fr-FR').format(Math.round(ctx.raw)) + ' FCFA' } }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: { callback: v => (v/1000) + 'K', font: { size: 11 } },
            grid: { color: '#f3f4f6' }
          },
          x: { ticks: { font: { size: 11 } }, grid: { display: false } }
        }
      }
    });
  } else if (ctx) {
    ctx.parentElement.innerHTML = '<p class="text-muted text-center py-4">Aucune donnée de CA pour cette période.</p>';
  }
});
</script>
