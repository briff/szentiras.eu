/** @type {import('tailwindcss').Config} */
export default {
  content: [
    './resources/views/textDisplay/book.twig',
  ],
  theme: {
    extend: {},
  },
  plugins: [],
  corePlugins: {
    preflight: false, // Disable Tailwind's base styles to avoid conflicts with Bootstrap
  },
  important: '.tailwind-scope', // Scope Tailwind to specific containers
}
