# Urban Goodz Stripe Connect Payout API Contract

Date: 2026-07-30
Audience: Claude role-facing UI worktrees and release integration

## Authentication and ownership

All endpoints require the existing role authentication middleware. The server derives
the native earning entity from that identity; clients cannot submit an arbitrary owner
ID. A user managing a non-native role must have a Master Admin-created payout actor
binding.

| Surface | Base endpoint | Authentication |
|---|---|---|
| Vendor/business/provider/stylist/creator when vendor-owned | `/api/v1/urban-goodz/vendor/payout-account` | Bearer token plus existing `vendorType` header (`vendor.api`) |
| Driver | `/api/v1/urban-goodz/driver/payout-account` | Existing driver bearer token (`dm.api`) |
| Business/dispatcher | `/api/v1/urban-goodz/business/payout-account` | Existing business guard |
| User-linked creator/stylist/event organiser | `/api/v1/urban-goodz/earner/payout-account` | Passport bearer token (`auth:api`) plus an Admin-created binding |

For a bound non-native earning entity, send both:

- `X-Urban-Goodz-Earning-Role`: `vendor`, `business`, `driver`,
  `service_provider`, `stylist`, `creator`, `dispatcher`, or `event_organiser`
- `X-Urban-Goodz-Earning-Entity-Id`: positive integer

Missing or mismatched bindings return `403`. Invalid roles/entity IDs return `422`.

## Buttons and screens

### Set Up Payouts

- Endpoint: `{base}/setup`
- Method: `POST`
- Request:

```json
{
  "email": "owner@example.com",
  "display_name": "Owner or business display name",
  "country": "US",
  "currency": "USD",
  "entity_type": "individual"
}
```

- Response `201`: `{"data":{"url":"https://accounts.stripe.com/...","expires_at":"..."}}`
- User action: open `url` in an in-app browser or system browser.
- Errors: `409` creation/setup state conflict; `422` validation; `503` missing HTTPS
  callback configuration; safe Stripe error code for sandbox API failure.
- Never collect or send a bank/routing number to Urban Goodz. Stripe hosted
  onboarding collects identity and payout method details directly.

### Continue Setup / Verification Required / Payouts Restricted

- Endpoint: `{base}/continue`
- Method: `POST`
- Request: empty JSON object
- Response `200`: a new, single-use Stripe hosted update URL.
- User action: show **Continue Setup** for `verification_required`,
  `requirements_due`, or `restricted`.
- Errors: `404` setup not started; `403` ownership mismatch; `409` Stripe account
  creation incomplete.

### Update Payout Account

- Endpoint: `{base}/manage`
- Method: `POST`
- Request: empty JSON object
- Response `200`: `{"data":{"url":"https://connect.stripe.com/express/...","expires_at":null}}`
- User action: redirect the authenticated owner immediately. Do not email or persist
  this single-use URL.

### Refresh Payout Status

- Endpoint: `{base}/refresh`
- Method: `POST`
- Request: empty JSON object
- Response: same complete payload as `GET {base}` after a Stripe account and balance
  refresh.
- User action: call after Stripe returns from onboarding and from pull-to-refresh.

### Status, balances, history, settlement details, refunds and reversals

- Endpoint: `{base}`
- Method: `GET`
- Response:

```json
{
  "data": {
    "account": {
      "owner_role": "vendor",
      "owner_id": 42,
      "status": "enabled",
      "restriction_status": "enabled",
      "disabled_reason": null,
      "charges_enabled": false,
      "payouts_enabled": true,
      "details_submitted": true,
      "transfer_capability_status": "active",
      "payout_capability_status": "active",
      "requirements_currently_due": [],
      "requirements_eventually_due": [],
      "available_balance_cents": 12500,
      "pending_balance_cents": 3800,
      "next_expected_payout_at": null,
      "instant_payout_eligible": false,
      "minimum_payout_cents": 0,
      "payout_schedule": "daily",
      "payout_delay_days": 0,
      "last_synced_at": "2026-07-30T22:00:00Z"
    },
    "required_owner_actions": [],
    "payouts": [],
    "transfers": [],
    "settlements": []
  }
}
```

Money fields are integer cents. Render currency using `currency`; never use
floating-point math.

`settlements` includes the immutable settlement, commission/admin fee split, refund
amount, append-only ledger, and reconciliation status. `transfers` includes pending,
blocked, created, partially reversed, reversed, failed, dispute hold, and manual review
states. `payouts` includes pending, paid, failed, canceled, and returned.

## UI state mapping

| API status | UI |
|---|---|
| `setup_required` | Set Up Payouts |
| `creating` / `pending` | Setup pending; allow refresh |
| `verification_required` | Verification Required + Continue Setup |
| `restriction_status=restricted` | Payouts Restricted; show safe `disabled_reason` |
| `enabled` and `payouts_enabled=true` | Payouts Enabled |
| `manual_hold`, suspension, or Admin-disabled | Contact Urban Goodz / payout on hold |
| transfer `blocked` | Pending action, never Paid |
| payout `paid` | Paid (only after `payout.paid`) |
| payout `failed` / `returned` | Action required / Admin review |

Error JSON follows Laravel validation/HTTP conventions. Do not retry `403` or `422`.
Retry transient `5xx` with backoff. A repeated setup, transfer, reversal, or webhook is
idempotent and returns/reuses the original record.
