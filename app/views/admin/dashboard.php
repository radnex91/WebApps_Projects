<div class="page-header">
    <div>
        <h1>Tableau de bord</h1>
        <div class="page-header-subtitle">Vue d'ensemble de l'activité du support</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi indigo">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Total Tickets</div>
                    <div class="card-kpi-value"><?= $totalTickets ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-ticket-perforated"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi amber">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Ouverts</div>
                    <div class="card-kpi-value"><?= $openTickets ?></div>
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
                    <div class="card-kpi-label">En cours</div>
                    <div class="card-kpi-value"><?= $inProgressTickets ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="card-material card-kpi green">
            <div class="card-kpi-header">
                <div>
                    <div class="card-kpi-label">Résolus</div>
                    <div class="card-kpi-value"><?= $resolvedTickets ?></div>
                </div>
                <div class="card-kpi-icon">
                    <i class="bi bi-check-circle"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-tools" style="margin-right:6px;color:var(--md-secondary)"></i> Interventions (30 jours)</h5>
            </div>
            <div class="card-content-body" style="padding:8px 16px 12px">
                <canvas id="interventionsChart" height="140"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card-content">
            <div class="card-content-header">
                <h5><i class="bi bi-star" style="margin-right:6px;color:var(--md-secondary)"></i> Top techniciens</h5>
            </div>
            <div class="card-content-body" style="padding:8px 16px 12px" id="topTechContainer">
                <div class="text-center text-muted small py-3">Chargement...</div>
            </div>
        </div>
    </div>
</div>

<script>
fetch('/gestion-support/admin/stats/interventions-over-time').then(r=>r.json()).then(d=>{
    const dates = d.map(i=>i.date);
    const counts = d.map(i=>i.count);
    new Chart(document.getElementById('interventionsChart'),{
        type:'line',
        data:{
            labels:dates,
            datasets:[{
                label:'Tickets résolus',
                data:counts,
                fill:true,
                backgroundColor:'rgba(33,150,243,0.12)',
                borderColor:'#1976D2',
                borderWidth:2,
                pointBackgroundColor:'#1976D2',
                pointRadius:3,
                tension:0.4
            }]
        },
        options:{
            responsive:true,
            plugins:{legend:{display:false}},
            scales:{
                y:{beginAtZero:true,ticks:{precision:0}},
                x:{ticks:{maxRotation:45,font:{size:10}}}
            },
            elements:{line:{tension:0.4}}
        }
    });
});

fetch('/gestion-support/admin/stats/top-technicians').then(r=>r.json()).then(d=>{
    let html = '<div style="display:flex;flex-direction:column;gap:8px">';
    const colors = ['#FFD700','#C0C0C0','#CD7F32','#9E9E9E','#9E9E9E'];
    d.forEach((t,i)=>{
        const medal = i<3 ? `<span style="margin-right:6px">${['🥇','🥈','🥉'][i]}</span>` : '';
        html += `<div style="display:flex;align-items:center;justify-content:space-between;padding:6px 10px;border-radius:6px;background:#f5f5f5">
            <div style="display:flex;align-items:center;gap:8px">
                ${medal}
                <div>
                    <div style="font-weight:500;font-size:13px">${t.nom}</div>
                    <div style="font-size:11px;color:#888">${t.email}</div>
                </div>
            </div>
            <div style="font-weight:600;font-size:15px;color:var(--md-primary)">${t.resolved_count}</div>
        </div>`;
    });
    html += '</div>';
    document.getElementById('topTechContainer').innerHTML = html;
});
</script>

