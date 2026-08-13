# Urban Goodz Platform Truth and Readiness Report

Audit date: 2026-07-23

Admin audit basis: `6937e5d5c8d7bbf0c6405eda8a6c1d0e922033c9`

Audit branch: `codex-full-platform-audit-sprint`

## Executive truth

Urban Goodz is a large, partially wired platform built on an active 6amMart marketplace foundation. It is not a clean-sheet replacement and is not ready for a trustworthy full-platform certification.

The source inventory has 127 feature rows:

| Evidence threshold | Result |
|---|---:|
| Built/source exists (score at least 2) | 107 / 127 |
| Plausibly UI-to-backend wired (score at least 3) | 72 / 127 |
| Focused local behavioral evidence (score at least 4) | 7 / 127 |
| Staging E2E evidence (score at least 5) | 0 / 127 |
| Current live certification (score 6) | 0 / 127 |

Current-state distribution:

| State | Count |
|---|---:|
| PARTIALLY WIRED | 62 |
| BROKEN | 33 |
| BACKEND ONLY | 9 |
| MOCK DATA ONLY | 9 |
| PLACEHOLDER | 6 |
| TESTED LOCALLY | 5 |
| BLOCKED | 2 |
| UNKNOWN | 1 |

These values are source/evidence classifications, not a launch percentage.

## What is actually wired

- Public Laravel navigation/catalog/location surfaces have plausible route/controller/view chains.
- Business Portal and Dispatcher source routes are under `/business/*` and `/business/dispatcher/*`.
- Vendor web uses the inherited marketplace vendor/store/order foundation.
- Admin authentication and a non-primary Admin module-permission boundary have focused PHP evidence.
- Driver pricing policy CRUD persists records and Order Anywhere has split-calculation components.
- Internal/manual/email load-sourcing components and queued sourcing jobs exist.
- Storage, mapping, scanning, reporting, and upload controls have source chains, but not staging certification.

“Actually wired” here means source chain score 3. It does not imply a clean database, external credentials, or live proof.

## What is only built, mock, placeholder, or absent

- Nine inventory rows are backend-only.
- Vendor and Driver standalone Flutter apps are dominated by mock repositories.
- Six Shopper Urban Goodz surfaces are explicit placeholder/waitlist/coming-soon implementations.
- AI Chief of Staff has route/controller/service/view source but is not a provider-backed operational orchestrator.
- Dispatcher commission approval/pay/wallet/ledger workflow is absent even though a commission list exists.
- A central Pricing and Payout Control Center is absent.
- Several external load adapters intentionally fail closed until contracts/credentials exist.

## What is broken or unsafe

1. Adyen webhook validation accepts an event when the HMAC key is absent.
2. Stored pricing safety flags are not enforced before AI-derived payout values can become approved earnings and wallet totals.
3. Driver payout approve/pay/reject endpoints lack dedicated manage authorization.
4. AI Chief of Staff is not in navigation, has no dedicated permission, writes during GET, uses synthetic inventory, and references an undefined `Vendor` class.
5. The migration chain alters `orders` but contains no authoritative creation baseline.
6. Default `db:seed` invokes fake-user, test-vendor, live-tester, and synthetic-load seeders.
7. The Shopper app persists a remembered password in `SharedPreferences`.
8. Shopper realtime code hard-codes a `6ammart` key and LAN fallback.
9. Shopper iOS bundle/Firebase configuration remains bound to legacy 6amTech identity.
10. Two mail classes can expose `6am Mart` as a fallback.
11. The Admin dashboard mixes inconsistent legacy scopes and unreconciled money/ranking data.
12. Current live SHA, cache identity, worker/cron state, and duplicate-copy state are unproven.
13. SMS/OTP delivery is not certified; 2Factor can falsely report delivery success from transport success and duplicate gateway implementations can drift.
14. Google reCAPTCHA live configuration is unproven, while the custom fallback is client-selectable, reusable, and not covered by the login-attempt limiter on invalid CAPTCHA.
15. The S3 selection expression always resolves to local storage; business/driver documents use public storage and private-file URL/ownership paths are contradictory.
16. No coordinated database/media backup, off-site retention, or isolated restore drill is evidenced; Reels processing/delivery is not production-certified.

## Legacy 6amMart disposition

The dedicated [legacy contamination audit](URBAN_GOODZ_6AMMART_CONTAMINATION_AUDIT.md) classifies 56 component families:

- 8 required foundations;
- 10 customized and active components;
- 7 legacy but referenced components;
- 4 legacy-data families;
- 4 demo/test-data families;
- 3 duplicate implementations;
- 4 obsolete components;
- 12 unsafe components;
- 4 unknown components.

The safe strategy is selective isolation and migration, not global deletion. The inherited routes, models, middleware, module permission helper, Tax project discriminator, and Shopper Dart import namespace are compatibility foundations.

## Database truth

**Reproducibility:** blocked.

- The current SHA has 24 migrations that call `Schema::table('orders', ...)`.
- No committed create-`orders` migration or canonical full installation schema was found.
- Model/query inspection can infer required columns but cannot prove authoritative types, indexes, constraints, or historical data shape.
- Unknown local databases were not used.
- The default seeder is not a safe installation substitute; it injects synthetic operational data.

Required evidence is a sanitized, read-only schema-only export from the authoritative environment, followed by a reviewed schema diff and empty-disposable-database migration run.

## Money and payment truth

Provider/configuration classes, payment ledgers, order transactions, payout requests, wallet models, and some split logic exist. They do not form one proven, reconciled system.

The required equation is:

`customer/load price - driver/carrier payout - dispatcher commission - external costs - processing fees = Urban Goodz gross margin`

No single control surface or immutable rule snapshot proves this equation across loads, courier, medical, packages, rentals, creators, services, and business billing. “Paid” can be a database state without provider settlement. AI output can influence a payout path without enforcing recommendation-only, approval, sandbox, or live-pricing flags.

## Dashboard truth

The source includes an Urban Goodz Command Center and Revenue Command Center, but live screenshots show the legacy marketplace section remains dominant.

The visible 130 vendors/providers versus 0 stores is explained by inconsistent query scoping: one card counts active-vendor stores across the settings context while the user chart always filters stores by the current module, which can be null. Catalog item counts do not consistently require a valid store relation. Gross sale is a legacy `order_transactions.order_amount` sum and is not reconciled to provider events, Urban Goodz ledgers, vendors, drivers, or customers.

`Demo Product` and `Carrot Imported` are visibly non-production-like ranking records. Their exact creation path, store ownership, and orphan status remain unproven until a read-only data audit.

The system currently has two competing dashboard concepts: the inherited `/admin` marketplace landing page and fragmented Urban Goodz command/revenue/routes/settings/JSON surfaces. They lack one metric registry and canonical control center. The approved direction is one role-aware Urban Goodz Operations Command Center, with legacy marketplace reporting isolated as a named module only after every dependency and replacement metric is traced and tested.

## SMS, CAPTCHA, and media-storage truth

The dedicated [SMS, CAPTCHA, and storage report](URBAN_GOODZ_SMS_CAPTCHA_STORAGE_READINESS_REPORT.md) records direct source paths and evidence limits.

- Live Twilio/2Factor status is user-reported, not independently proven. Actor recovery channels differ; Business reset is email and Vendor/standalone-app OTP is not established.
- Google reCAPTCHA v3 has focused fail-closed controller evidence, but live status, key/domain restrictions, and browser behavior are unproven.
- Custom CAPTCHA fallback can be selected by a client field; the phrase is reusable and invalid CAPTCHA does not hit the login-attempt limiter.
- The configured third-party storage selector always chooses local. Private Fashion Fit has one strong owned-download path, but legacy association/link paths and public business/driver document storage block a platform privacy claim.
- No application media backup/restore system was found. Reels upload/range streaming exists, but scanning, transcoding, CDN, lifecycle, restore, and live operation are unproven.

## Test-integrity truth

- Admin auth focused PHP evidence: 33 tests, 134 assertions, 0 failures/errors/skips in the reviewed artifact.
- Full patched PHP artifact: 388 tests, 112 errors, 7 failures; baseline has the same 119 failing identities.
- The database cannot be rebuilt cleanly from committed migrations.
- Admin Playwright staging certification was not executed.
- The earlier 32/32 browser certification was false-positive.
- Current Appium JUnit: 22 tests, 17 failures; no adequate artifact proves 352 passing executions.
- Vendor/Driver Flutter repositories each retain a template-level test estate rather than operational workflow certification.

No tests were executed during this low-disk, read-only evidence sprint.

## Surface readiness

Each entry is `built / total; wired / total; locally tested / total; live certified / total`.

| Surface | Readiness |
|---|---|
| Public website | 10/10; 10/10; 1/10; 0/10 |
| Admin Portal | 15/15; 13/15; 2/15; 0/15 |
| Business Portal | 8/8; 8/8; 0/8; 0/8 |
| Dispatcher Portal | 5/5; 5/5; 0/5; 0/5 |
| Vendor Web Portal | 5/5; 5/5; 0/5; 0/5 |
| Shopper app | 9/14; 7/14; 0/14; 0/14 |
| Vendor app | 1/7; 1/7; 0/7; 0/7 |
| Driver app | 4/8; 3/8; 0/8; 0/8 |
| Payments | 5/5; 3/5; 1/5; 0/5 |
| Security | 9/9; 2/9; 2/9; 0/9 |
| Notifications | 5/5; 0/5; 0/5; 0/5 |
| Realtime | 2/2; 0/2; 0/2; 0/2 |
| Database | 4/4; 1/4; 0/4; 0/4 |
| AI Operations | 1/1; 0/1; 0/1; 0/1 |
| Load sourcing | 3/3; 1/3; 0/3; 0/3 |

Feature-specific rows for orders, courier, medical, Fashion Fit, creators/reels/video, messaging, events, rentals, services, and Order Anywhere are in the master CSV. None has staging or current-live certification.

## What testers can use today

Source-level/manual exploration can proceed only in an isolated non-production environment with a reviewed prebuilt database and disposable accounts. The focused Admin login/authorization PHP test set can be rerun in its known compatible environment.

Release-signoff testers cannot yet rely on:

- a clean install;
- full Admin browser workflows;
- money settlement;
- cross-role isolation;
- Vendor/Driver standalone operational data;
- iOS Shopper identity/Firebase;
- external adapters;
- production dashboard truth;
- notification/queue/cron delivery;
- full-platform mobile automation.

## Shortest safe path to core tester readiness

1. Recover and review the authoritative schema baseline.
2. Make seeding production-safe and create explicit disposable fixtures.
3. Close fail-open webhook and payout authorization/financial-control defects.
4. Coordinate the Admin password-cookie finding with the active auth owner.
5. Make AI Chief of Staff read-only and permissioned or disable its route until repaired.
6. Replace false-positive tests and establish a clean full-suite baseline.
7. Provision isolated staging roles, payments, notifications, realtime, and mobile identities.
8. Execute meaningful Admin/Business/Dispatcher/Vendor/Shopper/Driver E2E workflows with final-state and next-role assertions.
9. Reconcile dashboard data and classify demo/imported records.
10. Approve one exact SHA for controlled deployment with backup and rollback evidence.

## Effort and dependencies

The 73-task recovery plan estimates:

- P0: 1,064 engineering hours;
- P1: 736 hours;
- P2: 320 hours;
- P3: 80 hours;
- total: 2,200 engineering hours before contingency.

Parallel lanes reduce calendar time but not evidence requirements. Core tester readiness is approximately the P0 program plus the relevant P1 portal/mobile work: roughly 1,200-1,500 engineering hours depending on schema recovery and external-access latency. Full feature readiness is the full 2,200-hour backlog. Trustworthy certification adds staging execution, evidence review, production backup/deployment, and live verification after implementation.

Critical external blockers:

- authoritative schema-only export;
- isolated staging database and role fixtures;
- Apple signing/App Store ownership;
- Firebase project ownership and isolated app registrations;
- payment provider sandbox credentials/webhook secrets;
- SMTP/SMS/push/realtime staging credentials;
- DAT/Truckstop and other partner contracts/credentials;
- narrow read-only production deployment and data evidence.

## Safe work completed in this audit

Only documentation, source-derived inventories, census tooling, and sprint planning were created. No application runtime file, authentication owner file, schema, migration, environment, database, cache, server, provider, or production system was changed.

## Claude handoff after usage reset

The next bounded Claude task should be the Admin authentication/authorization deployment gate on its existing ownership branch:

- review the latest exact auth SHA and parent;
- remove any client-side password/reversible credential retention;
- preserve generic failed-login behavior and CAPTCHA fail-closed checks;
- run the focused auth suite;
- execute the guarded non-production Playwright suite only after a reviewed staging database and non-primary authorized/restricted accounts exist;
- produce exact-SHA, artifact, backup, rollback, and live-verification evidence.

Claude should not change Dashboard metrics, AI Chief of Staff, pricing/payout, seeders, mobile apps, the missing `orders` schema, or this audit branch during that bounded task.

## Final readiness decision

- Ready for exploratory source review: **YES**
- Ready for controlled isolated development: **YES**
- Ready for release-candidate tester certification: **NO**
- Ready for deployment: **NO**
- Ready for public launch: **NO**
- Ready for full certification: **NO**
