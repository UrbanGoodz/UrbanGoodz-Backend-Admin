import type { Browser } from 'webdriverio';
import { assertAnyText, tapText, typeIntoFirstMatching } from './appium.js';

const loginSelectors = [
  'android=new UiSelector().className("android.widget.EditText").instance(0)',
  'android=new UiSelector().resourceIdMatches(".*email.*|.*phone.*|.*login.*")',
];

const passwordSelectors = [
  'android=new UiSelector().className("android.widget.EditText").instance(1)',
  'android=new UiSelector().resourceIdMatches(".*password.*")',
];

export async function login(driver: Browser, loginValue: string, password: string): Promise<void> {
  if (!loginValue || !password) throw new Error('Real login credentials are required for this E2E test.');
  await typeIntoFirstMatching(driver, loginSelectors, loginValue);
  await typeIntoFirstMatching(driver, passwordSelectors, password);
  await tapText(driver, ['Login', 'Sign In', 'Continue']);
}

export async function assertLoginValidation(driver: Browser): Promise<void> {
  await tapText(driver, ['Login', 'Sign In', 'Continue']);
  await assertAnyText(driver, [
    'Please enter password',
    'Please enter email or phone',
    'Required',
    'Enter valid phone number',
  ]);
}

export async function shopperNavigate(driver: Browser, destination: 'Home' | 'Categories' | 'Services' | 'Orders' | 'Account'): Promise<void> {
  await tapText(driver, [destination]);
  await assertAnyText(driver, [destination]);
}

export async function vendorOpenOrders(driver: Browser): Promise<void> {
  await tapText(driver, ['Orders', 'Order']);
  await assertAnyText(driver, ['Orders', 'New Orders', 'Pending']);
}

export async function driverOpenJobs(driver: Browser): Promise<void> {
  await tapText(driver, ['Jobs', 'Orders', 'Available Jobs', 'Dashboard']);
  await assertAnyText(driver, ['Available', 'Jobs', 'Orders', 'Dashboard']);
}
