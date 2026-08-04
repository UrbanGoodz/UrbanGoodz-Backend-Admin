import 'dotenv/config';

function required(name: string): string {
  const value = process.env[name]?.trim();
  if (!value) throw new Error(`Missing required environment variable: ${name}`);
  return value;
}

function optional(name: string, fallback = ''): string {
  return process.env[name]?.trim() || fallback;
}

function bool(name: string): boolean {
  return optional(name).toLowerCase() === 'true';
}

export const config = {
  baseUrl: optional('PRODUCTION_BASE_URL', 'https://admin.urbangoodzdelivery.com'),
  appiumUrl: optional('APPIUM_URL', 'http://127.0.0.1:4723'),
  deviceUdid: optional('DEVICE_UDID', 'ZT42268MG6'),
  packages: {
    shopper: optional('SHOPPER_PACKAGE', 'com.urbangoodz.customer'),
    vendor: optional('VENDOR_PACKAGE', 'com.urbangoodz.vendor'),
    driver: optional('DRIVER_PACKAGE', 'com.urbangoodz.driver'),
  },
  credentials: {
    shopper: { login: optional('SHOPPER_EMAIL') || optional('SHOPPER_PHONE'), password: optional('SHOPPER_PASSWORD') },
    vendor: { login: optional('VENDOR_EMAIL') || optional('VENDOR_PHONE'), password: optional('VENDOR_PASSWORD') },
    driver: { login: optional('DRIVER_EMAIL') || optional('DRIVER_PHONE'), password: optional('DRIVER_PASSWORD') },
    admin: { login: optional('ADMIN_EMAIL'), password: optional('ADMIN_PASSWORD') },
    business: { login: optional('BUSINESS_EMAIL'), password: optional('BUSINESS_PASSWORD') },
    dispatcher: { login: optional('DISPATCHER_EMAIL'), password: optional('DISPATCHER_PASSWORD') },
  },
  qa: {
    productName: optional('QA_PRODUCT_NAME'),
    storeName: optional('QA_STORE_NAME'),
    serviceName: optional('QA_SERVICE_NAME'),
    providerA: optional('QA_PROVIDER_A_NAME'),
    providerB: optional('QA_PROVIDER_B_NAME'),
    deliveryAddress: optional('QA_DELIVERY_ADDRESS'),
    packageRecipient: optional('QA_BUSINESS_PACKAGE_RECIPIENT'),
    testPhone: optional('QA_TEST_PHONE'),
    testEmail: optional('QA_TEST_EMAIL'),
  },
  allowed: {
    sandboxPayment: bool('ALLOW_SANDBOX_PAYMENT'),
    sandboxSms: bool('ALLOW_SANDBOX_SMS'),
    sandboxEmail: bool('ALLOW_SANDBOX_EMAIL'),
  },
  paths: {
    adminLogin: optional('ADMIN_LOGIN_PATH', '/login/admin'),
    adminDashboard: optional('ADMIN_DASHBOARD_PATH', '/admin'),
    businessLogin: optional('BUSINESS_LOGIN_PATH', '/business/login'),
    dispatcherLogin: optional('DISPATCHER_LOGIN_PATH', '/dispatcher/login'),
  },
  require,
};
