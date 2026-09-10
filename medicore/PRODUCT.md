# Product

## Register

product

## Users

Healthcare professionals in francophone West African hospitals, clinics, and health centers. They use MediCore during active clinical workflows: triage in emergencies, prescribing during consultations, dispensing at the pharmacy, managing beds during rounds, processing billing at the cash desk. Their context is high-stakes, time-pressured, and often interrupted; they need information fast, actions to be obvious, and the system to never lose data. Five roles with distinct needs: Administrateurs (system config, user management), Médecins (clinical decisions, prescriptions), Infirmiers (bed management, medication administration, vital signs), Pharmaciens (stock, dispensing, ordonnances), Comptables (billing, invoices, cash reconciliation).

## Product Purpose

MediCore is a hospital management ERP that replaces paper-based workflows and fragmented spreadsheets with a single system covering 32 clinical and administrative modules. From patient admission through emergency triage, hospitalization, pharmacy dispensing, lab orders, surgery scheduling, and billing, every step lives in one application. Success means: staff spend less time on administrative tasks, clinical data is never lost, stock levels are accurate, billing is complete, and the audit trail is unbroken. The tagline "La santé, simplifiée" captures the core promise: strip complexity from healthcare operations.

## Brand Personality

Confiant. Sûr. Efficace.

The interface should feel like a trusted colleague, not a flashy product. Calm competence over visual excitement. Staff should feel that the system is reliable under pressure, that data is safe, and that every action has a clear outcome. No decoration for its own sake. Confidence comes from clarity and consistency, not from visual flair.

## Anti-references

Generic dark SaaS dashboards with glowing gradients, glassmorphism, neon accent bars on near-black backgrounds, and atmospheric radial gradients. The current MediCore visual language leans heavily into this category. The goal is to move away from the "AI-generated dark theme" look toward something that reads as a professional medical tool, not a crypto dashboard or a SaaS landing page.

## Design Principles

1. **Clarity under pressure.** During a triage or a pharmacy rush, staff cannot afford to parse decorative layers. Every pixel either aids comprehension or gets out of the way. Information density is welcome when it serves the workflow; decoration is not.
2. **Trust through consistency.** The same interaction pattern, the same visual language, the same data display conventions across all 32 modules. Staff transfer learning from one module to the next without relearning patterns.
3. **Action over display.** The interface exists so staff can do things: admit a patient, dispense medication, close a shift. Metric dashboards serve workflow decisions, not vanity. If a number doesn't drive an action, it shouldn't dominate the page.
4. **Resilient by default.** Form submissions don't lose data. Edits are confirmable. Destructive actions warn clearly. The system never leaves the user wondering "did that work?"
5. **Accessible, not optional.** Hospital staff includes people with varying visual abilities, motor control, and situations (gloves, bright wards, noisy environments). WCAG 2.1 AA compliance is the floor, not the ceiling.

## Accessibility & Inclusion

WCAG 2.1 AA target. Critical gaps to address: zero ARIA attributes across the application, no semantic HTML structure (no `<main>`, `<section>`, `<header>`), form labels not programmatically associated with inputs, modals without keyboard support or focus trapping, status indicators relying solely on color, no skip-navigation links. Priority order: keyboard navigation and modal accessibility first, then ARIA and semantic structure, then contrast and color-independent status indicators.