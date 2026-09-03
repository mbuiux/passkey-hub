import { defineConfig } from '@playwright/test';

const baseURL = process.env.PLAYWRIGHT_BASE_URL || 'https://demo.local';

export default defineConfig({
  testDir: 'tests/e2e',
  timeout: 90_000,
  // Serialize: many specs mutate shared state (wp_options, mu-plugin fixtures, the one admin session)
  // on the same live WordPress site, so parallel workers race and cause spurious failures.
  fullyParallel: false,
  workers: 1,
  expect: {
    timeout: 10_000,
  },
  use: {
    baseURL,
    ignoreHTTPSErrors: true,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
  },
  projects: [
    {
      // Chromium is required because the suite uses CDP WebAuthn virtual authenticators.
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
  reporter: [['list'], ['html', { open: 'never' }]],
});
