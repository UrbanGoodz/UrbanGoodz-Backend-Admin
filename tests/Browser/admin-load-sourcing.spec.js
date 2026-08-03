// @ts-check
'use strict';

const { test, expect } = require('@playwright/test');
const fs = require('fs');
const os = require('os');
const path = require('path');

const ADMIN_EMAIL = process.env.ADMIN_TEST_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_TEST_PASSWORD;
const EVIDENCE_ROOT = process.env.E2E_EVIDENCE_DIR
  || path.join(os.tmpdir(), 'urban-goodz-admin-load-sourcing');

const FORBIDDEN = /{{|}}|@endif|@foreach|@php|ErrorException|Undefined variable|Undefined offset|Trying to access|SQLSTATE\[|Stack trace:|Route \[.*] not defined|Whoops, looks like something went wrong/i;

const PAGE_PATHS = [
  '/admin/urban-goodz/load-sourcing/sources',
  '/admin/urban-goodz/load-sourcing/search',
  '/admin/urban-goodz/load-sourcing/saved-searches',
  '/admin/urban-goodz/load-sourcing/sourced-loads',
  '/admin/urban-goodz/load-sourcing/recommendations',
  '/admin/urban-goodz/load-sourcing/sync-runs',
  '/admin/urban-goodz/load-sourcing/errors',
  '/admin/urban-goodz/load-sourcing/settings',
];

function requireCredentials() {
  if (!ADMIN_EMAIL || !ADMIN_PASSWORD) {
    throw new Error(
      'Missing ADMIN_TEST_EMAIL or ADMIN_TEST_PASSWORD. The Load Sourcing production gate must fail, not skip, when secure credentials are unavailable.'
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
  let submittedCaptcha = '';
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
    submittedCaptcha = await customCaptcha.inputValue();
    expect(submittedCaptcha, 'Custom CAPTCHA is not prefilled by an approved test environment').not.toBe('');
  } else if (await page.locator('.g-recaptcha, iframe[src*="recaptcha"]').count()) {
    throw new Error('Interactive reCAPTCHA requires an approved authenticated state or test environment.');
  }

  const sessionCookies = (await page.context().cookies()).filter((cookie) => /session/i.test(cookie.name));
  expect(sessionCookies.length, 'Admin login GET did not establish a session cookie').toBeGreaterThan(0);
  expect(sessionCookies.every((cookie) => !cookie.secure), 'Local HTTP session cookie was marked Secure').toBe(true);

  const [loginRequest, loginResponse] = await Promise.all([
    page.waitForRequest((candidate) => {
      const url = new URL(candidate.url());
      return url.pathname.endsWith('/login_submit') && candidate.method() === 'POST';
    }),
    page.waitForResponse((candidate) => {
      const url = new URL(candidate.url());
      return url.pathname.endsWith('/login_submit') && candidate.request().method() === 'POST';
    }),
    page.locator('#signInBtn, button[type="submit"], input[type="submit"]').first().click(),
  ]);
  expect(new URL(loginRequest.url()).origin, 'Login form posted to a different origin').toBe(new URL(page.url()).origin);
  expect(new URLSearchParams(loginRequest.postData() || '').get('custome_recaptcha')).toBe(submittedCaptcha);
  const requestCookie = await loginRequest.headerValue('cookie');
  expect(requestCookie, 'Login POST did not carry the session cookie').toMatch(/session/i);
  expect(loginResponse.status()).toBeLessThan(400);
  await page.waitForLoadState('domcontentloaded');
  if (/\/login\/admin/.test(page.url())) {
    const visibleErrors = await page.locator('#toast-container .toast-message').allInnerTexts();
    const sourceErrors = [...(await page.content()).matchAll(/toastr\.error\('([^']+)'/g)].map((match) => match[1]);
    const errors = [...visibleErrors, ...sourceErrors].map((message) => message.trim()).filter(Boolean);
    throw new Error(`Admin login was rejected: ${errors.join(' | ') || 'no rendered error'}`);
  }
  await expect(page).not.toHaveURL(/\/login\/admin/);
}

async function clickUiLink(page, pathName, expectedPath = pathName) {
  const link = page.locator(`a[href$="${pathName}"]`).first();
  await expect(link, `UI link is missing for ${pathName}`).toHaveCount(1);
  const navigation = page.waitForResponse((candidate) => {
    const url = new URL(candidate.url());
    return url.pathname === expectedPath && candidate.request().method() === 'GET';
  });
  const click = link.isVisible().then((visible) => visible
    ? link.click()
    : link.evaluate((element) => element.click()));
  const response = await navigation;
  expect(response.status(), `UI navigation failed for ${expectedPath}`).toBe(200);
  await click;
  await page.waitForLoadState('domcontentloaded');
  await expect(page).toHaveURL(new RegExp(`${expectedPath.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?:\\?.*)?$`));
}

async function assertHealthyPage(page, pathName) {
  const body = page.locator('body');
  await expect(body).not.toContainText(FORBIDDEN);
  await expect(body).toContainText(/Load Sourcing|Sources|Search|Loads|Recommendations|Sync|Errors|Settings/i);
  expect((await page.content()).trimStart().startsWith('{')).toBe(false);

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
  test(`live Admin Load Sourcing pages and controls render on ${device.name}`, async ({ browser }, testInfo) => {
    test.setTimeout(240000);
    const evidenceDir = path.join(EVIDENCE_ROOT, device.name);
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
      await clickUiLink(
        page,
        '/admin/urban-goodz/load-sourcing',
        '/admin/urban-goodz/load-sourcing/overview'
      );
      actionLog.push({ action: 'open-load-sourcing-through-admin-ui', url: page.url() });

      for (const pathName of PAGE_PATHS) {
        await clickUiLink(page, pathName);
        await assertHealthyPage(page, pathName);
        actionLog.push({ action: 'open-subpage-through-ui', path: pathName, url: page.url() });
      }

      await page.goto('/admin/urban-goodz/load-sourcing/search', { waitUntil: 'domcontentloaded' });
      await expect(page.locator('form#loadSearchForm')).toBeVisible();
      await expect(page.locator('input[name="origin_city"], input[name="origin_state"]').first()).toBeVisible();
      actionLog.push({ action: 'verify-search-form-controls', result: 'visible' });

      await page.screenshot({ path: path.join(evidenceDir, 'load-sourcing.png'), fullPage: true });
      expect(consoleMessages.filter((message) => FORBIDDEN.test(message.text))).toEqual([]);
      expect(networkFailures.filter((failure) => {
        try {
          return new URL(failure.url).origin === new URL(page.url()).origin
            && /HTTP 5\d\d/.test(failure.error);
        } catch {
          return false;
        }
      })).toEqual([]);
    } catch (error) {
      fs.writeFileSync(path.join(evidenceDir, 'load-sourcing-failure.html'), await page.content());
      await page.screenshot({ path: path.join(evidenceDir, 'load-sourcing-failure.png'), fullPage: true });
      throw error;
    } finally {
      fs.writeFileSync(path.join(evidenceDir, 'console.json'), JSON.stringify(consoleMessages, null, 2));
      fs.writeFileSync(path.join(evidenceDir, 'network-failures.json'), JSON.stringify(networkFailures, null, 2));
      fs.writeFileSync(path.join(evidenceDir, 'actions.json'), JSON.stringify(actionLog, null, 2));
      await context.tracing.stop({ path: path.join(evidenceDir, 'trace.zip') });
      await context.close();
      testInfo.annotations.push({ type: 'evidence', description: evidenceDir });
    }
  });
}
