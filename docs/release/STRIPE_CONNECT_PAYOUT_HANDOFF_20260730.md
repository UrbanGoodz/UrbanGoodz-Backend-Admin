# Stripe Connect P0 Payout Handoff

## Source

- Parent branch: `codex/financial-control-center-20260730`
- Parent SHA: `19b4914b3034b4352be4bb3eea0d32f1c6974b28`
- Delivery branch: `codex/stripe-connect-payouts-20260730`
- Deployment: intentionally not performed

## Delivered backend scope

- Accounts v2 recipient creation with explicit platform fee/loss responsibility.
- Hosted onboarding and continuation links collecting currently and eventually due
  requirements, future requirements, identity, and Stripe-hosted payout methods.
- Express account-management links.
- One connected account per `(owner_role, owner_id)` and one Stripe account ID globally.
- Native-role ownership plus explicit Master Admin bindings for secondary earning roles.
- Status/balance/history/settlement API for all required earning roles.
- Sandbox-only and `sk_test_`-only enforcement.
- Payment-confirmed, integer-cent, idempotent separate transfers.
- Immutable recipient allocations, Urban Goodz commission and Driver Admin fee retention.
- Proportional partial/full transfer reversals with failed reversal manual review.
- Balanced append-only transfer/reversal ledger entries and reconciliation runs.
- Signature-verified integration through the existing Stripe webhook endpoint.
- Replay-safe, sanitized, connected-account-isolated, out-of-order-safe Connect events.
- Master Admin role controls, per-account holds/suspension/delay/schedule/minimum/instant
  eligibility, failed/returned review data, actor bindings, and audit history.

## Migration

`database/migrations/2026_07_30_230000_create_stripe_connect_payout_tables.php`

Creates:

- `urban_goodz_connected_accounts`
- `urban_goodz_payout_actor_bindings`
- `urban_goodz_payout_role_controls`
- `urban_goodz_settlement_recipients`
- `urban_goodz_payout_transfers`
- `urban_goodz_transfer_reversals`
- `urban_goodz_connected_payouts`
- `urban_goodz_stripe_connect_events`
- `urban_goodz_payout_audit_events`

## Routes

Role APIs are documented in
`docs/contracts/STRIPE_CONNECT_PAYOUT_API_CONTRACT.md`.

Admin JSON routes under the existing Financial Control permission boundary:

- `GET admin/urban-goodz/financial-control/stripe-connect`
- `PUT admin/urban-goodz/financial-control/stripe-connect/accounts/{account}`
- `PUT admin/urban-goodz/financial-control/stripe-connect/roles/{role}`
- `POST admin/urban-goodz/financial-control/stripe-connect/actor-bindings`

Existing webhook:

- `POST /api/v1/payments/webhooks/stripe`

## Webhook events

`account.updated`, `v2.core.account.updated`, `capability.updated`,
`balance.available`, `transfer.created`, `transfer.failed`, `transfer.reversed`,
`payout.created`, `payout.updated`, `payout.paid`, `payout.failed`,
`payout.canceled`, `charge.refunded`, `charge.dispute.created`,
`charge.dispute.closed`.

## Environment variable names

- `URBAN_GOODZ_PAYMENT_PROVIDER`
- `URBAN_GOODZ_PAYMENT_MODE`
- `STRIPE_ENABLED`
- `STRIPE_PUBLISHABLE_KEY`
- `STRIPE_SECRET_KEY`
- `STRIPE_WEBHOOK_SECRET`
- `STRIPE_CONNECT_ENABLED`
- `STRIPE_CONNECT_API_VERSION`
- `STRIPE_CONNECT_RETURN_BASE_URL`

Do not put live keys in this lane. `STRIPE_CONNECT_RETURN_BASE_URL` must be an HTTPS UI
base that implements `/return` and `/refresh`, re-authenticates the owner, then calls the
authenticated refresh/continue API.

## Focused verification

- `tests/Unit/UrbanGoodzStripeConnectPayoutTest.php`
- PHP syntax checks for every changed PHP/config/route file
- Existing focused financial test remains the settlement/reconciliation regression suite

## Stripe Sandbox certification status

Not certified in this worktree: no test keys, webhook secret, HTTPS callback URL, or
Stripe CLI session were present. No live-money call was attempted.

Required certification owner run:

1. Configure test-only variables above.
2. Create Vendor, Driver, and Service Provider actor records/bindings.
3. Complete hosted test onboarding and call refresh.
4. Complete a test Checkout/PaymentIntent and call
   `ConnectedPayoutService::transferConfirmedSettlement`.
5. Confirm balances and payout webhooks.
6. Send a partial refund and verify proportional reversals/reconciliation.
7. Replay identical webhook payloads and send older events.
8. Attempt another actor's role/entity headers and verify `403`.

## Expected conflicts and integration

Likely conflicts: `routes/api/v1/urban_goodz.php`, `routes/admin.php`,
`config/urban_goodz_payments.php`, `composer.json`, `composer.lock`, and
`PaymentWebhookController.php` if the serialized release lane also changed payments.
Preserve this branch's Connect dispatch after signature verification while retaining
new shopper event behavior from the release branch.

Cherry-pick the final commit reported by the producing Codex session; do not deploy it
independently. Run the migration, focused financial/Connect tests, and Sandbox
certification before enabling any payout role.
