import test from 'node:test';
import assert from 'node:assert/strict';
import { config } from '../../src/config.js';
import { assertAnyText, closeAppSession, createAppSession, saveMobileEvidence, tapText } from '../../src/appium.js';
import { login } from '../../src/mobile-flows.js';

async function findOrderReference(driver: WebdriverIO.Browser): Promise<string> {
  const source = await driver.getPageSource();
  const match = source.match(/(?:Order\s*(?:ID|#|No\.?)[^0-9]*)([0-9]{3,})/i);
  if (!match) throw new Error('A real order reference was not visible after checkout.');
  return match[1];
}

test('real marketplace: Shopper creates order, Vendor advances it, Driver completes it', async (t) => {
  assert.ok(config.qa.productName, 'QA_PRODUCT_NAME is required.');
  assert.ok(config.qa.storeName, 'QA_STORE_NAME is required.');
  assert.ok(config.qa.deliveryAddress, 'QA_DELIVERY_ADDRESS is required.');

  const shopper = await createAppSession('shopper');
  const vendor = await createAppSession('vendor');
  const driver = await createAppSession('driver');
  t.after(async () => {
    await closeAppSession(shopper);
    await closeAppSession(vendor);
    await closeAppSession(driver);
  });

  try {
    await login(shopper, config.credentials.shopper.login, config.credentials.shopper.password);
    await tapText(shopper, ['Home']);
    await tapText(shopper, [config.qa.storeName]);
    await tapText(shopper, [config.qa.productName]);
    await tapText(shopper, ['Add to Cart', 'Add']);
    await tapText(shopper, ['Cart', 'View Cart']);
    await tapText(shopper, ['Checkout', 'Proceed to Checkout']);
    await assertAnyText(shopper, ['Subtotal', 'Delivery Fee', 'Total']);
    await tapText(shopper, [config.qa.deliveryAddress, 'Change Address', 'Select Address']);

    if (config.allowed.sandboxPayment) {
      await tapText(shopper, ['Card', 'Digital Payment', 'Pay Now', 'Place Order']);
    } else {
      await tapText(shopper, ['Cash on Delivery', 'Place Order', 'Confirm Order']);
    }

    await assertAnyText(shopper, ['Order Placed', 'Order Confirmed', 'Track Order']);
    const orderId = await findOrderReference(shopper);
    await saveMobileEvidence(shopper, `marketplace-shopper-created-${orderId}`);

    await login(vendor, config.credentials.vendor.login, config.credentials.vendor.password);
    await tapText(vendor, ['Orders', 'New Orders', 'Pending']);
    await tapText(vendor, [orderId, `#${orderId}`]);
    await tapText(vendor, ['Accept', 'Confirm']);
    await tapText(vendor, ['Preparing', 'Start Preparing']);
    await tapText(vendor, ['Ready', 'Ready for Pickup']);
    await assertAnyText(vendor, ['Ready', 'Ready for Pickup']);
    await saveMobileEvidence(vendor, `marketplace-vendor-ready-${orderId}`);

    await login(driver, config.credentials.driver.login, config.credentials.driver.password);
    await tapText(driver, ['Jobs', 'Available Jobs', 'Orders']);
    await tapText(driver, [orderId, `#${orderId}`]);
    await tapText(driver, ['Accept', 'Accept Job']);
    await tapText(driver, ['Arrived', 'Reached Store']);
    await tapText(driver, ['Picked Up', 'Confirm Pickup']);
    await tapText(driver, ['Delivered', 'Confirm Delivery']);
    await assertAnyText(driver, ['Delivered', 'Completed']);
    await saveMobileEvidence(driver, `marketplace-driver-delivered-${orderId}`);

    await tapText(shopper, ['Orders']);
    await tapText(shopper, [orderId, `#${orderId}`]);
    await assertAnyText(shopper, ['Delivered', 'Completed']);
    await saveMobileEvidence(shopper, `marketplace-shopper-completed-${orderId}`);
  } catch (error) {
    await saveMobileEvidence(shopper, 'marketplace-shopper-failure');
    await saveMobileEvidence(vendor, 'marketplace-vendor-failure');
    await saveMobileEvidence(driver, 'marketplace-driver-failure');
    throw error;
  }
});
