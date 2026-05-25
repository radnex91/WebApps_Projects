<?php $page_title = 'Gestion des chambres'; ?>

<!-- Stats rapides -->
<div class="row g-3 mb-3">
  <?php
  $stat_map = [
    'disponible'  => ['success','bi-check-circle', 'Disponibles'],
    'occupee'     => ['danger', 'bi-people',        'Occupées'],
    'nettoyage'   => ['warning','bi-stars',          'Nettoyage'],
    'maintenance' => ['dark',   'bi-tools',          'Maintenance'],
  ];
  foreach ($stat_map as $key => [$color, $icon, $label]):
    $n = $stats_statuts[$key] ?? 0;
  ?>
  <div class="col-6 col-md-3">
    <a href="?page=chambres&statut=<?= $key ?>" class="text-decoration-none">
      <div class="kpi-card">
        <div>
          <div class="label"><?= $label ?></div>
          <div class="value"><?= $n ?></div>
        </div>
        <div class="icon bg-<?= $color ?> bg-opacity-10 text-<?= $color ?>">
          <i class="bi <?= $icon ?>"></i>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtres + ajout -->
<div class="section-header">
  <div class="d-flex gap-2 flex-wrap">
    <a href="?page=chambres" class="btn btn-sm <?= empty($_GET['statut']) && empty($_GET['type']) ? 'btn-dark' : 'btn-outline-secondary' ?>">
      Toutes
    </a>
    <?php foreach ($stat_map as $key => [$color, $icon, $label]): ?>
    <a href="?page=chambres&statut=<?= $key ?>"
       class="btn btn-sm <?= ($_GET['statut'] ?? '') === $key ? "btn-$color" : "btn-outline-$color" ?>">
      <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
    </a>
    <?php endforeach; ?>
  </div>
  <?php if (has_permission('all') || has_permission('chambres')): ?>
  <a href="?page=chambres&action=create" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i>Ajouter chambre
  </a>
  <?php endif; ?>
</div>

<!-- Vue grille -->
<div class="chambre-grid mb-4">
  <?php if (empty($chambres)): ?>
  <div class="col-12">
    <p class="text-muted text-center py-4">Aucune chambre trouvée.</p>
  </div>
  <?php endif; ?>

  <?php foreach ($chambres as $c): ?>
  <div class="chambre-tile <?= $c['statut'] ?>" onclick="openChambreModal(<?= htmlspecialchars(json_encode($c)) ?>)">
    <div class="num"><?= e($c['numero']) ?></div>
    <div class="type"><?= e($c['type_nom']) ?></div>
    <div class="stat">
      <?php
      $icons_stat = ['disponible'=>'✅','occupee'=>'🔴','nettoyage'=>'🧹','maintenance'=>'🔧'];
      echo ($icons_stat[$c['statut']] ?? '⬜') . ' ' . ucfirst($c['statut']);
      ?>
    </div>
    <div class="small text-muted mt-1"><?= format_money($c['tarif_nuit']) ?>/nuit</div>
    <?php if ($c['client_actuel']): ?>
    <div class="small text-danger mt-1"><i class="bi bi-person-fill"></i> <?= e($c['client_actuel']) ?></div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tableau détaillé -->
<div class="card">
  <div class="card-header">
    <h5><i class="bi bi-table me-2"></i>Détail des chambres</h5>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Nº</th><th>Type</th><th>Étage</th><th>Capacité</th>
            <th>Tarif/nuit</th><th>Statut</th><th>Client actuel</th><th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($chambres as $c): ?>
          <tr>
            <td class="fw-bold"><?= e($c['numero']) ?></td>
            <td><?= e($c['type_nom']) ?></td>
            <td>Ét. <?= $c['etage'] ?></td>
            <td><?= $c['capacite'] ?> pers.</td>
            <td><?= format_money($c['tarif_nuit']) ?></td>
            <td><?= badge_statut_chambre($c['statut']) ?></td>
            <td><?= $c['client_actuel'] ? '<span class="fw-semibold text-danger">' . e($c['client_actuel']) . '</span>' : '<span class="text-muted">—</span>' ?></td>
            <td>
              <div class="d-flex gap-1">
                <button class="btn btn-xs btn-outline-secondary"
                        onclick="openChambreModal(<?= htmlspecialchars(json_encode($c)) ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <?php if ($c['statut'] !== 'disponible' && !$c['client_actuel']): ?>
                <form method="POST" action="?page=chambres&action=updateStatut" class="d-inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= $c['id'] ?>">
                  <input type="hidden" name="statut" value="disponible">
                  <button type="submit" class="btn btn-xs btn-success" title="Marquer disponible">
                    <i class="bi bi-check"></i>
                  </button>
                </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal changement statut -->
<div class="modal fade" id="chambreModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Chambre <span id="modalNum"></span></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="modalInfo" class="mb-3"></div>
        <form method="POST" action="?page=chambres&action=updateStatut">
          <?= csrf_field() ?>
          <input type="hidden" name="id" id="modalId">
          <div class="mb-3">
            <label class="form-label">Changer le statut</label>
            <select class="form-select" name="statut" id="modalStatut">
              <option value="disponible">✅ Disponible</option>
              <option value="nettoyage">🧹 En nettoyage</option>
              <option value="maintenance">🔧 Maintenance</option>
            </select>
          </div>
          <div class="d-flex justify-content-end gap-2">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            <button type="submit" class="btn btn-primary">Mettre à jour</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function openChambreModal(c) {
  document.getElementById('modalNum').textContent   = c.numero;
  document.getElementById('modalId').value          = c.id;
  document.getElementById('modalStatut').value      = c.statut;
  document.getElementById('modalInfo').innerHTML    =
    `<div class="d-flex gap-3">
       <div><small class="text-muted">Type</small><div class="fw-bold">${c.type_nom}</div></div>
       <div><small class="text-muted">Tarif</small><div class="fw-bold">${parseInt(c.tarif_nuit).toLocaleString('fr-FR')} FCFA/nuit</div></div>
       <div><small class="text-muted">Capacité</small><div class="fw-bold">${c.capacite} pers.</div></div>
     </div>`;
  new bootstrap.Modal(document.getElementById('chambreModal')).show();
}
</script>
