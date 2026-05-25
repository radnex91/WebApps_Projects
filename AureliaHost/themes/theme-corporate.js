/* ============================================================
   THEME 1 — CORPORATE ÉLÉGANT
   Style luxe hôtelier : bleu marine profond, accents dorés
   ============================================================ */

export const corporateTheme = {
  id: 'corporate',
  name: 'Corporate Élégant',
  description: 'Bleu marine profond et accents dorés pour un style luxe intemporel.',

  colors: {
    /* === Fondations === */
    primary: {
      50:  '#eef2f8',
      100: '#d4dff0',
      200: '#a9bfe1',
      300: '#7e9fd2',
      400: '#537fc3',
      500: '#1e3a5f', // ★ Brand primary — marine profond
      600: '#18304f',
      700: '#12263f',
      800: '#0c1c2f',
      900: '#06121f',
      950: '#030912',
    },
    secondary: {
      50:  '#fdf8ed',
      100: '#fbf0d5',
      200: '#f7e1ab',
      300: '#f3d281',
      400: '#efc357',
      500: '#c8a44e', // ★ Gold accent
      600: '#a5833e',
      700: '#82622e',
      800: '#5f411e',
      900: '#3c200e',
    },
    accent: {
      DEFAULT: '#c8a44e', // Gold
      hover:   '#d9b85f',
      muted:   'rgba(200,164,78,0.12)',
    },

    /* === Surfaces === */
    surface: {
      background: '#f5f6f8',
      card:       '#ffffff',
      elevated:   '#fafbfc',
      sidebar:    '#0f1f33',
      input:      '#f1f3f6',
    },

    /* === Texte === */
    text: {
      primary:   '#0c1c2f',
      secondary: '#556678',
      muted:     '#8a98a8',
      inverse:   '#ffffff',
      link:      '#2d6db5',
    },

    /* === Statuts === */
    status: {
      success:  { bg: '#ecfdf5', text: '#065f46', dot: '#10b981' },
      warning:  { bg: '#fffbeb', text: '#92400e', dot: '#f59e0b' },
      error:    { bg: '#fef2f2', text: '#991b1b', dot: '#ef4444' },
      info:     { bg: '#eff6ff', text: '#1e40af', dot: '#3b82f6' },
      neutral:  { bg: '#f8fafc', text: '#475569', dot: '#94a3b8' },
    },

    /* === Bordures === */
    border: {
      DEFAULT: '#e2e6ec',
      light:   '#eef1f5',
      focus:   '#1e3a5f',
    },
  },

  /* === Typographie === */
  typography: {
    fontFamily: {
      heading: ['Playfair Display', 'Georgia', 'serif'],
      body:    ['Inter', 'system-ui', 'sans-serif'],
      mono:    ['JetBrains Mono', 'monospace'],
    },
    fontScale: {
      xs:   '0.75rem',
      sm:   '0.8125rem',
      base: '0.9375rem',
      lg:   '1.0625rem',
      xl:   '1.25rem',
      '2xl': '1.5rem',
      '3xl': '1.875rem',
      '4xl': '2.25rem',
    },
    fontWeight: {
      heading: '700',
      body:    '400',
      medium:  '500',
      semibold:'600',
    },
    googleFonts:
      'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap',
  },

  /* === Effets === */
  effects: {
    radius: {
      sm: '6px',
      md: '10px',
      lg: '14px',
      xl: '20px',
    },
    shadow: {
      card:  '0 1px 3px rgba(12,28,47,0.06), 0 1px 2px rgba(12,28,47,0.04)',
      elevate: '0 4px 16px rgba(12,28,47,0.08), 0 2px 4px rgba(12,28,47,0.04)',
      modal: '0 20px 60px rgba(12,28,47,0.18)',
      glow:  '0 0 20px rgba(200,164,78,0.25)',
    },
    transition: '200ms cubic-bezier(0.4, 0, 0.2, 1)',
  },

  /* === Tailwind Config Override === */
  tailwind: {
    extend: {
      colors: {
        brand:    '#1e3a5f',
        gold:     '#c8a44e',
        surface:  '#f5f6f8',
        sidebar:  '#0f1f33',
      },
      fontFamily: {
        heading: ['Playfair Display', 'Georgia', 'serif'],
        body:    ['Inter', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        card: '10px',
        btn:  '8px',
      },
    },
  },
};

/* ===== CSS Variables (drop-in) ===== */
export const corporateCSS = `
:root[data-theme="corporate"] {
  --color-primary: #1e3a5f;
  --color-primary-light: #2d5a8e;
  --color-secondary: #c8a44e;
  --color-secondary-light: #d9b85f;
  --color-accent: #c8a44e;

  --bg-surface: #f5f6f8;
  --bg-card: #ffffff;
  --bg-elevated: #fafbfc;
  --bg-sidebar: #0f1f33;
  --bg-input: #f1f3f6;

  --text-primary: #0c1c2f;
  --text-secondary: #556678;
  --text-muted: #8a98a8;

  --status-success-bg: #ecfdf5;
  --status-success-text: #065f46;
  --status-warning-bg: #fffbeb;
  --status-warning-text: #92400e;
  --status-error-bg: #fef2f2;
  --status-error-text: #991b1b;
  --status-info-bg: #eff6ff;
  --status-info-text: #1e40af;

  --border-color: #e2e6ec;
  --border-light: #eef1f5;

  --font-heading: 'Playfair Display', Georgia, serif;
  --font-body: 'Inter', system-ui, sans-serif;

  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 14px;

  --shadow-card: 0 1px 3px rgba(12,28,47,0.06);
  --shadow-elevate: 0 4px 16px rgba(12,28,47,0.08);
  --shadow-modal: 0 20px 60px rgba(12,28,47,0.18);
}
`;
