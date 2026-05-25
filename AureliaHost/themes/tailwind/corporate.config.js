/* ============================================================
   Tailwind Config — Corporate Élégant
   Bleu marine profond + accents dorés
   ============================================================ */

export default {
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#1e3a5f',
          50:  '#eef2f8',
          100: '#d4dff0',
          200: '#a9bfe1',
          300: '#7e9fd2',
          400: '#537fc3',
          500: '#1e3a5f',
          600: '#18304f',
          700: '#12263f',
          800: '#0c1c2f',
          900: '#06121f',
          950: '#030912',
        },
        gold: {
          DEFAULT: '#c8a44e',
          50:  '#fdf8ed',
          100: '#fbf0d5',
          200: '#f7e1ab',
          300: '#f3d281',
          400: '#efc357',
          500: '#c8a44e',
          600: '#a5833e',
          700: '#82622e',
          800: '#5f411e',
          900: '#3c200e',
        },
        surface: {
          bg:    '#f5f6f8',
          card:  '#ffffff',
          high:  '#fafbfc',
          input: '#f1f3f6',
        },
        sidebar: '#0f1f33',
      },
      fontFamily: {
        heading: ['Playfair Display', 'Georgia', 'serif'],
        body:    ['Inter', 'system-ui', 'sans-serif'],
        mono:    ['JetBrains Mono', 'monospace'],
      },
      fontSize: {
        'display': ['2.25rem', { lineHeight: '1.2', fontWeight: '700', fontFamily: 'Playfair Display' }],
        'heading': ['1.5rem',  { lineHeight: '1.3', fontWeight: '700', fontFamily: 'Playfair Display' }],
        'subhead': ['1.125rem', { lineHeight: '1.4', fontWeight: '600' }],
      },
      borderRadius: {
        card: '10px',
        btn:  '8px',
        input:'7px',
      },
      boxShadow: {
        card:    '0 1px 3px rgba(12,28,47,0.06), 0 1px 2px rgba(12,28,47,0.04)',
        elevate: '0 4px 16px rgba(12,28,47,0.08)',
        modal:   '0 20px 60px rgba(12,28,47,0.18)',
        glow:    '0 0 20px rgba(200,164,78,0.25)',
      },
      transitionTimingFunction: {
        smooth: 'cubic-bezier(0.4, 0, 0.2, 1)',
      },
    },
  },
};

/* ===== Utilisation dans tailwind.config.js =====
   const corporate = require('./themes/tailwind/corporate.config');
   module.exports = {
     presets: [corporate],
     content: ['./src/**/*.{js,jsx,ts,tsx}'],
   };
*/
