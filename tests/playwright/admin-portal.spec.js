import { test, expect } from '@playwright/test';
import { readLatestCaptchaPhrase } from './helpers/sessionCaptcha';
import { latestOrderId } from './helpers/dbLookup';

// Dedicated test identity — never a real admin account. Created via:
//   php artisan tinker --execute="\App\Models\Admin::updateOrCreate(['email'=>'admin_test@urbangoodz.test'], [...])"
const ADMIN_TEST = {
    email: 'admin_test@urbangoodz.test',
    password: 'TestPass123!',
};

async function loginAsAdmin(page) {
    await page.goto('/login/admin');
    await expect(page.locator('#signInBtn')).toBeVisible();

    const captchaPhrase = readLatestCaptchaPhrase();

    await page.fill('input[name="email"]', ADMIN_TEST.email);
    await page.fill('#signupSrPassword', ADMIN_TEST.password);
    await page.fill('#custome_recaptcha', captchaPhrase);
    await page.click('#signInBtn');
}

test.describe('Admin Portal — Authentication & System Administration', () => {
    test('Admin Auth — Valid Admin Login reaches the real dashboard', async ({ page }) => {
        await loginAsAdmin(page);

        await expect(page).toHaveURL(/\/admin$/);
        await expect(page.getByText('Dashboard', { exact: false }).first()).toBeVisible();
        await expect(page.getByText('Total Revenue', { exact: false }).first()).toBeVisible({ timeout: 15000 });
    });

    test('Admin Auth — Rejects Invalid Password', async ({ page }) => {
        await page.goto('/login/admin');
        const captchaPhrase = readLatestCaptchaPhrase();

        await page.fill('input[name="email"]', ADMIN_TEST.email);
        await page.fill('#signupSrPassword', 'DefinitelyWrongPassword!');
        await page.fill('#custome_recaptcha', captchaPhrase);
        await page.click('#signInBtn');

        // Must stay on the login page, never reach the dashboard.
        await expect(page).toHaveURL(/\/login\/admin$/);
    });

    test('Admin Auth — Invalidates Session on Logout', async ({ page }) => {
        await loginAsAdmin(page);
        await expect(page).toHaveURL(/\/admin$/);

        await page.goto('/logout');

        // A logged-out session must be redirected to login, not shown the
        // dashboard again.
        await page.goto('/admin');
        await expect(page).toHaveURL(/\/login\/admin$/);
    });

    test('Admin Auth — Unauthenticated Request Redirects to Login, Not a 500', async ({ page }) => {
        const response = await page.goto('/admin/urban-goodz/ai-chief-of-staff');
        expect(response.status()).toBeLessThan(500);
        await expect(page).toHaveURL(/\/login/);
    });
});

test.describe('Admin Portal — Markets, Zones, Vendors & Drivers', () => {
    test('Admin Vendors — List loads with real data, no 500', async ({ page }) => {
        await loginAsAdmin(page);
        const response = await page.goto('/admin/store/list');
        expect(response.status()).toBe(200);
        await expect(page.locator('table')).toBeVisible();
    });

    test('Admin Drivers — List loads with real data, no 500', async ({ page }) => {
        await loginAsAdmin(page);
        const response = await page.goto('/admin/users/delivery-man');
        expect(response.status()).toBe(200);
        await expect(page.locator('table')).toBeVisible();
    });
});

test.describe('Admin Portal — Customer & Order Management', () => {
    test('Admin Customers — List loads with real data, no 500', async ({ page }) => {
        await loginAsAdmin(page);
        const response = await page.goto('/admin/customer/list');
        expect(response.status()).toBe(200);
        await expect(page.locator('table')).toBeVisible();
    });

    test('Admin Orders — List loads for every status filter, no 500', async ({ page }) => {
        await loginAsAdmin(page);
        for (const status of ['all', 'pending', 'confirmed', 'processing', 'out_for_delivery', 'delivered', 'canceled']) {
            const response = await page.goto(`/admin/order/list/${status}`);
            expect(response.status(), `status filter: ${status}`).toBe(200);
        }
    });

    test('Admin Orders — Order details page shows a real order, no 500', async ({ page }) => {
        await loginAsAdmin(page);
        const orderId = latestOrderId();
        expect(orderId, 'expected at least one real order to exist').not.toBeNull();

        const response = await page.goto(`/admin/order/details/${orderId}`);
        expect(response.status()).toBe(200);
        await expect(page.getByText('Order Details', { exact: false }).first()).toBeVisible();
    });
});
