# P6 — Driver Notification Behavioral Test Report

Phase: 4B-P6 (Test / Hardening only)
Subject: P4 driver dispatch notification inbox + P5 producer hooks

## DB-backed testing availability

**NOT AVAILABLE in this environment.** A read-only `php artisan tinker` introspection attempt (`Schema::getColumnListing('user_notifications')`) timed out (no reachable/seedable database connection from the local working copy). Per the allowed gap, behavioral assertions were implemented as **static / regression tests** that validate the producer service logic, the allowlist, the dedupe query pattern, and the hook call sites — without faking passing DB behavior.

## Tests added

- `tests/Feature/UrbanGoodzDriverNotificationBehavioralTest.php` (new) — 9 static/regression assertions.

(Run alongside the previously added P4 `UrbanGoodzDriverDispatchNotificationSecurityTest` and P5 `UrbanGoodzDriverDispatchNotificationProducerTest`.)

## Tests run

```
php artisan test --filter=UrbanGoodzDriverDispatchNotification
```

Result: **21 tests passed** (P4 Security 7 + P5 Producer 7 + P6 Behavioral 9; 64 + additional assertions). No `route:cache` executed.

`php -l` clean on all changed PHP files. `php artisan view:clear` and `php artisan route:clear` succeeded.

## Notification row creation verification

Direct DB row creation could not be executed (no DB). Verified by static analysis:
- `createForDriver` calls `UserNotification::create(['delivery_man_id' => $deliveryManId, 'data' => json_encode($clean)])`.
- `data` is built only from the `ALLOWED_PAYLOAD_KEYS` allowlist; no raw model dump is written.

## Dedupe verification

- `createForDriver` accepts a `dedupeKey`; before insert it runs `alreadyExists()` which scans the driver's `user_notifications` for a matching `dedupe_key` inside `data` and returns `null` (no insert) when found.
- Applied to all produced types with stable keys (e.g., `business_courier_assigned:{jobId}:{driverId}`). Cross-path duplicate analysis (A1+A2) confirms no duplicate rows for the same assignment.

## P4 inbox compatibility verification

- P4 controller (`UrbanGoodzDriverDispatchNotificationController`) is unchanged and still passes its 7 security tests.
- P5-produced rows use the same `data` JSON shape the P4 inbox normalizes (`type`, `title`, `description`, `job_id`/`order_id`, `job_type`), so the inbox reads them correctly. `markRead` / `readAll` / `dismiss` operate on the same `data` JSON (`read_at` / `dismissed_at`), so they work with P5-produced rows.

## Sensitive field protection verification

Static assertions confirm the producer service source contains **none** of: `admin_notes`, `authorized_amount`, `final_amount`, `quote_amount`, `customer_phone`, `customer_email`, `payout`, `commission`. Payloads contain only IDs + safe strings. The P4 inbox also strips anything beyond allowlisted fields, so produced rows cannot leak sensitive data downstream.

## Driver scoping / security confirmation

- Notifications are created only for the assigned/intended driver (`assigned_delivery_man_id` / `assigned_driver_id`).
- `createForDriver` validates `DeliveryMan::where('id', $driverId)->exists()` and rejects invalid IDs.
- No `driver_id` is accepted from any unauthenticated/public request; targeting derives solely from the existing admin assignment flow.
- No "notify all drivers" path; no push/WebSocket (service contains no Firebase/FCM/push/broadcast references).

## Remaining test gaps

- No live DB-backed assertion of actual `user_notifications` row insertion, dedupe suppression at the database level, or end-to-end P4-read-of-P5-row behavior. Requires a DB-seeded environment.
- AI Ops auto-dispatch (R2), Order Anywhere assignment (O1), and dedicated-route package exceptions (E2/E3) are intentionally not covered (see coverage audit).

## Known unrelated failures (documented only)

- `ExampleTest` 302-vs-200 baseline.
- Session 1 `ImportUrbanGoodzCleanedBusinesses.php` fatal.
- `.phpunit.result.cache` permission warning.

None affect P4/P5/P6 notification work.
