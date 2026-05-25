<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Facture <?= e($facture['reference']) ?> — <?= APP_NAME ?></title>
  <style>
    @import url('https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap');
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Nunito', Arial, sans-serif;
      color: #1f2937; font-size: 13px;
      background: #fff; padding: 20px;
    }
    .page { max-width: 720px; margin: 0 auto; padding: 30px; }
    .header { display: flex; justify-content: space-between; margin-bottom: 30px; }
    .hotel-info .name { font-size: 20px; font-weight: 700; color: #1a56db; }
    .hotel-info .sub  { color: #6b7280; font-size: 12px; line-height: 1.8; }
    .invoice-meta { text-align: right; }
    .invoice-meta .inv-label { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: .5px; }
    .invoice-meta .inv-num   { font-size: 22px; font-weight: 700; color: #1f2937; }
    .invoice-meta .inv-date  { font-size: 12px; color: #6b7280; }
    .divider { border: none; border-top: 2px solid #1a56db; margin: 20px 0; }
    .addresses { display: flex; gap: 40px; margin-bottom: 24px; }
    .address-block .label { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #9ca3af; margin-bottom: 6px; }
    .address-block .name  { font-weight: 700; font-size: 14px; }
    .address-block .detail { font-size: 12px; color: #4b5563; line-height: 1.7; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    table.items thead th {
      background: #1a56db; color: #fff;
      padding: 9px 12px; font-size: 11px;
      text-align: left; font-weight: 700; text-transform: uppercase; letter-spacing: .4px;
    }
    table.items thead th.right { text-align: right; }
    table.items tbody td { padding: 10px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    table.items tbody td.right { text-align: right; }
    table.items tfoot td { padding: 7px 12px; }
    .total-row { background: #1a56db; color: #fff; font-weight: 700; font-size: 15px; }
    .subtotal-row { color: #6b7280; font-size: 12px; }
    .badge-paid {
      display: inline-block;
      background: #d1fae5; color: #065f46;
      border: 1.5px solid #6ee7b7;
      border-radius: 20px; padding: 3px 14px;
      font-size: 11px; font-weight: 700;
    }
    .badge-pending {
      display: inline-block;
      background: #fef3c7; color: #92400e;
      border: 1.5px solid #fcd34d;
      border-radius: 20px; padding: 3px 14px;
      font-size: 11px; font-weight: 700;
    }
    .payments-section { margin-top: 20px; }
    .payments-section h4 { font-size: 12px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
    .payment-row { display: flex; justify-content: space-between; font-size: 12px; padding: 4px 0; border-bottom: 1px dashed #e5e7eb; }
    .footer { margin-top: 30px; text-align: center; color: #9ca3af; font-size: 11px; border-top: 1px solid #e5e7eb; padding-top: 16px; }
    .no-print { text-align: center; margin-bottom: 20px; }
    .no-print button {
      background: #1a56db; color: #fff; border: none;
      padding: 10px 24px; border-radius: 8px;
      font-size: 14px; cursor: pointer; font-family: 'Nunito', sans-serif; font-weight: 600;
      margin-right: 8px;
    }
    .no-print button.secondary {
      background: #f3f4f6; color: #374151;
    }
    @media print { .no-print { display: none !important; } }
  </style>
</head>
<body>

<div class="no-print">
  <button onclick="window.print()"><i>🖨</i> Imprimer</button>
  <button class="secondary" onclick="window.close()">✕ Fermer</button>
</div>

<div class="page">

  <div class="header">
    <div class="hotel-info">
      <div class="name"><?= APP_NAME ?></div>
      <div class="sub">
        <?= e(HOTEL_NOM) ?><br>
        <?= e(HOTEL_ADRESSE) ?><br>
        <?= e(HOTEL_TEL) ?> · <?= e(HOTEL_EMAIL) ?>
      </div>
    </div>
    <div class="invoice-meta">
      <div class="inv-label">Facture</div>
      <div class="inv-num"><?= e($facture['reference']) ?></div>
      <div class="inv-date">Émise le : <?= format_date($facture['date_emission']) ?></div>
      <div class="inv-date">Réservation : <?= e($facture['res_ref']) ?></div>
      <div style="margin-top:8px;">
        <?php if ($facture['statut'] === 'payee'): ?>
          <span class="badge-paid">✓ PAYÉE</span>
        <?php else: ?>
          <span class="badge-pending">⏳ EN ATTENTE</span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <hr class="divider">

  <div class="addresses">
    <div class="address-block">
      <div class="label">Émis par</div>
      <div class="name"><?= e(HOTEL_NOM) ?></div>
      <div class="detail">
        <?= e(HOTEL_ADRESSE) ?><br>
        Tél : <?= e(HOTEL_TEL) ?>
      </div>
    </div>
    <div class="address-block">
      <div class="label">Facturé à</div>
      <div class="name"><?= e($facture['client_nom']) ?></div>
      <div class="detail">
        <?= e($facture['client_tel']) ?><br>
        <?php if ($facture['client_email']): ?><?= e($facture['client_email']) ?><br><?php endif; ?>
        <?php if ($facture['client_adresse']): ?><?= e($facture['client_adresse']) ?><?php endif; ?>
      </div>
    </div>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="right">Qté</th>
        <th class="right">Prix unit. HT</th>
        <th class="right">Total HT</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong>Séjour — Chambre <?= e($facture['chambre_num']) ?> (<?= e($facture['type_chambre']) ?>)</strong><br>
          <span style="color:#6b7280; font-size:11px;">
            Arrivée : <?= format_date($facture['date_arrivee']) ?> —
            Départ : <?= format_date($facture['date_depart']) ?>
          </span>
        </td>
        <td class="right"><?= $facture['nb_nuits'] ?> nuit(s)</td>
        <td class="right"><?= format_money($facture['sous_total'] / max(1,$facture['nb_nuits'])) ?></td>
        <td class="right"><?= format_money($facture['sous_total']) ?></td>
      </tr>
    </tbody>
    <tfoot>
      <tr class="subtotal-row">
        <td colspan="3" style="text-align:right;">Sous-total HT</td>
        <td style="text-align:right;"><?= format_money($facture['sous_total']) ?></td>
      </tr>
      <tr class="subtotal-row">
        <td colspan="3" style="text-align:right;">TVA (<?= $facture['tva_taux'] ?>%)</td>
        <td style="text-align:right;"><?= format_money($facture['tva_montant']) ?></td>
      </tr>
      <?php if ($facture['remise'] > 0): ?>
      <tr class="subtotal-row">
        <td colspan="3" style="text-align:right; color:#059669;">Remise accordée</td>
        <td style="text-align:right; color:#059669;">−<?= format_money($facture['remise']) ?></td>
      </tr>
      <?php endif; ?>
      <tr class="total-row">
        <td colspan="3" style="text-align:right;">TOTAL TTC</td>
        <td style="text-align:right;"><?= format_money($facture['total_ttc']) ?></td>
      </tr>
    </tfoot>
  </table>

  <!-- Paiements -->
  <?php if (!empty($paiements)): ?>
  <div class="payments-section">
    <h4>Paiements reçus</h4>
    <?php $total_p = 0; foreach ($paiements as $p): $total_p += $p['montant']; ?>
    <div class="payment-row">
      <span><?= format_datetime($p['date_paiement']) ?> — <?= ucfirst(str_replace('_',' ',$p['mode'])) ?>
        <?php if ($p['reference_paiement']): ?>· <em><?= e($p['reference_paiement']) ?></em><?php endif; ?>
      </span>
      <span style="color:#059669; font-weight:600;"><?= format_money($p['montant']) ?></span>
    </div>
    <?php endforeach; ?>
    <?php $reste = $facture['total_ttc'] - $total_p; ?>
    <?php if ($reste > 0): ?>
    <div style="text-align:right; font-weight:700; color:#dc2626; margin-top:6px; font-size:13px;">
      Reste à payer : <?= format_money($reste) ?>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <div class="footer">
    <strong><?= e(HOTEL_NOM) ?></strong> — <?= e(HOTEL_ADRESSE) ?><br>
    <?= e(HOTEL_TEL) ?> · <?= e(HOTEL_EMAIL) ?><br>
    Merci de votre confiance · Document généré le <?= format_datetime(date('Y-m-d H:i:s')) ?>
  </div>

</div>

</body>
</html>
