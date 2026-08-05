import { test, expect, type Page } from '@playwright/test';
import { config } from '../../src/config.js';

async function login(page: Page): Promise<void> {
  if (!config.credentials.admin.login || !config.credentials.admin.password) {
    throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD are required for real authenticated Playwright tests.');
  }
  await page.goto(config.paths.adminLogin, { waitUntil: 'domcontentloaded' });
  const loginInput = page.locator('input[type="email"], input[name*="email"], input[name*="phone"], input[type="text"]').first();
  const passwordInput = page.locator('input[type="password"]').first();
  await expect(loginInput).toBeVisible();
  await expect(passwordInput).toBeVisible();
  await loginInput.fill(config.credentials.admin.login);
  await passwordInput.fill(config.credentials.admin.password);

  const recaptchaInput = page.locator('input[name="custome_recaptcha"]');
  if (await recaptchaInput.count() > 0) {
    await recaptchaInput.fill('9999');
  }

  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.getByRole('button', { name: /login|sign in/i }).click(),
  ]);
  await expect(page).not.toHaveURL(/login/i);
}

function installFailureGuards(page: Page): string[] {
  const failures: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      const text = message.text();
      if (text.includes('messaging/permission-default') || text.includes('firebase') || text.includes('Maps HTML5 API') || text.includes('compute-pressure')) {
        return;
      }
      failures.push(`console: ${text}`);
    }
  });
  page.on('response', (response) => {
    if (response.status() >= 500) failures.push(`http ${response.status()}: ${response.url()}`);
  });
  page.on('pageerror', (error) => {
    const msg = error.message;
    if (msg.includes("Unexpected token '<'") || msg.includes("Firebase")) {
      return;
    }
    failures.push(`pageerror: ${msg}`);
  });
  return failures;
}

const pages = [
  { name: 'Dashboard', paths: ['/admin', '/admin/dashboard'] },
  { name: 'Payments', paths: ['/admin/payments', '/admin/urban-goodz/payments'] },
  { name: 'Platform Economics', paths: ['/admin/platform-economics', '/admin/urban-goodz/platform-economics'] },
  { name: 'Financial Control', paths: ['/admin/financial-control', '/admin/urban-goodz/financial-control'] },
  { name: 'Ledger', paths: ['/admin/ledger', '/admin/urban-goodz/ledger'] },
  { name: 'Reconciliation', paths: ['/admin/reconciliation', '/admin/urban-goodz/reconciliation'] },
  { name: 'Order Anywhere', paths: ['/admin/urban-goodz/order-anywhere'] },
  { name: 'Dispatcher Best Loads', paths: ['/admin/urban-goodz/dispatcher/best-loads', '/admin/dispatcher/best-loads'] },
  { name: 'Package Scanner', paths: ['/admin/urban-goodz/package-scanner', '/admin/scanner'] },
  { name: 'Route Optimizer', paths: ['/admin/urban-goodz/route-optimizer', '/admin/route-optimizer'] },
  { name: 'AI Operations', paths: ['/admin/urban-goodz/ai-operations', '/admin/ai-operations'] },
  { name: 'Creator Commerce', paths: ['/admin/urban-goodz/creator-commerce', '/admin/creator-commerce'] },
];

for (const target of pages) {
  test(`authenticated production page: ${target.name}`, async ({ page }, testInfo) => {
    const failures = installFailureGuards(page);
    await login(page);

    let rendered = false;
    for (const path of target.paths) {
      const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
      if (response && response.status() < 400 && !/login/i.test(page.url())) {
        rendered = true;
        break;
      }
    }

    expect(rendered, `${target.name} did not render from any known production route`).toBeTruthy();
    await expect(page.locator('body')).not.toContainText(/SQLSTATE|RouteNotFoundException|Whoops|Stack trace|Undefined property/i);
    await expect(page.locator('body')).toBeVisible();
    expect(failures, failures.join('\n')).toEqual([]);
    await page.screenshot({ path: testInfo.outputPath(`${target.name.replace(/\s+/g, '_')}.png`), fullPage: true });
  });
}

test('restricted production routes fail closed for unauthenticated users', async ({ page }) => {
  for (const path of ['/admin/payments', '/admin/urban-goodz/order-anywhere', '/admin/urban-goodz/route-optimizer']) {
    const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
    const isRedirectedToLogin = page.url().includes('/login') || [401, 403].includes(response?.status() ?? 0);
    expect(isRedirectedToLogin).toBeTruthy();
    await expect(page.locator('body')).not.toContainText(/SQLSTATE|Stack trace|APP_KEY|DB_PASSWORD/i);
  }
});
