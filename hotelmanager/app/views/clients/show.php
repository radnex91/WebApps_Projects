<?php $page_title = e($client['prenom'] . ' ' . $client['nom']); ?>

<div class="row g-3">
<div class="col-lg-4">

  <!-- Fiche client -->
  <div class="card mb-3">
    <div class="card-body text-center py-4">
      <div class="user-avatar mx-auto mb-3" style="width:64px;height:64px;font-size:22px;">
        <?= strtoupper(substr($client['prenom'],0,1).substr($client['nom'],0,1)) ?>
      </div>
      <h5 class="fw-bold mb-1"><?= e($client['prenom'].' '.$client['nom']) ?></h5>
      <div class="small text-muted mb-2"><?= e($client['reference']) ?></div>
      <?php
      $type_badges = ['standard'=>'secondary','fidele'=>'info','vip'=>'warning','professionnel'=>'primary'];
      ?>
      <span class="badge bg-<?= $type_badges[$client['type_client']] ?>">
        <?= ucfirst($client['type_client']) ?>
      </span>

      <?php if ($client['points_fidelite'] > 0): ?>
      <div class="mt-2 badge bg-warning text-dark">
        <i class="bi bi-star-fill me-1"></i><?= $client['points_fidelite'] ?> pts fidélité
      </div>
      <?php endif; ?>
    </div>
    <div class="card-body border-top pt-3">
      <table class="table table-sm table-borderless mb-0">
        <tr><td class="text-muted small">Téléphone</td><td class="fw-semibold small"><?= e($client['telephone']) ?></td></tr>
        <?php if ($client['email']): ?>
        <tr><td class="text-muted small">Email</td><td class="small"><?= e($client['email']) ?></td></tr>
        <?php endif; ?>
        <?php if ($client['nationalite']): ?>
        <tr><td class="text-muted small">Nationalité</td><td class="small"><?= e($client['nationalite']) ?></td></tr>
        <?php endif; ?>
        <?php if ($client['numero_piece']): ?>
        <tr><td class="text-muted small"><?= e($client['type_piece']) ?></td><td class="small"><?= e($client['numero_piece']) ?></td></tr>
        <?php endif; ?>
        <?php if ($client['date_naissance']): ?>
        <tr><td class="text-muted small">Naissance</td><td class="small"><?= format_date($client['date_naissance']) ?></td></tr>
        <?php endif; ?>
        <?php if ($client['adresse']): ?>
        <tr><td class="text-muted small">Adresse</td><td class="small"><?= e($client['adresse'].',<br>'.$client['ville'].' '.$client['pays']) ?></td></tr>
        <?php endif; ?>
      </table>
    </div>
    <?php if ($client['notes']): ?>
    <div class="card-body border-top">
      <div class="small text-muted mb-1">Notes</div>
      <div class="small"><?= e($client['notes']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Stats rapides -->
  <div class="card mb-3">
    <div class="card-body">
      <div class="row text-center g-2">
        <div class="col-6">
          <div class="fw-bold fs-4 text-primary"><?= count($sejours) ?></div>
          <div class="small text-muted">Séjour(s)</div>
        </div>
        <div class="col-6">
          <div class="fw-bold fs-5 text-success"><?= format_money($total_depenses) ?></div>
          <div class="small text-muted">Total dépensé</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Actions -->
  <div class="card">
    <div class="card-body d-grid gap-2">
      <a href="?page=reservations&action=create&client_id=<?= $client['id'] ?>"
         class="btn btn-primary btn-sm">
        <i class="bi bi-calendar-plus me-1"></i>Nouvelle réservation
      </a>
      <a href="?page=clients&action=edit&id=<?= $client['id'] ?>"
         class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-pencil me-1"></i>Modifier la fiche
      </a>
      <a href="?page=clients" class="btn btn-outline-dark btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Retour à la liste
      </a>
    </div>
  </div>

</div>
<div class="col-lg-8">

  <!-- Historique des séjours -->
  <div class="card">
    <div class="card-header">
      <h5><i class="bi bi-clock-history me-2"></i>Historique des séjours</h5>
    </div>
    <div class="card-body p-0">
      <?php if (empty($sejours)): ?>
      <p class="text-muted text-center py-4">Aucun séjour enregistré pour ce client.</p>
      <?php else: ?>
      <table class="table mb-0">
        <thead>
          <tr><th>Réf.</th><th>Chambre</th><th>Arrivée</th><th>Départ</th><th>Nuits</th><th>Montant</th><th>Statut</th></tr>
        </thead>
        <tbody>
          <?php foreach ($sejours as $s): ?>
          <tr>
            <td>
              <a href="?page=reservations&action=show&id=<?= $s['id'] ?>" class="text-primary fw-semibold small">
                <?= e($s['reference']) ?>
              </a>
            </td>
            <td>
              <span class="badge bg-light text-dark border"><?= e($s['chambre_num']) ?></span>
              <div class="small text-muted"><?= e($s['type_chambre']) ?></div>
            </td>
            <td><?= format_date($s['date_arrivee']) ?></td>
            <td><?= format_date($s['date_depart']) ?></td>
            <td class="text-center"><?= $s['nb_nuits'] ?></td>
            <td><?= format_money($s['montant_total']) ?></td>
            <td><?= badge_statut_reservation($s['statut']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

</div>
</div>
