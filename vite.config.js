import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    outDir: 'dist',
    emptyOutDir: false,
    sourcemap: false,
    chunkSizeWarningLimit: 30,
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('assets/js/fe/')) {
            return 'fe';
          }

          if (id.includes('assets/js/admin/')) {
            return 'admin';
          }

          return undefined;
        },
      },
    },
  },
});
