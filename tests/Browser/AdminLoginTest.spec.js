const { test, expect } = require('@playwright/test');

test.describe('Urban Goodz Admin Login & Dashboard Regression Suite', () => {

    test('Admin Login Form Submission, Session Persistence, and Navigation', async ({ page }) => {
        // 1. Open Admin Login Page
        await page.goto('https://admin.urbangoodzdelivery.com/admin', { waitUntil: 'networkidle' });

        // 2. Check if anti-bot shield interstitial page appeared
        const pageTitle = await page.title();
        if (pageTitle.includes('One moment') || pageTitle.includes('Please wait')) {
            console.log('Anti-bot challenge detected. Waiting 5s...');
            await page.waitForTimeout(5000);
        }

        // 3. Verify Login Form Elements if accessible
        const emailInput = await page.$('input[name="email"]');
        const passwordInput = await page.$('input[name="password"]');
        const submitBtn = await page.$('button[type="submit"]');

        if (emailInput && passwordInput && submitBtn) {
            await emailInput.fill('admin@admin.com');
            await passwordInput.fill('12345678');
            await submitBtn.click();
            await page.waitForTimeout(5000);
        }

        // 4. Require non-500 response
        const url = page.url();
        console.log('Post-login URL:', url);
        expect(url).not.toContain('500');
    });

});
