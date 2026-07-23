// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const { assertSafeBaseUrl } = require('./base-url-guard');

const BASE_URL = assertSafeBaseUrl(process.env.BASE_URL, {
  allowProduction: process.env.ALLOW_PRODUCTION_BASE_URL === 'true',
});

// Failure artifacts (screenshots, HTML report, and, for specs that don't
// disable it, traces) can contain staging email addresses, session cookies,
// and other reusable material. Both directories below are gitignored and
// must be treated as access-controlled: restrict who can fetch them from
// CI, and delete them once the run has been reviewed -- do not archive them
// indefinitely or publish the HTML report to a public location.
module.exports = defineConfig({
  testDir: './',
  fullyParallel: false,
  forbidOnly: true,
  retries: 1,
  workers: 1,
  outputDir: '../playwright-artifacts',
  reporter: [['list'], ['html', { open: 'never', outputFolder: '../playwright-report' }]],
  timeout: 30000,
  use: {
    baseURL: BASE_URL,
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    actionTimeout: 10000,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
