import { remote, type Browser } from 'webdriverio';
import { mkdir, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { config } from './config.js';

export type UgApp = 'shopper' | 'vendor' | 'driver';

export async function createAppSession(app: UgApp): Promise<Browser> {
  const url = new URL(config.appiumUrl);
  return remote({
    hostname: url.hostname,
    port: Number(url.port || 4723),
    path: url.pathname === '/' ? '/' : url.pathname,
    logLevel: 'info',
    connectionRetryTimeout: 120_000,
    capabilities: {
      platformName: 'Android',
      'appium:automationName': 'UiAutomator2',
      'appium:udid': config.deviceUdid,
      'appium:deviceName': config.deviceUdid,
      'appium:appPackage': config.packages[app],
      'appium:noReset': true,
      'appium:autoGrantPermissions': true,
      'appium:newCommandTimeout': 180,
      'appium:disableWindowAnimation': true,
    },
  });
}

export async function tapText(driver: Browser, labels: string[], timeout = 20_000): Promise<void> {
  let lastError: unknown;
  for (const label of labels) {
    const selectors = [
      `android=new UiSelector().text("${label}")`,
      `android=new UiSelector().textContains("${label}")`,
      `~${label}`,
    ];
    for (const selector of selectors) {
      try {
        const element = await driver.$(selector);
        await element.waitForDisplayed({ timeout: Math.min(timeout, 7_000) });
        await element.click();
        return;
      } catch (error) {
        lastError = error;
      }
    }
  }
  throw new Error(`Could not tap any label: ${labels.join(', ')}. Last error: ${String(lastError)}`);
}

export async function typeIntoFirstMatching(
  driver: Browser,
  selectors: string[],
  value: string,
): Promise<void> {
  for (const selector of selectors) {
    try {
      const element = await driver.$(selector);
      await element.waitForDisplayed({ timeout: 8_000 });
      await element.click();
      await element.clearValue();
      await element.setValue(value);
      return;
    } catch {
      // Continue to the next selector.
    }
  }
  throw new Error(`No input matched selectors: ${selectors.join(', ')}`);
}

export async function assertAnyText(driver: Browser, labels: string[], timeout = 20_000): Promise<string> {
  for (const label of labels) {
    for (const selector of [
      `android=new UiSelector().text("${label}")`,
      `android=new UiSelector().textContains("${label}")`,
      `~${label}`,
    ]) {
      try {
        const element = await driver.$(selector);
        await element.waitForDisplayed({ timeout: Math.min(timeout, 7_000) });
        return label;
      } catch {
        // Continue.
      }
    }
  }
  throw new Error(`None of the expected labels appeared: ${labels.join(', ')}`);
}

export async function saveMobileEvidence(driver: Browser, testName: string): Promise<void> {
  const safe = testName.replace(/[^a-z0-9_-]+/gi, '_');
  const dir = join(process.cwd(), 'artifacts', 'appium', safe);
  await mkdir(dir, { recursive: true });
  await driver.saveScreenshot(join(dir, 'screen.png'));
  await writeFile(join(dir, 'page-source.xml'), await driver.getPageSource(), 'utf8');
  await writeFile(join(dir, 'session.txt'), String(driver.sessionId ?? ''), 'utf8');
}

export async function closeAppSession(driver: Browser): Promise<void> {
  try {
    await driver.deleteSession();
  } catch {
    // Preserve the original test failure if session cleanup also fails.
  }
}
