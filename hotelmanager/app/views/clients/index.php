<?php $page_title = 'Gestion des clients'; ?>

<!-- Header -->
<div class="section-header mb-3">
  <h2><i class="bi bi-people me-2"></i>Clients
    <span class="badge bg-secondary ms-2"><?= $total ?></span>
  </h2>
  <a href="<?= APP_URL ?>/index.php?page=clients&action=create" class="btn btn-primary">
    <i class="bi bi-person-plus me-1"></i>Nouveau client
  </a>
</div>

<!-- Recherche -->
<div class="card mb-3">
  <div class="card-body py-2">
    <form method="GET" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="clients">
      <div class="col-sm-5">
        <input type="text" class="form-control" name="search"
               value="<?= e($_GET['search'] ?? '') ?>"
               placeholder="🔍 Nom, téléphone, email, référence...">
      </div>
      <div class="col-sm-3">
        <select class="form-select" name="type">
          <option value="">Tous types</option>
          <?php foreach (['standard'=>'Standard','fidele'=>'Fidèle','vip'=>'VIP','professionnel'=>'Professionnel'] as $v=>$l): ?>
          <option value="<?= $v ?>" <?= ($_GET['type']??'') === $v ? 'selected':'' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm">Rechercher</button>
        <a href="?page=clients" class="btn btn-outline-secondary btn-sm">Réinitialiser</a>
      </div>
    </form>
  </div>
</div>

<!-- Table -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Réf.</th><th>Nom complet</th><th>Téléphone</th>
            <th>Nationalité</th><th>Type</th><th>Séjours</th>
            <th>Dernier séjour</th><th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($clients)): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">
            <i class="bi bi-person-x fs-3 d-block mb-2"></i>Aucun client trouvé.
          </td></tr>
          <?php endif; ?>
          <?php foreach ($clients as $c): ?>
          <tr>
            <td><span class="small text-muted"><?= e($c['reference']) ?></span></td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="user-avatar" style="width:30px;height:30px;font-size:11px;flex-shrink:0;">
                  <?= strtoupper(substr($c['prenom'],0,1) . substr($c['nom'],0,1)) ?>
                </div>
                <div>
                  <div class="fw-semibold"><?= e($c['prenom'] . ' ' . $c['nom']) ?></div>
                  <?php if ($c['email']): ?><div class="small text-muted"><?= e($c['email']) ?></div><?php endif; ?>
                </div>
              </div>
            </td>
            <td><?= e($c['telephone']) ?></td>
            <td><?= e($c['nationalite'] ?? '—') ?></td>
            <td>
              <?php $type_badges = ['standard'=>'secondary','fidele'=>'info','vip'=>'warning','professionnel'=>'primary']; ?>
              <span class="badge bg-<?= $type_badges[$c['type_client']] ?? 'secondary' ?>">
                <?= ucfirst($c['type_client']) ?>
              </span>
            </td>
            <td class="text-center"><?= $c['nb_sejours'] ?></td>
            <td><?= $c['dernier_sejour'] ? format_date($c['dernier_sejour']) : '—' ?></td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end">
                <a href="?page=clients&action=show&id=<?= $c['id'] ?>"
                   class="btn btn-xs btn-outline-primary" title="Voir fiche">
                  <i class="bi bi-eye"></i>
                </a>
                <a href="?page=clients&action=edit&id=<?= $c['id'] ?>"
                   class="btn btn-xs btn-outline-secondary" title="Modifier">
                  <i class="bi bi-pencil"></i>
                </a>
                <a href="?page=reservations&action=create&client_id=<?= $c['id'] ?>"
                   class="btn btn-xs btn-outline-success" title="Réserver">
                  <i class="bi bi-calendar-plus"></i>
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
      <a class="page-link" href="?page=clients&p=<?= $i ?>&<?= http_build_query(array_diff_key($_GET,['p'=>''])) ?>"><?= $i ?></a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
