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
        target: 'https://www.kouprey.asia',
        changeOrigin: true,
        secure: false
      },
      '/kouprey': {
        target: 'https://www.kouprey.asia',
        changeOrigin: true,
        secure: false
      }
    }
  }
})
