module.exports = {
  content: [
    "../dominickzou.dev/public_html/**/*.html",
    "../dominickzou.dev/public_html/**/*.php",
    "../test.dominickzou.dev/public_html/**/*.html",
    "../test.dominickzou.dev/public_html/**/*.php",
    "../reporting.dominickzou.dev/public_html/**/*.php",
    "../reporting.dominickzou.dev/public_html/views/**/*.php",
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Inter"', 'system-ui', '-apple-system', 'sans-serif'],
      },
      colors: {
        brand: {
          50: '#f8fafc',
          100: '#f1f5f9',
          900: '#0f172a',
        }
      },
      spacing: {
        '18': '4.5rem',
        '22': '5.5rem',
      }
    },
  },
  plugins: [],
}
