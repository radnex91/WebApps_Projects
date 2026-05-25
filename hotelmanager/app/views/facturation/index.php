<?php $page_title = 'Facturation'; ?>

<div class="section-header mb-3">
  <h2><i class="bi bi-receipt me-2"></i>Factures
    <span class="badge bg-secondary ms-2"><?= $total ?></span>
  </h2>
</div>

<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Référence</th><th>Réservation</th><th>Client</th>
            <th>Émise le</th><th>HT</th><th>TVA</th><th>TTC</th><th>Statut</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($factures)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">
            <i class="bi bi-receipt fs-3 d-block mb-2"></i>Aucune facture.
          </td></tr>
          <?php endif; ?>
          <?php foreach ($factures as $f): ?>
          <?php
          $fstatus_map = ['brouillon'=>['secondary','Brouillon'],'emise'=>['warning','Émise'],
                          'payee'=>['success','Payée'],'annulee'=>['danger','Annulée']];
          [$fcls,$flbl] = $fstatus_map[$f['statut']] ?? ['secondary',$f['statut']];
          ?>
          <tr>
            <td class="fw-semibold text-primary"><?= e($f['reference']) ?></td>
            <td><a href="?page=reservations&action=show&id=<?= $f['reservation_id'] ?>"><?= e($f['res_ref']) ?></a></td>
            <td><?= e($f['client_nom']) ?></td>
            <td><?= format_date($f['date_emission']) ?></td>
            <td><?= format_money($f['sous_total']) ?></td>
            <td class="text-muted"><?= format_money($f['tva_montant']) ?></td>
            <td class="fw-bold"><?= format_money($f['total_ttc']) ?></td>
            <td><span class="badge bg-<?= $fcls ?>"><?= $flbl ?></span></td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end">
                <a href="?page=facturation&action=show&id=<?= $f['id'] ?>" class="btn btn-xs btn-outline-primary">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="?page=facturation&action=print&id=<?= $f['id'] ?>" target="_blank"
                   class="btn btn-xs btn-outline-secondary">
                  <i class="bi bi-printer"></i>
                </a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php if ($total_pages > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm justify-content-end">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
      <a class="page-link" href="?page=facturation&p=<?= $i ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
