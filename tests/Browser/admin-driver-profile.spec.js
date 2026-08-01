// @ts-check
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const ADMIN_EMAIL = process.env.ADMIN_TEST_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_TEST_PASSWORD;
const DRIVER_ID = process.env.DRIVER_PROFILE_ID || process.env.UG_E2E_DRIVER_ID;
const EVIDENCE_ROOT = process.env.E2E_EVIDENCE_DIR
  || path.join(os.tmpdir(), 'urban-goodz-admin-driver-profile');

const FORBIDDEN = /{{|}}|@endif|@foreach|@php|dm_rating_count|deliveryMan->rating|ErrorException|Undefined offset|Trying to access array offset|\bException\b/i;

function requireCredentials() {
  if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
    throw new Error(
      'Missing ADMIN_TEST_EMAIL or ADMIN_TEST_PASSWORD. The driver-profile certification gate must fail, not skip, when secure Admin credentials are unavailable.'
    );
  }
}

async function loginThroughAdminPage(page) {
  requireCredentials();

  const response = await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  await page.locator('#form-id #signinSrEmail').fill(ADMIN_EMAIL);
  await page.locator('#form-id #signupSrPassword').fill(ADMIN_PASSWORD);

  const customCaptcha = page.locator('#custome_recaptcha');
  if (await customCaptcha.count()) {
    const approvedValue = await customCaptcha.inputValue();
    if (!approvedValue) {
      throw new Error('The Admin login requires a CAPTCHA value and no approved automated test value is available.');
    }
  } else if (await page.locator('.g-recaptcha, iframe[src*="recaptcha"]').count()) {
    throw new Error('The Admin login requires interactive reCAPTCHA; an approved automated test mechanism is required.');
  }

  const [loginResponse] = await Promise.all([
    page.waitForResponse((candidate) => {
      const url = new URL(candidate.url());
      return url.pathname.endsWith('/login_submit') && candidate.request().method() === 'POST';
    }),
    page.locator('#signInBtn, button[type="submit"], input[type="submit"]').first().click(),
  ]);

  expect(loginResponse.status()).toBeLessThan(400);
  await page.waitForLoadState('networkidle');
  await expect(page).not.toHaveURL(/\/login\/admin/);
}

async function followLink(page, locator) {
  await expect(locator).toHaveCount(1);
  if (await locator.isVisible()) {
    await locator.click();
  } else {
    await locator.evaluate((link) => link.click());
  }
  await page.waitForLoadState('networkidle');
}

async function openDriverProfileThroughUi(page) {
  await page.goto('/admin', { waitUntil: 'networkidle' });

  const deliveryMenLink = page
    .locator('a[href*="/admin/users/delivery-man"]')
    .filter({ hasText: /delivery|driver/i })
    .first();
  await followLink(page, deliveryMenLink);
  await expect(page).toHaveURL(/\/admin\/users\/delivery-man/);

  const profileSelector = DRIVER_ID
    ? `a[href*="/admin/users/delivery-man/preview/${DRIVER_ID}"]`
    : 'a[href*="/admin/users/delivery-man/preview/"]';
  await followLink(page, page.locator(profileSelector).first());
  await expect(page).toHaveURL(/\/admin\/users\/delivery-man\/preview\/\d+/);
}

async function verifyDriverProfile(page) {
  const body = page.locator('body');
  await expect(page).toHaveTitle(/delivery man|driver/i);
  await expect(body).not.toContainText(FORBIDDEN);
  await expect(page.locator('img[alt="Delivery man image"]')).toBeVisible();
  await expect(page.locator('a[href^="mailto:"]')).toBeVisible();
  await expect(page.locator('a[href^="tel:"]')).toBeVisible();
  await expect(body).toContainText(/Job Type/i);
  await expect(body).toContainText(/Vehicle Type/i);
  await expect(body).toContainText(/Zone/i);
  await expect(body).toContainText(/Online|Offline|Suspended/i);
  await expect(page.getByTestId('driver-average-rating')).toContainText(/^\s*\d+(?:\.\d)?\/5\s*$/);
  await expect(page.getByTestId('driver-review-count')).toContainText(/\d+\s+Reviews?/i);
  await expect(page.getByTestId('driver-rating-distribution').locator('li')).toHaveCount(5);
  await expect(page.locator('a[href*="/admin/users/delivery-man/edit/"]')).toBeVisible();

  const dimensions = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    viewportWidth: window.innerWidth,
  }));
  expect(dimensions.scrollWidth).toBeLessThanOrEqual(dimensions.viewportWidth + 2);
}

for (const device of [
  { name: 'desktop', viewport: { width: 1440, height: 1000 } },
  { name: 'mobile', viewport: { width: 390, height: 844 } },
]) {
  test(`live Admin driver profile renders safely on ${device.name}`, async ({ browser }, testInfo) => {
    test.setTimeout(90000);
    const evidenceDir = path.join(EVIDENCE_ROOT, device.name);
    fs.mkdirSync(evidenceDir, { recursive: true });

    // Authenticate in a credential-isolated context so traces and video never
    // retain password-field interactions.
    const authContext = await browser.newContext({ viewport: device.viewport });
    const authPage = await authContext.newPage();
    await loginThroughAdminPage(authPage);
    const storageState = await authContext.storageState();
    await authContext.close();

    const context = await browser.newContext({
      viewport: device.viewport,
      storageState,
      recordVideo: { dir: evidenceDir },
    });
    await context.tracing.start({ screenshots: true, snapshots: true, sources: true });

    const page = await context.newPage();
    const consoleMessages = [];
    const networkFailures = [];
    page.on('console', (message) => {
      if (['warning', 'error'].includes(message.type())) {
        consoleMessages.push({ type: message.type(), text: message.text() });
      }
    });
    page.on('requestfailed', (request) => {
      networkFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' });
    });

    try {
      await openDriverProfileThroughUi(page);
      await verifyDriverProfile(page);
      await page.screenshot({ path: path.join(evidenceDir, 'driver-profile.png'), fullPage: true });
      expect(consoleMessages.filter((message) => /exception|error/i.test(message.text))).toEqual([]);
      expect(networkFailures.filter((failure) => failure.url.startsWith(page.url().split('/admin/')[0]))).toEqual([]);
    } catch (error) {
      fs.writeFileSync(path.join(evidenceDir, 'driver-profile-failure.html'), await page.content());
      await page.screenshot({ path: path.join(evidenceDir, 'driver-profile-failure.png'), fullPage: true });
      throw error;
    } finally {
      fs.writeFileSync(path.join(evidenceDir, 'console.json'), JSON.stringify(consoleMessages, null, 2));
      fs.writeFileSync(path.join(evidenceDir, 'network-failures.json'), JSON.stringify(networkFailures, null, 2));
      await context.tracing.stop({ path: path.join(evidenceDir, 'trace.zip') });
      await context.close();
      testInfo.annotations.push({ type: 'evidence', description: evidenceDir });
    }
  });
}
