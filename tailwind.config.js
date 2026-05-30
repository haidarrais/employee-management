/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        // Action Blue — the single interactive accent
        blue: {
          DEFAULT: '#0066cc',
          focus:   '#0071e3',
          dark:    '#2997ff',   // on-dark surfaces only
        },
        // Surfaces
        canvas:    '#ffffff',
        parchment: '#f5f5f7',
        pearl:     '#fafafc',
        tile: {
          1: '#272729',
          2: '#2a2a2c',
          3: '#252527',
        },
        void: '#000000',
        // Text
        ink:    '#1d1d1f',
        muted:  '#6e6e73',
        faint:  '#cccccc',
        // Hairlines
        line:   '#e0e0e0',
        soft:   '#f0f0f0',
      },
      fontFamily: {
        sans: [
          'Inter',
          'system-ui',
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'sans-serif',
        ],
      },
      fontSize: {
        // Typography scale from design spec
        'hero':    ['56px', { lineHeight: '1.07', letterSpacing: '-0.28px', fontWeight: '600' }],
        'display': ['40px', { lineHeight: '1.10', letterSpacing: '0',       fontWeight: '600' }],
        'section': ['34px', { lineHeight: '1.47', letterSpacing: '-0.374px',fontWeight: '600' }],
        'lead':    ['28px', { lineHeight: '1.14', letterSpacing: '0.196px', fontWeight: '400' }],
        'tagline': ['21px', { lineHeight: '1.19', letterSpacing: '0.231px', fontWeight: '600' }],
        'body':    ['17px', { lineHeight: '1.47', letterSpacing: '-0.374px',fontWeight: '400' }],
        'caption': ['14px', { lineHeight: '1.43', letterSpacing: '-0.224px',fontWeight: '400' }],
        'fine':    ['12px', { lineHeight: '1.0',  letterSpacing: '-0.12px', fontWeight: '400' }],
      },
      spacing: {
        section: '80px',
        tile:    '64px',
      },
      borderRadius: {
        pill: '9999px',
        card: '18px',
        util: '8px',
        cap:  '11px',
      },
      animation: {
        'fade-in':  'fadeIn 0.3s ease-out',
        'slide-up': 'slideUp 0.25s ease-out',
        'pulse-dot':'pulseDot 2s ease-in-out infinite',
      },
      keyframes: {
        fadeIn:   { '0%': { opacity: '0' },                              '100%': { opacity: '1' } },
        slideUp:  { '0%': { opacity: '0', transform: 'translateY(8px)' },'100%': { opacity: '1', transform: 'translateY(0)' } },
        pulseDot: { '0%,100%': { opacity: '1' },                         '50%':  { opacity: '0.4' } },
      },
      boxShadow: {
        // The ONE product shadow from the spec
        product: 'rgba(0,0,0,0.22) 3px 5px 30px 0',
        // Hairline for utility cards
        hairline: '0 0 0 1px #e0e0e0',
      },
    },
  },
  plugins: [],
}
