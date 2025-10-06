import path from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig(({ mode }) => {
  const isProd = mode === 'production';

  return {
    build: {
      outDir: 'assets/dist',
      emptyOutDir: false,
      sourcemap: !isProd,
      minify: isProd ? 'esbuild' : false,
      lib: {
        entry: path.resolve(__dirname, 'assets/js/fe/form-app-optimized.js'),
        name: 'FPResv',
        formats: ['es', 'iife'],
        fileName: (format) => {
          if (format === 'es') {
            return 'fe/onepage.esm.js';
          }

          return 'fe/onepage.iife.js';
        },
      },
      rollupOptions: {
        output: {
          inlineDynamicImports: true,
        },
      },
    },
  };
});
