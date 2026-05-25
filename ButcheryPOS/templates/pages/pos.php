<?php
// ButcheryPOS - POS (Point of Sale) Page
use App\Core\Auth;
use App\Core\Permission;

$user = Auth::user();
$categories = $productService->getAllCategories();
$products = $productService->getAllProducts(null, true);
$currency = $appSettings['company_currency'] ?? 'XAF';
?>

<div id="pos-page" class="pos-page">
    <!-- Données i18n pour le JavaScript -->
    <span id="pos-i18n" class="d-none"><?= json_encode([
        'cart_empty' => t('cart_empty'),
        'no_terminal' => t('open_terminal'),
        'sale_completed' => t('sale_completed'),
        'sale_failed' => t('error_occurred'),
        'sale_on_hold' => t('hold_sale'),
        'terminal_opened' => t('open_terminal'),
        'terminal_closed' => t('close_terminal'),
        'items_label' => t('products'),
    ]) ?></span>
    <span id="app-currency" class="d-none"><?= e($currency) ?></span>
    <!-- Left: Product Grid -->
    <div class="pos-products">
        <div class="product-search">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" id="product-search" class="form-control"
                       placeholder="<?= t('search_products') ?>"
                       oninput="POS.loadProducts(null)">
            </div>
        </div>
        <div class="category-tabs">
            <span class="category-tab active" onclick="POS.loadProducts(null); this.parentElement.querySelectorAll('.category-tab').forEach(t=>t.classList.remove('active')); this.classList.add('active');">
                <?= t('all') ?>
            </span>
            <?php foreach ($categories as $cat): ?>
            <span class="category-tab"
                  onclick="POS.loadProducts(<?= $cat['id'] ?>); this.parentElement.querySelectorAll('.category-tab').forEach(t=>t.classList.remove('active')); this.classList.add('active');">
                <?= e($cat['name']) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <div id="product-grid" class="product-grid">
            <?php foreach ($products as $p): ?>
            <div class="pos-product-card <?= (float)$p['quantity_in_stock'] <= 0 ? 'out-of-stock' : '' ?>"
                 data-product='<?= json_encode(['id'=>$p['id'],'name'=>$p['name'],'sku'=>$p['sku'],'sale_price'=>$p['sale_price'],'unit_abbr'=>$p['unit_abbr'],'quantity_in_stock'=>$p['quantity_in_stock'],'unit_type'=>$p['unit_type']??'weight']) ?>'
                 onclick="<?= (float)$p['quantity_in_stock'] > 0 ? "POS.addItem(" . htmlspecialchars(json_encode(['id'=>$p['id'],'name'=>$p['name'],'sku'=>$p['sku'],'sale_price'=>$p['sale_price'],'unit_abbr'=>$p['unit_abbr'],'quantity_in_stock'=>$p['quantity_in_stock'],'unit_type'=>$p['unit_type']??'weight']), ENT_QUOTES) . ")" : "" ?>">
                <div class="product-name"><?= e($p['name']) ?></div>
                <div class="product-price"><?= money((float)$p['sale_price'], $currency) ?></div>
                <div class="product-stock"><?= $p['quantity_in_stock'] ?> <?= e($p['unit_abbr'] ?? '') ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Center: Cart -->
    <div class="pos-cart">
        <div class="cart-header">
            <span><i class="bi bi-cart3"></i> <span id="cart-item-count">0</span> <?= t('products') ?></span>
            <div>
                <button class="btn btn-sm btn-outline-secondary" onclick="POS.holdSale()" title="<?= t('hold_sale') ?>">
                    <i class="bi bi-pause-circle"></i>
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="POS.clearCart()" title="<?= t('clear_cart') ?>">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>

        <!-- Customer selector -->
        <div class="p-2 border-bottom bg-light">
            <select id="cart-customer" class="form-select form-select-sm"
                    onchange="POS.cart.customer_id = this.value || null">
                <option value=""><?= t('walk_in_customer') ?></option>
                <?php foreach ($customerModel->all(['is_active' => 1], 'name ASC') as $c): ?>
                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?> <?= $c['phone'] ? '- ' . e($c['phone']) : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div id="cart-items" class="cart-items">
            <div class="text-center text-muted py-5">
                <i class="bi bi-cart3" style="font-size:48px"></i>
                <p class="mt-2" id="cart-empty-text"><?= t('cart_empty') ?></p>
            </div>
        </div>

        <div class="cart-footer">
            <!-- Discount -->
            <div class="mb-2">
                <div class="input-group input-group-sm">
                    <span class="input-group-text"><?= t('discount') ?></span>
                    <input type="number" class="form-control" id="cart-discount" value="0" min="0" step="100"
                           onchange="POS.cart.discount_amount = parseFloat(this.value)||0; POS.renderCart();">
                    <span class="input-group-text"><?= $currency ?></span>
                </div>
            </div>

            <!-- Totals -->
            <div class="cart-totals">
                <div class="total-row">
                    <span><?= t('subtotal') ?></span>
                    <span id="cart-subtotal"><?= money(0, $currency) ?></span>
                </div>
                <div class="total-row">
                    <span><?= t('discount') ?></span>
                    <span id="cart-discount-display">0 <?= $currency ?></span>
                </div>
                <div class="total-row grand">
                    <span><?= t('total') ?></span>
                    <span id="cart-total"><?= money(0, $currency) ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Payment & Scale -->
    <div class="pos-payment">
        <!-- Scale Display -->
        <div class="scale-display">
            <div class="scale-label"><?= t('scale_weight') ?></div>
            <span id="scale-weight">0.000</span> <small>kg</small>
        </div>

        <!-- Payment Methods -->
        <div class="payment-section">
            <h6 class="mb-2"><?= t('payment_method') ?></h6>
            <div class="payment-methods">
                <div class="payment-method-btn active" data-method="cash" onclick="POS.selectPaymentMethod('cash')">
                    <i class="bi bi-cash-stack"></i><br><?= t('cash') ?>
                </div>
                <div class="payment-method-btn" data-method="card" onclick="POS.selectPaymentMethod('card')">
                    <i class="bi bi-credit-card"></i><br><?= t('card') ?>
                </div>
                <div class="payment-method-btn" data-method="orange_money" onclick="POS.selectPaymentMethod('orange_money')">
                    <i class="bi bi-phone"></i><br><?= t('orange_money') ?>
                </div>
                <div class="payment-method-btn" data-method="mtn_momo" onclick="POS.selectPaymentMethod('mtn_momo')">
                    <i class="bi bi-phone"></i><br><?= t('mtn_momo') ?>
                </div>
                <?php if (($appSettings['mobile_money_wave_enabled'] ?? '0') === '1'): ?>
                <div class="payment-method-btn" data-method="wave" onclick="POS.selectPaymentMethod('wave')">
                    <i class="bi bi-phone"></i><br><?= t('wave') ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Cash Tender -->
            <div id="cash-tender-section" class="cash-tender show">
                <label class="form-label small"><?= t('amount_tendered') ?></label>
                <input type="number" class="form-control form-control-lg" id="amount-tendered"
                       min="0" step="100" onchange="POS.setAmountTendered(this.value)">
                <div class="change-display mt-2">
                    <span class="small text-muted"><?= t('change_due') ?>:</span>
                    <span id="change-due" class="d-block"><?= money(0, $currency) ?></span>
                </div>
            </div>

            <!-- Mobile Money -->
            <div id="mobile-money-section" class="mobile-money-section">
                <label class="form-label small"><?= t('phone') ?></label>
                <input type="tel" class="form-control" id="mm-phone" placeholder="+237 6XX XXX XXX">
                <button class="btn btn-outline-primary btn-sm mt-2 w-100"><?= t('pay') ?></button>
            </div>

            <!-- Complete Sale -->
            <button class="btn btn-danger btn-complete-sale w-100 mt-3"
                    onclick="POS.completeSale()">
                <i class="bi bi-check-circle"></i> <?= t('complete_sale') ?>
            </button>
        </div>

        <!-- Session Control -->
        <div class="pos-session-control">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <small class="text-muted"><i class="bi bi-display"></i> <span id="pos-session-status">--</span></small>
                <?php if (Permission::currentUserCan('pos', 'can_manage')): ?>
                <button class="btn btn-sm btn-outline-secondary" onclick="POS.closeTerminal()">
                    <?= t('close_terminal') ?>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Terminal Open Modal -->
<div id="terminal-modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cash-register"></i> <?= t('open_terminal') ?></h5>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label"><?= t('terminal_name') ?></label>
                    <input type="text" id="terminal-name" class="form-control" value="Terminal-1">
                </div>
                <div class="mb-3">
                    <label class="form-label"><?= t('opening_cash') ?> (<?= $currency ?>)</label>
                    <input type="number" id="opening-cash" class="form-control" value="0" min="0" step="500">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-danger" onclick="POS.openTerminal()"><?= t('open_terminal') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Held Sales Panel (hidden by default) -->
<div id="held-sales-panel" style="display:none">
    <h6 class="p-2 mb-0"><?= t('held_sales') ?></h6>
    <div id="held-sales-list" class="p-2"></div>
</div>