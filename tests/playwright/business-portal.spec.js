import { test, expect } from '@playwright/test';

// Dedicated test identity, not a real business account. Created via:
//   php artisan tinker --execute="\App\Models\UrbanGoodzBusinessClientUser::updateOrCreate(['email'=>'business_test@urbangoodz.test'], [...])"
const BUSINESS_TEST = {
    email: 'business_test@urbangoodz.test',
    password: 'TestPass123!',
};

async function loginAsBusiness(page) {
    await page.goto('/business/login');
    await page.fill('input[name="email"]', BUSINESS_TEST.email);
    await page.fill('input[name="password"]', BUSINESS_TEST.password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/business\/dashboard$/);
}

test.describe('Business Portal — Corporate Operations & Package Management', () => {
    test('Business Auth — Valid Corporate Account Login', async ({ page }) => {
        await loginAsBusiness(page);
        await expect(page.getByText('Smoke Test Company', { exact: false }).first()).toBeVisible();
    });

    test('Business Auth — Rejects Invalid Password', async ({ page }) => {
        await page.goto('/business/login');
        await page.fill('input[name="email"]', BUSINESS_TEST.email);
        await page.fill('input[name="password"]', 'DefinitelyWrongPassword!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/\/business\/login$/);
    });

    test('Business Dashboard — Loads without error', async ({ page }) => {
        await loginAsBusiness(page);
        const response = await page.goto('/business/dashboard');
        expect(response.status()).toBe(200);
    });

    test('Business Package Pool — Loads unassigned/assigned packages, no 500', async ({ page }) => {
        await loginAsBusiness(page);
        const response = await page.goto('/business/packages/pool');
        expect(response.status()).toBe(200);
    });

    test('Business Package Scan — Scan intake page loads, no 500', async ({ page }) => {
        await loginAsBusiness(page);
        const response = await page.goto('/business/packages/scan');
        expect(response.status()).toBe(200);
    });

    test('Business Invoices — List loads, no 500', async ({ page }) => {
        await loginAsBusiness(page);
        const response = await page.goto('/business/invoices');
        expect(response.status()).toBe(200);
    });

    test('Business Auth — Logout invalidates session', async ({ page }) => {
        await loginAsBusiness(page);
        // The logout control submits a hidden #logout-form via JS rather
        // than being a plain link — submit it directly instead of guessing
        // which visible element triggers it.
        await page.locator('#logout-form').evaluate((form) => form.submit());
        await page.waitForURL(/\/(business\/login|login)/);

        await page.goto('/business/dashboard');
        await expect(page).toHaveURL(/\/business\/login$/);
    });
});
