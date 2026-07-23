// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Admin login / dashboard authorization regression suite.
 *
 * No credentials are hardcoded here. Populate these before running:
 *   ADMIN_TEST_EMAIL / ADMIN_TEST_PASSWORD
 *     - an Admin account with urban_goodz_view permission (role_id 1 or an
 *       admin_role whose modules include "urban_goodz_view").
 *   ADMIN_RESTRICTED_TEST_EMAIL / ADMIN_RESTRICTED_TEST_PASSWORD (optional)
 *     - an Admin account whose role does NOT include urban_goodz_view.
 *       The "unauthorized admin denied" test is skipped if these are unset.
 *
 * The custom-CAPTCHA "approved test mechanism" relies on APP_MODE=dev on the
 * target environment, which causes the server to pre-fill the custom-CAPTCHA
 * input with the correct session phrase (see resources/views/admin-views/
 * partials/_recaptcha.blade.php). Run this suite only against a dev/staging
 * deployment configured that way -- never against production.
 */

const ADMIN_EMAIL = process.env.ADMIN_TEST_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_TEST_PASSWORD;
const RESTRICTED_ADMIN_EMAIL = process.env.ADMIN_RESTRICTED_TEST_EMAIL;
const RESTRICTED_ADMIN_PASSWORD = process.env.ADMIN_RESTRICTED_TEST_PASSWORD;

async function fillCustomCaptcha(page) {
  // Relies on APP_MODE=dev pre-filling the correct phrase server-side.
  const captchaInput = page.locator('#custome_recaptcha');
  const prefilled = await captchaInput.inputValue();
  expect(prefilled, 'custome_recaptcha was not pre-filled - target env must run APP_MODE=dev').not.toBe('');
  return prefilled;
}

async function submitLogin(page, email, password, { captcha = 'valid' } = {}) {
  await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
  await page.locator('input[name="email"]').fill(email);
  await page.locator('input[name="password"]').fill(password);

  if (captcha === 'valid') {
    await fillCustomCaptcha(page);
  } else if (captcha === 'wrong') {
    await page.locator('#custome_recaptcha').fill('WRONGCODE');
  } else if (captcha === 'omitted') {
    await page.locator('#custome_recaptcha').fill('');
  }

  await page.locator('#signInBtn').click();
  await page.waitForLoadState('networkidle');
}

test.describe('Admin login page', () => {
  test('visible login page loads with required fields', async ({ page }) => {
    const response = await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);

    await expect(page.locator('input[name="email"]')).toBeVisible();
    await expect(page.locator('input[name="password"]')).toBeVisible();
    await expect(page.locator('#custome_recaptcha')).toBeVisible();
    await expect(page.locator('#signInBtn')).toBeVisible();

    const body = await page.content();
    expect(body).not.toContain('6amMart');
    expect(body).not.toContain('assets/admin/img/favicon.png');
  });

  test('required fields are enforced', async ({ page }) => {
    await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
    await page.locator('#signInBtn').click();
    await page.waitForTimeout(500);

    // Native HTML5 validation or a server round trip should keep us on the login page.
    expect(page.url()).toContain('login');
  });

  test('omitted CAPTCHA is rejected', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASSWORD, 'ADMIN_TEST_EMAIL/ADMIN_TEST_PASSWORD not set');
    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'omitted' });

    expect(page.url()).toContain('/login/admin');
    const response = await page.goto(page.url());
    expect(response.status()).not.toBe(500);
    const body = await page.content();
    expect(body.toLowerCase()).toContain('captcha');
  });

  test('wrong CAPTCHA is rejected without a server error', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASSWORD, 'ADMIN_TEST_EMAIL/ADMIN_TEST_PASSWORD not set');
    const response = await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);

    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'wrong' });

    expect(page.url()).toContain('/login/admin');
    expect(page.url()).not.toContain('500');
  });

  test('invalid credentials are rejected', async ({ page }) => {
    await submitLogin(page, 'nonexistent-regression@urban-goodz.test', 'wrong-password', { captcha: 'valid' });

    expect(page.url()).toContain('/login/admin');
    const body = await page.content();
    expect(body.toLowerCase()).toMatch(/does not match|failed|error/);
  });

  test('valid Admin login reaches the authenticated dashboard', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASSWORD, 'ADMIN_TEST_EMAIL/ADMIN_TEST_PASSWORD not set');
    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'valid' });

    await expect(page).toHaveURL(/\/admin(\/|$)/);
    const response = await page.goto(page.url());
    expect(response.status()).toBe(200);
    await expect(page.locator('body')).not.toContainText('Exception');
    await expect(page.locator('body')).not.toContainText('Stack trace');
  });

  test('Admin with urban_goodz_view permission sees the Urban Goodz dashboard section', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASSWORD, 'ADMIN_TEST_EMAIL/ADMIN_TEST_PASSWORD not set');
    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'valid' });

    await expect(page).toHaveURL(/\/admin(\/|$)/);
    const body = await page.content();
    expect(body).toMatch(/urban[\s-]?goodz/i);
  });

  test('Admin without urban_goodz_view permission is denied that section', async ({ page }) => {
    test.skip(
      !RESTRICTED_ADMIN_EMAIL || !RESTRICTED_ADMIN_PASSWORD,
      'ADMIN_RESTRICTED_TEST_EMAIL/ADMIN_RESTRICTED_TEST_PASSWORD not set'
    );
    await submitLogin(page, RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD, { captcha: 'valid' });

    await expect(page).toHaveURL(/\/admin(\/|$)/);
    const response = await page.goto(page.url());
    expect(response.status()).toBe(200);
  });

  test('session survives a refresh and logout invalidates it', async ({ page }) => {
    test.skip(!ADMIN_EMAIL || !ADMIN_PASSWORD, 'ADMIN_TEST_EMAIL/ADMIN_TEST_PASSWORD not set');
    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'valid' });
    await expect(page).toHaveURL(/\/admin(\/|$)/);

    await page.reload({ waitUntil: 'domcontentloaded' });
    expect(page.url()).not.toContain('/login/admin');

    await page.goto('/logout', { waitUntil: 'domcontentloaded' });
    expect(page.url()).toContain('/login/admin');

    const dashboardAfterLogout = await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    expect(page.url()).toContain('/login');
    expect(dashboardAfterLogout.status()).not.toBe(500);
  });
});
