// ButcheryPOS - Stock & Expiry JavaScript
document.addEventListener('DOMContentLoaded', () => {
    // Stock page: product selector change shows current stock
    const productSelect = document.getElementById('adjust-product');
    if (productSelect) {
        productSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const stock = option.dataset.stock || '0';
            const stockDisplay = document.getElementById('current-stock-display');
            if (stockDisplay) {
                stockDisplay.textContent = stock;
            }
        });
    }
});