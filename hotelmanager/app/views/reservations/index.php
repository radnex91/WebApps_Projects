<?php $page_title = 'Réservations'; ?>

<!-- Filtres -->
<div class="card mb-3">
  <div class="card-body py-3">
    <form method="GET" action="" class="row g-2 align-items-end">
      <input type="hidden" name="page" value="reservations">
      <div class="col-sm-4">
        <input type="text" class="form-control" name="search"
               value="<?= e($_GET['search'] ?? '') ?>"
               placeholder="🔍 Nom client, référence...">
      </div>
      <div class="col-sm-2">
        <select class="form-select" name="statut">
          <option value="">Tous les statuts</option>
          <?php foreach (['en_attente'=>'En attente','confirmee'=>'Confirmée','checkin'=>'Check-in','checkout'=>'Check-out','annulee'=>'Annulée','no_show'=>'No-show'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($_GET['statut'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-sm-2">
        <input type="date" class="form-control" name="date_from"
               value="<?= e($_GET['date_from'] ?? '') ?>" placeholder="Du">
      </div>
      <div class="col-sm-2">
        <input type="date" class="form-control" name="date_to"
               value="<?= e($_GET['date_to'] ?? '') ?>" placeholder="Au">
      </div>
      <div class="col-sm-2 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm flex-fill">Filtrer</button>
        <a href="<?= APP_URL ?>/index.php?page=reservations" class="btn btn-outline-secondary btn-sm">✕</a>
      </div>
    </form>
  </div>
</div>

<!-- Header + bouton -->
<div class="section-header">
  <h2><i class="bi bi-calendar-check me-2"></i>Liste des réservations
    <span class="badge bg-secondary ms-2"><?= $total ?></span>
  </h2>
  <a href="<?= APP_URL ?>/index.php?page=reservations&action=create" class="btn btn-primary">
    <i class="bi bi-plus-circle me-1"></i>Nouvelle réservation
  </a>
</div>

<!-- Tableau -->
<div class="card">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0">
        <thead>
          <tr>
            <th>Référence</th>
            <th>Client</th>
            <th>Chambre</th>
            <th>Arrivée</th>
            <th>Départ</th>
            <th>Nuits</th>
            <th>Montant</th>
            <th>Statut</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($reservations)): ?>
          <tr><td colspan="9" class="text-center text-muted py-4">
            <i class="bi bi-calendar-x fs-3 d-block mb-2"></i>
            Aucune réservation trouvée.
          </td></tr>
          <?php endif; ?>

          <?php foreach ($reservations as $r): ?>
          <tr>
            <td>
              <span class="fw-semibold text-primary"><?= e($r['reference']) ?></span>
            </td>
            <td>
              <div class="fw-semibold"><?= e($r['client_nom']) ?></div>
              <div class="small text-muted"><?= e($r['telephone']) ?></div>
            </td>
            <td>
              <span class="badge bg-light text-dark border"><?= e($r['chambre_num']) ?></span>
              <div class="small text-muted"><?= e($r['type_chambre']) ?></div>
            </td>
            <td><?= format_date($r['date_arrivee']) ?></td>
            <td><?= format_date($r['date_depart']) ?></td>
            <td class="text-center"><?= $r['nb_nuits'] ?></td>
            <td class="fw-semibold"><?= format_money($r['montant_total']) ?></td>
            <td><?= badge_statut_reservation($r['statut']) ?></td>
            <td class="text-end">
              <div class="d-flex gap-1 justify-content-end flex-wrap">
                <a href="<?= APP_URL ?>/index.php?page=reservations&action=show&id=<?= $r['id'] ?>"
                   class="btn btn-xs btn-outline-primary" title="Détails">
                  <i class="bi bi-eye"></i>
                </a>
                <?php if ($r['statut'] === 'confirmee' || $r['statut'] === 'en_attente'): ?>
                <a href="<?= APP_URL ?>/index.php?page=reservations&action=checkin&id=<?= $r['id'] ?>"
                   class="btn btn-xs btn-success" title="Check-in"
                   data-confirm="Confirmer le check-in pour <?= e($r['client_nom']) ?> ?">
                  <i class="bi bi-box-arrow-in-right"></i>
                </a>
                <?php endif; ?>
                <?php if ($r['statut'] === 'checkin'): ?>
                <a href="<?= APP_URL ?>/index.php?page=reservations&action=checkout&id=<?= $r['id'] ?>"
                   class="btn btn-xs btn-warning" title="Check-out"
                   data-confirm="Confirmer le check-out ?">
                  <i class="bi bi-box-arrow-right"></i>
                </a>
                <?php endif; ?>
                <?php if (!in_array($r['statut'], ['annulee','no_show','checkout'])): ?>
                <a href="<?= APP_URL ?>/index.php?page=reservations&action=cancel&id=<?= $r['id'] ?>"
                   class="btn btn-xs btn-outline-danger" title="Annuler"
                   data-confirm="Annuler la réservation <?= e($r['reference']) ?> ?">
                  <i class="bi bi-x-circle"></i>
                </a>
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

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav class="mt-3">
  <ul class="pagination pagination-sm justify-content-end">
    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
    <li class="page-item <?= $i == $page_num ? 'active' : '' ?>">
      <a class="page-link" href="?page=reservations&p=<?= $i ?>&<?= http_build_query(array_diff_key($_GET, ['p'=>''])) ?>">
        <?= $i ?>
      </a>
    </li>
    <?php endfor; ?>
  </ul>
</nav>
<?php endif; ?>
