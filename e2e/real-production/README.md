# Urban Goodz Real Production E2E

This suite drives the installed Shopper, Vendor, and Driver APKs through Appium and the deployed Admin, Business, and Dispatcher portals through Playwright.

It does **not** create terminal-state database rows, call controllers from temporary PHP scripts, or treat route presence as a workflow pass.

## Safety rules

- Use dedicated `UGQA` identities only.
- Use approved provider sandbox credentials only.
- Never use real customer payment methods or payout accounts.
- Never commit `.env`, cookies, tokens, keystores, passwords, or provider secrets.
- Database checks are read-only assertions after the UI workflow completes.
- A test failure remains a failure. Do not replace it with a fabricated database state.

## Setup

```powershell
cd e2e\real-production
Copy-Item .env.example .env
npm install
npm run install:browsers
appium driver install uiautomator2
appium
```

Populate `.env` locally with dedicated QA credentials and exact QA product, service, provider, address, and package-recipient values.

Confirm the release APKs are installed on `ZT42268MG6` before running mobile tests.

## Run

```powershell
npm run test:playwright
npm run test:mobile
npm run test:marketplace
npm run test:order-anywhere
npm run test:services
npm run test:package-route
npm run test:financial-notifications
```

The complete critical run is:

```powershell
npm run test:critical
```

## Evidence

The suite writes screenshots, Playwright traces, videos, JUnit output, Appium session IDs, and Android page sources under:

```text
artifacts/
```

A connected workflow passes only when actions are performed through the real UI and the next role sees the resulting record through its own UI.

## Required first execution order

1. `tests/appium/auth-and-shell.test.ts`
2. `tests/playwright/admin-production.spec.ts`
3. `tests/flows/marketplace.test.ts`
4. `tests/flows/order-anywhere.test.ts`
5. `tests/flows/services-fashion-fit.test.ts`
6. `tests/flows/package-route.test.ts`
7. `tests/flows/financial-notifications.test.ts`

When selectors differ from the current UI, update selectors to match the rendered accessibility labels or resource IDs, commit the repair, and rerun. Do not weaken assertions merely to obtain a green result.
