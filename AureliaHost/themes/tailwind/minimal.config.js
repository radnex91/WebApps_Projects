/* ============================================================
   Tailwind Config — Minimaliste Moderne
   Neutres chauds + vert sauge
   ============================================================ */

export default {
  theme: {
    extend: {
      colors: {
        brand: {
          DEFAULT: '#7c9086',
          50:  '#f6f8f5',
          100: '#e8ede4',
          200: '#d1dbca',
          300: '#bac9b0',
          400: '#a3b796',
          500: '#7c9086',
          600: '#64776d',
          700: '#4c5e54',
          800: '#34453b',
          900: '#1c2c22',
        },
        sand: {
          DEFAULT: '#a69888',
          50:  '#faf9f7',
          100: '#f2f0ec',
          200: '#e5e5d9',
          300: '#d5cfc0',
          400: '#c5b9a7',
          500: '#a69888',
          600: '#8b7d6e',
          700: '#6d6254',
          800: '#4f473a',
          900: '#312c20',
        },
        accent: {
          DEFAULT: '#d4a574',
          hover:   '#c08b5c',
          muted:   '#d4a57426',
        },
        surface: {
          bg:    '#faf9f7',
          card:  '#ffffff',
          high:  '#fdfcfb',
          input: '#f7f6f3',
        },
        sidebar: '#f5f3ef',
      },
      fontFamily: {
        heading: ['DM Serif Display', 'Georgia', 'serif'],
        body:    ['Work Sans', 'system-ui', 'sans-serif'],
        mono:    ['Source Code Pro', 'monospace'],
      },
      fontSize: {
        'display': ['2rem',    { lineHeight: '1.2', fontWeight: '600', fontFamily: 'DM Serif Display' }],
        'heading': ['1.4rem',  { lineHeight: '1.35', fontWeight: '600', fontFamily: 'DM Serif Display' }],
        'subhead': ['1.1rem',  { lineHeight: '1.4', fontWeight: '500' }],
      },
      borderRadius: {
        card: '8px',
        btn:  '6px',
        input:'5px',
      },
      boxShadow: {
        card:    '0 1px 2px rgba(44,40,34,0.04)',
        elevate: '0 2px 12px rgba(44,40,34,0.06)',
        modal:   '0 16px 48px rgba(44,40,34,0.12)',
      },
      transitionTimingFunction: {
        smooth: 'cubic-bezier(0.25, 0, 0.25, 1)',
      },
    },
  },
};
