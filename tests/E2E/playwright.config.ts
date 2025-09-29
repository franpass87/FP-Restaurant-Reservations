import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  timeout: 30_000,
  use: {
    baseURL: process.env.WP_BASE_URL ?? 'http://localhost:8889',
    ignoreHTTPSErrors: true,
  },
  reporter: [['list']],
});
