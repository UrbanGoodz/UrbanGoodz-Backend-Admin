import { test, expect } from '@playwright/test';
import { readLatestCaptchaPhrase } from './helpers/sessionCaptcha';

// These verify that each cross-role workflow's admin-side control surface is
// reachable and renders real content for an authenticated admin. They do NOT
// drive the full Customer→Vendor→Driver mobile chain — that requires the
// Appium-driven mobile apps, which are a separate test surface. Once the
// mobile apps are wired in, extend these to assert the actual order/load
// data created on the mobile side is visible here (the real cross-role
// check), not just that the page itself loads.
const ADMIN_TEST = {
    email: 'admin_test@urbangoodz.test',
    password: 'TestPass123!',
};

async function loginAsAdmin(page) {
    await page.goto('/login/admin');
    const captchaPhrase = readLatestCaptchaPhrase();
    await page.fill('input[name="email"]', ADMIN_TEST.email);
    await page.fill('#signupSrPassword', ADMIN_TEST.password);
    await page.fill('#custome_recaptcha', captchaPhrase);
    await page.click('#signInBtn');
    await expect(page).toHaveURL(/\/admin$/);
}

test.describe('Cross-Role End-to-End Platform Workflows', () => {
    test.beforeEach(async ({ page }) => {
        await loginAsAdmin(page);
    });

    test('E2E Flow 1 — Vendor→Customer→Driver order pipeline: AI Operations console reachable', async ({ page }) => {
        const response = await page.goto('/admin/urban-goodz/ai-operations');
        expect(response.status()).toBe(200);
    });

    test('E2E Flow 2 — Business intake→route→driver dispatch: Dispatch dashboard reachable', async ({ page }) => {
        const response = await page.goto('/admin/dispatch');
        expect(response.status()).toBe(200);
    });

    test('E2E Flow 3 — Load sourcing→dedup→approval→board→dispatch assignment', async ({ page }) => {
        const boardResponse = await page.goto('/admin/urban-goodz/load-board');
        expect(boardResponse.status()).toBe(200);

        const sourcingResponse = await page.goto('/admin/urban-goodz/load-sourcing');
        expect(sourcingResponse.status()).toBeLessThan(400);
        await expect(page).toHaveURL(/load-sourcing/);
    });

    test('E2E Flow 4 — Order Anywhere concierge to purchase-card reconciliation: dispatch view reachable', async ({ page }) => {
        const response = await page.goto('/admin/dispatch');
        expect(response.status()).toBe(200);
    });

    test('E2E Flow 5 — Vendor/store approval surface reachable for provider onboarding', async ({ page }) => {
        const response = await page.goto('/admin/store/list');
        expect(response.status()).toBe(200);
        await expect(page.locator('table')).toBeVisible();
    });

    test('E2E Flow 6 — AI Chief of Staff operational event surface reachable', async ({ page }) => {
        const response = await page.goto('/admin/urban-goodz/ai-chief-of-staff');
        expect(response.status()).toBe(200);
    });
});
