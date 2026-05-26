# POS Theme Adoption — Design Spec

**Date:** 2026-05-26
**Goal:** Refactor the POS page (`views/pos.php`, `assets/css/pos.css`, `assets/js/pos.js`) to use the dynamic theme system instead of its hardcoded Afro-Brutal style, so the POS visually matches whichever theme the user selects.

## Approach: Full Theme Adoption

The POS page adopts the general theme completely: same colors, same rounded corners, same soft shadows, same border widths. Only the POS-specific layout (grid with cart/catalog panels) and functionality remain unchanged.

## CSS Variable Mapping

All `--pos-*` variables are replaced with general theme variables:

| POS Variable | Replacement | Notes |
|---|---|---|
| `--pos-bg` (#fef7ed) | `var(--body-bg)` | Page background |
| `--pos-bg-alt` (#fffbeb) | `var(--card-bg)` | Cart panel background |
| `--pos-surface` (#ffffff) | `var(--card-bg)` | Card/surface background |
| `--pos-border` (#1a1a1a) | `var(--border)` | Border color |
| `--pos-shadow` (#d97706) | `var(--shadow-md)` or `var(--border)` | Hard shadow → soft shadow |
| `--pos-text` (#1a1a1a) | `var(--text)` | Primary text |
| `--pos-text-muted` (#92400e) | `var(--text-muted)` | Secondary text |
| `--pos-accent` (#d97706) | `var(--accent)` | Primary accent (matches sidebar) |
| `--pos-accent-hot` (#ea580c) | `var(--accent)` (hover lighten) | Accent hover state |
| `--pos-gold` (#fbbf24) | `var(--accent)` or `#fff` | Total badge text |
| `--pos-success` (#16a34a) | `var(--accent2)` | Prices/stock OK (uses secondary accent) |
| `--pos-danger` (#dc2626) | `var(--danger)` | Destructive actions |
| `--pos-info` (#0ea5e9) | `var(--accent)` | Info elements |
| `--pos-border-w` (3px) | `1px` | Thick → standard border width |
| `--pos-shadow-offset` (3px) | removed | Hard offset shadows removed |
| `--pos-radius` (0) | `8px`-`12px` | Sharp corners → rounded |

## Style Transformations

### Borders
- **Before:** `border: 2-3px solid #1a1a1a` (hard black)
- **After:** `border: 1px solid var(--border)` (theme-aware, subtle)

### Border Radius
- **Before:** `border-radius: 0` everywhere
- **After:** Cards/buttons: `8px`, Inputs: `8px`, Modals: `12px`, Badges/pills: `20px`

### Shadows
- **Before:** `box-shadow: 3px 3px 0 #d97706` (hard offset)
- **After:** `box-shadow: var(--shadow-md)` or `0 2px 8px rgba(...)` (soft)

### Hover Effects
- **Before:** `transform: translate(-2px, -2px)` + shadow expansion (neobrutalist lift)
- **After:** `transform: translateY(-1px)` + shadow deepening (subtle lift)

### Press/Active Effects
- **Before:** `transform: translate(2px, 2px)` + shadow removal (neobrutalist press)
- **After:** `transform: translateY(0)` + shadow reduction (subtle press)

### Font Weights
- **Before:** 800-900 (extra bold neobrutalist)
- **After:** 600-800 (bold but readable, matching rest of app)

### Background Colors
- **Before:** Hardcoded amber/cream (#fef7ed, #fef3c7, #fef3c7, #1a1a1a)
- **After:** Theme variables (`var(--body-bg)`, `var(--card-bg)`, etc.)

### Special Cases

1. **Session bar gradient:** Replace `linear-gradient(135deg, #d97706, #ea580c)` with `background: var(--accent)` (solid theme accent color)

2. **Cart totals dark section:** Replace hardcoded `#1a1a1a` background with `var(--card-bg)` with `color: var(--text)`. Use `var(--accent)` for total highlight instead of amber/gold.

3. **Product category colors:** Keep the per-category color system for category chips and grid tile accents, but derive from `--cat-color` inline style (already exists). Category chips use `--cat-color` for text/active background.

4. **Dashboard cards:** Replace `#fef3c7` background with `var(--card-bg)` and accent highlights.

5. **Stock badges:** Keep ok/low/out semantic colors but adjust to work on both light and dark themes.

6. **Change display:** Keep green/red semantics but use `var(--accent2)` and `var(--danger)`.

7. **Receipt modal:** Keep monospace style but neutralize POS-specific overrides. Receipt content is printed, so it stays white background with dark text.

## Files to Modify

1. **`assets/css/pos.css`** — Full rewrite of all CSS rules. Remove `--pos-*` variable definitions. Replace all hardcoded colors/borders/shadows/radii with theme variables. This is the primary file.

2. **`views/pos.php`** — Remove inline `style` attributes that reference `--pos-*` variables. Update PHP-generated inline styles to use theme-aware colors. Remove `pos-page` body class dependency on neobrutalist styles (keep it for layout-only overrides). Remove the `$extraHead` line that loads `pos.css` separately (merge into main theme or keep pos.css but with theme-aware styles).

3. **`assets/js/pos.js`** — Update any JS that references `--pos-*` CSS variables or applies neobrutalist inline styles (e.g., `border-radius: 0`, `box-shadow` manipulations, `--pos-accent` references).

## What Stays the Same

- Grid layout: `grid-template-columns: 380px 1fr`
- Cart panel / catalog panel split
- Mini dashboard with 4 stat cards
- Session bar at top of cart
- Client selector dropdown
- Cart item list with quantity controls
- Totals section (subtotal, discount, TVA, total)
- Payment method selector and amount input
- Product search with barcode support
- Category chip filters
- List view and grid view toggle
- Product tiles with category colors
- All modals (caisse selection, operations, close caisse, receipt)
- All animations (flashGreen, bouncePop, removing slide)
- All JavaScript functionality
- Responsive breakpoint at 1000px

## Dark Theme Considerations

Since themes can be dark (wallstreet, cyberpunk, etc.), the POS must work in both light and dark modes:
- All backgrounds use `var(--body-bg)` / `var(--card-bg)`
- All text uses `var(--text)` / `var(--text-muted)`
- All borders use `var(--border)`
- Stock badges use `var(--accent2)` for OK, `var(--danger)` for out
- Inputs/selects use `var(--card-bg)` background
- Receipt modal stays white-on-dark for print readability