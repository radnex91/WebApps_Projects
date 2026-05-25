/* ============================================================
   Tailwind Config — Dark Mode Professionnel
   Fonds sombres profonds + violet accent
   ============================================================ */

export default {
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#7c3aed',
          50:  '#f4f3f8',
          100: '#e3e1f0',
          200: '#c7c3e1',
          300: '#aba5d2',
          400: '#8f87c3',
          500: '#7c3aed',
          600: '#6d28d9',
          700: '#5b21b6',
          800: '#4c1d95',
          900: '#3b0f6e',
          950: '#2e0854',
        },
        indigo: {
          DEFAULT: '#4a6cf7',
          50:  '#f0f4fe',
          100: '#dce5fd',
          200: '#b9cbfb',
          300: '#96b1f9',
          400: '#7397f7',
          500: '#4a6cf7',
          600: '#3b56c9',
          700: '#2c409b',
          800: '#1d2a6d',
          900: '#0e143f',
        },
        accent: {
          DEFAULT: '#a78bfa',
          hover:   '#c4b5fd',
          muted:   '#a78bfa26',
          glow:    '#a78bfa40',
        },
        surface: {
          bg:    '#08090d',
          body:  '#0d0f14',
          card:  '#13151c',
          high:  '#181b24',
          input: '#11131a',
        },
        sidebar: '#0a0b10',
      },
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
      },
      fontSize: {
        'display': ['2.25rem', { lineHeight: '1.2', fontWeight: '800', letterSpacing: '-0.5px' }],
        'heading': ['1.5rem',  { lineHeight: '1.3', fontWeight: '700', letterSpacing: '-0.3px' }],
        'subhead': ['1.1rem',  { lineHeight: '1.4', fontWeight: '600' }],
      },
      borderRadius: {
        card: '10px',
        btn:  '8px',
        input:'7px',
      },
      boxShadow: {
        card:    '0 1px 2px rgba(0,0,0,0.3)',
        elevate: '0 4px 20px rgba(0,0,0,0.4)',
        modal:   '0 25px 80px rgba(0,0,0,0.6)',
        glow:    '0 0 25px rgba(167,139,250,0.25)',
      },
      backdropBlur: {
        topbar: '12px',
        modal:  '8px',
      },
      transitionTimingFunction: {
        smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
    },
  },
};
