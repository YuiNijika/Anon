/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
  ],
  theme: {
    extend: {},
  },
  plugins: [require("daisyui")],
  daisyui: {
    themes: ["light", "dark"],
  },
}
