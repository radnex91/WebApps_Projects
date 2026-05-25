/* ============================================================
   THEME 2 — MINIMALISTE MODERNE
   Style boutique hotel : neutres chauds, vert sauge
   ============================================================ */

export const minimalTheme = {
  id: 'minimal',
  name: 'Minimaliste Moderne',
  description: 'Tons neutres chauds et vert sauge pour un style boutique épuré.',

  colors: {
    /* === Fondations === */
    primary: {
      50:  '#f6f8f5',
      100: '#e8ede4',
      200: '#d1dbca',
      300: '#bac9b0',
      400: '#a3b796',
      500: '#7c9086', // ★ Vert sauge
      600: '#64776d',
      700: '#4c5e54',
      800: '#34453b',
      900: '#1c2c22',
    },
    secondary: {
      50:  '#faf9f7',
      100: '#f2f0ec',
      200: '#e5e5d9',
      300: '#d5cfc0',
      400: '#c5b9a7',
      500: '#a69888', // ★ Beige chaud
      600: '#8b7d6e',
      700: '#6d6254',
      800: '#4f473a',
      900: '#312c20',
    },
    accent: {
      DEFAULT: '#d4a574', // Terracotta doux
      hover:   '#c08b5c',
      muted:   'rgba(212,165,116,0.15)',
    },

    /* === Surfaces === */
    surface: {
      background: '#faf9f7',
      card:       '#ffffff',
      elevated:   '#fdfcfb',
      sidebar:    '#f5f3ef',
      input:      '#f7f6f3',
    },

    /* === Texte === */
    text: {
      primary:   '#2c2822',
      secondary: '#6b6560',
      muted:     '#9c9690',
      inverse:   '#ffffff',
      link:      '#5a7d6a',
    },

    /* === Statuts === */
    status: {
      success:  { bg: '#f2f7f3', text: '#3d5a45', dot: '#6ba87a' },
      warning:  { bg: '#fef9f0', text: '#8b5e2f', dot: '#e5a85c' },
      error:    { bg: '#fdf5f4', text: '#8b3d3a', dot: '#d4726e' },
      info:     { bg: '#f2f5f8', text: '#3a5070', dot: '#6e9cc4' },
      neutral:  { bg: '#f7f6f4', text: '#5c5854', dot: '#b0aaa4' },
    },

    /* === Bordures === */
    border: {
      DEFAULT: '#e5e2dc',
      light:   '#f0edeb',
      focus:   '#7c9086',
    },
  },

  /* === Typographie === */
  typography: {
    fontFamily: {
      heading: ['DM Serif Display', 'Georgia', 'serif'],
      body:    ['Work Sans', 'system-ui', 'sans-serif'],
      mono:    ['Source Code Pro', 'monospace'],
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
      heading: '600',
      body:    '400',
      medium:  '500',
      semibold:'600',
    },
    googleFonts:
      'https://fonts.googleapis.com/css2?family=DM+Serif+Display&family=Work+Sans:wght@300;400;500;600&display=swap',
  },

  /* === Effets === */
  effects: {
    radius: {
      sm: '4px',
      md: '8px',
      lg: '12px',
      xl: '16px',
    },
    shadow: {
      card:    '0 1px 2px rgba(44,40,34,0.04)',
      elevate: '0 2px 12px rgba(44,40,34,0.06)',
      modal:   '0 16px 48px rgba(44,40,34,0.12)',
    },
    transition: '220ms cubic-bezier(0.25, 0, 0.25, 1)',
  },

  /* === Tailwind Config Override === */
  tailwind: {
    extend: {
      colors: {
        brand:   '#7c9086',
        sand:    '#a69888',
        accent:  '#d4a574',
        surface: '#faf9f7',
        sidebar: '#f5f3ef',
      },
      fontFamily: {
        heading: ['DM Serif Display', 'Georgia', 'serif'],
        body:    ['Work Sans', 'system-ui', 'sans-serif'],
      },
      borderRadius: {
        card: '8px',
        btn:  '6px',
      },
    },
  },
};

/* ===== CSS Variables (drop-in) ===== */
export const minimalCSS = `
:root[data-theme="minimal"] {
  --color-primary: #7c9086;
  --color-primary-light: #9aada4;
  --color-secondary: #a69888;
  --color-secondary-light: #bdb1a4;
  --color-accent: #d4a574;

  --bg-surface: #faf9f7;
  --bg-card: #ffffff;
  --bg-elevated: #fdfcfb;
  --bg-sidebar: #f5f3ef;
  --bg-input: #f7f6f3;

  --text-primary: #2c2822;
  --text-secondary: #6b6560;
  --text-muted: #9c9690;

  --status-success-bg: #f2f7f3;
  --status-success-text: #3d5a45;
  --status-warning-bg: #fef9f0;
  --status-warning-text: #8b5e2f;
  --status-error-bg: #fdf5f4;
  --status-error-text: #8b3d3a;
  --status-info-bg: #f2f5f8;
  --status-info-text: #3a5070;

  --border-color: #e5e2dc;
  --border-light: #f0edeb;

  --font-heading: 'DM Serif Display', Georgia, serif;
  --font-body: 'Work Sans', system-ui, sans-serif;

  --radius-sm: 4px;
  --radius-md: 8px;
  --radius-lg: 12px;

  --shadow-card: 0 1px 2px rgba(44,40,34,0.04);
  --shadow-elevate: 0 2px 12px rgba(44,40,34,0.06);
  --shadow-modal: 0 16px 48px rgba(44,40,34,0.12);
}
`;
