// @ts-check
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const ADMIN_EMAIL = process.env.ADMIN_TEST_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_TEST_PASSWORD;
const EVIDENCE_ROOT = process.env.E2E_EVIDENCE_DIR
  || path.join(os.tmpdir(), 'urban-goodz-admin-ai-operations');
const FORBIDDEN = /{{|}}|@endif|@foreach|@php|ErrorException|Undefined variable|Undefined offset|Trying to access|SQLSTATE\[|Stack trace:|Whoops, looks like something went wrong/i;

async function loginThroughAdminPage(page) {
  if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
    throw new Error('Missing secure ADMIN_TEST_EMAIL or ADMIN_TEST_PASSWORD.');
  }

  const response = await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
  expect(response?.status()).toBe(200);
  await page.locator('#form-id #signinSrEmail').fill(ADMIN_EMAIL);
  await page.locator('#form-id #signupSrPassword').fill(ADMIN_PASSWORD);

  const customCaptcha = page.locator('#custome_recaptcha');
  if (await customCaptcha.count()) {
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
    expect(await customCaptcha.inputValue(), 'Custom CAPTCHA is not prefilled').not.toBe('');
  } else if (await page.locator('.g-recaptcha, iframe[src*="recaptcha"]').count()) {
    throw new Error('Interactive reCAPTCHA requires an approved authenticated state.');
  }

  const loginResponse = page.waitForResponse((candidate) =>
    new URL(candidate.url()).pathname.endsWith('/login_submit')
      && candidate.request().method() === 'POST'
  );
  await page.locator('#signInBtn, button[type="submit"], input[type="submit"]').first().click();
  expect((await loginResponse).status()).toBeLessThan(400);
  await page.waitForLoadState('domcontentloaded');
  await expect(page).not.toHaveURL(/\/login\/admin/);
}

async function assertHealthy(page) {
  await expect(page.locator('body')).not.toContainText(FORBIDDEN);
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
  test(`Admin AI controls are permission-safe on ${device.name}`, async ({ browser }, testInfo) => {
    test.setTimeout(180000);
    const evidenceDir = path.join(EVIDENCE_ROOT, device.name);
    fs.mkdirSync(evidenceDir, { recursive: true });

    const context = await browser.newContext({ viewport: device.viewport, recordVideo: { dir: evidenceDir } });
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
      await loginThroughAdminPage(page);
      await page.goto('/admin', { waitUntil: 'domcontentloaded' });

      const copilotLink = page.locator('a[href$="/admin/urban-goodz/ai-copilot"]').first();
      await expect(copilotLink).toBeVisible();
      await copilotLink.click();
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('h1')).toContainText(/AI Ops Copilot/i);
      expect(await page.locator('form[action$="/admin/urban-goodz/ai-copilot/generate"][method="POST"]').count()).toBeGreaterThanOrEqual(1);
      await expect(page.locator('a[href$="/admin/urban-goodz/ai-copilot/generate"]')).toHaveCount(0);
      const blockedGet = await page.request.get('/admin/urban-goodz/ai-copilot/generate', { maxRedirects: 0 });
      expect(blockedGet.status()).toBe(405);
      actionLog.push({ action: 'verify-copilot-post-only-generation', status: blockedGet.status() });
      await assertHealthy(page);

      await page.goto('/admin', { waitUntil: 'domcontentloaded' });
      const operationsLink = page.locator('a[href$="/admin/urban-goodz/ai-operations"]').first();
      await expect(operationsLink).toHaveCount(1);
      await operationsLink.evaluate((element) => element.click());
      await page.waitForLoadState('domcontentloaded');
      await expect(page.locator('h1')).toContainText(/AI Operations Center/i);
      await assertHealthy(page);
      actionLog.push({ action: 'open-ai-operations-through-admin-ui', url: page.url() });

      await page.goto('/admin/urban-goodz/ai-operations/feature-controls', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('form[action$="/feature-controls"][method="POST"]')).toHaveCount(1);
      await expect(page.locator('form[action$="/feature-controls"] input[name="_token"]')).toHaveCount(1);
      await assertHealthy(page);
      actionLog.push({ action: 'verify-feature-control-csrf-form', result: 'visible' });

      await page.screenshot({ path: path.join(evidenceDir, 'admin-ai-operations.png'), fullPage: true });
      expect(consoleMessages.filter((message) => FORBIDDEN.test(message.text))).toEqual([]);
      expect(networkFailures.filter((failure) => /HTTP 5\d\d/.test(failure.error))).toEqual([]);
    } catch (error) {
      fs.writeFileSync(path.join(evidenceDir, 'failure.html'), await page.content());
      await page.screenshot({ path: path.join(evidenceDir, 'failure.png'), fullPage: true });
      throw error;
    } finally {
      fs.writeFileSync(path.join(evidenceDir, 'console.json'), JSON.stringify(consoleMessages, null, 2));
      fs.writeFileSync(path.join(evidenceDir, 'network-failures.json'), JSON.stringify(networkFailures, null, 2));
      fs.writeFileSync(path.join(evidenceDir, 'actions.json'), JSON.stringify(actionLog, null, 2));
      await context.tracing.stop({ path: path.join(evidenceDir, 'trace.zip') }).catch(() => {});
      await context.close().catch(() => {});
      testInfo.annotations.push({ type: 'evidence', description: evidenceDir });
    }
  });
}
