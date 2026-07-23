// @ts-check
const { test, expect } = require('@playwright/test');
const { assertSafeBaseUrl, normalizeHostname } = require('./base-url-guard');

const PRODUCTION = 'https://admin.urbangoodzdelivery.com';

test.describe('BASE_URL production safety guard', () => {
  test('rejects a missing BASE_URL', () => {
    expect(() => assertSafeBaseUrl(undefined)).toThrow(/BASE_URL is required/);
    expect(() => assertSafeBaseUrl('')).toThrow(/BASE_URL is required/);
  });

  test('rejects the exact production hostname', () => {
    expect(() => assertSafeBaseUrl(PRODUCTION)).toThrow(/production hostname/);
    expect(() => assertSafeBaseUrl(`${PRODUCTION}/`)).toThrow(/production hostname/);
  });

  test('rejects the trailing-dot fully-qualified production hostname', () => {
    // DNS-equivalent to the bare hostname; an exact-match comparison would
    // let this through and point credentialed tests at production.
    expect(() => assertSafeBaseUrl('https://admin.urbangoodzdelivery.com./')).toThrow(/production hostname/);
    expect(() => assertSafeBaseUrl('https://admin.urbangoodzdelivery.com../')).toThrow(/production hostname/);
  });

  test('rejects case variants of the production hostname', () => {
    expect(() => assertSafeBaseUrl('https://ADMIN.URBANGOODZDELIVERY.COM/')).toThrow(/production hostname/);
    expect(() => assertSafeBaseUrl('https://Admin.UrbanGoodzDelivery.Com./')).toThrow(/production hostname/);
  });

  test('allows production only with the explicit override', () => {
    expect(assertSafeBaseUrl(PRODUCTION, { allowProduction: true })).toBe(PRODUCTION);
    expect(assertSafeBaseUrl('https://admin.urbangoodzdelivery.com./', { allowProduction: true }))
      .toBe('https://admin.urbangoodzdelivery.com./');
  });

  test('allows non-production hosts', () => {
    expect(assertSafeBaseUrl('https://staging.example.test')).toBe('https://staging.example.test');
    expect(assertSafeBaseUrl('http://localhost:8000')).toBe('http://localhost:8000');
  });

  test('rejects a malformed BASE_URL', () => {
    expect(() => assertSafeBaseUrl('not-a-url')).toThrow(/not a valid absolute URL/);
  });

  test('normalizeHostname strips trailing dots and lowercases', () => {
    expect(normalizeHostname('admin.urbangoodzdelivery.com.')).toBe('admin.urbangoodzdelivery.com');
    expect(normalizeHostname('ADMIN.URBANGOODZDELIVERY.COM')).toBe('admin.urbangoodzdelivery.com');
    expect(normalizeHostname('staging.example.test')).toBe('staging.example.test');
  });
});
