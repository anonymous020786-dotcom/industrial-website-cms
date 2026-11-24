/** Tailwind config — brand colours must match the CSS variables in resources/css/style.css */
module.exports = {
  // Every file that contains Tailwind class names (PHP templates + JS)
  content: ['./app/**/*.php', './resources/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        navy:     { DEFAULT: '#101D32', 800: '#16263F', 700: '#1C2F4C' },
        steel:    { DEFAULT: '#174A78', 600: '#1D5A90' },
        safety:   { DEFAULT: '#F28C28', 600: '#DB7718' },
        mist:     '#F3F5F7',
        charcoal: '#252B35',
      },
      fontFamily: {
        sans:    ['Barlow', 'system-ui', 'sans-serif'],
        display: ['"Barlow Condensed"', 'Barlow', 'sans-serif'],
      },
      maxWidth: { site: '1280px' },
    },
  },
  plugins: [],
};
