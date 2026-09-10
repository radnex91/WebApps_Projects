# POS Redesign — Afro-Brutal Neobrutaliste

**Date:** 2026-05-25
**Status:** Approved

## Overview

Complete redesign of `views/pos.php` (1680 lines) into an ultra-modern Afro-Brutal neobrutalist POS interface. The Cameroonian identity drives the visual language: amber/orange palette, warm backgrounds, hard shadows, thick borders, zero border-radius.

## User Choices

- **Style:** Neobrutalist + Coloré (Afro-Brutal variant)
- **Layout:** 2 columns (cart left 380px, catalog right 1fr)
- **Architecture:** Separate CSS + JS + PHP files
- **New features:** Improved barcode scanning + Mini daily sales dashboard

## Architecture

### File Structure

| File | Role | Est. Lines |
|---|---|---|
| `views/pos.php` | PHP only — data, conditionals, HTML structure | ~250 |
| `assets/css/pos.css` | All Afro-Brutal CSS | ~400 |
| `assets/js/pos.js` | Full logic (cart, filters, sale, caisse, scan) | ~600 |

**Loading in pos.php:**
```php
$extraHead = '<link rel="stylesheet" href="' . BASE_URL . '/assets/css/pos.css">';
$extraScript = '<script src="' . BASE_URL . '/assets/js/pos.js"></script>';
```

No build step. No bundler. Plain files loaded via `$extraHead` / `$extraScript`.

## Design Tokens

```css
:root {
  --pos-bg: #fef7ed;          /* warm main background */
  --pos-bg-alt: #fffbeb;      /* cart panel background */
  --pos-surface: #ffffff;     /* cards, inputs */
  --pos-border: #1a1a1a;     /* thick borders */
  --pos-shadow: #d97706;     /* offset shadows (amber) */
  --pos-text: #1a1a1a;       /* primary text */
  --pos-text-muted: #92400e; /* secondary text */
  --pos-accent: #d97706;     /* primary accent (amber) */
  --pos-accent-hot: #ea580c; /* secondary accent (orange) */
  --pos-gold: #fbbf24;       /* totals, highlights */
  --pos-success: #16a34a;    /* stock ok, sale validated */
  --pos-danger: #dc2626;     /* out of stock, errors */
  --pos-info: #0ea5e9;       /* info, links */
  --pos-border-w: 3px;       /* border thickness */
  --pos-shadow-offset: 3px;  /* hard shadow offset */
  --pos-radius: 0;           /* no border-radius (neobrutalist) */
}
```

Category colors map to shadow colors on product cards (each category gets a unique color from the existing `$colorPalette`).

## Layout

```
┌──────────────────────────────────────────────────────┐
│ TOPBAR: Caisse active + Mini Dashboard (4 cards)     │
├────────────────┬─────────────────────────────────────┤
│  CART (380px)  │  CATALOG (1fr)                       │
│  bg: #fffbeb   │  bg: #fef7ed                         │
│                 │                                       │
│  - Client       │  Toolbar:                            │
│  - Items        │   Search + Warehouse + View toggle   │
│  - Totals       │   Category chips                    │
│  - Payment      │                                       │
│  - Validate btn │  Product grid / list                 │
│                 │                                       │
└────────────────┴─────────────────────────────────────┘
```

- No border-radius anywhere
- Borders: 3px solid #1a1a1a
- Box-shadow: 3px 3px 0 var(--pos-shadow) on cards
- Mini dashboard: horizontal bar between global topbar and 2-column area

## Components

### Mini Dashboard (top bar)

4 stat cards in a row:

| Card | Data | Source |
|---|---|---|
| Ventes | Completed sales count today | New AJAX endpoint |
| Revenu | Total sales revenue in FCFA | New AJAX endpoint |
| Panier moyen | Revenue / sales count | Server-calculated |
| Articles | Total items sold today | New AJAX endpoint |

- Each card: border 3px, background `#fef3c7`, bold numbers 1.2rem
- Labels: uppercase 0.65rem, color `#92400e`
- Loaded on POS start (if caisse open)
- Auto-refresh every 60s
- Refresh after each sale validation
- Display "—" for 0-value metrics (not "0")

### Cart Panel (left column)

**Header:**
- "PANIER" bold + count badge (chip style: border 2px, black bg, white text)
- Client dropdown (neobrutal: border 2px, white bg)
- Trash icon button (border 2px, red-tinted bg)

**Cart item:**
- Border 2px solid, white bg, `box-shadow: 2px 2px 0 #d97706`
- +/- buttons: square, border 2px, bg `#fef3c7`, hover bg `#d97706` + white text
- Price in bold, color `#d97706`
- Flash animation (green → normal) on new item add

**Totals area:**
- Background `#1a1a1a`, text `#fef3c7`
- TOTAL in gold `#fbbf24`, size 1.1rem, font-weight 900
- Discount input: border 2px, width 40px, centered

**Payment area:**
- Method select: border 3px, white bg
- Amount input: border 3px, white bg, large font
- Change display: green chip (positive) or red chip (negative)
- Mobile Money provider select appears conditionally

**Validate button:**
- Background `#d97706`, white text, font-weight 900
- `box-shadow: 4px 4px 0 #1a1a1a`
- Hover: `translate(-2px, -2px)`, shadow grows to 6px
- Disabled: opacity 0.4, no shadow

### Catalog Panel (right column)

**Toolbar:**
- Search input: border 3px, white bg, `box-shadow: 3px 3px 0 #d97706`
- Warehouse select: border 2px, white bg
- View toggle: 2 square buttons, border 2px, adjacent

**Category chips:**
- "Tout" chip: bg `#d97706`, white text
- Other chips: white bg, border 2px
- Active chip: category-color bg + white text + border 3px
- Horizontal scroll if overflow

**Product grid (default when category selected):**
- Card: border 2px, white bg, `box-shadow: 3px 3px 0 {cat-color}`
- Category label top, color = category color
- Product name bold
- Price bold, color `#16a34a`
- Stock badge: border 1px, colored bg (green/yellow/red)
- Out of stock: opacity 0.4, grayscale, no shadow

**Product list (compact view):**
- HTML table with clickable rows
- Columns: image | name+barcode | stock | price
- Hover: `box-shadow: inset 3px 0 0 #d97706`
- Out of stock: strikethrough + opacity

## New Features

### Improved Barcode Scanning

**Current:** Rapid keystroke detection in search input, exact match on Enter.

**New behavior:**
- Scanner detection: if keystrokes arrive < 50ms apart → scanner, not human typing
- On scan detected:
  1. Input flashes green border `#16a34a`
  2. Product auto-added to cart
  3. Success toast "Riz Basmati ajouté" (green)
  4. Input cleared, ready for next scan
- Out of stock → warning toast "Rupture: Riz Basmati" (red)
- Barcode not found → info toast "Code non trouvé: 123456789"
- Scan works even with active category filter (searches all products via `data-ref`)

### Daily Stats Dashboard

**New AJAX endpoint:** `sale_controller.php?action=daily_stats&store_id=X`

Returns JSON:
```json
{
  "success": true,
  "data": {
    "sales_count": 12,
    "revenue": 145000,
    "avg_basket": 12083,
    "items_sold": 34
  }
}
```

- Loaded on POS start (if caisse session is open)
- Auto-refresh every 60s via `setInterval`
- Explicit refresh after each sale validation (`fetchDailyStats()`)
- 0 values display as "—" for sales_count and avg_basket

## Modals (Neobrutalist Style)

All modals share:
- Border: 3px solid #1a1a1a on `.modal-content`
- Box-shadow: 6px 6px 0 #d97706
- No border-radius
- White body, black header
- Buttons: hard shadow, hover translate

### Caisse Opening Modal
- Caisse cards: border 3px, `box-shadow: 4px 4px 0 #d97706`
- Selected card: bg `#fef3c7` + border `#d97706` + larger shadow
- Opening balance input: border 3px, monospace font
- "Open" button: bg `#d97706`, white text, hard shadow

### Caisse Operations Modal
- 2 side-by-side cards: Deposit (green tint) / Withdrawal (red tint)
- Active card: border 3px color + tinted bg
- Amount input: border 3px, monospace
- Reason input: border 2px
- Session summary: bg `#fef3c7`, border 2px

### Caisse Close Modal
- Full summary (opening, sales, deposits, withdrawals, expected)
- Counted amount input: border 3px, large
- Discrepancy displayed live: green chip (+) or red chip (-)
- "Close" button: bg `#dc2626`, white text, hard shadow

### Receipt / Z-Report Modal
- Thermal ticket style: white bg, DM Mono font, fixed width 280px
- Dashed borders for separators
- Print: `@media print` preserves same style

## Animations

| Interaction | Animation |
|---|---|
| Add to cart | Green flash on cart item + count badge bounce |
| Hover product card | `translateY(-2px)` + shadow 3px→5px |
| Hover validate button | `translate(-2px, -2px)` + shadow 4→6px |
| Click button | `translate(2px, 2px)` + shadow disappears (press effect) |
| Remove cart item | Slide-out left + fade |
| Toast notification | Slide-in from right + thick border |
| Stock badge change | Pop scale 1.2→1.0 |
| Category chip click | Border 2→3px + micro vibration |

All transitions: `transition: all 0.12s ease` (fast and snappy).

## Responsive

- **> 1000px:** 2 columns (380px | 1fr)
- **<= 1000px:** 1 column. Cart on top (max-height 45vh, scrollable), catalog below
- Mobile: product grid → 2 columns instead of auto-fill

## Cart Persistence

Same as current: `localStorage` with key `pos_cart_{storeId}_{warehouseId}`. No change needed.

## Existing Backend Dependencies

No changes to:
- `controllers/sale_controller.php` (sale creation endpoint)
- `controllers/caisse_session_ajax.php` (all caisse session endpoints)
- `models/Sale.php`, `models/CaisseSession.php`, `models/CaisseSession.php`

New endpoint needed:
- `sale_controller.php?action=daily_stats` (GET, returns JSON with sales_count, revenue, avg_basket, items_sold for today)

## Theming Integration

The cart panel currently uses hardcoded dark colors that ignore the 9-theme system. In the redesign, the POS uses its own `--pos-*` tokens which are independent of the global theme system. This is intentional — the POS has its own visual identity. The global sidebar/topbar from `layout_top.php` still respects themes; only the POS inner area uses Afro-Brutal tokens.