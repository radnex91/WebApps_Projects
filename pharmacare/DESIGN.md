---
name: PharmaCare
description: Dark clinical dashboard for pharmacy management — disciplined glow, vital-sign color coding, exact monospaced readouts.
colors:
  primary: "#22d3ee"
  gold: "#f59e0b"
  red: "#ef4444"
  blue: "#818cf8"
  purple: "#9b59b6"
  neutral-bg: "#09090b"
  neutral-bg-2: "#0f0f13"
  neutral-elevated: "#17171c"
  neutral-surface: "#0c0c10"
  neutral-surface-2: "#1a1a20"
  ink: "#e8edf5"
  ink-muted: "#8b97ab"
  ink-faint: "#5a6678"
typography:
  display:
    fontFamily: "Manrope, sans-serif"
    fontSize: "30px"
    fontWeight: 600
    lineHeight: 1
  headline:
    fontFamily: "Manrope, sans-serif"
    fontSize: "20px"
    fontWeight: 600
    lineHeight: 1.2
  title:
    fontFamily: "Manrope, sans-serif"
    fontSize: "17px"
    fontWeight: 600
    lineHeight: 1.3
  body:
    fontFamily: "Manrope, sans-serif"
    fontSize: "13px"
    fontWeight: 500
    lineHeight: 1.45
  label:
    fontFamily: "Manrope, sans-serif"
    fontSize: "10px"
    fontWeight: 500
    lineHeight: 1
    letterSpacing: "1px"
rounded:
  chip: "6px"
  sm: "8px"
  md: "14px"
  pill: "20px"
spacing:
  xs: "6px"
  sm: "8px"
  md: "14px"
  lg: "20px"
  xl: "26px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "#000000"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
    typography: "{typography.body}"
  button-primary-hover:
    backgroundColor: "{colors.primary}"
    textColor: "#000000"
    rounded: "{rounded.sm}"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.ink-muted}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  button-danger:
    backgroundColor: "rgba(239,68,68,0.10)"
    textColor: "{colors.red}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  button-gold:
    backgroundColor: "rgba(245,158,11,0.10)"
    textColor: "{colors.gold}"
    rounded: "{rounded.sm}"
    padding: "8px 16px"
  input-search:
    backgroundColor: "rgba(255,255,255,0.03)"
    textColor: "{colors.ink}"
    rounded: "{rounded.sm}"
    padding: "7px 13px"
  card:
    backgroundColor: "{colors.neutral-surface}"
    rounded: "{rounded.md}"
    padding: "16px 20px"
  stat-card:
    backgroundColor: "{colors.neutral-surface}"
    rounded: "{rounded.md}"
    padding: "18px 20px"
  badge:
    rounded: "{rounded.chip}"
    padding: "3px 9px"
  nav-item-active:
    backgroundColor: "rgba(34,211,238,0.10)"
    textColor: "{colors.primary}"
    rounded: "{rounded.sm}"
    padding: "7px 12px"
---

# Design System: PharmaCare

## 1. Overview

**Creative North Star: "The Clinical Dashboard"**

PharmaCare is a dark clinical monitoring surface for a pharmacy. The room is bright; the screen is a counter or office PC used for long shifts, often while a customer waits. The interface behaves like a medical instrument panel: a near-black field, vital-sign color coding, and exact monospaced readouts. Every glow is an indicator light, not decoration; every color says something specific about state.

Density is high — stock levels, prices, accounting entries, sales lines — but it never tips into the cluttered legacy ERP that PRODUCT.md rejects. Hierarchy and whitespace keep the eye on the next action. The surface is futuristic only insofar as "futuristic" means precise and legible under pressure; it explicitly rejects the over-glowy gamer UI (RGB spectacle, decorative gradients, glow that competes with text) and the toy/bright consumer app (playful colors, cartoonish shapes). This is a tool you trust with medicine and money.

The aesthetic is one well-tuned family, Manrope, in weight contrast, with DM Mono carrying every number and reference so money and quantities line up exactly. Restraint reads as trustworthiness: one glow per focal point, one clear action per moment.

**Key Characteristics:**
- Near-black instrument field (`#09090b`) with tonal surface layering, not flat black.
- Five-color vital-sign palette where each hue is a fixed semantic readout, never styling.
- Disciplined glow as state indicator, capped at one focal point per region.
- Monospaced numbers and references; everything you count lines up.
- Tactile micro-interactions: a 1px lift on hover, a soft focus glow on inputs. Reduced motion is honored.
- Single typeface (Manrope) across display and body, with DM Mono reserved for data.

## 2. Colors: The Vital-Sign Palette

The palette is a fixed semantic mapping. Each color is a readout, not a mood. Reuse the mapping consistently so staff read color as state at a glance.

### Primary
- **Counter Teal** (`#22d3ee`): primary value, money, the active action, focus. The "go" color. Used for primary buttons, the active nav item, sale totals, focus rings, and the top stripe of the revenue stat card. Carries the system's one permitted glow.

### Secondary
- **Apothecary Gold** (`#f59e0b`): attention and low stock. The "watch this" color. Magasin alerts, low-stock badges, the gold/gold-dim button variant, and the stock-value stat card. Never used for primary action — it signals caution, not confirmation.

### Tertiary
- **Rupture Red** (`#ef4444`): critical and destructive. Out-of-stock, rupture badges, delete actions, error states, and the alerts stat card. Always paired with the red-dim tint background on chips, never used as a large fill.
- **Ledger Blue** (`#818cf8`): activity and neutral-positive flow. Today's transactions, info toasts, the ventes-aujourd'hui stat card, and informational badges. The calm operational color.
- **Fiscal Violet** (`#9b59b6`): annual and summary. Year-to-date totals, the CA-année stat card, accounting summary accents. The bookkeeping color, used sparingly.

### Neutral
- **Instrument Black** (`#09090b`): the page field. Never pure `#000`; the slight lift keeps surfaces legible.
- **Console Panel** (`#0f0f13`): sidebar and topbar background — the chrome layer.
- **Surface** (`#0c0c10`): cards, modals, stat cards — the resting content surface.
- **Surface Raised** (`#1a1a20`): product tiles and raised elements within surfaces.
- **Recess** (`#17171c`): scrollbars and inset tracks.
- **Ink** (`#e8edf5`): primary text, always ≥4.5:1 on surfaces.
- **Ink Muted** (`#8b97ab`): secondary text and table body secondary. Use only at ≥14px or where it sits on a surface dark enough to clear AA — verify, do not assume.
- **Ink Faint** (`#5a6678`): labels, table headers, tertiary metadata, placeholders. Reserved for small uppercase labels and non-essential hints; never body copy.
- **Hairline** (`rgba(255,255,255,.05)`) / **Hairline Strong** (`rgba(255,255,255,.08)`): borders and dividers, expressed as white alpha over the dark field so they read as structural, not chromatic.

### Named Rules
**The Vital-Sign Rule.** Each hue means one thing. Counter Teal = money/active, Apothecary Gold = attention, Rupture Red = critical, Ledger Blue = activity, Fiscal Violet = annual. Never recolor a component for variety; if you need a new state, add a semantic role, not a new shade.

**The One Glow Rule.** Counter Teal carries the system's glow on primary actions and focus. Accents (gold, red, blue, purple) may use their own dim glow only on the single element signaling that state — a low-stock badge, a rupture pill, a toast icon. Never let two glows compete in the same region.

**The Tint-on-Dark Rule.** Accent chips and buttons render as a dim tint of the accent (≈10% alpha) with the full-strength accent as text and a low-alpha accent border — e.g. `.badge-gold` is `gold-dim` fill, `gold` text, `gold-glow` border. Never fill a chip with the full-strength accent on the dark field; it screams.

## 3. Typography

**Display Font:** Manrope (self-hosted, no CDN)
**Body Font:** Manrope (same family, weight contrast carries hierarchy)
**Label/Mono Font:** DM Mono (self-hosted) — references, money, quantities

**Character:** A single geometric humanist sans, tuned across weight, does all the talking. Manrope's clarity at small sizes makes it readable on a busy counter screen; DM Mono ensures every FCFA figure and reference lines up so the eye can compare without re-reading. No serif, no second sans — that would read as indecision on a tool.

### Hierarchy
- **Display** (Manrope 600, 30px, line-height 1): stat-card values — the big numbers (CA, counts). The loudest type on the page, used only for single readouts.
- **Headline** (Manrope 600, 19–20px, line-height 1.2): topbar title and modal titles. One per screen / dialog.
- **Title** (Manrope 600, 17px, line-height 1.3): card titles. Section anchors within a surface.
- **Body** (Manrope 500, 13–13.5px, line-height 1.45): default reading size for table cells, form inputs, button labels, prose. Cap line length at ~65–75ch where it runs as prose.
- **Label** (Manrope 500, 10px, letter-spacing 1px, uppercase): stat labels, form labels, table headers, nav sections. The tracked uppercase eyebrow — used here only as a genuine label system, never as decorative section kickers.
- **Mono** (DM Mono 500, 12px): references, money, quantities. `.td-mono`, `.p-price-cell`, `.fw-mono`. Right-align or tabular-align anything compared.

### Named Rules
**The Numbers-Line-Up Rule.** Every money value, quantity, and reference is DM Mono. If two numbers sit in the same column, they must share the same monospaced font so the digits align vertically. Never set money in proportional Manrope.

**The Label-Only Uppercase Rule.** Tracked uppercase is reserved for 10px labels (stat labels, form labels, table headers, nav sections). Never set a sentence, a heading, or body copy in uppercase.

## 4. Elevation

PharmaCare uses a **hybrid: tonal layering by default, shadows as state response.** Resting depth comes from the neutral ramp — Instrument Black field, Console Panel chrome, Surface cards, Surface Raised tiles — so cards read as elevated by value, not by shadow. Shadows appear only when an element responds: a card lifts on hover, a modal lands, a toast drops in.

### Shadow Vocabulary
- **Resting hairline:** `1px solid rgba(255,255,255,.05)` (border) — every surface's default edge. Depth without shadow.
- **Hover lift:** `box-shadow: 0 2px 12px rgba(0,0,0,.35)` (`--shadow-sm`) — cards and stat cards gain this on hover alongside a 1–3px `translateY` lift.
- **Modal landing:** `box-shadow: 0 8px 32px rgba(0,0,0,.45)` (`--shadow`) — modals and elevated overlays.
- **Indicator glow:** `0 0 16px rgba(34,211,238,.25)` (teal-glow) — the focus/active glow. Gold/red/blue carry 12px variants at their own alpha. This is light, not shadow; treat it as an indicator lamp.

### Named Rules
**The Flat-By-Default Rule.** Surfaces are flat at rest, separated by hairline borders and the neutral ramp. Shadows appear only as a response to state (hover, modal landing, toast). Never add an ambient shadow to a resting card.

## 5. Components

### Buttons
- **Shape:** 8px radius (`--radius-sm`), inline-flex, 13px Manrope 500, 8px 16px padding. A subtle 135° white-alpha sheen fades in on hover (`::before` gradient).
- **Primary:** Counter Teal fill, black text, 600 weight, `0 0 16px teal-glow`. Hover lifts 1px and deepens the shadow. The confirm action. One per region.
- **Ghost:** transparent, Ink Muted text, Hairline Strong border, 4px backdrop blur. Hover → glass fill, Ink text, Counter Teal border. Used for secondary actions ("Annuler", "Voir tout", icon buttons).
- **Danger:** red-dim tint fill, Rupture Red text, red-glow border. Hover → red-glow fill with 12px red glow. Destructive actions only.
- **Gold:** gold-dim fill, Apothecary Gold text. Cautionary non-destructive actions.
- **Sizes:** `btn-sm` 5px 11px / 12px; `btn-xs` 3px 8px / 11px — for in-table icon actions and tight toolbars.
- **Hover/Focus:** tactile — `translateY(-1px)` lift on primary, glow deepening. Reduced motion drops the lift to a color change.

### Chips / Badges
- **Style:** 6px radius, 3px 9px padding, 11px, 500 weight, no whitespace wrap. Tint-on-Dark Rule: `<accent>-dim` fill, full accent text, low-alpha accent border.
- **States:** `badge-green` (Counter Teal = Disponible), `badge-gold` (Apothecary Gold = Stock bas), `badge-red` (Rupture Red = Rupture), `badge-blue`, `badge-purple`, `badge-gray` (neutral category tags). Color maps to status, never to decoration.
- **Nav badge:** pill (`20px` radius), red fill with red-glow, or `nav-badge-gold` (gold fill, dark text) for magasin alerts.

### Cards / Containers
- **Corner:** 14px radius (`--radius`), overflow hidden.
- **Background:** Surface (`#0c0c10`), 8px backdrop blur, hairline border → Hairline Strong on hover.
- **Header:** 16px 20px 13px, bottom hairline, flex between title and actions.
- **Padding:** `card-pad` 20px; modals 22px; stat cards 18px 20px.
- **Stat card:** 2px top accent stripe in the card's semantic gradient (teal/gold/red/blue/purple) with a matching 12px glow — the one place color appears as a stripe, because it encodes the card's metric family.

### Inputs / Fields
- **Style:** glass fill (`rgba(255,255,255,.03)`), Hairline Strong border, 8px radius, 9px 13px padding, 13.5px Manrope, 4px backdrop blur.
- **Focus:** border → Counter Teal, `0 0 12px teal-glow`. The single focus signal.
- **Search box:** glass pill with a leading icon, same focus treatment; widens to `min-width:420px;max-width:640px` on list pages.
- **Numeric inputs:** spinners hidden — fields are numeric but unstyled-arrows for clean entry of FCFA and quantities.
- **Error:** `.form-error` in Rupture Red, 11px.

### Navigation
- **Sidebar:** 240px fixed, Console Panel bg, hairline right border, 12px backdrop blur. Logo mark in Counter Teal with a teal-dim tile.
- **Item:** 7px 12px, 8px radius, Ink Muted text, with a 30px circular nav-icon that holds a radial-gradient halo on hover/active. Active state: teal-dim fill, Counter Teal text, 2px left teal bar with 8px glow. Hover: glass fill, icon scales 1.1.
- **Icons:** hospital/pharma SVGs (pulse, bag, cash-register, boxes, capsule, truck, warehouse, calculator, shield, stethoscope). Icon color tints via `.i-teal/.i-gold/...` classes.
- **Topbar:** 58px, Console Panel bg, 26px padding. Title in 20px Manrope 600 with a `linear-gradient(135deg, ink, teal)` clipped fill — the one permitted gradient-text exception, reserved for the app title only.

### Toast / Flash
- **Style:** PS5-style card, `rgba(20,20,30,.90)` + 24px blur saturate(1.5), 12px radius, bottom-right, 300–420px. Circular icon tile in the toast's semantic accent with matching glow; 3px progress bar at the bottom in the accent color.
- **Motion:** toast-in `.4s cubic-bezier(.16,1,.3,1)` (translateY 40px + scale .92→1); toast-out `.28s cubic-bezier(.4,0,1,1)`.

### Modal
- **Style:** centered overlay `rgba(0,0,0,.65)` + 6px blur, 14px radius card, 16px backdrop blur, modal-landing shadow. Default 580px, `modal-lg` 780px, permission modals 920px.
- **Motion:** slide-up `.22s ease` (translateY 18px → 0, opacity 0→1).

## 6. Do's and Don'ts

### Do:
- **Do** use Counter Teal for exactly one primary action per region and let it carry the only strong glow.
- **Do** render accent chips as a 10% tint fill with full-strength accent text and a low-alpha accent border (Tint-on-Dark Rule).
- **Do** set every money value, quantity, and reference in DM Mono so digits line up (Numbers-Line-Up Rule).
- **Do** convey resting depth with the neutral ramp and hairline borders; reserve shadows for hover, modal landing, and toasts (Flat-By-Default Rule).
- **Do** honor `prefers-reduced-motion`: drop the `translateY` lift to a color/border change, keep the focus glow.
- **Do** map each hue to its fixed semantic role and reuse it everywhere that role appears (Vital-Sign Rule).
- **Do** keep tracked uppercase to 10px labels only — stat labels, form labels, table headers, nav sections.

### Don't:
- **Don't** let two glows compete in the same region — one focal glow, then dim the rest (One Glow Rule). This is the line between "clinical dashboard" and the over-glowy gamer UI that PRODUCT.md rejects.
- **Don't** fill chips, badges, or non-primary buttons with full-strength accent on the dark field — always the dim tint.
- **Don't** set body copy, headings, or sentences in uppercase or in gradient text. The topbar-title gradient is the single exception.
- **Don't** use a `border-left` greater than 1px as a colored stripe on cards or callouts. The nav-item active bar and the stat-card top stripe are the sanctioned exceptions; everywhere else use full borders or tints.
- **Don't** build the dense 90s-style ERP table: no tiny text, no zero whitespace, no flat hierarchy. Density comes with hierarchy and breathing room, or it doesn't come.
- **Don't** reach for bright playful colors, cartoonish rounding, or decorative gradients — the surface must feel like a tool you trust with medicine and money, not a toy consumer app.
- **Don't** set Ink Muted (`#8b97ab`) or Ink Faint (`#5a6678`) as body copy without verifying ≥4.5:1 contrast; they are for secondary metadata and labels, not reading text.
- **Don't** add ambient shadows to resting cards, or introduce a second display typeface. One family, weight contrast.