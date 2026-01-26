import { fileURLToPath, URL } from 'node:url'

import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import ui from '@nuxt/ui/vite'
import vueDevTools from 'vite-plugin-vue-devtools'
import Pages from 'vite-plugin-pages'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    ui(),
    vueDevTools(),
    Pages({
      dirs: 'src/views',
      extensions: ['vue']
    })
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url))
    },
  },
  server: {
    proxy: {
      '/anon-dev-server': {
        target: 'http://anon.localhost:8080',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/anon-dev-server/, ''),
      },
    },
  },
})
