import test from 'node:test';
import assert from 'node:assert/strict';
import { config } from '../../src/config.js';
import { assertAnyText, closeAppSession, createAppSession, saveMobileEvidence, tapText, typeIntoFirstMatching } from '../../src/appium.js';
import { login } from '../../src/mobile-flows.js';

const editable = ['android=new UiSelector().className("android.widget.EditText")'];

test('real Services and Fashion Fit: request, quote selection, consent and revocation', async (t) => {
  assert.ok(config.qa.serviceName, 'QA_SERVICE_NAME is required.');
  assert.ok(config.qa.providerA, 'QA_PROVIDER_A_NAME is required.');
  assert.ok(config.qa.providerB, 'QA_PROVIDER_B_NAME is required.');

  const shopper = await createAppSession('shopper');
  const vendor = await createAppSession('vendor');
  t.after(async () => {
    await closeAppSession(shopper);
    await closeAppSession(vendor);
  });

  try {
    await login(shopper, config.credentials.shopper.login, config.credentials.shopper.password);
    await tapText(shopper, ['Services']);
    await tapText(shopper, [config.qa.serviceName]);
    await tapText(shopper, ['Request Service', 'Get Quotes', 'Request Quotes']);
    await typeIntoFirstMatching(shopper, editable, `QA service request ${Date.now()}`);
    await tapText(shopper, ['Submit Request', 'Send Request']);
    await assertAnyText(shopper, ['Request Submitted', 'Waiting for Quotes', 'Quotes']);
    await saveMobileEvidence(shopper, 'services-request-created');

    await login(vendor, config.credentials.vendor.login, config.credentials.vendor.password);
    await tapText(vendor, ['Service Requests', 'Requests', 'Quotes']);
    await tapText(vendor, ['Submit Quote', 'Send Quote']);
    await typeIntoFirstMatching(vendor, editable, '125.00');
    await tapText(vendor, ['Submit', 'Send Quote']);
    await assertAnyText(vendor, ['Quote Submitted', 'Pending']);
    await saveMobileEvidence(vendor, 'services-provider-quote');

    await tapText(shopper, ['Services', 'Requests', 'Quotes']);
    await assertAnyText(shopper, [config.qa.providerA, config.qa.providerB, 'Compare Quotes']);
    await tapText(shopper, [config.qa.providerA, 'Select Provider', 'Choose']);
    await tapText(shopper, ['Book', 'Confirm Booking']);
    await assertAnyText(shopper, ['Booking Confirmed', 'Upcoming']);

    await tapText(shopper, ['Fashion Fit', 'Measurement Profile', 'Privacy']);
    await assertAnyText(shopper, ['Measurements', 'Body Photos', 'Not Shared', 'Private']);
    await tapText(shopper, ['Share Measurements', 'Approve Measurements']);
    await assertAnyText(shopper, ['Measurements Shared', 'Access Granted']);
    await tapText(shopper, ['Revoke Access', 'Stop Sharing']);
    await assertAnyText(shopper, ['Access Revoked', 'Not Shared']);
    await assertAnyText(shopper, ['Access Log', 'Privacy Log', 'Activity']);
    await saveMobileEvidence(shopper, 'fashion-fit-consent-revocation');
  } catch (error) {
    await saveMobileEvidence(shopper, 'services-fashion-fit-shopper-failure');
    await saveMobileEvidence(vendor, 'services-fashion-fit-provider-failure');
    throw error;
  }
});
