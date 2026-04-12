import path from 'node:path';
import { fileURLToPath } from 'node:url';

import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default defineConfig({
  base: '/spa/',
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 5173,
    strictPort: true,
    proxy: {
      '/api': {
        target: process.env.VITE_BACKEND_URL || 'http://localhost:8188',
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: '../public/spa',
    emptyOutDir: true,
    rollupOptions: {
      input: {
        // HTML must be an entry so Vite emits public/spa/index.html (Symfony SpaController).
        index: path.resolve(__dirname, 'index.html'),
        telegram: path.resolve(__dirname, 'telegram.html'),
        'header-switchers': path.resolve(__dirname, 'src/header-switchers.jsx'),
      },
      output: {
        entryFileNames: '[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return '[name].css';
          }

          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
