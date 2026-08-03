// @ts-check
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const ADMIN_EMAIL = process.env.ADMIN_TEST_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_TEST_PASSWORD;
const EVIDENCE_ROOT = process.env.E2E_EVIDENCE_DIR
  || path.join(os.tmpdir(), 'urban-goodz-admin-driver-edit');
const FORBIDDEN = /{{|}}|@endif|@foreach|@php|@endphp|\$vehicleTypes|\$identity_images|ErrorException|Undefined variable|Undefined offset|Trying to access|SQLSTATE\[|Stack trace:|Whoops, looks like something went wrong/i;

async function loginThroughAdminPage(page) {
  if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
    throw new Error('Missing secure ADMIN_TEST_EMAIL or ADMIN_TEST_PASSWORD.');
  }

  const response = await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  await page.locator('#form-id #signinSrEmail').fill(ADMIN_EMAIL);
  await page.locator('#form-id #signupSrPassword').fill(ADMIN_PASSWORD);

  const customCaptcha = page.locator('#custome_recaptcha');
  await expect(customCaptcha).toHaveCount(1);
  const defaultCaptchaMode = page.locator('#set_default_captcha_value');
  if (await defaultCaptchaMode.count()) {
    const [reloadResponse] = await Promise.all([
      page.waitForResponse((candidate) => new URL(candidate.url()).pathname === '/reload-captcha'),
      page.locator('.reloadCaptcha').click(),
    ]);
    expect(reloadResponse.status()).toBe(200);
    await reloadResponse.finished();
    await page.waitForTimeout(250);
    await defaultCaptchaMode.fill('1');
  }

  const captcha = await customCaptcha.inputValue();
  expect(captcha, 'Custom CAPTCHA is not prefilled by the approved test environment').not.toBe('');
  const sessionCookies = (await page.context().cookies()).filter((cookie) => /session/i.test(cookie.name));
  expect(sessionCookies.length).toBeGreaterThan(0);

  const [loginRequest, loginResponse] = await Promise.all([
    page.waitForRequest((candidate) => new URL(candidate.url()).pathname.endsWith('/login_submit') && candidate.method() === 'POST'),
    page.waitForResponse((candidate) => new URL(candidate.url()).pathname.endsWith('/login_submit') && candidate.request().method() === 'POST'),
    page.locator('#signInBtn').click(),
  ]);
  expect(loginResponse.status()).toBeLessThan(400);
  expect(new URLSearchParams(loginRequest.postData() || '').get('custome_recaptcha')).toBe(captcha);
  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/\/login\/admin/);
}

async function assertNoOverflow(page) {
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
  test(`Admin opens a real Driver Edit form through the UI on ${device.name}`, async ({ browser }) => {
    test.setTimeout(180000);
    const evidenceDir = path.join(EVIDENCE_ROOT, 'driver-edit', device.name);
    fs.mkdirSync(evidenceDir, { recursive: true });

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
    const actionLog = [];

    page.on('console', (message) => {
      if (['warning', 'error'].includes(message.type())) {
        consoleMessages.push({ type: message.type(), text: message.text() });
      }
    });
    page.on('requestfailed', (request) => {
      networkFailures.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' });
    });
    page.on('response', (response) => {
      if (response.status() >= 500) {
        networkFailures.push({ url: response.url(), error: `HTTP ${response.status()}` });
      }
    });

    try {
      await page.goto('/admin', { waitUntil: 'domcontentloaded' });
      const listLink = page.locator('a[href$="/admin/users/delivery-man"]').first();
      await expect(listLink).toHaveCount(1);
      const listResponsePromise = page.waitForResponse((candidate) =>
        new URL(candidate.url()).pathname === '/admin/users/delivery-man'
        && candidate.request().method() === 'GET'
      );
      await listLink.evaluate((element) => element.click());
      expect((await listResponsePromise).status()).toBe(200);
      await page.waitForLoadState('domcontentloaded');
      actionLog.push({ action: 'open-driver-list-through-admin-ui', url: page.url() });

      const editLink = page.locator('a[href*="/admin/users/delivery-man/edit/"]').first();
      await expect(editLink, 'No real Driver Edit action is available in the list').toBeVisible();
      const editResponsePromise = page.waitForResponse((candidate) =>
        /\/admin\/users\/delivery-man\/edit\/\d+$/.test(new URL(candidate.url()).pathname)
        && candidate.request().method() === 'GET'
      );
      await editLink.click();
      const editResponse = await editResponsePromise;
      expect(editResponse.status()).toBe(200);
      await page.waitForLoadState('domcontentloaded');
      await expect(page).toHaveURL(/\/admin\/users\/delivery-man\/edit\/\d+$/);
      actionLog.push({ action: 'open-real-driver-edit-through-ui', url: page.url(), status: editResponse.status() });

      const body = page.locator('body');
      await expect(body).toContainText(/Update Deliveryman|Vehicle.*Trailer.*Capability/i);
      await expect(body).not.toContainText(FORBIDDEN);
      await expect(page.locator('input[name="f_name"]')).toBeVisible();
      await expect(page.locator('input[name="email"]')).toBeVisible();
      await expect(page.locator('input[name="phone"]')).toBeVisible();
      await expect(page.locator('select[name="vehicle_type"]')).toBeVisible();
      await expect(page.locator('select[name="vehicle_type"] option')).toHaveCount(17);
      await expect(page.locator('select[name="trailer_type"]')).toHaveCount(1);
      await expect(page.locator('select[name="hitch_type"]')).toHaveCount(1);
      await expect(page.locator('select[name="cdl_status"]')).toHaveCount(1);
      await assertNoOverflow(page);

      const sameOriginFiveHundreds = networkFailures.filter((failure) => {
        try {
          return new URL(failure.url).origin === new URL(page.url()).origin
            && /HTTP 5\d\d/.test(failure.error);
        } catch {
          return false;
        }
      });
      expect(sameOriginFiveHundreds).toEqual([]);
      expect(consoleMessages.filter((message) => FORBIDDEN.test(message.text))).toEqual([]);
      await page.screenshot({ path: path.join(evidenceDir, 'driver-edit.png'), fullPage: true });
    } catch (error) {
      fs.writeFileSync(path.join(evidenceDir, 'driver-edit-failure.html'), await page.content());
      await page.screenshot({ path: path.join(evidenceDir, 'driver-edit-failure.png'), fullPage: true });
      throw error;
    } finally {
      fs.writeFileSync(path.join(evidenceDir, 'console.json'), JSON.stringify(consoleMessages, null, 2));
      fs.writeFileSync(path.join(evidenceDir, 'network-failures.json'), JSON.stringify(networkFailures, null, 2));
      fs.writeFileSync(path.join(evidenceDir, 'actions.json'), JSON.stringify(actionLog, null, 2));
      await context.tracing.stop({ path: path.join(evidenceDir, 'trace.zip') }).catch(() => {});
      await context.close().catch(() => {});
    }
  });
}
