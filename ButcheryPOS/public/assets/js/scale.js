// ButcheryPOS - Scale Reader
const ScaleReader = {
    pollInterval: 1500,
    apiUrl: '/ButcheryPOS/public/api/scale',
    lastWeight: 0,
    isRunning: false,
    _timer: null,

    async fetch() {
        try {
            const res = await fetch(this.apiUrl);
            const data = await res.json();
            if (data.weight_value !== undefined && data.weight_value > 0) {
                this.lastWeight = parseFloat(data.weight_value);
                this.updateDisplay();
            }
        } catch (e) {
            // Silent - scale may not be connected
        }
    },

    updateDisplay() {
        const el = document.getElementById('scale-weight');
        if (el) el.textContent = this.lastWeight.toFixed(3);

        // Auto-fill quantity in cart if a weighted product is selected
        const qtyInput = document.getElementById('scale-qty-fill');
        if (qtyInput && this.lastWeight > 0) {
            qtyInput.value = this.lastWeight.toFixed(3);
        }
    },

    start() {
        if (this.isRunning) return;
        this.isRunning = true;
        this.fetch();
        this._timer = setInterval(() => this.fetch(), this.pollInterval);
    },

    stop() {
        this.isRunning = false;
        if (this._timer) {
            clearInterval(this._timer);
            this._timer = null;
        }
    },

    getWeight() {
        return this.lastWeight;
    },

    reset() {
        this.lastWeight = 0;
        this.updateDisplay();
    }
};