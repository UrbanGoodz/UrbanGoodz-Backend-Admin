// @ts-check
'use strict';

/**
 * BASE_URL safety guard for the credential-bearing Browser suite.
 *
 * Extracted from playwright.config.js so the production-host abort is a
 * pure, directly testable function rather than side-effecting module load.
 */

const PRODUCTION_HOSTNAMES = ['admin.urbangoodzdelivery.com'];

/**
 * DNS treats a trailing dot as the same host (the fully-qualified form), and
 * hostnames are case-insensitive. An exact string comparison therefore lets
 * "admin.urbangoodzdelivery.com." through while it still resolves to
 * production. Strip trailing dots and lowercase before comparing.
 *
 * @param {string} hostname
 * @returns {string}
 */
function normalizeHostname(hostname) {
  return String(hostname).replace(/\.+$/, '').toLowerCase();
}

/**
 * @param {string|undefined} rawBaseUrl
 * @param {{ allowProduction?: boolean }} [options]
 * @returns {string} the validated BASE_URL
 */
function assertSafeBaseUrl(rawBaseUrl, options = {}) {
  const allowProduction = options.allowProduction === true;

  if (!rawBaseUrl) {
    throw new Error(
      'BASE_URL is required (e.g. a dev/staging URL). Refusing to default to production. ' +
      'This suite submits real login attempts and must not run against the live Admin panel.'
    );
  }

  let parsed;
  try {
    parsed = new URL(rawBaseUrl);
  } catch (e) {
    throw new Error(`BASE_URL is not a valid absolute URL: ${rawBaseUrl}`);
  }

  const hostname = normalizeHostname(parsed.hostname);

  if (PRODUCTION_HOSTNAMES.includes(hostname) && !allowProduction) {
    throw new Error(
      `BASE_URL resolves to the production hostname (${hostname}). ` +
      'Set ALLOW_PRODUCTION_BASE_URL=true to explicitly opt in if this is intentional.'
    );
  }

  return rawBaseUrl;
}

module.exports = { assertSafeBaseUrl, normalizeHostname, PRODUCTION_HOSTNAMES };
