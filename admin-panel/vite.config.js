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
    port: 5175,
    proxy: {
      '/admin-api.php': {
        target: 'https://www.kouprey.asia',
        changeOrigin: true,
        secure: false,
        configure: (proxy) => {
          proxy.on('proxyReq', (proxyReq, req) => {
            // Forward credentials & cookies properly
            if (req.headers.cookie) {
              proxyReq.setHeader('cookie', req.headers.cookie);
            }
          });
        }
      },
      '/api.php': {
        target: 'https://www.kouprey.asia',
        changeOrigin: true,
        secure: false
      }
    }
  }
})
