# Product

## Register

product

## Users

PharmaCare is a pharmacy management ERP used in Cameroonian pharmacies (currency FCFA/XAF, timezone Africa/Douala, SYSCOHADA accounting). The staff using it day-to-day:

- **Caissiers / Point de Vente**: ring up sales at the counter under time pressure, print thermal tickets, open and close the till. Fast, glanceable, low-error screens are the priority.
- **Pharmaciens / Gérants**: manage the medicine catalog (prix achat / prix vente, stock, expiration dates), monitor stock alerts and ruptures, oversee transfers between magasin (back stock) and pharmacie (front stock).
- **Magasiniers**: receive and adjust stock in the magasin, transfer quantities to the pharmacie, consult printable transfer history.
- **Comptables**: post SYSCOHADA-compliant accounting entries auto-generated from till sales, manage the plan comptable and period closes.
- **Administrateurs**: manage RBAC roles and permissions, users, app parameters (numbering, inactivity logout, fidelity, currency).

Primary job: run a real pharmacy end-to-end, from sale at the counter to stock, back-stock, accounting, and administration, on one system. The screen is usually a counter PC or office desktop in a brightly lit pharmacy, used for long shifts, often while serving a customer.

## Product Purpose

A single PHP/MySQL application that handles point-of-sale, medicine inventory (with separate magasin and pharmacie stock), fournisseurs, commandes, rapports, SYSCOHADA comptabilité, and role-based access control for a pharmacy. It exists so a pharmacy can operate without stitching together separate tools for sales, stock, and accounting, with the links between them automated (caisse sales auto-post accounting entries, magasin transfers track front-stock availability).

Success looks like: a caissier can complete a sale in seconds without confusion, a gérant sees stock alerts before ruptures, a comptable gets coherent SYSCOHADA entries without manual re-entry, and an admin can scope exactly what each role may do.

## Brand Personality

Dark futuristic. Clinical precision, not gamer neon. Calm authority of a tool you trust with medicine and money.

Three words: **precise, calm, dependable.**

Voice: clear and operational. Labels say what will happen (verb + object). Money and quantities are monospaced and exact. Color carries meaning, not decoration, teal for primary value/money, gold for warning/attention, red for rupture/critical, blue for activity, purple for annual totals.

## Anti-references

- **Over-glowy gamer UI**: excessive neon glows, decorative gradients, and effects that hurt readability. Dark futuristic here means disciplined glow in service of focus, not RGB spectacle.
- **Cluttered legacy ERP**: dense 90s-style admin tables with tiny text, no whitespace, and no hierarchy. Data density must not come at the cost of scannability.
- **Toy / bright consumer app**: playful bright colors, cartoonish rounded shapes, non-serious tone. This handles medicine and finance; the surface must feel trustworthy and professional.

## Design Principles

- **Density without clutter.** Show a lot of operational data (stock, prices, entries) but give it room to breathe and a clear hierarchy so the eye lands on what matters first. Never trade scannability for raw density.
- **Disciplined glow.** Dark futuristic means glow and accent used to direct attention and signal state, not as decoration. One glow per focal point; never let effects compete with text.
- **Color carries meaning.** Teal = money/primary value, gold = attention/low stock, red = rupture/critical, blue = activity, purple = annual/summary. Reuse this mapping consistently so color becomes a readout, not styling.
- **Exactness at a glance.** Money, quantities, references, and counts are monospaced and right-aligned where compared. A pharmacy counts pills and FCFA; numbers must line up.
- **Trust through restraint.** This tool handles medicine and money. Restraint reads as trustworthy. Prefer one clear action over two clever ones; prefer a quiet confirmation over a flashy animation.

## Accessibility & Inclusion

Target baseline usability: respect WCAG AA contrast where practical on the dark theme, honor `prefers-reduced-motion` for glow/hover/transition effects, and keep keyboard reachability for primary actions. Full screen-reader and strict AA+ auditing are deferred for now; the focus is workflow correctness and visual clarity first, with a path to harden accessibility later via `/impeccable harden` and `/impeccable audit`.