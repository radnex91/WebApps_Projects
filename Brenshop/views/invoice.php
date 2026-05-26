<?php
require_once __DIR__ . '/../includes/bootstrap.php';
requirePermission('invoices');

$saleId = (int)($_GET['id'] ?? 0);
if (!$saleId) {
    redirect(BASE_URL . '/views/sales.php');
}

$saleModel = new Sale();
$sale = $saleModel->getSaleWithItems($saleId);

if (!$sale) {
    setFlash('error', 'Facture introuvable.');
    redirect(BASE_URL . '/views/sales.php');
}

$printMode = isset($_GET['print']);
$storeModel = new Store();
$store = $storeModel->find($sale['store_id']);
$invoiceFont = $appSettings['invoice_font'] ?? 'DM Mono';
$receiptFooter = $appSettings['receipt_footer'] ?? '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture <?= e($sale['invoice_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-icons/1.11.3/font/bootstrap-icons.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;700;800&family=DM+Sans:wght@400;500&display=swap');
        @import url('https://fonts.googleapis.com/css2?family=<?= urlencode($invoiceFont) ?>:wght@400;500;600;700&display=swap');

        :root {
            --accent: #6366F1;
            --accent2: #10B981;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--border);
            min-height: 100vh;
        }

        .invoice-wrapper {
            max-width: 760px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .invoice-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        .invoice-header {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            padding: 2rem;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .brand-name {
            font-family: 'Syne', sans-serif;
            font-weight: 800;
            font-size: 1.6rem;
            letter-spacing: -0.03em;
        }
        .brand-name span { color: var(--accent); }
        .invoice-label {
            font-family: 'Syne', sans-serif;
            font-weight: 700;
            font-size: 1.1rem;
            color: rgba(255,255,255,0.9);
        }
        .invoice-number {
            font-family: '<?= e($invoiceFont) ?>', monospace;
            font-size: 0.85rem;
            color: rgba(255,255,255,0.5);
            margin-top: 0.25rem;
        }

        .invoice-meta {
            padding: 1.5rem 2rem;
            background: var(--body-bg);
            border-bottom: 1px solid #E2E8F0;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        .meta-item label {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #64748B;
            font-weight: 600;
            display: block;
            margin-bottom: 0.2rem;
        }
        .meta-item span {
            font-size: 0.875rem;
            font-weight: 500;
            color: #1E293B;
        }

        .invoice-body { padding: 1.5rem 2rem; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 1.5rem;
        }
        .items-table thead th {
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748B;
            font-weight: 600;
            padding: 0.5rem 0.75rem;
            border-bottom: 2px solid #E2E8F0;
        }
        .items-table tbody td {
            padding: 0.75rem 0.75rem;
            font-size: 0.875rem;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .items-table tfoot td {
            padding: 0.4rem 0.75rem;
            font-size: 0.875rem;
        }

        .totals-section {
            background: var(--body-bg);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            max-width: 320px;
            margin-left: auto;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-family: '<?= e($invoiceFont) ?>', monospace;
            font-size: 0.875rem;
            padding: 0.2rem 0;
            color: #475569;
        }
        .total-row.grand-total {
            font-family: '<?= e($invoiceFont) ?>', monospace;
            font-weight: 700;
            font-size: 1.1rem;
            color: var(--accent);
            border-top: 2px solid #E2E8F0;
            margin-top: 0.5rem;
            padding-top: 0.5rem;
        }
        .total-row.paid {
            color: var(--accent2);
        }

        .invoice-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #E2E8F0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--body-bg);
        }

        .payment-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: rgba(16,185,129,0.1);
            color: #10B981;
            border-radius: 20px;
            padding: 0.35rem 0.9rem;
            font-size: 0.8rem;
            font-weight: 600;
        }

        /* TICKET MODE */
        .ticket-wrapper {
            max-width: 300px;
            margin: 1rem auto;
            font-family: '<?= e($invoiceFont) ?>', monospace;
            font-size: 0.8rem;
            background: #fff;
            padding: 1rem;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .ticket-header { text-align: center; border-bottom: 1px dashed #333; padding-bottom: 0.5rem; margin-bottom: 0.5rem; }
        .ticket-row { display: flex; justify-content: space-between; }
        .ticket-divider { border-top: 1px dashed #333; margin: 0.4rem 0; }
        .ticket-total { font-weight: bold; font-size: 1rem; }

        /* Actions bar (non imprimable) */
        .actions-bar {
            position: sticky;
            top: 0;
            z-index: 100;
            background: #fff;
            border-bottom: 1px solid #E2E8F0;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
        }

        @media print {
            .actions-bar, .no-print { display: none !important; }
            body { background: #fff; }
            .invoice-wrapper { margin: 0; max-width: 100%; padding: 0; }
            .invoice-card { box-shadow: none; border-radius: 0; }
        }
    </style>
</head>
<body>

<!-- Barre d'actions -->
<div class="actions-bar no-print">
    <a href="<?= BASE_URL ?>/views/sales.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">
        <i class="bi bi-arrow-left me-1"></i>Retour
    </a>
    <div class="d-flex gap-2">
        <button onclick="window.print()" class="btn btn-sm btn-primary" style="border-radius:8px">
            <i class="bi bi-printer me-1"></i>Imprimer
        </button>
        <a href="?id=<?= $saleId ?>&ticket=1" class="btn btn-sm btn-outline-secondary" style="border-radius:8px">
            <i class="bi bi-receipt me-1"></i>Format Ticket
        </a>
    </div>
</div>

<?php if (isset($_GET['ticket'])): ?>
<!-- FORMAT TICKET CAISSE -->
<div class="ticket-wrapper">
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
    <?php if (max(0, (float)$sale['total_amount'] - (float)$sale['paid_amount']) > 0): ?>
    <div class="ticket-row" style="font-weight:bold;color:#EF4444"><span>Reliquat:</span><span><?= number_format(max(0, (float)$sale['total_amount'] - (float)$sale['paid_amount'])) ?> FCFA</span></div>
    <?php endif; ?>
    <?php if ((float)$sale['change_amount'] > 0 && empty($sale['change_refunded'])): ?>
    <div class="ticket-row"><span>Rendu:</span><span><?= number_format((float)$sale['change_amount']) ?> FCFA</span></div>
    <?php endif; ?>

    <div class="ticket-divider"></div>
    <div style="text-align:center;font-size:.7rem;margin-top:.4rem">
        <?= e($receiptFooter ?: 'Merci pour votre achat !<br>Conservez ce reçu pour tout échange.') ?>
    </div>
</div>

<?php else: ?>
<!-- FORMAT FACTURE A4 -->
<div class="invoice-wrapper">
    <div class="invoice-card">

        <!-- En-tête -->
        <div class="invoice-header">
            <div>
                <div class="brand-name"><?= e($appSettings['app_name'] ?? 'Brenshop') ?></div>
                <div style="font-size:.78rem;color:rgba(255,255,255,.45);margin-top:2px"><?= e($store['name'] ?? '') ?></div>
                <div style="font-size:.75rem;color:rgba(255,255,255,.4);margin-top:.75rem">
                    <?= e($store['address'] ?? '') ?><br>
                    <?= e($store['phone'] ?? '') ?> <?= $store['email'] ? '• ' . e($store['email']) : '' ?>
                </div>
            </div>
            <div style="text-align:right">
                <div class="invoice-label"><i class="bi bi-file-earmark-text me-2"></i>FACTURE</div>
                <div class="invoice-number"><?= e($sale['invoice_number']) ?></div>
                <div style="margin-top:0.75rem">
                    <span class="payment-badge" style="background:rgba(16,185,129,0.2);color:#10B981">
                        <i class="bi bi-check-circle-fill"></i>
                        <?= $sale['status'] === 'completed' ? 'PAYÉE' : strtoupper($sale['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Méta -->
        <div class="invoice-meta">
            <div class="meta-item">
                <label><i class="bi bi-calendar me-1"></i>Date de vente</label>
                <span><?= date('d/m/Y', strtotime($sale['sale_date'])) ?></span>
            </div>
            <div class="meta-item">
                <label><i class="bi bi-clock me-1"></i>Heure</label>
                <span><?= date('H:i', strtotime($sale['sale_date'])) ?></span>
            </div>
            <div class="meta-item">
                <label><i class="bi bi-person me-1"></i>Caissier</label>
                <span><?= e($sale['cashier_name']) ?></span>
            </div>
            <div class="meta-item">
                <label><i class="bi bi-building me-1"></i>Magasin</label>
                <span><?= e($sale['warehouse_name']) ?></span>
            </div>
        </div>

        <!-- Corps -->
        <div class="invoice-body">

            <!-- Infos client -->
            <?php if ($sale['customer_name']): ?>
            <div style="background:var(--body-bg);border-radius:8px;padding:.75rem 1rem;margin-bottom:1.25rem;display:flex;gap:1.5rem">
                <div>
                    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;color:#64748B;font-weight:600"><i class="bi bi-person me-1"></i>Client</div>
                    <div style="font-weight:600"><?= e($sale['customer_name']) ?></div>
                </div>
                <?php if ($sale['customer_phone']): ?>
                <div>
                    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.1em;color:#64748B;font-weight:600"><i class="bi bi-telephone me-1"></i>Téléphone</div>
                    <div><?= e($sale['customer_phone']) ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Tableau articles -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th><i class="bi bi-hash me-1"></i>#</th>
                        <th><i class="bi bi-box me-1"></i>Désignation</th>
                        <th class="text-center"><i class="bi bi-calculator me-1"></i>Qté</th>
                        <th class="text-end"><i class="bi bi-tag me-1"></i>Prix Unit.</th>
                        <?php if ((float)$sale['discount_amount'] > 0): ?>
                        <th class="text-end"><i class="bi bi-dash me-1"></i>Remise</th>
                        <?php endif; ?>
                        <th class="text-end"><i class="bi bi-currency-dollar me-1"></i>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sale['items'] as $i => $item): ?>
                    <tr>
                        <td style="color:#64748B;font-size:.8rem"><?= $i + 1 ?></td>
                        <td>
                            <div style="font-weight:500"><?= e($item['product_name']) ?></div>
                            <?php if ($item['barcode']): ?>
                            <div style="font-size:.7rem;color:#94A3B8;font-family:'<?= e($invoiceFont) ?>',monospace"><?= e($item['barcode']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= number_format((float)$item['quantity'], 2) ?></td>
                        <td class="text-end"><?= number_format((float)$item['unit_price']) ?></td>
                        <?php if ((float)$sale['discount_amount'] > 0): ?>
                        <td class="text-end" style="color:#EF4444">
                            <?= (float)$item['discount_percent'] > 0 ? '-' . number_format((float)$item['discount_amount']) : '—' ?>
                        </td>
                        <?php endif; ?>
                        <td class="text-end" style="font-weight:600"><?= number_format((float)$item['total_price']) ?> FCFA</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Totaux -->
            <div class="totals-section">
                <div class="total-row"><span>Sous-total</span><span><?= formatMoney((float)$sale['subtotal']) ?></span></div>
                <?php if ((float)$sale['discount_amount'] > 0): ?>
                <div class="total-row" style="color:#EF4444"><span>Remise</span><span>-<?= formatMoney((float)$sale['discount_amount']) ?></span></div>
                <?php endif; ?>
                <div class="total-row"><span>TVA</span><span><?= formatMoney((float)$sale['tax_amount']) ?></span></div>
                <div class="total-row grand-total"><span>TOTAL</span><span><?= formatMoney((float)$sale['total_amount']) ?></span></div>
                <div class="total-row">
                    <span>Payé</span>
                    <span><?= formatMoney((float)$sale['paid_amount']) ?></span>
                </div>
                <?php if (max(0, (float)$sale['total_amount'] - (float)$sale['paid_amount']) > 0): ?>
                <div class="total-row" style="color:#EF4444;font-weight:600">
                    <span>Reliquat</span>
                    <span><?= formatMoney(max(0, (float)$sale['total_amount'] - (float)$sale['paid_amount'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if ((float)$sale['change_amount'] > 0 && empty($sale['change_refunded'])): ?>
                <div class="total-row"><span>Monnaie rendue</span><span><?= formatMoney((float)$sale['change_amount']) ?></span></div>
                <?php endif; ?>
            </div>

        </div><!-- .invoice-body -->

        <!-- Pied -->
        <div class="invoice-footer">
            <div style="font-size:.78rem;color:#64748B">
                <?= e($receiptFooter ?: 'Merci pour votre achat. Ce document fait office de reçu officiel.') ?><br>
                <span style="font-family:'<?= e($invoiceFont) ?>',monospace"><?= e($sale['invoice_number']) ?></span> — Émis le <?= date('d/m/Y à H:i', strtotime($sale['created_at'])) ?>
            </div>
            <div style="font-family:'Syne',sans-serif;font-size:.8rem;color:#CBD5E1;font-weight:700"><?= e($appSettings['app_name'] ?? 'Brenshop') ?></div>
        </div>

    </div>
</div>
<?php endif; ?>

<script>
<?php if ($printMode): ?>
window.onload = () => window.print();
<?php endif; ?>
</script>
</body>
</html>
