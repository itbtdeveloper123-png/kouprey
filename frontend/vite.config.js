import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import tailwindcss from '@tailwindcss/vite'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react(),
    tailwindcss()
  ],
  server: {
    port: 5173,
    proxy: {
      '/api.php': {
        target: 'http://localhost/kouprey/public',
        changeOrigin: true
      },
      '/uploads': {
        target: 'http://localhost/kouprey/public',
        changeOrigin: true
      },
      '/assets': {
        target: 'http://localhost/kouprey/public',
        changeOrigin: true
      }
    }
  }
})
