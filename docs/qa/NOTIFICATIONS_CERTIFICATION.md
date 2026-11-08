# Notifications certification

Existing coverage re-run: 43 notification tests pass (delivery job, dispatch notification producer/security,
driver behaviour). Added: `NotificationPayloadAndOptOutTest` (5).

## Verified by reading code + tests
- `urban-goodz/notifications/ai/*` (generate, batch, history, mark-read) is strictly self-scoped: a signed-in customer
  can only create/read/mark their own notifications.
- Vendor inbox (`VendorNotificationController`) scopes every read/mark/delete by `vendor_id`.
- Driver and customer legacy inboxes filter `UserNotification` by the caller's own id (driver isolation now tested).
- Firebase job: queued on `notifications`, 3 tries with backoff, never logs the device token.

## Defects fixed
1. **Every AI/ops/dispatch push arrived as "Urban Goodz" with an empty message.** `SendFirebaseNotification` read
   `title`/`description`, but `UrbanGoodzNotification` stores `subject`/`body`. Payload now carries the real text.
2. **Email-channel notifications were never sent** and stayed `pending` forever while the API said success. Email is now
   delivered through the configured mailer (status `sent`/`failed`).
3. **SMS and webhook channels have no transport** and were also left `pending` forever. They are now marked `failed` with a
   clear `delivery_note`, so nobody believes an SMS went out.
4. A recipient with no device token caused 3 retries with backoff and error noise. It now fails once, quietly; the in-app
   copy is unaffected.
5. Defensive: the vendor inbox no longer indexes `stores[0]`.

## Open / decisions
- **No per-user notification preferences exist.** The settings table is admin-level per-event switches. There is no user
  opt-out for promotions, and no SMS/email consent record. Needed for TCPA / CAN-SPAM before any marketing sends.
- **No SMS provider is integrated** for `sms` channel rows (only the legacy 6amMart SMS module). Decide: wire a provider or
  remove the channel from the API.
- Production needs `MAIL_FROM_ADDRESS` set (the example default is `null`, which makes every email fail) and a running
  queue worker on the `notifications` queue.
- Push delivery to real devices not verified (no phone reachable from this environment); the FCM transport was mocked.
- Admin `admin_alert` rows are stored; confirm the admin panel renders them.
