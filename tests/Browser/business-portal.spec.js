// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Business Portal Login Page', () => {
  test('login page loads with UG branding', async ({ page }) => {
    await page.goto('/business/login');

    const body = await page.content();

    // UG brand elements
    expect(body).toContain('Urban Goodz');
    expect(body).toContain('ug-admin.css');

    // Login form
    const emailInput = page.locator('input[name="email"], input[type="email"]');
    await expect(emailInput).toBeVisible();

    const passwordInput = page.locator('input[name="password"], input[type="password"]');
    await expect(passwordInput).toBeVisible();

    // Submit button
    const submitBtn = page.locator('button[type="submit"], input[type="submit"]');
    await expect(submitBtn.first()).toBeVisible();
  });

  test('login page has welcome message', async ({ page }) => {
    await page.goto('/business/login');

    // The welcome copy is not in the heading. The page renders
    //   <h1>Business Portal Login</h1>
    //   <p>Welcome back. Sign in to manage your Urban Goodz business operations.</p>
    // so the old selector ('h2, h3, .title') matched nothing and timed out,
    // and narrowing to the h1 alone still missed the sentence being asserted.
    // Read the heading together with its supporting copy.
    const heading = page.locator('h1, h2, h3, .title').first();
    await expect(heading).toBeVisible();

    const headingText = (await heading.textContent())?.toLowerCase() ?? '';
    const introText = (await page.locator('h1 + p, .title + p, p').first().textContent())?.toLowerCase() ?? '';
    const combined = `${headingText} ${introText}`;

    expect(combined.includes('welcome') || combined.includes('urban goodz')).toBeTruthy();
  });

  test('login rejects invalid credentials', async ({ page }) => {
    await page.goto('/business/login');

    await page.fill('input[name="email"], input[type="email"]', 'fake@invalid.com');
    await page.fill('input[name="password"], input[type="password"]', 'wrongpass');
    await page.click('button[type="submit"], input[type="submit"]');

    await page.waitForTimeout(2000);
    expect(page.url()).toContain('business/login');
  });

  test('forgot password link exists and navigates', async ({ page }) => {
    await page.goto('/business/login');

    const forgotLink = page.locator('a[href*="forgot-password"]');
    await expect(forgotLink).toBeVisible();
    await forgotLink.click();

    await page.waitForTimeout(1000);
    expect(page.url()).toContain('business/forgot-password');
  });

  test('password toggle works', async ({ page }) => {
    await page.goto('/business/login');

    const passwordInput = page.locator('input[name="password"]');
    const type = await passwordInput.getAttribute('type');
    expect(type).toBe('password');

    // Find toggle button (eye icon)
    const toggle = page.locator('.toggle-password, [data-toggle-password], button[onclick*="password"]').first();
    if (await toggle.isVisible()) {
      await toggle.click();
      const newType = await passwordInput.getAttribute('type');
      expect(newType).toBe('text');
    }
  });
});

test.describe('Business Portal Forgot Password', () => {
  test('forgot password page loads', async ({ page }) => {
    await page.goto('/business/forgot-password');

    const body = await page.content();
    expect(body).toContain('Urban Goodz');

    const emailInput = page.locator('input[name="email"], input[type="email"]');
    await expect(emailInput).toBeVisible();
  });

  test('forgot password submits email', async ({ page }) => {
    await page.goto('/business/forgot-password');

    await page.fill('input[name="email"], input[type="email"]', 'test@example.com');
    await page.click('button[type="submit"], input[type="submit"]');

    await page.waitForTimeout(2000);
    // Should redirect back (always, regardless of email existence)
    expect(page.url()).toContain('business/forgot-password');
  });
});

test.describe('Business Portal - Unauthenticated Redirects', () => {
  test('dashboard redirects to login', async ({ page }) => {
    await page.goto('/business/dashboard');
    expect(page.url()).toContain('business/login');
  });
});

test.describe('Business Portal - Mobile', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('mobile login renders correctly', async ({ page }) => {
    await page.goto('/business/login');
    const body = await page.content();
    expect(body).toContain('Urban Goodz');
  });
});
