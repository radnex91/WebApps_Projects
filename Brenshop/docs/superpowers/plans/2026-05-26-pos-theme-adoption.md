# POS Theme Adoption Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the POS page to use the dynamic theme system so it visually matches whichever theme the user selects.

**Architecture:** Replace all hardcoded Afro-Brutal CSS variables (`--pos-*`) with the general theme's CSS custom properties (`--accent`, `--card-bg`, `--body-bg`, `--text`, `--border`, `--shadow-md`, etc.), change sharp corners to rounded, hard shadows to soft, and thick borders to thin. The POS keeps its layout grid and all functionality.

**Tech Stack:** PHP 8.0+, CSS custom properties, vanilla JS, Bootstrap 5.3.2

---

### Task 1: Rewrite pos.css — Design tokens and base overrides

**Files:**
- Modify: `assets/css/pos.css:1-28` (design tokens + base overrides)

Replace the entire `:root` block and `body.pos-page` overrides. Remove all `--pos-*` variable definitions. Replace base overrides with theme-aware ones.

- [ ] **Step 1: Replace design tokens and base overrides**

Replace lines 1-28 (the `:root` block and base overrides) with:

```css
/* ═══════════════════════════════════════════════════════════
   POS — THEME-AWARE STYLE
   Brenshop Point de Vente
   ═══════════════════════════════════════════════════════════ */

/* ── BASE OVERRIDES ── */
body.pos-page { overflow: hidden; background: var(--body-bg); }
body.pos-page .page-content { padding: 0; }
```

No more `--pos-*` variables. Everything inherits from the theme system in `layout_top.php`.

- [ ] **Step 2: Commit design tokens**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): remove hardcoded pos design tokens, use theme variables"
```

---

### Task 2: Rewrite pos.css — Layout and mini dashboard

**Files:**
- Modify: `assets/css/pos.css:30-67` (POS layout + mini dashboard)

- [ ] **Step 1: Replace layout and dashboard styles**

Replace the `.pos-layout` through `.pos-dashboard .dash-value.accent` rules (approximately lines 30-67) with:

```css
/* ── POS LAYOUT ── */
.pos-layout {
  display: grid;
  grid-template-columns: 380px 1fr;
  height: calc(100vh - 57px);
  overflow: hidden;
}

/* ── MINI DASHBOARD ── */
.pos-dashboard {
  display: flex;
  border-bottom: 1px solid var(--border);
  background: var(--card-bg);
  flex-shrink: 0;
}
.pos-dashboard .dash-card {
  flex: 1;
  padding: 6px 12px;
  border-right: 1px solid var(--border);
  text-align: center;
}
.pos-dashboard .dash-card:last-child { border-right: none; }
.pos-dashboard .dash-label {
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
}
.pos-dashboard .dash-value {
  font-size: 1.15rem;
  font-weight: 800;
  color: var(--text);
  font-variant-numeric: tabular-nums;
  line-height: 1.2;
}
.pos-dashboard .dash-value.accent { color: var(--accent); }
```

Key changes: `var(--pos-border-w)` → `1px`, `var(--pos-border)` → `var(--border)`, `#fef3c7` → `var(--card-bg)`, font-weight 700→600 for labels, 900→800 for values.

- [ ] **Step 2: Commit**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): theme-adopt layout and dashboard styles"
```

---

### Task 3: Rewrite pos.css — Cart panel (session bar through cart header)

**Files:**
- Modify: `assets/css/pos.css:68-141` (cart panel, session bar, cart header)

- [ ] **Step 1: Replace cart panel, session bar, and cart header**

Replace lines 68-141 (`.cart-panel` through `.cart-header .cart-count`) with:

```css
/* ── CART PANEL (left) ── */
.cart-panel {
  background: var(--card-bg);
  border-right: 1px solid var(--border);
  display: flex;
  flex-direction: column;
  overflow: hidden;
  height: 100%;
}

/* Session bar */
.cart-session-bar {
  background: var(--accent);
  padding: 5px 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-shrink: 0;
  gap: 6px;
  border-radius: 0;
}
.cart-session-bar .sess-caisse {
  font-weight: 700;
  font-size: 0.75rem;
  color: #fff;
}
.cart-session-bar .sess-balance {
  background: rgba(255,255,255,.2);
  color: #fff;
  padding: 1px 8px;
  font-size: 0.68rem;
  font-weight: 700;
  border-radius: 4px;
}
.cart-session-bar .sess-actions { display: flex; gap: 4px; }
.cart-session-bar .sess-btn {
  background: rgba(255,255,255,.15);
  border: 1px solid rgba(255,255,255,.25);
  color: #fff;
  font-size: 0.72rem;
  padding: 2px 7px;
  cursor: pointer;
  font-weight: 600;
  border-radius: 4px;
  transition: all 0.15s;
}
.cart-session-bar .sess-btn:hover { background: rgba(255,255,255,.3); }
.cart-session-bar .sess-btn.danger { border-color: rgba(220,38,38,.4); }
.cart-session-bar .sess-btn.danger:hover { background: rgba(220,38,38,.5); }

/* Cart header */
.cart-header {
  padding: 6px 10px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.cart-header h6 {
  font-family: 'Manrope', sans-serif;
  font-weight: 800;
  font-size: 0.82rem;
  color: var(--text);
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.cart-header .cart-count {
  background: var(--accent);
  color: #fff;
  font-size: 0.7rem;
  padding: 1px 8px;
  font-weight: 800;
  border-radius: 12px;
}
```

Key changes: `linear-gradient(135deg, ...)` → `var(--accent)`, `var(--pos-border-w)` → `1px`, `var(--pos-border)` → `var(--border)`, `border: 2px solid` → `1px solid`, `border-radius: 0` → `border-radius: 4px`/`12px`, `#fef3c7` → removed, `var(--pos-surface)` → `var(--card-bg)`, `var(--pos-text)` → `var(--text)`.

- [ ] **Step 2: Commit**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): theme-adopt cart panel, session bar, cart header"
```

---

### Task 4: Rewrite pos.css — Client row and cart items

**Files:**
- Modify: `assets/css/pos.css:143-288` (cart-client-row through cart-empty)

- [ ] **Step 1: Replace client row and cart items styles**

Replace lines 143-288 (`.cart-client-row` through `.cart-empty i`) with:

```css
/* Client row */
.cart-client-row {
  padding: 5px 10px;
  display: flex;
  gap: 5px;
  border-bottom: 1px solid var(--border);
  flex-shrink: 0;
}
.cart-client-row select {
  flex: 1;
  font-size: 0.75rem;
  padding: 4px 6px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  color: var(--text);
  outline: none;
  border-radius: 6px;
  font-weight: 500;
}
.cart-client-row select:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}
.cart-client-row .btn-clear {
  background: rgba(220,38,38,.08);
  border: 1px solid var(--danger);
  color: var(--danger);
  padding: 3px 8px;
  cursor: pointer;
  font-size: 0.8rem;
  font-weight: 600;
  border-radius: 6px;
  transition: all 0.15s;
}
.cart-client-row .btn-clear:hover {
  background: var(--danger);
  color: #fff;
}

/* Cart items */
.cart-items {
  flex: 1;
  overflow-y: auto;
  padding: 4px 6px;
  min-height: 0;
}
.cart-items::-webkit-scrollbar { width: 5px; }
.cart-items::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }

.cart-item {
  border: 1px solid var(--border);
  background: var(--card-bg);
  box-shadow: var(--shadow);
  padding: 6px 8px;
  margin-bottom: 4px;
  border-radius: 8px;
  display: grid;
  grid-template-columns: 1fr 20px;
  gap: 2px;
  transition: all 0.15s;
}
.cart-item.flash {
  background: rgba(22,163,74,0.1);
  border-color: var(--accent2);
}
.cart-item .ci-top {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 4px;
  grid-column: 1 / -1;
}
.cart-item .ci-name {
  font-weight: 700;
  font-size: 0.78rem;
  color: var(--text);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  min-width: 0;
}
.cart-item .ci-remove {
  color: var(--danger);
  cursor: pointer;
  font-size: 0.85rem;
  opacity: 0.5;
  transition: opacity 0.15s;
  flex-shrink: 0;
}
.cart-item .ci-remove:hover { opacity: 1; }
.cart-item .ci-bottom {
  display: flex;
  justify-content: space-between;
  align-items: center;
  grid-column: 1 / -1;
}
.cart-item .ci-price {
  color: var(--accent);
  font-size: 0.72rem;
  font-weight: 600;
}
.cart-item .ci-qty {
  display: flex;
  align-items: center;
  gap: 3px;
}
.cart-item .ci-qty button {
  width: 22px;
  height: 22px;
  border: 1px solid var(--border);
  background: var(--card-bg);
  color: var(--text);
  cursor: pointer;
  font-size: 0.72rem;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s;
  border-radius: 4px;
}
.cart-item .ci-qty button:hover {
  background: var(--accent);
  color: #fff;
  border-color: var(--accent);
}
.cart-item .ci-qty span {
  font-weight: 800;
  font-size: 0.82rem;
  min-width: 20px;
  text-align: center;
}
.cart-item .ci-subtotal {
  font-weight: 800;
  font-size: 0.82rem;
  color: var(--accent);
  white-space: nowrap;
}

/* Cart empty */
.cart-empty {
  text-align: center;
  color: var(--text-muted);
  padding: 2rem 0.5rem;
  font-size: 0.82rem;
  font-weight: 500;
}
.cart-empty i { font-size: 2rem; opacity: 0.15; display: block; margin-bottom: 0.5rem; }
```

Key changes: `border: 2px solid var(--pos-border)` → `1px solid var(--border)`, `box-shadow: 2px 2px 0` → `var(--shadow)`, `border-radius: 0` → `8px`/`6px`/`4px`, `background: #fef3c7` → `var(--card-bg)`, flash animation uses `rgba` with accent2, font-weight 800→700, 900→800.

- [ ] **Step 2: Commit**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): theme-adopt client row and cart items"
```

---

### Task 5: Rewrite pos.css — Cart totals, payment, and actions

**Files:**
- Modify: `assets/css/pos.css:290-423` (cart-totals through btn-validate)

- [ ] **Step 1: Replace cart totals, payment, and validate button styles**

Replace lines 290-423 (`.cart-totals` through `.btn-validate:disabled`) with:

```css
/* Cart totals */
.cart-totals {
  border-top: 1px solid var(--border);
  background: var(--card-bg);
  color: var(--text);
  padding: 6px 10px;
  flex-shrink: 0;
}
.cart-totals .total-row {
  display: flex;
  justify-content: space-between;
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-bottom: 1px;
}
.cart-totals .discount-row {
  display: flex;
  align-items: center;
  gap: 4px;
  font-size: 0.72rem;
  color: var(--text-muted);
  margin-bottom: 1px;
}
.cart-totals .discount-row input {
  width: 38px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  color: var(--text);
  text-align: center;
  font-size: 0.68rem;
  padding: 1px 2px;
  font-weight: 700;
  border-radius: 4px;
}
.cart-totals .total-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin: 4px 0 2px;
  padding: 5px 8px;
  background: var(--accent);
  border-radius: 6px;
  font-weight: 800;
  font-size: 0.9rem;
  color: #fff;
}

/* Cart payment */
.cart-payment {
  padding: 6px 10px;
  flex-shrink: 0;
  background: var(--card-bg);
  border-top: 1px solid var(--border);
}
.cart-payment .payment-row {
  display: flex;
  gap: 5px;
  align-items: center;
  margin-bottom: 4px;
}
.cart-payment select {
  flex: 1;
  font-size: 0.75rem;
  padding: 5px 6px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  color: var(--text);
  border-radius: 6px;
  outline: none;
  font-weight: 600;
}
.cart-payment select:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.cart-payment input {
  flex: 1;
  font-size: 0.85rem;
  padding: 5px 8px;
  background: var(--card-bg);
  border: 1px solid var(--border);
  color: var(--text);
  font-weight: 800;
  border-radius: 6px;
  outline: none;
}
.cart-payment input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(99,102,241,0.15); }
.cart-payment input::placeholder { color: var(--text-muted); font-weight: 500; font-size: 0.75rem; }

.change-display {
  font-size: 0.72rem;
  padding: 2px 8px;
  font-weight: 800;
  white-space: nowrap;
  border: 1px solid var(--border);
  border-radius: 4px;
}
.change-display.positive { background: rgba(14,168,126,0.1); color: var(--accent2); border-color: var(--accent2); }
.change-display.negative { background: rgba(220,38,38,0.08); color: var(--danger); border-color: var(--danger); }

/* Cart actions */
.cart-actions {
  padding: 4px 10px 6px;
  flex-shrink: 0;
  background: var(--card-bg);
}

.btn-validate {
  width: 100%;
  padding: 10px;
  border: none;
  border-radius: 8px;
  background: var(--accent);
  color: #fff;
  font-family: 'Manrope', sans-serif;
  font-weight: 800;
  font-size: 0.9rem;
  cursor: pointer;
  transition: all 0.15s;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.btn-validate:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}
.btn-validate:active {
  transform: translateY(0);
  box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}
.btn-validate:disabled {
  opacity: 0.35;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}
```

Key changes: Removed the dark `#1a1a1a` backgrounds, replaced with `var(--card-bg)`. Total highlight uses `var(--accent)` with `border-radius: 6px` instead of amber+gold neobrutalist style. Validate button: `border: none`, `border-radius: 8px`, soft shadow, `translateY` instead of `translate`.

- [ ] **Step 2: Commit**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): theme-adopt cart totals, payment, and validate button"
```

---

### Task 6: Rewrite pos.css — Catalog panel (toolbar, search, categories)

**Files:**
- Modify: `assets/css/pos.css:425-543` (catalog-panel through cat-chip.active)

- [ ] **Step 1: Replace catalog panel, toolbar, search, and category chip styles**

Replace lines 425-543 with:

```css
/* ── CATALOG PANEL (right) ── */
.catalog-panel {
  display: flex;
  flex-direction: column;
  background: var(--body-bg);
  overflow: hidden;
  height: 100%;
}

/* Toolbar */
.catalog-toolbar {
  background: var(--card-bg);
  border-bottom: 1px solid var(--border);
  padding: 8px 12px;
  flex-shrink: 0;
}
.search-row {
  display: flex;
  gap: 6px;
  align-items: center;
  margin-bottom: 6px;
}
.search-row .search-wrap {
  flex: 1;
  position: relative;
}
.search-row .search-wrap input {
  width: 100%;
  border: 1px solid var(--border);
  padding: 8px 12px 8px 36px;
  font-size: 0.88rem;
  background: var(--card-bg);
  color: var(--text);
  outline: none;
  font-weight: 500;
  border-radius: 8px;
  box-shadow: var(--shadow);
  transition: all 0.15s;
}
.search-row .search-wrap input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}
.search-row .search-wrap input.scan-flash {
  border-color: var(--accent2);
  box-shadow: 0 0 0 3px rgba(14,168,126,0.15);
}
.search-row .search-wrap i {
  position: absolute;
  left: 12px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--text-muted);
  font-size: 0.88rem;
}
.search-row select {
  border: 1px solid var(--border);
  padding: 7px 8px;
  font-size: 0.82rem;
  background: var(--card-bg);
  color: var(--text);
  outline: none;
  border-radius: 6px;
  font-weight: 600;
}
.search-row select:focus { border-color: var(--accent); }

/* View toggle */
.view-toggle {
  display: flex;
  border: 1px solid var(--border);
  overflow: hidden;
  border-radius: 6px;
}
.view-toggle button {
  padding: 6px 10px;
  border: none;
  border-right: 1px solid var(--border);
  background: var(--card-bg);
  color: var(--text-muted);
  cursor: pointer;
  font-size: 0.88rem;
  font-weight: 600;
  transition: all 0.15s;
}
.view-toggle button:last-child { border-right: none; }
.view-toggle button.active {
  background: var(--accent);
  color: #fff;
}

/* Category chips */
.cat-chips {
  display: flex;
  gap: 4px;
  overflow-x: auto;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.cat-chips::-webkit-scrollbar { display: none; }
.cat-chip {
  padding: 4px 12px;
  font-size: 0.72rem;
  font-weight: 600;
  white-space: nowrap;
  border: 1px solid var(--border);
  background: var(--card-bg);
  color: var(--text);
  cursor: pointer;
  transition: all 0.15s;
  border-radius: 20px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.cat-chip:hover { border-color: var(--accent); color: var(--accent); }
.cat-chip.active {
  background: var(--accent);
  border-color: var(--accent);
  color: #fff;
}
```

Key changes: All `var(--pos-*)` → theme vars, `border-radius: 0` → `8px`/`6px`/`20px`, search focus uses `box-shadow: 0 0 0 3px` ring instead of hard offset, chips are pill-shaped (`20px`).

- [ ] **Step 2: Commit**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): theme-adopt catalog panel, search, categories"
```

---

### Task 7: Rewrite pos.css — Product list and grid

**Files:**
- Modify: `assets/css/pos.css:545-707` (products-container through product-tile .pt-stock)

- [ ] **Step 1: Replace product list table and product grid styles**

Replace lines 545-707 with:

```css
/* ── PRODUCT LIST (table) ── */
.products-container {
  flex: 1;
  overflow-y: auto;
  padding: 6px;
  -webkit-overflow-scrolling: touch;
}
.products-container::-webkit-scrollbar { width: 5px; }
.products-container::-webkit-scrollbar-thumb { background: var(--accent); border-radius: 4px; }

.products-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
}
.products-table thead { position: sticky; top: 0; z-index: 10; }
.products-table th {
  background: var(--card-bg);
  padding: 8px;
  font-size: 0.68rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  font-weight: 700;
  border-bottom: 1px solid var(--border);
  text-align: left;
  white-space: nowrap;
}
.products-table th:last-child { text-align: right; }
.products-table td {
  padding: 6px 8px;
  border-bottom: 1px solid var(--border);
  vertical-align: middle;
}
.products-table td:last-child { text-align: right; }

.product-row {
  cursor: pointer;
  transition: all 0.15s;
  user-select: none;
}
.product-row:hover {
  background: rgba(99,102,241,0.06);
  box-shadow: inset 3px 0 0 var(--accent);
}
.product-row.out {
  opacity: 0.35;
  cursor: not-allowed;
  filter: grayscale(0.6);
}
.product-row.out:hover { background: transparent; box-shadow: none; }
.product-row.flash {
  background: rgba(14,168,126,0.08) !important;
  box-shadow: inset 3px 0 0 var(--accent2) !important;
}

.p-img-cell {
  width: 36px;
  height: 36px;
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  border: 1px solid var(--border);
  border-radius: 6px;
}
.p-img-cell img { width: 100%; height: 100%; object-fit: cover; }
.p-img-cell .p-icon { font-size: 0.9rem; color: #fff; }
.p-name-cell { min-width: 0; }
.p-name-cell .p-title { font-weight: 700; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.p-name-cell .p-ref { font-size: 0.68rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.p-price-cell {
  font-weight: 800;
  color: var(--accent2);
  font-variant-numeric: tabular-nums;
  white-space: nowrap;
  font-family: 'DM Mono', monospace;
}

/* Stock badge */
.stock-badge {
  display: inline-block;
  padding: 1px 8px;
  font-size: 0.68rem;
  font-weight: 700;
  border: 1px solid;
  border-radius: 12px;
}
.stock-badge.ok { background: rgba(14,168,126,0.1); color: var(--accent2); border-color: rgba(14,168,126,0.3); }
.stock-badge.low { background: rgba(217,119,6,0.1); color: #b45309; border-color: rgba(217,119,6,0.3); }
.stock-badge.out { background: rgba(220,38,38,0.08); color: var(--danger); border-color: rgba(220,38,38,0.3); }

/* ── PRODUCT GRID (cards) ── */
.products-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
  gap: 8px;
}
.product-tile {
  background: var(--card-bg);
  border: 1px solid var(--border);
  padding: 12px;
  cursor: pointer;
  position: relative;
  overflow: hidden;
  user-select: none;
  border-radius: 10px;
  box-shadow: var(--shadow);
  transition: all 0.15s;
}
.product-tile:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
  border-color: var(--accent);
}
.product-tile:active { transform: translateY(0); box-shadow: var(--shadow); }
.product-tile.out {
  opacity: 0.35;
  cursor: not-allowed;
  filter: grayscale(0.6);
}
.product-tile.out:hover {
  transform: none;
  box-shadow: var(--shadow);
  border-color: var(--border);
}

.product-tile .pt-cat {
  font-size: 0.65rem;
  font-weight: 700;
  margin-bottom: 4px;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.product-tile .pt-name {
  font-weight: 700;
  font-size: 0.82rem;
  color: var(--text);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  line-height: 1.3;
  margin-bottom: 6px;
}
.product-tile .pt-footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.product-tile .pt-price {
  font-family: 'DM Mono', monospace;
  font-weight: 800;
  font-size: 0.85rem;
  color: var(--accent2);
}
.product-tile .pt-stock {
  font-size: 0.68rem;
  font-weight: 700;
  border: 1px solid;
  padding: 0 6px;
  border-radius: 12px;
}
```

Key changes: Hard `#fef3c7` → `var(--card-bg)`, `var(--pos-border-w)` → `1px`, `var(--pos-shadow)` → removed (soft shadow via `var(--shadow)`), `border-radius: 0` → `10px`/`6px`/`12px`, row hover uses `rgba(99,102,241,0.06)`, stock badges use semi-transparent themed backgrounds, `var(--pos-success)` → `var(--accent2)`, `var(--pos-danger)` → `var(--danger)`.

- [ ] **Step 2: Commit**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): theme-adopt product list and grid styles"
```

---

### Task 8: Rewrite pos.css — Welcome screen, caisse cards, modals, receipt, animations

**Files:**
- Modify: `assets/css/pos.css:709-918` (pos-welcome through print media)

- [ ] **Step 1: Replace welcome, caisse cards, modals, receipt, animations, responsive, print**

Replace lines 709-930 with:

```css
/* ── CAISSE SELECTION (no session) ── */
.pos-welcome {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  text-align: center;
}
.pos-welcome-icon {
  width: 80px;
  height: 80px;
  border: 1px solid var(--border);
  box-shadow: var(--shadow-md);
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 1.5rem;
  background: var(--card-bg);
}
.pos-welcome-icon i { font-size: 2.5rem; color: var(--accent); }
.pos-welcome h4 {
  font-family: 'Manrope', sans-serif;
  font-weight: 800;
  color: var(--text);
  margin-bottom: 0.5rem;
  font-size: 1.3rem;
}
.pos-welcome p {
  color: var(--text-muted);
  font-size: 0.9rem;
  margin-bottom: 1.5rem;
}
.btn-open-caisse {
  border: none;
  border-radius: 8px;
  background: var(--accent);
  color: #fff;
  padding: 10px 24px;
  font-weight: 800;
  font-size: 0.95rem;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  transition: all 0.15s;
  font-family: 'Manrope', sans-serif;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.btn-open-caisse:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* Caisse cards in modal */
.caisse-card {
  border: 1px solid var(--border);
  padding: 1rem;
  cursor: pointer;
  text-align: center;
  background: var(--card-bg);
  box-shadow: var(--shadow);
  border-radius: 10px;
  transition: all 0.15s;
}
.caisse-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-md);
  border-color: var(--accent);
}
.caisse-card.selected {
  background: rgba(99,102,241,0.08);
  border-color: var(--accent);
  box-shadow: var(--shadow-md);
}
.caisse-card-icon {
  width: 48px;
  height: 48px;
  border: 1px solid var(--border);
  background: var(--card-bg);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto 0.5rem;
}
.caisse-card-icon i { font-size: 1.4rem; color: var(--accent); }

/* ── MODALS (theme-aware) ── */
body.pos-page .modal-content {
  border: 1px solid var(--border);
  border-radius: 12px;
  box-shadow: var(--shadow-md);
  background: var(--card-bg);
}
body.pos-page .modal-header {
  background: var(--accent);
  color: #fff;
  border-bottom: none;
  border-radius: 12px 12px 0 0;
}
body.pos-page .modal-header .modal-title {
  color: #fff;
  font-weight: 700;
}
body.pos-page .modal-header .btn-close { filter: brightness(0) invert(1); }
body.pos-page .modal-footer { border-top: 1px solid var(--border); border-radius: 0 0 12px 12px; }

/* Pay options in modals */
.pay-option {
  border: 1px solid var(--border);
  padding: 0.75rem;
  cursor: pointer;
  text-align: center;
  transition: all 0.15s;
  color: var(--text-muted);
  border-radius: 8px;
  background: var(--card-bg);
}
.pay-option:hover { border-color: var(--accent); }
.pay-option.active {
  border-color: var(--accent);
  background: rgba(99,102,241,0.08);
  color: var(--text);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
}
.pay-option i { font-size: 1.4rem; }
.pay-option span { display: block; font-size: 0.78rem; margin-top: 0.25rem; font-weight: 600; }

/* Session info block in modals */
.session-info-block {
  background: rgba(99,102,241,0.06);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.75rem;
}

/* Form inputs in modals */
body.pos-page .modal .form-control,
body.pos-page .modal input[type="number"],
body.pos-page .modal input[type="text"],
body.pos-page .modal textarea,
body.pos-page .modal select {
  border: 1px solid var(--border);
  border-radius: 6px;
  background: var(--card-bg);
  color: var(--text);
  font-weight: 600;
}
body.pos-page .modal .form-control:focus,
body.pos-page .modal input:focus,
body.pos-page .modal textarea:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px rgba(99,102,241,0.15);
  outline: none;
}

/* Close caisse modal specifics */
.close-summary {
  background: rgba(99,102,241,0.06);
  border: 1px solid var(--border);
  border-radius: 8px;
  padding: 0.75rem;
}
.discrepancy-badge {
  font-weight: 800;
  font-size: 0.95rem;
  padding: 6px 12px;
  text-align: center;
  border: 1px solid var(--border);
  border-radius: 6px;
}
.discrepancy-badge.positive { background: rgba(14,168,126,0.1); color: var(--accent2); border-color: rgba(14,168,126,0.3); }
.discrepancy-badge.negative { background: rgba(220,38,38,0.08); color: var(--danger); border-color: rgba(220,38,38,0.3); }

/* ── RECEIPT MODAL ── */
.receipt-wrapper {
  font-family: 'DM Mono', monospace;
  font-size: 0.75rem;
  background: #fff;
  color: #1a1a1a;
  padding: 0.5rem;
  width: 280px;
  margin: 0 auto;
  border-radius: 8px;
}
.receipt-header { text-align: center; border-bottom: 2px dashed #999; padding-bottom: 0.5rem; margin-bottom: 0.5rem; }
.receipt-row { display: flex; justify-content: space-between; }
.receipt-divider { border-top: 2px dashed #999; margin: 0.4rem 0; }
.receipt-total { font-weight: 900; font-size: 0.9rem; }
.receipt-store-name { font-weight: 900; font-size: 0.85rem; }

/* ── ANIMATIONS ── */
@keyframes flashGreen {
  0% { background: rgba(14,168,126,0.15); }
  100% { background: var(--card-bg); }
}
@keyframes bouncePop {
  0% { transform: scale(1); }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); }
}
.cart-count.bounce { animation: bouncePop 0.25s ease; }
.cart-item.flash { animation: flashGreen 0.35s ease; }

/* Slide-out animation for removed items */
.cart-item.removing {
  transform: translateX(-100%);
  opacity: 0;
  transition: all 0.2s ease;
}

/* ── RESPONSIVE ── */
@media (max-width: 1000px) {
  .pos-layout { grid-template-columns: 1fr; }
  .cart-panel { max-height: 45vh; border-right: none; border-bottom: 1px solid var(--border); }
  .products-grid { grid-template-columns: repeat(2, 1fr); }
}

/* ── PRINT ── */
@media print {
  body.pos-page * { visibility: hidden; }
  #receiptModal { position: absolute; left: 0; top: 0; visibility: visible; }
  #receiptModal .modal-dialog { max-width: 300px; margin: 0 auto; }
  #receiptModal .modal-content { box-shadow: none; border: 1px solid #ccc; }
  #receiptModal .modal-header,
  #receiptModal .modal-footer { display: none !important; }
  #receiptModal .modal-body { padding: 0; }
  #receiptModalBody * { visibility: visible; }
}
```

Key changes: Welcome icon `border-radius: 16px`, caisse cards `border-radius: 10px`, modals `border-radius: 12px`, modal header uses `var(--accent)`, `#fef3c7` backgrounds → `rgba(99,102,241,0.06)` (light accent tint), `#fef3c7` borders → `var(--border)`, hard shadows → soft, receipt wrapper stays white for print but with `border-radius: 8px`, flashGreen animation uses `rgba(14,168,126,0.15)` instead of `#dcfce7`.

- [ ] **Step 2: Commit**

```bash
git add assets/css/pos.css
git commit -m "refactor(pos): theme-adopt welcome, modals, receipt, animations"
```

---

### Task 9: Update pos.php — Remove inline --pos-* styles

**Files:**
- Modify: `views/pos.php`

The PHP file contains inline `style` attributes referencing `--pos-*` variables and hardcoded neobrutalist styles. These need to be removed or replaced since the CSS classes now use theme variables.

- [ ] **Step 1: Update pos.php inline styles**

In `views/pos.php`, make the following changes:

1. Line 47: Change the `$extraHead` to just load pos.css (already correct, keep as-is since the CSS is now theme-aware)

2. Lines 79-82: Remove `style="color:var(--pos-text-muted)"` and `style="color:var(--pos-accent)"` from the caisse modal spinner, since `.modal-body` text now inherits from theme:

Change:
```html
<div style="color:var(--pos-text-muted)">
    <div class="spinner-border spinner-border-sm mb-2" style="color:var(--pos-accent)"></div>
    <div style="font-size:.82rem">Chargement des caisses...</div>
</div>
```
To:
```html
<div class="text-center py-3" style="color:var(--text-muted)">
    <div class="spinner-border spinner-border-sm mb-2" style="color:var(--accent)"></div>
    <div style="font-size:.82rem">Chargement des caisses...</div>
</div>
```

3. Line 85: Change `border-top:2px solid var(--pos-border)` to `border-top:1px solid var(--border)`:
```html
<hr style="border-top:1px solid var(--border)">
```

4. Lines 92-93: Update button styles to remove `border-radius:0` and replace `--pos-*`:
```html
<button class="btn btn-outline-secondary" onclick="POS.resetCaisseSelection()" style="font-weight:600">Changer de caisse</button>
<button id="openCaisseBtn" class="btn btn-primary px-4" onclick="POS.openCaisse()" style="font-weight:800">
```

5. Line 174: Update the mobile provider select inline style:
```html
<select id="mobileProvider" class="d-none" style="flex:1;font-size:.75rem;padding:5px 6px;background:var(--card-bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-weight:600">
```

6. Line 184: Update the credit client select inline style:
```html
<select id="creditClientSelect" style="flex:1;font-size:.75rem;padding:5px 6px;background:var(--card-bg);border:1px solid var(--border);color:var(--text);border-radius:6px;font-weight:600">
```

7. Lines 315-320: Update operations modal inline styles — replace `var(--pos-success)`, `var(--pos-danger)`, `var(--pos-accent)`, `var(--pos-border)`, `2px solid var(--pos-border)`:
```html
<div style="font-size:.82rem;color:var(--text-muted);margin-bottom:.5rem;border-top:1px solid var(--border);padding-top:.5rem"><span>Solde attendu</span><strong id="opsExpected" style="color:var(--accent)">-</strong></div>
```

And similar changes for the other modal inline styles in lines 327-346 — replace all `--pos-*` references with theme variables and remove `border-radius:0`.

8. Lines 368-394: Update close caisse modal — replace `var(--pos-text-muted)`, `var(--pos-border)`, `var(--pos-danger)`, `var(--pos-accent)` inline styles with theme equivalents. Remove `border-radius:0` from buttons.

9. Lines 390-392: Update close caisse confirm button:
```html
<button class="btn btn-danger px-4" id="confirmCloseBtn" onclick="POS.confirmCloseCaisse()" style="font-weight:800">
```

10. Lines 410-413: Update receipt modal buttons:
```html
<button class="btn btn-primary btn-sm" onclick="window.print()" style="font-weight:800">
    <i class="bi bi-printer me-1"></i>Imprimer
</button>
<button class="btn btn-outline-secondary btn-sm" onclick="POS.closeReceipt()" style="font-weight:600">
    <i class="bi bi-x-circle me-1"></i>Fermer
</button>
```

- [ ] **Step 2: Commit**

```bash
git add views/pos.php
git commit -m "refactor(pos): update inline styles to use theme variables"
```

---

### Task 10: Update pos.js — Replace --pos-* references in JS

**Files:**
- Modify: `assets/js/pos.js`

The JS file has several inline style references to `--pos-*` variables and hardcoded neobrutalist styles.

- [ ] **Step 1: Update JS inline style references**

In `assets/js/pos.js`, make these changes:

1. **Line 408** — `filterCat` function: Remove the `borderWidth` manipulation. The active state is now handled purely by CSS class `.active`:
```javascript
function filterCat(catId, btn) {
    currentCategory = parseInt(catId);
    document.querySelectorAll('.cat-chip').forEach(b => {
        b.classList.remove('active');
    });
    btn.classList.add('active');

    const query = document.getElementById('searchInput').value.trim().toLowerCase();
    filterProducts(query, currentCategory);

    if (catId > 0) { setView('grid'); } else { setView('list'); }
}
```

2. **Lines 469-476** — `openCaisseSelection` caisse card HTML: Replace `var(--pos-text-muted)` and `var(--pos-text)` with theme variables:
```javascript
container.innerHTML = '<div class="col-12 text-center py-3" style="color:var(--text-muted)"><i class="bi bi-exclamation-circle" style="font-size:1.5rem;opacity:.3;display:block;margin-bottom:.4rem"></i>Aucune caisse disponible.</div>';
```
And for the caisse cards:
```javascript
'<div style="font-weight:800;font-size:.85rem;color:var(--text)">' + escHtml(c.name) + '</div>' +
```

3. **Lines 519, 533, 539, 579, 596-598, 647, 656-659** — Error message inline styles: Replace all `background:#fee2e2;color:var(--pos-danger);...border:2px solid var(--pos-border)` with theme-aware styles. Create a helper function and use it:

Add this helper after the `escHtml` function (around line 25):
```javascript
function msgStyle(type) {
    if (type === 'error') return 'background:rgba(220,38,38,0.08);color:var(--danger);padding:4px 8px;font-size:.82rem;font-weight:700;border:1px solid rgba(220,38,38,0.3);border-radius:6px';
    return '';
}
```

Then replace all error message inline styles:
- `'background:#fee2e2;color:var(--pos-danger);padding:4px 8px;font-size:.82rem;font-weight:700;border:2px solid var(--pos-border)'` → `msgStyle('error')`

4. **Line 479** — Error loading caisses message: Replace `var(--pos-danger)` with `var(--danger)`:
```javascript
'<div class="col-12 text-center py-3" style="color:var(--danger)">Erreur de chargement.</div>'
```

5. **Line 668** — Z-report error: Same replacement:
```javascript
document.getElementById('receiptModalBody').innerHTML = '<div style="background:rgba(220,38,38,0.08);color:var(--danger);padding:8px;font-weight:700;border-radius:6px">Erreur.</div>';
```

6. **Line 689** — Z-report discrepancy color: Replace hardcoded `#16a34a` and `#dc2626` with `var(--accent2)` and `var(--danger)`:
```javascript
html += '<div class="receipt-row receipt-total"><span>Ecart</span><span style="color:' + (disc >= 0 ? 'var(--accent2)' : 'var(--danger)') + '">' + (disc >= 0 ? '+' : '') + formatMoney(disc) + '</span></div>';
```

- [ ] **Step 2: Commit**

```bash
git add assets/js/pos.js
git commit -m "refactor(pos): update JS inline styles to use theme variables"
```

---

### Task 11: Verify and final cleanup

**Files:**
- Verify: `assets/css/pos.css`, `views/pos.php`, `assets/js/pos.js`

- [ ] **Step 1: Search for remaining --pos-* references**

Run: `grep -rn "pos-" assets/css/pos.css views/pos.php assets/js/pos.js | grep -v "pos-page" | grep -v "pos-layout" | grep -v "pos-dashboard" | grep -v "pos-welcome" | grep -v "pos-"`
Actually, search for `var(--pos` specifically — there should be zero results:
```bash
grep -rn "var(--pos" assets/css/pos.css views/pos.php assets/js/pos.js
```
Expected: No results (all --pos-* variables replaced).

Also search for remaining hardcoded neobrutalist values:
```bash
grep -rn "border-radius: 0" assets/css/pos.css
grep -rn "box-shadow:.*px.*px 0" assets/css/pos.css
grep -rn "#fef3c7\|#fef7ed\|#fffbeb\|#1a1a1a" assets/css/pos.css views/pos.php assets/js/pos.js
```
Expected: No results.

- [ ] **Step 2: Visually test in browser**

Open the POS page in a browser at `http://localhost/Brenshop/` and verify:
1. POS page loads without console errors
2. Cart panel displays correctly with theme colors
3. Product search and category filters work
4. Adding items to cart works
5. Validate sale button is visible and styled
6. Switch theme in settings and reload POS — colors should follow the theme
7. Dark themes (wallstreet, cyberpunk) look correct on POS
8. Modals (caisse selection, operations, close) display correctly
9. Receipt modal displays correctly

- [ ] **Step 3: Final commit**

```bash
git add -A
git commit -m "refactor(pos): complete theme adoption — POS now follows selected theme"
```