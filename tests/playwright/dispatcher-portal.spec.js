import { test, expect } from '@playwright/test';

// Dedicated test identity, not a real dispatch company account. Created via:
//   php artisan tinker (see business-portal.spec.js header for the pattern) —
//   attached to a UrbanGoodzBusinessClient with account_type='dispatch_company',
//   role='dispatch_owner' (only role with implicit full dispatch permissions).
const DISPATCHER_TEST = {
    email: 'dispatcher_test@urbangoodz.test',
    password: 'TestPass123!',
};

async function loginAsDispatcher(page) {
    // Dispatch company users log in through the same business login form —
    // there is no separate /dispatcher/login route. The controller routes
    // them to the dispatcher dashboard based on account_type + role.
    await page.goto('/business/login');
    await page.fill('input[name="email"]', DISPATCHER_TEST.email);
    await page.fill('input[name="password"]', DISPATCHER_TEST.password);
    await page.click('button[type="submit"]');
    await expect(page).toHaveURL(/\/business\/dispatcher\/dashboard$/);
}

test.describe('Dispatcher Portal — Autonomous Freight & Load Matching', () => {
    test('Dispatcher Auth — Valid Dispatcher Account Login', async ({ page }) => {
        await loginAsDispatcher(page);
    });

    test('Dispatcher Dashboard — Loads without error', async ({ page }) => {
        await loginAsDispatcher(page);
        const response = await page.goto('/business/dispatcher/dashboard');
        expect(response.status()).toBe(200);
    });

    test('Dispatcher Loads — Load list loads, no 500', async ({ page }) => {
        await loginAsDispatcher(page);
        const response = await page.goto('/business/dispatcher/loads');
        expect(response.status()).toBe(200);
    });

    test('Dispatcher Commissions — Settlement page loads, no 500', async ({ page }) => {
        await loginAsDispatcher(page);
        const response = await page.goto('/business/dispatcher/commissions');
        expect(response.status()).toBe(200);
    });

    test('Dispatcher Auth — Non-dispatch business account cannot reach dispatcher portal', async ({ page }) => {
        // business_test belongs to a regular (non dispatch_company) client —
        // it must be refused the dispatcher-only area, not silently let in.
        await page.goto('/business/login');
        await page.fill('input[name="email"]', 'business_test@urbangoodz.test');
        await page.fill('input[name="password"]', 'TestPass123!');
        await page.click('button[type="submit"]');
        await expect(page).toHaveURL(/\/business\/dashboard$/);

        const response = await page.goto('/business/dispatcher/dashboard');
        expect(response.status()).toBe(403);
    });
});
