// @ts-check
const { test, expect } = require('@playwright/test');
const crypto = require('crypto');
const fs = require('fs');
const path = require('path');

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
 *     - a non-primary Admin account (role_id != 1) whose admin_role.modules
 *       includes "urban_goodz_view".
 *   ADMIN_RESTRICTED_TEST_EMAIL / ADMIN_RESTRICTED_TEST_PASSWORD
 *     - a non-primary Admin account whose admin_role is otherwise identical
 *       but does NOT include "urban_goodz_view".
 *   These two accounts must differ ONLY in that module permission, so the
 *   authorization tests prove module:urban_goodz_view (ModulePermissionMiddleware
 *   / Helpers::module_permission_check) itself, not the unrelated role_id===1
 *   gate that controls the dashboard's "Urban Goodz Command Center" panel.
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

const ROLE_FIXTURE_ATTESTATION = path.join(
  __dirname, '..', '..', 'docs', 'qa', 'evidence', 'role-fixture-verification.json'
);

/** Truncated SHA-256, matching accountRef() in verify-admin-role-fixture.php. */
function accountRef(email) {
  return crypto.createHash('sha256').update(String(email).trim().toLowerCase()).digest('hex').slice(0, 16);
}

/**
 * Requires the read-only attestation produced from the staging database by
 * scripts/verify-admin-role-fixture.php. The browser cannot read
 * admin_roles.modules, so permissions that render no link are invisible to
 * it; without this, "the roles differ only by urban_goodz_view" would be an
 * assumption rather than a verified precondition.
 */
function requireRoleFixtureAttestation() {
  if (!fs.existsSync(ROLE_FIXTURE_ATTESTATION)) {
    throw new Error(
      `Missing role fixture attestation at ${ROLE_FIXTURE_ATTESTATION}. Run on the staging host:\n` +
      '  php scripts/verify-admin-role-fixture.php --authorized=<email> --restricted=<email>\n' +
      'The authorization tests cannot certify the urban_goodz_view boundary without it.'
    );
  }

  const att = JSON.parse(fs.readFileSync(ROLE_FIXTURE_ATTESTATION, 'utf8'));

  expect(att.verdict, `role fixture attestation failed: ${JSON.stringify(att.problems)}`).toBe('PASS');
  expect(att.symmetric_module_difference, 'stored roles differ by more than urban_goodz_view')
    .toEqual(['urban_goodz_view']);
  expect(att.authorized.role_id_is_primary, 'authorized fixture is the primary Admin').toBe(false);
  expect(att.restricted.role_id_is_primary, 'restricted fixture is the primary Admin').toBe(false);

  // The attestation must describe the same accounts this run is testing.
  expect(att.authorized.account_ref, 'attestation does not match ADMIN_TEST_EMAIL')
    .toBe(accountRef(ADMIN_EMAIL));
  expect(att.restricted.account_ref, 'attestation does not match ADMIN_RESTRICTED_TEST_EMAIL')
    .toBe(accountRef(RESTRICTED_ADMIN_EMAIL));
}

// Gated purely by the `module:urban_goodz_view` route-group middleware
// (routes/admin.php) -- UrbanGoodzBusinessClientController@index has no
// internal role_id check of its own, unlike the dashboard panel or
// UrbanGoodzAdminController@index. This is the actual permission boundary
// under review, independent of the primary-admin role_id gate.
const PROTECTED_MODULE_ROUTE = '/admin/urban-goodz/business-clients';
const PROTECTED_MODULE_HEADING = 'Business Clients';

// Rendered only inside the `role_id == 1` branch of
// admin-views/dashboard.blade.php. Its ABSENCE is how we prove an account
// is non-primary, so that the module-permission tests cannot be silently
// satisfied by a primary Admin short-circuiting module_permission_check().
const PRIMARY_ONLY_DASHBOARD_MARKER = 'Urban Goodz Command Center';

const GENERIC_LOGIN_ERROR = 'Invalid email or password.';

async function fillCustomCaptcha(page) {
  // Relies on APP_MODE=dev pre-filling the correct phrase server-side.
  const captchaInput = page.locator('#custome_recaptcha');
  const prefilled = await captchaInput.inputValue();
  expect(prefilled, 'custome_recaptcha was not pre-filled - target env must run APP_MODE=dev').not.toBe('');
  return prefilled;
}

/**
 * Submits the login form and returns the actual POST /login_submit
 * response, so callers can assert on the real submission rather than on a
 * separate GET issued before or after it.
 */
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

  const [postResponse] = await Promise.all([
    page.waitForResponse(
      (r) => new URL(r.url()).pathname.endsWith('/login_submit') && r.request().method() === 'POST'
    ),
    page.locator('#signInBtn').click(),
  ]);

  await page.waitForLoadState('networkidle');
  return postResponse;
}

/**
 * The complete set of error messages actually rendered to the user.
 *
 * The login Blade emits errors only through toastr.error(...) inside a
 * <script> block, so searching page.content() matches the script source and
 * passes even when the visible message has regressed. Reading the rendered
 * toast elements is the only assertion that reflects what a user sees.
 * Sorted so the comparison is set-equality rather than order-dependent.
 */
async function captureVisibleErrors(page) {
  const toasts = page.locator('#toast-container .toast-message');
  await expect(
    toasts.first(),
    'no toastr error element rendered; the login Blade surfaces errors only via toastr.error()'
  ).toBeVisible();
  const texts = await toasts.allInnerTexts();
  return texts.map((t) => t.trim()).filter(Boolean).sort();
}

/** Sorted, deduped set of same-origin link paths visible to this account. */
async function visibleAdminPaths(page) {
  const hrefs = await page.locator('a[href]').evaluateAll((nodes) =>
    nodes.map((n) => n.getAttribute('href'))
  );
  const paths = new Set();
  for (const href of hrefs) {
    if (!href) continue;
    try {
      paths.add(new URL(href, page.url()).pathname.replace(/\/+$/, ''));
    } catch {
      /* ignore unparseable hrefs (javascript:, #, mailto:) */
    }
  }
  return [...paths].sort();
}

async function loginAndOpenDashboard(page, email, password) {
  await submitLogin(page, email, password, { captcha: 'valid' });
  await expect(page).toHaveURL(/\/admin(\/|$)/);
  await page.goto('/admin', { waitUntil: 'domcontentloaded' });
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

  for (const captcha of ['omitted', 'wrong']) {
    test(`${captcha} CAPTCHA is rejected by the login POST and creates no session`, async ({ page }) => {
      requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);

      // Assert the status of the actual POST /login_submit response, not a
      // separate GET issued before or after it.
      const postResponse = await submitLogin(page, ADMIN_EMAIL, ADMIN_PASSWORD, { captcha });
      expect(postResponse.status()).not.toBe(500);
      expect(postResponse.status()).toBeLessThan(400);

      expect(page.url()).toContain('/login/admin');
      await expect(page.locator('body')).toContainText(/captcha/i);

      // No session may have been created: the protected dashboard route
      // must still bounce back to the login page.
      const dashboardAttempt = await page.goto('/admin', { waitUntil: 'domcontentloaded' });
      expect(dashboardAttempt.status()).not.toBe(500);
      expect(page.url()).toContain('/login');
    });
  }

  test('unknown email and wrong password show byte-identical visible errors', async ({ page }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);

    // Case 1: an account that does not exist.
    await submitLogin(page, 'nonexistent-regression@urban-goodz.test', 'wrong-password', { captcha: 'valid' });
    expect(page.url()).toContain('/login/admin');
    const unknownEmailErrors = await captureVisibleErrors(page);

    // Case 2: a real account with the wrong password.
    await submitLogin(page, ADMIN_EMAIL, 'definitely-not-the-right-password', { captcha: 'valid' });
    expect(page.url()).toContain('/login/admin');
    const wrongPasswordErrors = await captureVisibleErrors(page);

    // The two branches must be indistinguishable to the user: not merely
    // "each contains the generic string somewhere on the page", but the
    // same complete set of rendered error messages. An extra or differing
    // toast on one branch would itself be an enumeration signal.
    expect(unknownEmailErrors).toEqual(wrongPasswordErrors);
    expect(unknownEmailErrors).toEqual([GENERIC_LOGIN_ERROR]);

    for (const messages of [unknownEmailErrors, wrongPasswordErrors]) {
      expect(messages.join('\n')).not.toContain('Email does not match');
      expect(messages.join('\n')).not.toContain('Password does not match');
    }
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

  // Preflight: the two authorization tests below are only meaningful if the
  // fixture accounts really are non-primary and really differ only by
  // urban_goodz_view. A primary Admin short-circuits
  // Helpers::module_permission_check() via `role_id == 1` and would make the
  // "authorized" test pass without exercising the module permission at all.
  // This asserts that topology instead of assuming it from a comment.
  test('role fixture preflight: both accounts are non-primary and differ only by urban_goodz_view', async ({ browser }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD, RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD);

    // Authoritative check first, from stored admin_roles.modules rather than
    // from anything the browser can see. Produced on the staging host by
    // scripts/verify-admin-role-fixture.php.
    requireRoleFixtureAttestation();

    const authorizedContext = await browser.newContext();
    const restrictedContext = await browser.newContext();

    try {
      const authorizedPage = await authorizedContext.newPage();
      const restrictedPage = await restrictedContext.newPage();

      await loginAndOpenDashboard(authorizedPage, ADMIN_EMAIL, ADMIN_PASSWORD);
      await loginAndOpenDashboard(restrictedPage, RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD);

      // 1. Neither account may be the primary Admin (role_id == 1).
      await expect(
        authorizedPage.locator('body'),
        'ADMIN_TEST_EMAIL is a primary Admin (role_id=1); it cannot prove the urban_goodz_view boundary'
      ).not.toContainText(PRIMARY_ONLY_DASHBOARD_MARKER);
      await expect(
        restrictedPage.locator('body'),
        'ADMIN_RESTRICTED_TEST_EMAIL is a primary Admin (role_id=1)'
      ).not.toContainText(PRIMARY_ONLY_DASHBOARD_MARKER);

      // 2. Corroborating (NOT authoritative) browser-side signal. Reachable
      //    link paths only reveal permissions that render a link, so this
      //    cannot by itself prove the roles differ by exactly one module --
      //    that is what the attestation above establishes from stored data.
      const authorizedPaths = await visibleAdminPaths(authorizedPage);
      const restrictedPaths = await visibleAdminPaths(restrictedPage);

      const onlyAuthorized = authorizedPaths.filter((p) => !restrictedPaths.includes(p));
      const onlyRestricted = restrictedPaths.filter((p) => !authorizedPaths.includes(p));

      expect(
        onlyAuthorized.every((p) => p.includes('urban-goodz')),
        `Authorized-only paths outside urban-goodz (roles differ by more than urban_goodz_view): ${JSON.stringify(onlyAuthorized)}`
      ).toBe(true);
      expect(
        onlyRestricted.every((p) => p.includes('urban-goodz')),
        `Restricted-only paths outside urban-goodz: ${JSON.stringify(onlyRestricted)}`
      ).toBe(true);

      // 3. The permission difference must actually be present, otherwise
      //    both accounts are identical and the pair proves nothing.
      expect(
        onlyAuthorized.length,
        'Authorized and restricted accounts expose the same paths; the urban_goodz_view difference is not present'
      ).toBeGreaterThan(0);
    } finally {
      await authorizedContext.close();
      await restrictedContext.close();
    }
  });

  test('Admin with urban_goodz_view permission reaches the module-protected Urban Goodz route', async ({ page }) => {
    requireCredentials(ADMIN_EMAIL, ADMIN_PASSWORD);
    await loginAndOpenDashboard(page, ADMIN_EMAIL, ADMIN_PASSWORD);

    // Re-assert non-primary here so this test cannot pass in isolation with
    // a primary-Admin fixture.
    await expect(page.locator('body')).not.toContainText(PRIMARY_ONLY_DASHBOARD_MARKER);

    const response = await page.goto(PROTECTED_MODULE_ROUTE, { waitUntil: 'domcontentloaded' });
    expect(response.status()).toBe(200);
    expect(page.url()).toContain(PROTECTED_MODULE_ROUTE);
    await expect(page.locator('h1,h3', { hasText: PROTECTED_MODULE_HEADING })).toBeVisible();
  });

  test('Admin without urban_goodz_view permission is denied the module-protected Urban Goodz route', async ({ page }) => {
    requireCredentials(RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD);
    await loginAndOpenDashboard(page, RESTRICTED_ADMIN_EMAIL, RESTRICTED_ADMIN_PASSWORD);

    await expect(page.locator('body')).not.toContainText(PRIMARY_ONLY_DASHBOARD_MARKER);

    const response = await page.goto(PROTECTED_MODULE_ROUTE, { waitUntil: 'domcontentloaded' });
    expect(response.status()).not.toBe(500);

    // ModulePermissionMiddleware bounces denied requests away with
    // Toastr::error + back() -- the protected route, its heading, and its
    // record data must all be unreachable, regardless of where it lands.
    expect(page.url()).not.toContain(PROTECTED_MODULE_ROUTE);
    await expect(page.locator('h1,h3', { hasText: PROTECTED_MODULE_HEADING })).toHaveCount(0);
    await expect(page.locator('table tbody tr')).toHaveCount(0);
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
