import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

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
      output: {
        entryFileNames: 'login-app.js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            return 'login-app.css';
          }

          return 'assets/[name]-[hash][extname]';
        },
      },
    },
  },
});
