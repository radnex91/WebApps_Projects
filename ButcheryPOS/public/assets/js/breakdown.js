// ButcheryPOS - Breakdown (Découpe) JavaScript
// Reads configuration from window.BreakdownConfig set in the template

(function () {
    'use strict';

    let outputRowCount = 0;
    const config = window.BreakdownConfig || {};

    function init() {
        addOutputRow();
        bindEvents();
    }

    function bindEvents() {
        const sourceProduct = document.getElementById('sourceProductId');
        const sourceQuantity = document.getElementById('sourceQuantity');
        const wasteWeight = document.getElementById('wasteWeight');

        if (sourceProduct) {
            sourceProduct.addEventListener('change', loadBatches);
        }
        if (sourceQuantity) {
            sourceQuantity.addEventListener('input', updateReconciliation);
        }
        if (wasteWeight) {
            wasteWeight.addEventListener('input', updateReconciliation);
        }
    }

    function loadBatches() {
        const productId = document.getElementById('sourceProductId').value;
        const batchSelect = document.getElementById('sourceBatchId');

        batchSelect.innerHTML = '<option value="">' + config.i18n.loading + '</option>';

        if (!productId) {
            batchSelect.innerHTML = '<option value="">' + config.i18n.selectProductFirst + '</option>';
            return;
        }

        fetch(config.apiBaseUrl + 'breakdown/batches/' + productId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!Array.isArray(data)) {
                    batchSelect.innerHTML = '<option value="">' + config.i18n.selectProductFirst + '</option>';
                    return;
                }
                batchSelect.innerHTML = '<option value="">' + config.i18n.selectBatch + '</option>';
                data.forEach(function (batch) {
                    const opt = document.createElement('option');
                    opt.value = batch.id;
                    let label = batch.batch_reference + ' — ' + parseFloat(batch.quantity_remaining).toFixed(2) + ' kg';
                    if (batch.supplier_name) label += ' (' + batch.supplier_name + ')';
                    if (batch.expiry_date) label += ' | Exp: ' + batch.expiry_date;
                    opt.textContent = label;
                    batchSelect.appendChild(opt);
                });
            })
            .catch(function () {
                batchSelect.innerHTML = '<option value="">Error</option>';
            });
    }

    function addOutputRow() {
        outputRowCount++;
        const tbody = document.getElementById('outputItemsBody');
        const row = document.createElement('tr');
        row.id = 'outputRow_' + outputRowCount;

        const products = config.retailProducts || [];
        let productOptions = '<option value="">' + config.i18n.selectProduct + '</option>';
        products.forEach(function (p) {
            productOptions += '<option value="' + p.id + '">' + escapeHtml(p.name) + ' (' + escapeHtml(p.sku || '') + ')</option>';
        });

        row.innerHTML =
            '<td>' +
                '<select name="output_product_id[]" class="form-select form-select-sm output-product" required>' +
                    productOptions +
                '</select>' +
            '</td>' +
            '<td>' +
                '<input type="number" name="output_quantity[]" class="form-control form-control-sm text-end output-quantity" step="0.01" min="0.01" required>' +
            '</td>' +
            '<td class="text-center">' +
                '<div class="form-check form-check-inline mb-0">' +
                    '<input type="checkbox" name="output_is_byproduct[' + outputRowCount + ']" value="1" class="form-check-input">' +
                '</div>' +
            '</td>' +
            '<td>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" onclick="window.BreakdownJS.removeOutputRow(\'' + row.id + '\')" title="' + config.i18n.delete + '">' +
                    '<i class="bi bi-x-lg"></i>' +
                '</button>' +
            '</td>';

        tbody.appendChild(row);

        const qtyInput = row.querySelector('.output-quantity');
        if (qtyInput) {
            qtyInput.addEventListener('input', updateReconciliation);
        }

        updateReconciliation();
    }

    function removeOutputRow(rowId) {
        const row = document.getElementById(rowId);
        if (row) {
            row.remove();
            updateReconciliation();
        }
    }

    function updateReconciliation() {
        const sourceQty = parseFloat((document.getElementById('sourceQuantity') || {}).value || 0);
        const wasteQty = parseFloat((document.getElementById('wasteWeight') || {}).value || 0);

        let outputTotal = 0;
        document.querySelectorAll('.output-quantity').forEach(function (input) {
            const val = parseFloat(input.value || 0);
            if (!isNaN(val)) outputTotal += val;
        });

        const diff = Math.abs(sourceQty - (outputTotal + wasteQty));

        document.getElementById('reconSource').textContent = sourceQty.toFixed(2);
        document.getElementById('reconOutput').textContent = outputTotal.toFixed(2);
        document.getElementById('reconWaste').textContent = wasteQty.toFixed(2);
        document.getElementById('reconDiff').textContent = diff.toFixed(2);

        const statusEl = document.getElementById('reconStatus');
        const submitBtn = document.getElementById('submitBreakdownBtn');

        if (sourceQty <= 0) {
            statusEl.className = 'badge bg-secondary';
            statusEl.textContent = config.i18n.pendingReconciliation;
            if (submitBtn) submitBtn.disabled = true;
        } else if (diff <= 0.5) {
            statusEl.className = 'badge bg-success';
            statusEl.textContent = config.i18n.weightOk + ' ✓';
            if (submitBtn) submitBtn.disabled = false;
        } else {
            statusEl.className = 'badge bg-danger';
            statusEl.textContent = config.i18n.weightMismatch + ' (' + diff.toFixed(2) + ' kg)';
            if (submitBtn) submitBtn.disabled = true;
        }
    }

    function resetBreakdownForm() {
        document.getElementById('breakdownForm').reset();
        document.getElementById('sourceBatchId').innerHTML = '<option value="">' + config.i18n.selectProductFirst + '</option>';
        document.getElementById('batchAvailableInfo').textContent = '';
        document.getElementById('outputItemsBody').innerHTML = '';
        outputRowCount = 0;
        addOutputRow();
        updateReconciliation();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Expose functions to global scope for onclick handlers
    window.BreakdownJS = {
        addOutputRow: addOutputRow,
        removeOutputRow: removeOutputRow,
        resetBreakdownForm: resetBreakdownForm,
    };

    // Init on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();