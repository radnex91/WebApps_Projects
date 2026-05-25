/* ============================================================
   THEME 3 — DARK MODE PROFESSIONNEL
   Style SaaS moderne : fonds sombres, violet accent, verre
   ============================================================ */

export const darkTheme = {
  id: 'dark',
  name: 'Dark Mode Pro',
  description: 'Interface sombre sophistiquée avec accent violet pour un style SaaS moderne.',

  colors: {
    /* === Fondations === */
    primary: {
      50:  '#f4f3f8',
      100: '#e3e1f0',
      200: '#c7c3e1',
      300: '#aba5d2',
      400: '#8f87c3',
      500: '#7c3aed', // ★ Violet accent
      600: '#6d28d9',
      700: '#5b21b6',
      800: '#4c1d95',
      900: '#3b0f6e',
      950: '#2e0854',
    },
    secondary: {
      50:  '#f0f4fe',
      100: '#dce5fd',
      200: '#b9cbfb',
      300: '#96b1f9',
      400: '#7397f7',
      500: '#4a6cf7', // ★ Indigo tech
      600: '#3b56c9',
      700: '#2c409b',
      800: '#1d2a6d',
      900: '#0e143f',
    },
    accent: {
      DEFAULT: '#a78bfa', // Violet clair
      hover:   '#c4b5fd',
      muted:   'rgba(167,139,250,0.15)',
      glow:    'rgba(167,139,250,0.25)',
    },

    /* === Surfaces — dark layered === */
    surface: {
      background: '#08090d',
      body:       '#0d0f14',
      card:       '#13151c',
      elevated:   '#181b24',
      sidebar:    '#0a0b10',
      input:      '#11131a',
      overlay:    'rgba(0,0,0,0.7)',
    },

    /* === Texte — soft white === */
    text: {
      primary:   '#e4e5ea',
      secondary: '#8b8d96',
      muted:     '#5d5f68',
      inverse:   '#08090d',
      link:      '#a78bfa',
    },

    /* === Statuts — vibrant sur fond sombre === */
    status: {
      success:  { bg: 'rgba(52,211,153,0.12)', text: '#6ee7b7', dot: '#34d399' },
      warning:  { bg: 'rgba(251,191,36,0.12)', text: '#fcd34d', dot: '#fbbf24' },
      error:    { bg: 'rgba(248,113,113,0.12)', text: '#fca5a5', dot: '#f87171' },
      info:     { bg: 'rgba(96,165,250,0.12)', text: '#93c5fd', dot: '#60a5fa' },
      neutral:  { bg: 'rgba(255,255,255,0.06)', text: '#8b8d96', dot: '#5d5f68' },
    },

    /* === Bordures — hairline === */
    border: {
      DEFAULT: 'rgba(255,255,255,0.08)',
      light:   'rgba(255,255,255,0.05)',
      focus:   '#7c3aed',
    },
  },

  /* === Typographie === */
  typography: {
    fontFamily: {
      heading: ['Inter', 'system-ui', 'sans-serif'],
      body:    ['Inter', 'system-ui', 'sans-serif'],
      mono:    ['JetBrains Mono', 'Fira Code', 'monospace'],
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
      'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500&display=swap',
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
      card:    '0 1px 2px rgba(0,0,0,0.3)',
      elevate: '0 4px 20px rgba(0,0,0,0.4)',
      modal:   '0 25px 80px rgba(0,0,0,0.6)',
      glow:    '0 0 25px rgba(167,139,250,0.25)',
    },
    transition: '200ms cubic-bezier(0.4, 0, 0.2, 1)',
    blur: {
      topbar: 'blur(12px)',
      modal:  'blur(8px)',
    },
  },

  /* === Tailwind Config Override === */
  tailwind: {
    extend: {
      colors: {
        brand:   '#7c3aed',
        indigo:  '#4a6cf7',
        accent:  '#a78bfa',
        surface: '#0d0f14',
        card:    '#13151c',
        sidebar: '#0a0b10',
      },
      fontFamily: {
        sans:  ['Inter', 'system-ui', 'sans-serif'],
        mono:  ['JetBrains Mono', 'Fira Code', 'monospace'],
      },
      borderRadius: {
        card: '10px',
        btn:  '8px',
      },
      backdropBlur: {
        topbar: '12px',
      },
    },
  },
};

/* ===== CSS Variables (drop-in) ===== */
export const darkCSS = `
:root[data-theme="dark"] {
  --color-primary: #7c3aed;
  --color-primary-light: #a78bfa;
  --color-secondary: #4a6cf7;
  --color-secondary-light: #7397f7;
  --color-accent: #a78bfa;

  --bg-surface: #08090d;
  --bg-body: #0d0f14;
  --bg-card: #13151c;
  --bg-elevated: #181b24;
  --bg-sidebar: #0a0b10;
  --bg-input: #11131a;

  --text-primary: #e4e5ea;
  --text-secondary: #8b8d96;
  --text-muted: #5d5f68;

  --status-success-bg: rgba(52,211,153,0.12);
  --status-success-text: #6ee7b7;
  --status-warning-bg: rgba(251,191,36,0.12);
  --status-warning-text: #fcd34d;
  --status-error-bg: rgba(248,113,113,0.12);
  --status-error-text: #fca5a5;
  --status-info-bg: rgba(96,165,250,0.12);
  --status-info-text: #93c5fd;

  --border-color: rgba(255,255,255,0.08);
  --border-light: rgba(255,255,255,0.05);

  --font-heading: 'Inter', system-ui, sans-serif;
  --font-body: 'Inter', system-ui, sans-serif;

  --radius-sm: 6px;
  --radius-md: 10px;
  --radius-lg: 14px;

  --shadow-card: 0 1px 2px rgba(0,0,0,0.3);
  --shadow-elevate: 0 4px 20px rgba(0,0,0,0.4);
  --shadow-modal: 0 25px 80px rgba(0,0,0,0.6);
  --shadow-glow: 0 0 25px rgba(167,139,250,0.25);
}
`;
