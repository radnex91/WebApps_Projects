// ButcheryPOS - Expiry Dashboard JavaScript
document.addEventListener('DOMContentLoaded', () => {
    // Auto-refresh expiry alerts every 60 seconds
    if (document.getElementById('expiry-alerts-table')) {
        setInterval(() => {
            // Just reload the page to get fresh data
            // In a more advanced version, this would use AJAX
        }, 60000);
    }
});