// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const PRODUCTION_HOSTNAME = 'admin.urbangoodzdelivery.com';

if (!process.env.BASE_URL) {
  throw new Error(
    'BASE_URL is required (e.g. a dev/staging URL). Refusing to default to production. ' +
    'This suite submits real login attempts and must not run against the live Admin panel.'
  );
}

const BASE_URL = process.env.BASE_URL;

if (new URL(BASE_URL).hostname === PRODUCTION_HOSTNAME && process.env.ALLOW_PRODUCTION_BASE_URL !== 'true') {
  throw new Error(
    `BASE_URL resolves to the production hostname (${PRODUCTION_HOSTNAME}). ` +
    'Set ALLOW_PRODUCTION_BASE_URL=true to explicitly opt in if this is intentional.'
  );
}

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
