<?php
// ButcheryPOS - Thermal Receipt Template (80mm)
// Opened in a new window for printing

if (!isset($sale)) {
    $saleId = (int)($_GET['id'] ?? 0);
    if ($saleId && isset($saleService)) {
        $sale = $saleService->getSaleForReceipt($saleId);
    }
}

if (!$sale) {
    echo '<p>' . t('no_data') . '</p>';
    return;
}

$currency = $appSettings['company_currency'] ?? 'XAF';
$companyName = $appSettings['company_name'] ?? 'ButcheryPOS';
$companyPhone = $appSettings['company_phone'] ?? '';
$companyAddress = $appSettings['company_address'] ?? '';
$receiptFooter = $appSettings['branding_receipt_footer'] ?? 'Thank you!';
$receiptWidth = (int)($appSettings['branding_receipt_width'] ?? 80);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= t('receipt') ?> - <?= e($sale['reference'] ?? '') ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            width: <?= $receiptWidth ?>mm;
            margin: 0 auto;
            padding: 4mm;
            color: #000;
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: bold; }
        .line { border-top: 1px dashed #000; margin: 4px 0; }
        .double-line { border-top: 2px solid #000; margin: 4px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 11px; padding: 2px 0; }
        td { padding: 1px 0; font-size: 11px; }
        td:last-child { text-align: right; }
        .total-row td { font-weight: bold; font-size: 14px; }
        @media print {
            body { width: <?= $receiptWidth ?>mm; margin: 0; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="center bold" style="font-size:16px"><?= e($companyName) ?></div>
    <?php if ($companyAddress): ?>
    <div class="center" style="font-size:10px"><?= e($companyAddress) ?></div>
    <?php endif; ?>
    <?php if ($companyPhone): ?>
    <div class="center" style="font-size:10px"><?= e($companyPhone) ?></div>
    <?php endif; ?>

    <div class="double-line"></div>

    <div><?= t('sale_reference') ?>: <span class="bold"><?= e($sale['reference'] ?? '') ?></span></div>
    <div><?= t('date') ?>: <?= format_date($sale['created_at'] ?? 'now') ?></div>
    <div><?= t('payment_method') ?>: <?= e($sale['payment_method'] ?? 'cash') ?></div>

    <div class="line"></div>

    <table>
        <thead>
            <tr>
                <th><?= t('quantity') ?></th>
                <th><?= t('product_name') ?></th>
                <th><?= t('total') ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sale['items'] ?? [] as $item): ?>
            <tr>
                <td><?= number_format((float)$item['quantity'], 3) ?></td>
                <td><?= e($item['product_name'] ?? '') ?></td>
                <td><?= number_format((float)$item['subtotal'], 0) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td class="bold"><?= t('subtotal') ?></td>
            <td><?= number_format((float)($sale['subtotal'] ?? 0), 0) ?> <?= e($currency) ?></td>
        </tr>
        <?php if (($sale['discount_amount'] ?? 0) > 0): ?>
        <tr>
            <td><?= t('discount') ?></td>
            <td>-<?= number_format((float)$sale['discount_amount'], 0) ?> <?= e($currency) ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total-row">
            <td><?= t('total') ?></td>
            <td><?= number_format((float)($sale['total_amount'] ?? 0), 0) ?> <?= e($currency) ?></td>
        </tr>
    </table>

    <div class="double-line"></div>

    <?php foreach ($sale['payments'] ?? [] as $pay): ?>
    <div><?= e($pay['payment_method']) ?>: <?= number_format((float)$pay['amount'], 0) ?> <?= e($currency) ?></div>
    <?php endforeach; ?>

    <div class="line"></div>

    <div class="center" style="margin-top:8px; font-size:11px;">
        <?= e($receiptFooter) ?>
    </div>
</body>
</html>