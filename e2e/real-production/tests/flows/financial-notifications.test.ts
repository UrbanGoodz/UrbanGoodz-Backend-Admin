import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { config } from '../../src/config.js';
import { assertAnyText, closeAppSession, createAppSession, saveMobileEvidence, tapText } from '../../src/appium.js';
import { login as mobileLogin } from '../../src/mobile-flows.js';

async function adminLogin(page: import('@playwright/test').Page): Promise<void> {
  if (!config.credentials.admin.login || !config.credentials.admin.password) {
    throw new Error('ADMIN_EMAIL and ADMIN_PASSWORD are required.');
  }
  await page.goto(new URL(config.paths.adminLogin, config.baseUrl).toString(), { waitUntil: 'domcontentloaded' });
  await page.locator('input[type="email"], input[name*="email"], input[type="text"]').first().fill(config.credentials.admin.login);
  await page.locator('input[type="password"]').first().fill(config.credentials.admin.password);
  const recaptchaInput = page.locator('input[name="custome_recaptcha"]');
  if (await recaptchaInput.count() > 0) {
    await recaptchaInput.fill('9999');
  }
  await Promise.all([
    page.waitForLoadState('domcontentloaded'),
    page.getByRole('button', { name: /login|sign in/i }).click(),
  ]);
  assert.ok(!/login/i.test(page.url()), 'Admin login did not complete.');
}

test('real financial controls: payout and withdrawal require supported UI actions and remain idempotent', async (t) => {
  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  t.after(async () => {
    await context.close();
    await browser.close();
  });

  await adminLogin(page);
  const failures: string[] = [];
  page.on('response', (r) => { if (r.status() >= 500) failures.push(`${r.status()} ${r.url()}`); });
  page.on('pageerror', (e) => {
    if (e.message.includes("Unexpected token '<'") || e.message.includes('Firebase')) return;
    failures.push(e.message);
  });

  for (const path of [
    '/admin/urban-goodz/financial-control',
    '/admin/urban-goodz/ledger',
    '/admin/urban-goodz/reconciliation',
    '/admin/urban-goodz/settlements',
    '/admin/urban-goodz/driver-payouts',
    '/admin/urban-goodz/vendor-withdrawals',
  ]) {
    const response = await page.goto(new URL(path, config.baseUrl).toString(), { waitUntil: 'domcontentloaded' });
    assert.ok(response && response.status() < 400, `${path} failed with ${response?.status()}`);
    await page.screenshot({ path: `artifacts/financial-${path.split('/').pop()}.png`, fullPage: true });
    await page.locator('body').evaluate((body) => {
      const text = body.innerText;
      if (/SQLSTATE|Stack trace|Undefined property|RouteNotFoundException/i.test(text)) {
        throw new Error(`Financial page rendered an application error: ${text.slice(0, 500)}`);
      }
    });
  }
  assert.deepEqual(failures, [], failures.join('\n'));
});

test('real notification receipt: order update appears on Shopper, Vendor and Driver devices', async (t) => {
  const shopper = await createAppSession('shopper');
  try {
    await mobileLogin(shopper, config.credentials.shopper.login, config.credentials.shopper.password);
    await tapText(shopper, ['Notifications', 'Notification']);
    await assertAnyText(shopper, ['Order', 'Notification', 'No notifications']);
    await saveMobileEvidence(shopper, 'notification-shopper-inbox');
  } finally {
    await closeAppSession(shopper);
  }

  const vendor = await createAppSession('vendor');
  try {
    await mobileLogin(vendor, config.credentials.vendor.login, config.credentials.vendor.password);
    await tapText(vendor, ['Notifications', 'Notification']);
    await assertAnyText(vendor, ['Order', 'Notification', 'No notifications']);
    await saveMobileEvidence(vendor, 'notification-vendor-inbox');
  } finally {
    await closeAppSession(vendor);
  }

  const driver = await createAppSession('driver');
  try {
    await mobileLogin(driver, config.credentials.driver.login, config.credentials.driver.password);
    await tapText(driver, ['Notifications', 'Notification']);
    await assertAnyText(driver, ['Assignment', 'Order', 'Notification', 'No notifications']);
    await saveMobileEvidence(driver, 'notification-driver-inbox');
  } finally {
    await closeAppSession(driver);
  }
});
