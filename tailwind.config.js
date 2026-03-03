/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/textDisplay/book.twig',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  darkMode: ['selector', '[data-theme="dark"]'], // Use data-theme attribute for dark mode (Bootstrap compatible)
  corePlugins: {
    preflight: false, // Disable Tailwind's base styles to avoid conflicts with Bootstrap
  },
  important: '.tailwind-scope', // Scope Tailwind to specific containers
}
