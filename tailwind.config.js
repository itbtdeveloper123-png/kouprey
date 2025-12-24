/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./app/Views/**/*.{php,html,js}",
    "./public/**/*.{php,html,js}",
    "./admin/**/*.{php,html,js}"
  ],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Freeman', 'sans-serif'],
      },
    },
  },
  plugins: [],
}