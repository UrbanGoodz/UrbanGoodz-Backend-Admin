# SESSION-09: Money, Notifications, and End-to-End Proof

**Date:** 2026-07-14
**Session:** 09
**Branch:** `adminpanel-v39-backend-sprint`
**Status:** COMPLETE — PASS
**Starting HEAD:** `91e4456`

---

## Starting Git State

- Branch matched `adminpanel-v39-backend-sprint` and was synchronized with origin.
- Pre-existing modified files: `.gitignore`, `.rnd`.
- `.rnd` remains preserved and unstaged.
- The four-line `.gitignore` OAuth-key rule was verified, intentionally adopted, and committed with the first security milestone.
- `storage/oauth-private.key` and `storage/oauth-public.key` exist locally, are ignored, and are not tracked.
- `firebase-messaging-sw.js` contains none of: `private_key`, `client_secret`, `server_key`, `STRIPE_SECRET`, `DB_PASSWORD`, or `BEGIN PRIVATE KEY`.

## Baseline Validation

- `php artisan route:list`: PASS, 2,140 routes.
- `php artisan test`: PASS, 139 tests / 498 assertions.
- Local test database: Laragon MySQL 8.4.3, `urbangoodz_test` on `127.0.0.1:3306`.
- Production database and live payment providers were not accessed.

## Milestone 1: Sandbox Payments, Webhooks, and Ownership

### Completed

- Proved staged-test payment-link creation and replay idempotency.
- Proved successful capture webhook and duplicate replay idempotency.
- Added durable zero-value failure ledger entries for failed webhooks.
- Proved failed capture webhook replay creates one failure ledger entry only.
- Fixed webhook ledger event mapping (`authorization`, capture/refund failure types).
- Fixed unhandled webhook events being reported as handled.
- Changed Stripe webhook validation to fail closed when the signing secret is absent.
- Split Order Anywhere API routes by actor middleware:
  - customer: `auth:api`
  - admin: `auth:admin`
  - vendor: `vendor.api`
  - driver: `dm.api`
- Scoped customer records/payments, vendor records, and driver assignments to the authenticated owner.

### Focused Evidence

- `php artisan test tests/Feature/UrbanGoodzPaymentAuditTest.php`
  - PASS: 15 tests / 54 assertions.
- `php artisan route:list --path=api/v1/order-anywhere -v`
  - PASS: 15 routes with expected actor middleware.
- `php -l` on all changed PHP files: PASS.
- `git diff --check`: PASS.

### Files Changed

- `.gitignore`
- `app/Http/Controllers/Api/V1/OrderAnywhereTesterController.php`
- `app/Http/Controllers/Api/V1/PaymentWebhookController.php`
- `app/Services/Payments/StripePaymentGateway.php`
- `app/Services/UrbanGoodzPaymentService.php`
- `routes/api/v1/urban_goodz.php`
- `tests/Feature/UrbanGoodzPaymentAuditTest.php`

### Commit and Push

- Commit: `e1186a2` — `fix(payments): harden webhooks and order ownership`
- Push: SUCCESS to `origin/adminpanel-v39-backend-sprint`.

## Milestone 2: Wallets, Earnings, Refunds, Withdrawals, and Reconciliation

### Completed

- Proved settlement credits vendor wallet, driver wallet/earning, and platform admin wallet exactly once.
- Removed double counting of Order Anywhere splits from driver all-time earnings.
- Added recipient-level refund reversal splits with vendor-first, then driver, then platform allocation.
- Added driver negative earning entries for settled refund reversals.
- Proved a full $50.00 refund returns vendor, driver, and platform wallet deltas to zero.
- Proved capture splits ($50.00), reversal splits ($50.00), and net ledger ($0.00) reconcile.
- Made vendor withdrawal validation numeric and method-aware.
- Locked vendor wallets during withdrawal reservation and cancellation.
- Rejected insufficient balances without adding a second withdrawal or changing reserved funds.
- Scoped withdrawal cancellation to the authenticated vendor.
- Proved driver earnings exclude another driver's records.
- Proved another business's invoice returns 404.

### Focused Evidence

- Focused money/ownership suites: PASS, 21 tests / 87 assertions.
- PHP syntax checks on all changed files: PASS.
- `git diff --check`: PASS.

### Files Changed

- `app/Http/Controllers/Api/UrbanGoodzDriverApiController.php`
- `app/Http/Controllers/Vendor/WalletController.php`
- `app/Services/UrbanGoodzPaymentService.php`
- `tests/Feature/UrbanGoodzBusinessInvoiceOwnershipTest.php`
- `tests/Feature/UrbanGoodzPaymentAuditTest.php`
- `tests/Feature/UrbanGoodzWithdrawalSecurityTest.php`

### Commit and Push

- Commit: `60a85ac` — `fix(money): reconcile refunds and secure withdrawals`
- Push: SUCCESS to `origin/adminpanel-v39-backend-sprint`.

## Milestone 3: SMTP, Firebase, Persistence, Queue, Scheduler, and Retry

### Completed

- Added a shared notification service that persists in-app notifications and queues FCM delivery.
- Wired service-booking customer/vendor notifications and driver-dispatch notifications through the shared service.
- Added customer, vendor, and driver token resolution at job execution time so tokens are not serialized into queued payloads.
- Added a dedicated `notifications` queue job with 3 attempts and 30/120/300-second backoff.
- Added terminal failure logging containing notification/recipient IDs and exception class, but no recipient token.
- Added an injectable Firebase transport over the existing FCM HTTP helper.
- Fixed SMTP test-mail acceptance when the mail transport returns no message object (including Laravel mail fakes).
- Registered schedules in the active Laravel 12 `bootstrap/app.php` configuration; the legacy console kernel alone was not active.
- Registered a one-shot notification queue worker every minute with overlap protection.

### Focused Evidence

- Notification/SMTP/service-booking/driver suites: PASS, 40 tests / 163 assertions.
- Safe SMTP recipient: `session9-safe-recipient@urbangoodz.test`, with `Mail::fake`; no real email sent.
- Customer/vendor/driver Firebase tokens were test-only fixtures with `Queue::fake`; no real push sent.
- Queue job transport execution: PASS with mocked transport.
- Retry/backoff and terminal failure logging: PASS.
- `php artisan schedule:list`: PASS; 3 tasks registered.
- `php artisan queue:work database --queue=notifications --stop-when-empty --tries=3 --backoff=30`: PASS, clean exit.
- PHP syntax checks and `git diff --check`: PASS.

### Files Changed

- `app/Http/Controllers/Admin/BusinessSettingsController.php`
- `app/Jobs/SendFirebaseNotification.php`
- `app/Services/FirebaseNotificationTransport.php`
- `app/Services/ServiceBookings/ServiceBookingWorkflow.php`
- `app/Services/UrbanGoodzDriverDispatchNotificationService.php`
- `app/Services/UrbanGoodzNotificationService.php`
- `bootstrap/app.php`
- `tests/Feature/UrbanGoodzDriverDispatchNotificationProducerTest.php`
- `tests/Feature/UrbanGoodzNotificationDeliveryTest.php`
- `tests/Feature/UrbanGoodzSmtpDispatchTest.php`
- `tests/Unit/ServiceBookingContractTest.php`

### Commit and Push

- Commit: `f026801` — `feat(notifications): queue Firebase delivery with retries`
- Push: SUCCESS to `origin/adminpanel-v39-backend-sprint`.

## Final Validation

- `php artisan route:list`: PASS, 2,140 routes.
- `php artisan test`: PASS, 153 tests / 568 assertions.
- Focused payment/webhook suite: PASS, 15 tests / 54 assertions at Milestone 1.
- Focused money/withdrawal/ownership suites: PASS, 21 tests / 87 assertions at Milestone 2.
- Focused SMTP/Firebase/queue suites: PASS, 40 tests / 163 assertions at Milestone 3.
- `php artisan schedule:list`: PASS, 3 registered schedules.
- One-shot `notifications` database worker: PASS.
- Final `git diff --check`: PASS.
- APK/build artifacts: N/A (Laravel backend session).

## Sandbox Scenarios Proven

1. Staged-test payment-link creation and persistent idempotent replay.
2. Successful capture webhook and order payment transition to `captured`.
3. Failed capture webhook and order payment transition to `capture_failed`.
4. Duplicate successful and failed webhook replay without duplicate ledger entries.
5. Valid order lifecycle transitions through completion and settlement.
6. Vendor wallet credit once.
7. Driver wallet and earnings credit once.
8. Platform fee split and admin-wallet credit once.
9. Partial and full refund reversal behavior.
10. Vendor withdrawal validation and atomic balance reservation.
11. Insufficient-balance rejection without a second withdrawal.
12. Customer payment, vendor withdrawal, driver earnings, and business invoice ownership restrictions.
13. Capture/reversal/ledger reconciliation to zero for full refund.
14. Safe SMTP test path using `Mail::fake`.
15. Customer Firebase persistence and queue dispatch.
16. Vendor Firebase persistence and queue dispatch.
17. Driver Firebase persistence and queue dispatch.
18. In-app notification persistence for all three recipient types.
19. Queue transport processing with mocked FCM transport.
20. Laravel 12 scheduler registration and one-shot worker execution.
21. Retry/backoff and token-free terminal failure logging.

## Ledger Evidence

- Capture: $50.00.
- Platform split: $5.00.
- Vendor split: $35.00.
- Driver split: $10.00.
- Full refund recipient reversals: $50.00 total.
- Final net ledger: $0.00.
- Vendor, driver, and platform wallet deltas after full refund: $0.00.

## Commit Chain and Push Results

- `e1186a2` — payment/webhook/ownership implementation — PUSHED.
- `75a2993` — payment DCP checkpoint — PUSHED.
- `60a85ac` — money/reconciliation/withdrawal implementation — PUSHED.
- `bf0d112` — money DCP checkpoint — PUSHED.
- `f026801` — queued notification/SMTP implementation — PUSHED.
- `d38dd88` — notification DCP checkpoint — PUSHED.
- Final Session 9 DCP closure: recorded by the commit containing this section.

## Final Git State and Preserved Work

- Branch: `adminpanel-v39-backend-sprint`.
- All Session 9 implementation and checkpoint commits pushed to origin.
- Pre-existing `.rnd` binary modification remains preserved and unstaged.
- No production database command, production payment, real email, or real push notification was executed.

## Remaining P0 Defects

- None found in Session 9 scope.

## Blockers

- None. Local Laragon MySQL 8.4.3 must be running for `urbangoodz_test` validation.

## Exact Continuation Commands

```powershell
cd "C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39"
git status --short --branch
git log -8 --oneline
[xml]$config = Get-Content "phpunit.xml"
foreach ($server in $config.phpunit.php.server) {
  [Environment]::SetEnvironmentVariable([string]$server.name, [string]$server.value, "Process")
}
php artisan route:list
php artisan schedule:list
php artisan test
```
