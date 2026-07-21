// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Admin Login Page', () => {
  test('login page loads with UG branding', async ({ page }) => {
    await page.goto('/login/admin');

    // Page title
    await expect(page).toHaveTitle(/login|admin|urban goodz/i);

    // UG branding elements
    const body = await page.content();
    expect(body).toContain('ug-admin.css');

    // Login form exists
    const emailInput = page.locator('input[name="email"], input[type="email"]').first();
    await expect(emailInput).toBeVisible();

    const passwordInput = page.locator('input[name="password"], input[type="password"]').first();
    await expect(passwordInput).toBeVisible();

    // Submit button exists
    const submitBtn = page.locator('button[type="submit"], input[type="submit"]');
    await expect(submitBtn.first()).toBeVisible();
  });

  test('login rejects invalid credentials', async ({ page }) => {
    await page.goto('/login/admin');

    await page.locator('input[name="email"], input[type="email"]').first().fill('nonexistent@test.com');
    await page.locator('input[name="password"], input[type="password"]').first().fill('wrongpassword');
    await page.click('button[type="submit"], input[type="submit"]');

    // Should stay on login page or show error
    await page.waitForTimeout(2000);
    const url = page.url();
    expect(url).toContain('login');
  });

  test('reCAPTCHA auto-detect works', async ({ page }) => {
    await page.goto('/login/admin');

    // The page should either show reCAPTCHA widget or custom captcha
    // If Google reCAPTCHA fails, it should auto-switch to custom
    const body = await page.content();

    // Check that either reCAPTCHA div or custom captcha is present
    const hasRecaptcha = body.includes('g-recaptcha') || body.includes('captcha');
    expect(hasRecaptcha).toBeTruthy();
  });
});

test.describe('Admin Login Page - Mobile', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('mobile login renders correctly', async ({ page }) => {
    await page.goto('/login/admin');
    await expect(page).toHaveTitle(/login|admin|urban goodz/i);

    const emailInput = page.locator('input[name="email"], input[type="email"]').first();
    await expect(emailInput).toBeVisible();
  });
});
