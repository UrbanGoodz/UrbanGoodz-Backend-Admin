import test from 'node:test';
import assert from 'node:assert/strict';
import { config } from '../../src/config.js';
import { closeAppSession, createAppSession, saveMobileEvidence } from '../../src/appium.js';
import { assertLoginValidation, driverOpenJobs, login, shopperNavigate, vendorOpenOrders } from '../../src/mobile-flows.js';

for (const app of ['shopper', 'vendor', 'driver'] as const) {
  test(`${app}: required-field validation is rendered by the installed release APK`, async (t) => {
    const driver = await createAppSession(app);
    t.after(() => closeAppSession(driver));
    try {
      await assertLoginValidation(driver);
      assert.ok(driver.sessionId, 'Appium must create a real UiAutomator2 session.');
      await saveMobileEvidence(driver, `${app}-required-field-validation`);
    } catch (error) {
      await saveMobileEvidence(driver, `${app}-required-field-validation-failure`);
      throw error;
    }
  });
}

test('shopper: real login and all five bottom-navigation destinations', async (t) => {
  const driver = await createAppSession('shopper');
  t.after(() => closeAppSession(driver));
  try {
    await login(driver, config.credentials.shopper.login, config.credentials.shopper.password);
    for (const destination of ['Home', 'Categories', 'Services', 'Orders', 'Account'] as const) {
      await shopperNavigate(driver, destination);
    }
    await saveMobileEvidence(driver, 'shopper-login-and-shell');
  } catch (error) {
    await saveMobileEvidence(driver, 'shopper-login-and-shell-failure');
    throw error;
  }
});

test('vendor: real login opens live order queue', async (t) => {
  const driver = await createAppSession('vendor');
  t.after(() => closeAppSession(driver));
  try {
    await login(driver, config.credentials.vendor.login, config.credentials.vendor.password);
    await vendorOpenOrders(driver);
    await saveMobileEvidence(driver, 'vendor-login-orders');
  } catch (error) {
    await saveMobileEvidence(driver, 'vendor-login-orders-failure');
    throw error;
  }
});

test('driver: real login opens live jobs or dispatch queue', async (t) => {
  const driver = await createAppSession('driver');
  t.after(() => closeAppSession(driver));
  try {
    await login(driver, config.credentials.driver.login, config.credentials.driver.password);
    await driverOpenJobs(driver);
    await saveMobileEvidence(driver, 'driver-login-jobs');
  } catch (error) {
    await saveMobileEvidence(driver, 'driver-login-jobs-failure');
    throw error;
  }
});
