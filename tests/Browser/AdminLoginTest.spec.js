// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Admin login / dashboard authorization regression suite.
 *
 * No credentials are hardcoded here. This is a certification gate: the
 * credentialed tests FAIL (not skip) if the required environment variables
 * are absent, so a green run is proof that login/authorization actually
 * ran, never a silently-skipped no-op.
 *
 * Required:
 *   ADMIN_TEST_EMAIL / ADMIN_TEST_PASSWORD
 *     - an Admin account with urban_goodz_view permission (role_id 1 or an
 *       admin_role whose modules include "urban_goodz_view").
 *   ADMIN_RESTRICTED_TEST_EMAIL / ADMIN_RESTRICTED_TEST_PASSWORD
 *     - an Admin account whose role does NOT include urban_goodz_view.
 *
 * The custom-CAPTCHA "approved test mechanism" relies on APP_MODE=dev on the
 * target environment, which causes the server to pre-fill the custom-CAPTCHA
 * input with the correct session phrase (see resources/views/admin-views/
 * partials/_recaptcha.blade.php). playwright.config.js refuses to run this
 * suite against the production hostname unless ALLOW_PRODUCTION_BASE_URL=true
 * is explicitly set -- run it against a dev/staging deployment instead.
 *
 * Traces are disabled for this file specifically: form fills (including the
 * password field) can otherwise be captured in retained trace/video
 * artifacts. Screenshots-on-failure remain enabled; treat any retained
 * artifact from this suite as secret-bearing regardless.
 */

test.use({ trace: 'off' });

const ADMIN_EMAIL = process.env.ADMIN_TEST_EMAIL;
const ADMIN_PASSWORD = process.env.ADMIN_TEST_PASSWORD;
const RESTRICTED_ADMIN_EMAIL = process.env.ADMIN_RESTRICTED_TEST_EMAIL;
const RESTRICTED_ADMIN_PASSWORD = process.env.ADMIN_RESTRICTED_TEST_PASSWORD;

function requireCredentials(...values) {
  for (const value of values) {
    if (!value) {
      throw new Error(
        'Missing required test credentials: set ADMIN_TEST_EMAIL, ADMIN_TEST_PASSWORD, ' +
        'ADMIN_RESTRICTED_TEST_EMAIL, and ADMIN_RESTRICTED_TEST_PASSWORD before running this suite. ' +
        'This is a certification gate and must fail, not skip, when credentials are absent.'
      );
    }
  }
}

// The card header rendered only inside the role_id===1 branch of
// admin-views/dashboard.blade.php -- the one DOM element that distinguishes
// an authorized vs. unauthorized Admin on the post-login dashboard.
function urbanGoodzPanel(page) {
  return page.locator('.card-header-title', { hasText: 'Urban Goodz Command Center' });
}

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

    const emailInput = page.locator('input[name="email"]');
    const passwordInput = page.locator('input[name="password"]');

    // Assert the fields actually declare themselves required, rather than
    // inferring it from a post-submit URL check.
    await expect(emailInput).toHaveAttribute('required', /.*/);
    await expect(passwordInput).toHaveAttribute('required', /.*/);

    await page.locator('#signInBtn').click();
    await page.waitForTimeout(300);

    // Native HTML5 validation must block the submission client-side.
    const emailValidationMessage = await emailInput.evaluate((el) => el.validationMessage);
    expect(emailValidationMessage).not.toBe('');
    expect(page.url()).toContain('/login/admin');
  });

  test('omitted CAPTCHA is rejected', async ({ page }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);
    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'omitted' });

    expect(page.url()).toContain('/login/admin');
    const body = await page.content();
    expect(body.toLowerCase()).toContain('captcha');

    const response = await page.goto(page.url());
    expect(response.status()).not.toBe(500);
  });

  test('wrong CAPTCHA is rejected with a visible error and no session is created', async ({ page }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);
    const response = await page.goto('/login/admin', { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);

    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'wrong' });

    expect(response.status()).not.toBe(500);
    expect(page.url()).toContain('/login/admin');

    const body = await page.content();
    expect(body.toLowerCase()).toMatch(/captcha/);

    // No session should have been created: the protected dashboard route
    // must still bounce back to the login page.
    const dashboardAttempt = await page.goto('/admin', { waitUntil: 'domcontentloaded' });
    expect(dashboardAttempt.status()).not.toBe(500);
    expect(page.url()).toContain('/login');
  });

  test('invalid credentials are rejected', async ({ page }) => {
    await submitLogin(page, 'nonexistent-regression@urban-goodz.test', 'wrong-password', { captcha: 'valid' });

    expect(page.url()).toContain('/login/admin');
    const body = await page.content();
    expect(body.toLowerCase()).toMatch(/does not match|failed|error/);
  });

  test('valid Admin login reaches the authenticated dashboard', async ({ page }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);
    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'valid' });

    await expect(page).toHaveURL(/\/admin(\/|$)/);
    const response = await page.goto(page.url());
    expect(response.status()).toBe(200);
    await expect(page.locator('body')).not.toContainText('Exception');
    await expect(page.locator('body')).not.toContainText('Stack trace');
  });

  test('Admin with urban_goodz_view permission sees the Urban Goodz Command Center panel', async ({ page }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);
    await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha: 'valid' });

    await expect(page).toHaveURL(/\/admin(\/|$)/);
    await expect(urbanGoodzPanel(page)).toBeVisible();
  });

  test('Admin without urban_goodz_view permission does not see the Urban Goodz Command Center panel', async ({ page }) => {
    requireCredentials(RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD);
    await submitLogin(page, RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD, { captcha: 'valid' });

    await expect(page).toHaveURL(/\/admin(\/|$)/);
    const response = await page.goto(page.url());
    expect(response.status()).toBe(200);
    await expect(urbanGoodzPanel(page)).toHaveCount(0);
  });

  test('session survives a refresh and logout invalidates it', async ({ page }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);
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
