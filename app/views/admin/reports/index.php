<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1><i class="bi bi-bar-chart"></i> Rapports</h1>
</div>
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-bg-primary"><div class="card-body"><h6>Total</h6><p class="display-6"><?= $stats['total'] ?></p></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-success"><div class="card-body"><h6>Résolus</h6><p class="display-6"><?= $stats['resolved'] ?></p></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-warning"><div class="card-body"><h6>Ouverts</h6><p class="display-6"><?= $stats['open'] ?></p></div></div>
    </div>
    <div class="col-md-3">
        <div class="card text-bg-info"><div class="card-body"><h6>Taux résolution</h6><p class="display-6"><?= $stats['rate'] ?>%</p></div></div>
    </div>
</div>
<div class="row">
    <div class="col-md-6"><div class="card"><div class="card-header">Tickets par statut</div><div class="card-body"><canvas id="reportStatusChart"></canvas></div></div></div>
    <div class="col-md-6"><div class="card"><div class="card-header">Tickets par technicien</div><div class="card-body"><canvas id="techChart"></canvas></div></div></div>
</div>
<div class="row mt-4">
    <div class="col-12"><div class="card"><div class="card-header">Tickets dans le temps</div><div class="card-body"><canvas id="timeChart"></canvas></div></div></div>
</div>
<script>
fetch('/gestion-support/admin/stats/tickets-by-status').then(r=>r.json()).then(d=>new Chart(document.getElementById('reportStatusChart'),{type:'pie',data:{labels:d.map(i=>i.label),datasets:[{data:d.map(i=>i.count)]}}));
fetch('/gestion-support/admin/stats/technician-load').then(r=>r.json()).then(d=>new Chart(document.getElementById('techChart'),{type:'bar',data:{labels:d.map(i=>i.name),datasets:[{label:'Tickets',data:d.map(i=>i.count)]}}));
fetch('/gestion-support/admin/stats/tickets-over-time').then(r=>r.json()).then(d=>new Chart(document.getElementById('timeChart'),{type:'line',data:{labels:d.map(i=>i.date),datasets:[{label:'Tickets',data:d.map(i=>i.count)]}}));
</script>
