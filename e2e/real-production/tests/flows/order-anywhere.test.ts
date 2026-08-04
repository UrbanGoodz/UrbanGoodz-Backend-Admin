import test from 'node:test';
import assert from 'node:assert/strict';
import { config } from '../../src/config.js';
import { assertAnyText, closeAppSession, createAppSession, saveMobileEvidence, tapText, typeIntoFirstMatching } from '../../src/appium.js';
import { login } from '../../src/mobile-flows.js';

const textAreaSelectors = [
  'android=new UiSelector().className("android.widget.EditText")',
  'android=new UiSelector().resourceIdMatches(".*details.*|.*request.*|.*items.*")',
];

test('real Order Anywhere: Shopper request reaches Driver and completes through supported UI', async (t) => {
  assert.ok(config.qa.deliveryAddress, 'QA_DELIVERY_ADDRESS is required.');
  const shopper = await createAppSession('shopper');
  const driver = await createAppSession('driver');
  t.after(async () => {
    await closeAppSession(shopper);
    await closeAppSession(driver);
  });

  try {
    await login(shopper, config.credentials.shopper.login, config.credentials.shopper.password);
    await tapText(shopper, ['Order Anywhere', 'Shop Anywhere']);
    await typeIntoFirstMatching(shopper, textAreaSelectors, `QA order ${Date.now()}: two grocery items from QA store`);
    await tapText(shopper, ['Continue', 'Review Request', 'Submit Request']);
    await assertAnyText(shopper, ['Request Submitted', 'Pending Quote', 'Request Created']);
    const source = await shopper.getPageSource();
    const requestMatch = source.match(/(?:Request\s*(?:ID|#|No\.?)[^0-9]*)([0-9]{1,})/i);
    assert.ok(requestMatch, 'A real Order Anywhere request reference must be rendered.');
    const requestId = requestMatch[1];
    await saveMobileEvidence(shopper, `order-anywhere-created-${requestId}`);

    await login(driver, config.credentials.driver.login, config.credentials.driver.password);
    await tapText(driver, ['Order Anywhere', 'Available Jobs', 'Jobs']);
    await tapText(driver, [requestId, `#${requestId}`]);
    await tapText(driver, ['Accept', 'Accept Request']);
    await tapText(driver, ['Start Shopping', 'Shopping']);
    await tapText(driver, ['Checkout Complete', 'Purchased']);
    await tapText(driver, ['Picked Up', 'Confirm Pickup']);
    await tapText(driver, ['Delivered', 'Complete']);
    await assertAnyText(driver, ['Delivered', 'Completed']);
    await saveMobileEvidence(driver, `order-anywhere-driver-completed-${requestId}`);

    await tapText(shopper, ['Orders', 'Order Anywhere']);
    await tapText(shopper, [requestId, `#${requestId}`]);
    await assertAnyText(shopper, ['Delivered', 'Completed']);
    await saveMobileEvidence(shopper, `order-anywhere-shopper-completed-${requestId}`);
  } catch (error) {
    await saveMobileEvidence(shopper, 'order-anywhere-shopper-failure');
    await saveMobileEvidence(driver, 'order-anywhere-driver-failure');
    throw error;
  }
});
