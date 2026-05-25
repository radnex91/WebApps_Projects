<div class="page-header">
    <div>
        <h1>Rapports</h1>
        <div class="page-header-subtitle">Statistiques et indicateurs d'activité</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi indigo">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Total</div>
                    <div class="card-kpi-value"><?= $stats['total'] ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-ticket-perforated"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi green">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Résolus</div>
                    <div class="card-kpi-value"><?= $stats['resolved'] ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi amber">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Ouverts</div>
                    <div class="card-kpi-value"><?= $stats['open'] ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-envelope-open"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi cyan">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Taux résolution</div>
                    <div class="card-kpi-value"><?= $stats['rate'] ?>%</div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-percent"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-pie-chart" style="margin-right:6px;color:var(--md-secondary)"></i> Tickets par statut</h5>
            </div>
            <div class="card-content-body">
                <canvas id="reportStatusChart" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-people" style="margin-right:6px;color:var(--md-secondary)"></i> Tickets par technicien</h5>
            </div>
            <div class="card-content-body">
                <canvas id="techChart" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mt-2">
    <div class="col-12">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-graph-up" style="margin-right:6px;color:var(--md-secondary)"></i> Tickets dans le temps</h5>
            </div>
            <div class="card-content-body">
                <canvas id="timeChart" height="180"></canvas>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
fetch('/gestion-support/admin/stats/tickets-by-status').then(r=>r.json()).then(d=>new Chart(document.getElementById('reportStatusChart'),{type:'pie',data:{labels:d.map(i=>i.label),datasets:[{data:d.map(i=>i.count)]}}));
fetch('/gestion-support/admin/stats/technician-load').then(r=>r.json()).then(d=>new Chart(document.getElementById('techChart'),{type:'bar',data:{labels:d.map(i=>i.name),datasets:[{label:'Tickets',data:d.map(i=>i.count)]}}));
fetch('/gestion-support/admin/stats/tickets-over-time').then(r=>r.json()).then(d=>new Chart(document.getElementById('timeChart'),{type:'line',data:{labels:d.map(i=>i.date),datasets:[{label:'Tickets',data:d.map(i=>i.count)]}}));
</script>