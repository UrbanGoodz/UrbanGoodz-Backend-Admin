import test from 'node:test';
import assert from 'node:assert/strict';
import { chromium } from '@playwright/test';
import { config } from '../../src/config.js';
import { assertAnyText, closeAppSession, createAppSession, saveMobileEvidence, tapText } from '../../src/appium.js';
import { login as mobileLogin } from '../../src/mobile-flows.js';

async function portalLogin(page: import('@playwright/test').Page, path: string, login: string, password: string): Promise<void> {
  if (!login || !password) throw new Error('Business portal credentials are required.');
  await page.goto(new URL(path, config.baseUrl).toString());
  await page.locator('input[type="email"], input[name*="email"], input[type="text"]').first().fill(login);
  await page.locator('input[type="password"]').first().fill(password);
  await page.getByRole('button', { name: /login|sign in/i }).click();
  await page.waitForLoadState('networkidle');
  assert.ok(!/login/i.test(page.url()), 'Business portal login did not complete.');
}

test('real package route: Business creates package, Admin optimizes, Driver scans and completes', async (t) => {
  assert.ok(config.qa.packageRecipient, 'QA_BUSINESS_PACKAGE_RECIPIENT is required.');
  assert.ok(config.qa.deliveryAddress, 'QA_DELIVERY_ADDRESS is required.');

  const browser = await chromium.launch({ headless: false });
  const context = await browser.newContext();
  const page = await context.newPage();
  const driver = await createAppSession('driver');
  t.after(async () => {
    await closeAppSession(driver);
    await context.close();
    await browser.close();
  });

  try {
    await portalLogin(page, config.paths.businessLogin, config.credentials.business.login, config.credentials.business.password);
    await page.getByRole('link', { name: /packages|package management/i }).first().click();
    await page.getByRole('button', { name: /create package|new package/i }).click();
    await page.locator('input[name*="recipient"]').first().fill(config.qa.packageRecipient);
    await page.locator('input[name*="address"], textarea[name*="address"]').first().fill(config.qa.deliveryAddress);
    await page.getByRole('button', { name: /save|create|submit/i }).click();
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: 'artifacts/package-business-created.png', fullPage: true });
    const body = await page.locator('body').innerText();
    const trackingMatch = body.match(/(?:Tracking\s*(?:ID|#|No\.?)[^A-Z0-9]*)([A-Z0-9-]{5,})/i);
    assert.ok(trackingMatch, 'Business Portal did not render a package tracking ID.');
    const trackingId = trackingMatch[1];

    await page.goto(new URL('/admin/urban-goodz/package-scanner', config.baseUrl).toString());
    if (/login/i.test(page.url())) throw new Error('Authenticated Admin session is required for package assignment.');
    await page.getByText(trackingId, { exact: false }).first().click();
    await page.getByRole('button', { name: /assign to route|add to route/i }).click();
    await page.goto(new URL('/admin/urban-goodz/route-optimizer', config.baseUrl).toString());
    await page.getByRole('button', { name: /optimize|reoptimize/i }).click();
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `artifacts/package-route-optimized-${trackingId}.png`, fullPage: true });

    await mobileLogin(driver, config.credentials.driver.login, config.credentials.driver.password);
    await tapText(driver, ['Routes', 'My Route', 'Packages']);
    await tapText(driver, [trackingId]);
    await assertAnyText(driver, [trackingId, 'Optimized Route', 'Stop 1']);
    await tapText(driver, ['Scan Pickup', 'Pickup Scan']);
    await tapText(driver, ['Scan Drop-off', 'Drop-off Scan']);
    await tapText(driver, ['Complete Route', 'Delivered']);
    await assertAnyText(driver, ['Completed', 'Delivered']);
    await saveMobileEvidence(driver, `package-route-completed-${trackingId}`);
  } catch (error) {
    await page.screenshot({ path: 'artifacts/package-route-browser-failure.png', fullPage: true });
    await saveMobileEvidence(driver, 'package-route-driver-failure');
    throw error;
  }
});
