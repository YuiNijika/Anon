import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    react({
      babel: {
        plugins: [['babel-plugin-react-compiler']],
      },
    }),
  ],
  resolve: {
    alias: {
      '@': path.resolve(__dirname, './src'),
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
  build: {
    outDir: '../core/Static/admin',
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'src/main.tsx'),
      output: {
        entryFileNames: 'index.js',
        chunkFileNames: 'index.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'index.css'
          }
          return assetInfo.name || 'assets/[name].[ext]'
        },
      },
    },
    copyPublicDir: false,
  },
})
