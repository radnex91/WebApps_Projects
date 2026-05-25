<?php $page_title = 'Facture ' . e($facture['reference']); ?>

<div class="row g-3">

<!-- Facture -->
<div class="col-lg-8">
  <div class="card" id="invoiceCard">
    <div class="card-header">
      <h5><i class="bi bi-receipt me-2"></i><?= e($facture['reference']) ?></h5>
      <div class="d-flex gap-2">
        <a href="?page=facturation&action=print&id=<?= $facture['id'] ?>" target="_blank"
           class="btn btn-sm btn-outline-secondary">
          <i class="bi bi-printer me-1"></i>Imprimer
        </a>
        <a href="?page=facturation" class="btn btn-sm btn-outline-dark">
          <i class="bi bi-arrow-left me-1"></i>Retour
        </a>
      </div>
    </div>
    <div class="card-body">

      <!-- Entête facture -->
      <div class="row mb-4">
        <div class="col-6">
          <div class="fw-bold fs-5 text-primary"><?= APP_NAME ?></div>
          <div class="small text-muted"><?= e(HOTEL_NOM) ?></div>
          <div class="small"><?= e(HOTEL_ADRESSE) ?></div>
          <div class="small"><?= e(HOTEL_TEL) ?></div>
          <div class="small"><?= e(HOTEL_EMAIL) ?></div>
        </div>
        <div class="col-6 text-end">
          <div class="fw-bold text-muted small">FACTURE</div>
          <div class="fw-bold fs-4"><?= e($facture['reference']) ?></div>
          <div class="small">Émise le : <?= format_date($facture['date_emission']) ?></div>
          <div class="small">Réservation : <?= e($facture['res_ref']) ?></div>
        </div>
      </div>

      <!-- Client -->
      <div class="row mb-4">
        <div class="col-6">
          <div class="small fw-bold text-muted mb-1">FACTURÉ À</div>
          <div class="fw-bold"><?= e($facture['client_nom']) ?></div>
          <div class="small"><?= e($facture['client_tel']) ?></div>
          <?php if ($facture['client_email']): ?>
          <div class="small"><?= e($facture['client_email']) ?></div>
          <?php endif; ?>
          <?php if ($facture['client_adresse']): ?>
          <div class="small text-muted"><?= e($facture['client_adresse']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Lignes -->
      <table class="table table-bordered mb-4">
        <thead class="table-dark">
          <tr>
            <th>Description</th><th class="text-center">Qté</th>
            <th class="text-end">Prix unit.</th><th class="text-end">Total HT</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <div class="fw-semibold">Séjour — Chambre <?= e($facture['chambre_num']) ?> (<?= e($facture['type_chambre']) ?>)</div>
              <div class="small text-muted">
                Du <?= format_date($facture['date_arrivee']) ?> au <?= format_date($facture['date_depart']) ?>
              </div>
            </td>
            <td class="text-center"><?= $facture['nb_nuits'] ?> nuit(s)</td>
            <td class="text-end"><?= format_money($facture['sous_total'] / $facture['nb_nuits']) ?></td>
            <td class="text-end"><?= format_money($facture['sous_total']) ?></td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="table-light">
            <td colspan="3" class="text-end text-muted small">Sous-total HT</td>
            <td class="text-end"><?= format_money($facture['sous_total']) ?></td>
          </tr>
          <tr class="table-light">
            <td colspan="3" class="text-end text-muted small">TVA (<?= $facture['tva_taux'] ?>%)</td>
            <td class="text-end"><?= format_money($facture['tva_montant']) ?></td>
          </tr>
          <?php if ($facture['remise'] > 0): ?>
          <tr class="table-light">
            <td colspan="3" class="text-end text-success small">Remise</td>
            <td class="text-end text-success">-<?= format_money($facture['remise']) ?></td>
          </tr>
          <?php endif; ?>
          <tr class="table-primary">
            <td colspan="3" class="text-end fw-bold">TOTAL TTC</td>
            <td class="text-end fw-bold fs-5"><?= format_money($facture['total_ttc']) ?></td>
          </tr>
        </tfoot>
      </table>

      <!-- Paiements reçus -->
      <?php if (!empty($paiements)): ?>
      <div class="mb-3">
        <div class="fw-bold small text-muted mb-2">PAIEMENTS REÇUS</div>
        <?php foreach ($paiements as $p): ?>
        <div class="d-flex justify-content-between small border-bottom py-1">
          <span><?= format_datetime($p['date_paiement']) ?> — <?= ucfirst($p['mode']) ?>
            <?php if ($p['reference_paiement']): ?>
              <span class="text-muted">(<?= e($p['reference_paiement']) ?>)</span>
            <?php endif; ?>
          </span>
          <span class="fw-semibold text-success">+<?= format_money($p['montant']) ?></span>
        </div>
        <?php endforeach; ?>
        <div class="d-flex justify-content-between fw-bold mt-1 pt-1">
          <span>Total payé</span>
          <span class="text-success"><?= format_money($montant_paye) ?></span>
        </div>
        <?php if ($reste_a_payer > 0): ?>
        <div class="d-flex justify-content-between fw-bold mt-1 text-danger">
          <span>Reste à payer</span>
          <span><?= format_money($reste_a_payer) ?></span>
        </div>
        <?php else: ?>
        <div class="alert alert-success mt-2 py-2 small">
          <i class="bi bi-check-circle me-1"></i>Facture entièrement payée.
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <div class="text-center text-muted small mt-4 border-top pt-3">
        Merci de votre confiance — <?= e(HOTEL_NOM) ?>
      </div>
    </div>
  </div>
</div>

<!-- Paiement -->
<div class="col-lg-4">

  <?php if ($facture['statut'] !== 'payee' && $reste_a_payer > 0): ?>
  <div class="card mb-3">
    <div class="card-header">
      <h5><i class="bi bi-cash me-2 text-success"></i>Encaisser un paiement</h5>
    </div>
    <div class="card-body">
      <form method="POST" action="?page=facturation&action=pay">
        <?= csrf_field() ?>
        <input type="hidden" name="facture_id" value="<?= $facture['id'] ?>">
        <div class="mb-3">
          <label class="form-label">Montant</label>
          <div class="input-group">
            <input type="number" class="form-control" name="montant"
                   value="<?= round($reste_a_payer, 0) ?>"
                   min="1" max="<?= $facture['total_ttc'] ?>" step="100" required>
            <span class="input-group-text small">FCFA</span>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Mode de paiement</label>
          <select class="form-select" name="mode">
            <option value="especes">💵 Espèces</option>
            <option value="mobile_money">📱 Mobile Money (MTN/Orange)</option>
            <option value="carte">💳 Carte bancaire</option>
            <option value="virement">🏦 Virement</option>
            <option value="cheque">📄 Chèque</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Réf. transaction <span class="text-muted small">(optionnel)</span></label>
          <input type="text" class="form-control" name="reference_paiement"
                 placeholder="Ex: TXN123456789">
        </div>
        <button type="submit" class="btn btn-success w-100">
          <i class="bi bi-check-circle me-1"></i>Enregistrer le paiement
        </button>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <!-- Résumé -->
  <div class="card">
    <div class="card-body">
      <div class="d-flex justify-content-between mb-2">
        <span class="text-muted small">Total TTC</span>
        <span class="fw-bold"><?= format_money($facture['total_ttc']) ?></span>
      </div>
      <div class="d-flex justify-content-between mb-2">
        <span class="text-muted small">Payé</span>
        <span class="fw-bold text-success"><?= format_money($montant_paye) ?></span>
      </div>
      <?php if ($reste_a_payer > 0): ?>
      <div class="d-flex justify-content-between pt-2 border-top">
        <span class="fw-bold text-danger">Reste</span>
        <span class="fw-bold text-danger"><?= format_money($reste_a_payer) ?></span>
      </div>
      <?php endif; ?>

      <div class="mt-3 d-grid gap-2">
        <a href="?page=facturation&action=print&id=<?= $facture['id'] ?>" target="_blank"
           class="btn btn-outline-secondary btn-sm">
          <i class="bi bi-printer me-1"></i>Imprimer la facture
        </a>
      </div>
    </div>
  </div>

</div>

</div>
