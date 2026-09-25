import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// In sviluppo il dev server fa da proxy verso Laravel: browser, API e cookie
// restano sulla stessa origine (come in produzione dietro Caddy).
const backend = process.env.VITE_BACKEND_URL ?? 'http://127.0.0.1:8000'

export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: { '@': fileURLToPath(new URL('./src', import.meta.url)) },
  },
  server: {
    port: 5173,
    proxy: {
      '/api': { target: backend },
      '/sanctum': { target: backend },
    },
  },
  build: {
    sourcemap: false,
  },
})
