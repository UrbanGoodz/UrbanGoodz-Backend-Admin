# Urban Goodz Final Sprint DCP - 2026-07-27

## Authoritative state

- Repository: `AdminPanel_Update_V39`
- Branch: `adminpanel-v39-backend-sprint`
- Protected route baseline: `0976528956553844d9d4d13a1cc188ace1303a33`
- Integration SHA `8f38b283e846b11dce98e5016883a90087591c40` was pushed and deployed; the scheduler flag hotfix below supersedes it.
- Live checkout: `/home/urbakkej/admin.urbangoodzdelivery.com/AdminPanel_Update_V39`
- Admin document root: `/home/urbakkej/admin.urbangoodzdelivery.com/AdminPanel_Update_V39/public`
- Public apex/www document root: `/home/urbakkej/public_html`
- The accepted deterministic route lifecycle was reverified from the correct live checkout without regression.

## Database and PHPUnit certification

- PHPUnit: `11.5.50`
- Active configuration: `phpunit.xml`, migrated with `php vendor/bin/phpunit --migrate-configuration`.
- Dedicated database: `urbangoodz_codex_test_20260727` on local `127.0.0.1`.
- `tests/CreatesApplication.php` rejects non-testing environments, non-allowlisted databases, non-local hosts, production-like names, and runtime connection changes.
- Before and after XML migration: 19 passed, 1,117 assertions, 0 failures, 0 skipped.
- Gate command:
  `php artisan test tests/Feature/UrbanGoodzRouteClusteringTest.php tests/Feature/UrbanGoodzPaymentAuditTest.php --log-junit "C:\UG\evidence\final-sprint\db-gate-junit.xml"`
- Commit/push: `03cb1569cf3f1b45151ec75548fed505a9d8b10a`.
- First full-suite run: 294 passed, 110 failed, 1,162 assertions. This was not accepted as a certified pass.
- Proven harness cause: `UrbanGoodzAiCopilotTest` declared SQLite but rebuilt core tables in the shared MySQL test database, corrupting later tests.
- Local fixes isolate that fixture, restore the disposable schema, correct the DB import, and make creator controller naming production-safe.
- Repaired focused suite: 42 passed, 132 assertions. Extended payment suite: 36 passed, 142 assertions.
- A later integration run exposed 24 remaining failures: stale admin-root expectation, two schema-mutating AI tests, an authentication-state test defect, stale driver-resequence expectations that contradicted the protected 409 contract, and three migration-ledger/schema mismatches in the disposable database.
- Each root cause was repaired or reconciled. The final committed-tree command was:
  `php artisan test --log-junit storage/app/test-evidence/final-committed-full-backend-junit.xml`
- Final result: 424 passed, 2,713 assertions, 0 failures, 0 errors, 0 skipped, 231.14 seconds.
- Safety guard remained active against `urbangoodz_codex_test_20260727`.

## AI

- Both the legacy OpenAI Business Setting and the owner-provided local key returned sanitized HTTP 401 invalid-key results. No credential value was exposed.
- Environment configuration now takes precedence over the legacy database fallback. Commit `f909abc9bcfa81f575bbff51c0764db2b8b8e113` is pushed/deployed within the current baseline.
- Deployed provider work adds allowlisted `openai`, `openrouter`, `gemini`, and `disabled` providers, fail-closed selection, bounded retries, sanitized health, and deterministic fallback.
- Provider tests: 10 passed, 43 assertions; wider affected tests: 31 passed, 159 assertions.
- Live configuration resolves to `gemini` and `gemini-3.5-flash-lite`; key presence was confirmed without disclosure.
- Sanitized synthetic health check passed at `2026-07-27T04:44:55-05:00`.

## Production queue and scheduler

- `QUEUE_CONNECTION=database`; `jobs` and `failed_jobs` exist; current failed-job count was zero.
- Worker queues: `payments,notifications,ai,load-sourcing,default`; tries 3; backoff 30.
- Installed cron:
  `* * * * * cd /home/urbakkej/admin.urbangoodzdelivery.com/AdminPanel_Update_V39 && /usr/local/bin/php artisan schedule:run >> /home/urbakkej/admin.urbangoodzdelivery.com/AdminPanel_Update_V39/storage/logs/scheduler.log 2>&1`
- Post-deployment review found Laravel rendered `--stop-when-empty='1'`, which Symfony rejected because the flag accepts no value.
- The focused hotfix passes the flag positionally. `schedule:list` now renders `--stop-when-empty`.
- Focused route/payment regression: 19 passed, 1,117 assertions; JUnit `storage/app/test-evidence/queue-schedule-fix-junit.xml`.

## Public routing and cities

- Apex and www return the public site with HTTP 200 and no redirect.
- Admin redirects only to the admin login and then renders successfully.
- The public tree is an unversioned legacy Laravel app. The backend repository root route redirects to admin login, so it must not be copied blindly into `public_html`.
- Existing zones are not sufficient to certify named cities as Now Serving. No multi-city homepage was deployed.

## Vendor/store reconciliation

- Production: 130 vendor accounts, 130 stores, one store per account, no orphan link.
- The Command Center's 130 count is an unscoped store count. The legacy Stores page defaults to the first active module, Car Rental, which contains exactly one store. This module scope is the proven 130-versus-1 cause.
- Structurally eligible active module/zone stores: 127. Stores with active approved offerings: 79. Eligible without an offering: 48. Demo-module stores: 3.
- Deployed work provides a read-only Vendors & Businesses directory and truthful Command Center labels without altering records.
- Directory tests: 4 passed, 32 assertions; dashboard regression: 33 passed, 134 assertions.

## Realtime

- Production remains intentionally inactive with log broadcasting. No Pusher credential was exposed or committed.
- Deployed backend supports `BROADCAST_CONNECTION` with legacy `BROADCAST_DRIVER` fallback, HTTPS Pusher configuration, private account/assignment channels, role-specific authorization endpoints, sanitized events, and Echo Pusher/Reverb selection.
- Realtime tests: 11 passed, 59 assertions. Route/payment regression aggregate: 30 passed, 1,176 assertions.
- Do not enable Pusher until all Flutter clients use the authenticated private-channel contract.

## Credential inventory (presence only)

- Google Maps browser/server configuration: present; route optimization remains deterministic Haversine, nearest-neighbor, and bounded 2-opt.
- Stripe legacy configuration: present, but disconnected from the Urban Goodz env-only payment adapter. Urban Goodz remains staged-test/sandbox and not live-certified.
- Firebase web/project/service configuration: present.
- SMTP: stored and enabled.
- Pusher production app: created in cluster `us2`; values are not installed in the repository.

## Integration commits pushed/deployed in `8f38b283`

- `a74b5ae` - test isolation, authentication-state correction, creator controller production naming, and protected driver-sequence assertions.
- `e46dc6a` - allowlisted AI provider gateway.
- `c91a3ff` - read-only Vendors & Businesses reconciliation.
- `4346a12` - private realtime/Pusher backend foundation.
- `707d4d3` - Playwright browser tooling dependency.
- Recovery evidence: `C:\UG\evidence\recovery\codex-final-integration-20260727\`.
- These commits passed the complete backend suite, were pushed, and were deployed in `8f38b283e846b11dce98e5016883a90087591c40`.

Do not enable live Stripe, mutate reconciled vendor/store records, enable production Pusher, or label a city "Now Serving" until the corresponding acceptance gates pass.
