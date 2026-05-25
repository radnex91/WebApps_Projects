<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requireLogin();
requirePermission('pos');

$action = $_GET['action'] ?? '';

if ($action === 'ticket') {
    $saleId = (int)($_GET['id'] ?? 0);
    if (!$saleId) {
        jsonResponse(false, 'ID vente requis', null, 400);
    }

    $saleModel = new Sale();
    $sale = $saleModel->getSaleWithItems($saleId);
    if (!$sale) {
        jsonResponse(false, 'Vente introuvable', null, 404);
    }

    $storeModel = new Store();
    $store = $storeModel->find($sale['store_id']);
    $receiptFooter = $appSettings['receipt_footer'] ?? '';

    ob_start();
    ?>
    <div class="ticket-wrapper" id="ticketContent">
        <div class="ticket-header">
            <div style="font-size:1rem;font-weight:bold"><?= e($store['name'] ?? 'Boutique') ?></div>
            <div style="font-size:.72rem"><?= e($store['address'] ?? '') ?></div>
            <div style="font-size:.72rem"><?= e($store['phone'] ?? '') ?></div>
            <div style="margin-top:.3rem;font-size:.7rem">*** REÇU DE VENTE ***</div>
        </div>

        <div style="margin-bottom:.3rem">
            <div class="ticket-row"><span>Facture:</span><span><?= e($sale['invoice_number']) ?></span></div>
            <div class="ticket-row"><span>Date:</span><span><?= date('d/m/Y H:i', strtotime($sale['sale_date'])) ?></span></div>
            <div class="ticket-row"><span>Caissier:</span><span><?= e($sale['cashier_name']) ?></span></div>
            <?php if ($sale['customer_name']): ?>
            <div class="ticket-row"><span>Client:</span><span><?= e($sale['customer_name']) ?></span></div>
            <?php endif; ?>
            <div class="ticket-row"><span>Paiement:</span><span><?= e($sale['payment_method']) ?></span></div>
        </div>

        <div class="ticket-divider"></div>
        <div style="margin:.3rem 0">
            <?php foreach ($sale['items'] as $item): ?>
            <div><?= e($item['product_name']) ?></div>
            <div class="ticket-row">
                <span><?= number_format((float)$item['quantity'], 2) ?> x <?= number_format((float)$item['unit_price']) ?></span>
                <span><?= number_format((float)$item['total_price']) ?> FCFA</span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="ticket-divider"></div>
        <div class="ticket-row"><span>Sous-total:</span><span><?= number_format((float)$sale['subtotal']) ?> FCFA</span></div>
        <?php if ((float)$sale['discount_amount'] > 0): ?>
        <div class="ticket-row"><span>Remise:</span><span>-<?= number_format((float)$sale['discount_amount']) ?> FCFA</span></div>
        <?php endif; ?>
        <div class="ticket-row"><span>TVA:</span><span><?= number_format((float)$sale['tax_amount']) ?> FCFA</span></div>
        <div class="ticket-divider"></div>
        <div class="ticket-row ticket-total"><span>TOTAL:</span><span><?= number_format((float)$sale['total_amount']) ?> FCFA</span></div>
        <div class="ticket-row"><span>Payé:</span><span><?= number_format((float)$sale['paid_amount']) ?> FCFA</span></div>
        <?php $balance = max(0, (float)$sale['total_amount'] - (float)$sale['paid_amount']); ?>
        <?php if ($balance > 0): ?>
        <div class="ticket-row" style="font-weight:bold;color:#EF4444"><span>Reliquat:</span><span><?= number_format($balance) ?> FCFA</span></div>
        <?php endif; ?>
        <?php if ((float)$sale['change_amount'] > 0 && empty($sale['change_refunded'])): ?>
        <div class="ticket-row"><span>Rendu:</span><span><?= number_format((float)$sale['change_amount']) ?> FCFA</span></div>
        <?php endif; ?>

        <div class="ticket-divider"></div>
        <div style="text-align:center;font-size:.7rem;margin-top:.4rem">
            <?= nl2br(e($receiptFooter ?: "Merci pour votre achat !\nConservez ce reçu pour tout échange.")) ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    jsonResponse(true, 'OK', ['html' => $html, 'invoice_number' => $sale['invoice_number']]);
} else {
    jsonResponse(false, 'Action invalide', null, 400);
}
