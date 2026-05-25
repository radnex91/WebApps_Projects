<?php $page_title = 'Réservation ' . e($reservation['reference']); ?>

<div class="row g-3">

<!-- Colonne gauche -->
<div class="col-lg-8">

  <!-- Infos principales -->
  <div class="card mb-3">
    <div class="card-header">
      <div>
        <h5><i class="bi bi-calendar-check me-2"></i><?= e($reservation['reference']) ?></h5>
        <small class="text-muted">Créée le <?= format_datetime($reservation['created_at']) ?> par <?= e($reservation['agent_nom']) ?></small>
      </div>
      <div class="d-flex gap-2 align-items-center">
        <?= badge_statut_reservation($reservation['statut']) ?>
        <?php if (in_array($reservation['statut'], ['confirmee','en_attente'])): ?>
        <a href="<?= APP_URL ?>/index.php?page=reservations&action=checkin&id=<?= $reservation['id'] ?>"
           class="btn btn-sm btn-success"
           data-confirm="Effectuer le check-in ?">
          <i class="bi bi-box-arrow-in-right me-1"></i>Check-in
        </a>
        <?php endif; ?>
        <?php if ($reservation['statut'] === 'checkin'): ?>
        <a href="<?= APP_URL ?>/index.php?page=reservations&action=checkout&id=<?= $reservation['id'] ?>"
           class="btn btn-sm btn-warning text-dark"
           data-confirm="Effectuer le check-out ?">
          <i class="bi bi-box-arrow-right me-1"></i>Check-out
        </a>
        <?php endif; ?>
      </div>
    </div>

    <div class="card-body">
      <div class="row g-3">
        <div class="col-sm-6">
          <div class="small text-muted mb-1">Client</div>
          <div class="fw-bold fs-6"><?= e($reservation['client_nom']) ?></div>
          <div class="small"><?= e($reservation['client_tel']) ?></div>
          <?php if ($reservation['client_email']): ?>
          <div class="small text-muted"><?= e($reservation['client_email']) ?></div>
          <?php endif; ?>
          <a href="<?= APP_URL ?>/index.php?page=clients&action=show&id=<?= $reservation['client_id'] ?>"
             class="btn btn-xs btn-outline-primary mt-2">
            <i class="bi bi-person me-1"></i>Voir la fiche client
          </a>
        </div>
        <div class="col-sm-6">
          <div class="small text-muted mb-1">Chambre</div>
          <div class="fw-bold fs-5">N° <?= e($reservation['chambre_num']) ?></div>
          <div class="small text-muted"><?= e($reservation['type_chambre']) ?></div>
        </div>
        <div class="col-sm-3 text-center">
          <div class="small text-muted">Arrivée</div>
          <div class="fw-bold text-success"><?= format_date($reservation['date_arrivee']) ?></div>
          <?php if ($reservation['checkin_at']): ?>
          <div class="small text-muted"><?= format_datetime($reservation['checkin_at']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-sm-3 text-center">
          <div class="small text-muted">Départ</div>
          <div class="fw-bold text-danger"><?= format_date($reservation['date_depart']) ?></div>
          <?php if ($reservation['checkout_at']): ?>
          <div class="small text-muted"><?= format_datetime($reservation['checkout_at']) ?></div>
          <?php endif; ?>
        </div>
        <div class="col-sm-3 text-center">
          <div class="small text-muted">Durée</div>
          <div class="fw-bold"><?= $reservation['nb_nuits'] ?> nuit(s)</div>
        </div>
        <div class="col-sm-3 text-center">
          <div class="small text-muted">Personnes</div>
          <div class="fw-bold"><?= $reservation['nb_adultes'] ?> adulte(s)</div>
          <?php if ($reservation['nb_enfants']): ?>
          <div class="small"><?= $reservation['nb_enfants'] ?> enfant(s)</div>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($reservation['notes']): ?>
      <div class="alert alert-light mt-3 mb-0">
        <i class="bi bi-sticky me-2"></i><?= e($reservation['notes']) ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Montants -->
  <div class="card mb-3">
    <div class="card-header">
      <h5><i class="bi bi-cash me-2"></i>Détail financier</h5>
    </div>
    <div class="card-body">
      <table class="table table-sm">
        <tr>
          <td class="text-muted">Tarif / nuit</td>
          <td class="text-end"><?= format_money($reservation['tarif_nuit']) ?></td>
        </tr>
        <tr>
          <td class="text-muted">× <?= $reservation['nb_nuits'] ?> nuit(s)</td>
          <td class="text-end fw-bold"><?= format_money($reservation['montant_total']) ?></td>
        </tr>
        <tr class="table-light">
          <td><strong>Montant total HT</strong></td>
          <td class="text-end fw-bold fs-6 text-primary"><?= format_money($reservation['montant_total']) ?></td>
        </tr>
      </table>
    </div>
  </div>

  <!-- Factures -->
  <div class="card">
    <div class="card-header">
      <h5><i class="bi bi-receipt me-2"></i>Facture(s)</h5>
      <?php if (empty($factures) && $reservation['statut'] !== 'annulee'): ?>
      <a href="<?= APP_URL ?>/index.php?page=facturation&action=generate&reservation_id=<?= $reservation['id'] ?>"
         class="btn btn-sm btn-primary"
         data-confirm="Générer la facture pour cette réservation ?">
        <i class="bi bi-file-earmark-plus me-1"></i>Générer facture
      </a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (empty($factures)): ?>
      <p class="text-muted text-center py-2">Aucune facture générée.</p>
      <?php else: ?>
      <?php foreach ($factures as $f): ?>
      <div class="d-flex align-items-center justify-content-between border rounded p-3 mb-2">
        <div>
          <div class="fw-bold"><?= e($f['reference']) ?></div>
          <div class="small text-muted">Émise le <?= format_date($f['date_emission']) ?></div>
        </div>
        <div class="text-center">
          <div class="fw-bold text-primary"><?= format_money($f['total_ttc']) ?></div>
          <?php
            $f_badges = ['brouillon'=>'secondary','emise'=>'warning','payee'=>'success','annulee'=>'danger'];
            $f_labels = ['brouillon'=>'Brouillon','emise'=>'Émise','payee'=>'Payée','annulee'=>'Annulée'];
          ?>
          <span class="badge bg-<?= $f_badges[$f['statut']] ?>"><?= $f_labels[$f['statut']] ?></span>
        </div>
        <div class="d-flex gap-2">
          <a href="<?= APP_URL ?>/index.php?page=facturation&action=show&id=<?= $f['id'] ?>"
             class="btn btn-xs btn-outline-primary">
            <i class="bi bi-eye me-1"></i>Voir
          </a>
          <a href="<?= APP_URL ?>/index.php?page=facturation&action=print&id=<?= $f['id'] ?>"
             target="_blank" class="btn btn-xs btn-outline-secondary">
            <i class="bi bi-printer me-1"></i>Imprimer
          </a>
        </div>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

</div>

<!-- Colonne droite -->
<div class="col-lg-4">

  <!-- Actions -->
  <div class="card mb-3">
    <div class="card-header"><h5><i class="bi bi-gear me-2"></i>Actions</h5></div>
    <div class="card-body d-grid gap-2">
      <a href="<?= APP_URL ?>/index.php?page=reservations" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Retour à la liste
      </a>
      <?php if (!in_array($reservation['statut'], ['annulee','no_show','checkout'])): ?>
      <a href="<?= APP_URL ?>/index.php?page=reservations&action=cancel&id=<?= $reservation['id'] ?>"
         class="btn btn-outline-danger btn-sm"
         data-confirm="Annuler définitivement cette réservation ?">
        <i class="bi bi-x-circle me-1"></i>Annuler la réservation
      </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Timeline -->
  <div class="card">
    <div class="card-header"><h5><i class="bi bi-clock-history me-2"></i>Historique</h5></div>
    <div class="card-body">
      <?php
      $timeline = [];
      $timeline[] = ['icon'=>'bi-plus-circle','color'=>'primary',   'text'=>'Réservation créée', 'date'=>$reservation['created_at']];
      if ($reservation['statut'] !== 'en_attente')
        $timeline[] = ['icon'=>'bi-check-circle','color'=>'success', 'text'=>'Réservation confirmée','date'=>$reservation['updated_at']];
      if ($reservation['checkin_at'])
        $timeline[] = ['icon'=>'bi-box-arrow-in-right','color'=>'success','text'=>'Check-in effectué','date'=>$reservation['checkin_at']];
      if ($reservation['checkout_at'])
        $timeline[] = ['icon'=>'bi-box-arrow-right','color'=>'warning','text'=>'Check-out effectué','date'=>$reservation['checkout_at']];
      if ($reservation['statut'] === 'annulee')
        $timeline[] = ['icon'=>'bi-x-circle','color'=>'danger','text'=>'Réservation annulée','date'=>$reservation['updated_at']];
      ?>
      <div class="timeline">
        <?php foreach ($timeline as $t): ?>
        <div class="d-flex gap-3 mb-3">
          <div class="text-<?= $t['color'] ?> mt-1"><i class="bi <?= $t['icon'] ?>"></i></div>
          <div>
            <div class="fw-semibold small"><?= $t['text'] ?></div>
            <div class="text-muted" style="font-size:11px;"><?= format_datetime($t['date']) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

</div>

</div>
