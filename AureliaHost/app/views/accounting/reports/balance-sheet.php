<?php $title = 'Bilan comptable'; ?>
<h4><i class="bi bi-file-earmark-bar-graph"></i> Bilan comptable</h4>
<div class="row g-3 mt-2">
    <div class="col-md-6">
        <div class="card"><div class="card-header bg-success text-white"><h6 class="mb-0"><i class="bi bi-plus-circle"></i> Actif</h6></div>
        <div class="card-body">
            <table class="table"><tbody>
                <tr><td>Valeur du patrimoine (chambres)</td><td class="text-end fw-bold"><?= formatMoney($actifRooms) ?></td></tr>
                <tr><td>Créances clients</td><td class="text-end fw-bold"><?= formatMoney($actifCreances) ?></td></tr>
                <tr class="table-success"><th>Total Actif</th><th class="text-end"><?= formatMoney($actifRooms + $actifCreances) ?></th></tr>
            </tbody></table>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card"><div class="card-header bg-danger text-white"><h6 class="mb-0"><i class="bi bi-dash-circle"></i> Passif</h6></div>
        <div class="card-body">
            <table class="table"><tbody>
                <tr><td>Salaires à payer</td><td class="text-end fw-bold"><?= formatMoney($passifPaie) ?></td></tr>
                <tr class="table-danger"><th>Total Passif</th><th class="text-end"><?= formatMoney($passifPaie) ?></th></tr>
            </tbody></table>
        </div></div>
    </div>
</div>
