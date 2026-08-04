import { defineConfig, devices } from '@playwright/test';
import 'dotenv/config';

const baseURL = process.env.PRODUCTION_BASE_URL ?? 'https://admin.urbangoodzdelivery.com';

export default defineConfig({
  testDir: './tests/playwright',
  timeout: 90_000,
  expect: { timeout: 15_000 },
  fullyParallel: false,
  forbidOnly: true,
  retries: 1,
  workers: 1,
  reporter: [
    ['list'],
    ['html', { outputFolder: 'artifacts/playwright-report', open: 'never' }],
    ['junit', { outputFile: 'artifacts/playwright-results.xml' }],
  ],
  use: {
    baseURL,
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    ignoreHTTPSErrors: false,
  },
  projects: [
    { name: 'admin-desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'admin-mobile', use: { ...devices['Pixel 7'] } },
  ],
  outputDir: 'artifacts/playwright-output',
});
