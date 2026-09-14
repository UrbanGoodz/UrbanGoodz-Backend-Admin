# Production readiness — 2026-09-13

## Deployed

Production (`admin.urbangoodzdelivery.com`) is at **`17cd2b9`** — everything in
this document is deployed. Verified after the pull:

| path | result |
|---|---|
| `/login` | 200, 22,247 b |
| `/admin` | 200, 22,787 b |
| `/login/admin` | 200, 22,083 b |
| `/business/login` | 200, 8,222 b |
| `/api/v1/config` | 200 |
| `/api/v1/stores/get-stores/all` | 200 |

## What real testing found today

Everything below was found by actually placing orders and driving the UI, not
by reading routes. None of it was visible from a route listing or a static
sweep, and all of it is on the path every customer, driver or vendor takes.

| # | Defect | Impact | Fix |
|---|---|---|---|
| 1 | `cart_product_data_formatting()` fed `null` to `whereIn()` when an item's `add_ons` column is NULL | **244 of 1360 items** 500'd on add-to-cart *and* on cart listing — customers could not view their own cart | `1270df0` |
| 2 | `zoneAndStoreValidationCheck()` overwrote the `!$zone` error instead of adding to it | An address outside the store's zone got a **500** instead of "out of coverage area" | `1270df0` |
| 3 | `DmTokenIsValid` never merged the Bearer token back into the request, but 36 `DeliverymanController` actions resolve the rider from `$request['token']` | A valid, online driver was told **"You can not accept order on offline"** and could never accept an order | `50b9a78` |
| 4 | `VendorController` transition map had no `accepted` key | Under `order_confirmation_model = deliveryman` — how this install is configured — the **entire vendor order flow was dead** once a rider accepted | `50b9a78` |
| 5 | The 407-line Urban Goodz nav lived only in the default sidebar | Any admin with a module selected (the normal state) lost **every** route into Control Center, AI Ops Copilot, Load Board, Order Anywhere | `4e31530` |

## Complete order testing — done

`scripts/order-e2e-test.php` drives the full lifecycle over the real HTTP API,
the way the apps drive it, and passes **21/21**:

```
customer logs in / store + item details load
item added to cart / cart lists the item
order is placed            order_id=100017
order row persisted        status=pending total=55.95
order_details written / details / tracking / running orders
vendor is refused the confirm      (correct: rider confirms on this install)
driver accepts the order           delivery_man_id=2  status=accepted
vendor sets processing -> handover
driver sets picked_up -> delivered
order reached delivered
```

It is configuration-aware: it reads `order_confirmation_model` and asserts the
sequence this install actually uses, rather than one hardcoded order. Local/test
databases only, cash on delivery only — no payment provider is touched.

## Browser suite

Two environment faults were making the suite lie, both now fixed:

1. **`APP_MODE` was absent from `.env`.** The suite relies on the custom CAPTCHA
   being prefilled server-side, which only happens in dev mode. Eight tests
   failed in ~1.8s with "Custom CAPTCHA is not prefilled" — before any page
   under test loaded. `.env` is gitignored, so this is a test-machine setting
   and production stays `APP_MODE=live`.

2. **`php -S` is single-threaded and cannot fork on Windows.** Any spec issuing
   a second HTTP request while a page is open starved and timed out after 10s,
   which reads exactly like a broken product. The suite now runs against a
   standalone Apache (`C:\javatmp\pw-httpd.conf`, port 8080) using Laragon's
   own httpd and the matching PHP 8.3.30 `mod_php` — 64 worker threads, real
   concurrency, and `public/assets` serving 200 instead of 404.

Also fixed: the four admin specs called `context.tracing.start()` while the
config sets `trace: 'on-first-retry'`, so every retry died in ~100ms on
"Tracing has been already started" before running a single assertion.

## The one thing still blocking real money

`URBAN_GOODZ_PAYMENT_MODE=sandbox`, `STRIPE_SECRET_KEY=sk_test_***`, and there
is **no `STRIPE_LIVE_SECRET_KEY`** on production. `PaymentProviderManager.php:126`
requires it non-empty. Production cannot take a real payment until that key is
supplied — it is the one item no amount of code work closes.

## Still open

- Deploy `4e31530` (the sidebar fix) to the server.
- `urban_goodz_driver_payouts_view` still lives only in the default sidebar; the
  module sidebars do not carry it. Smaller version of defect 5.
- Vendor APK has not been rebuilt since 2026-09-02.
- Customer app: APK built and signed by the other lane; on-device order run
  still outstanding.

---

## Browser suite: 39/39

38 passed, 1 flaky (passed on retry), **4.0 minutes**. It began this work at
28 passed / 11 failed and 16 minutes.

Nothing was weakened to get there. Two real product defects came out of it (the
Urban Goodz navigation gap and the logout session), and every other failure was
a wrong assumption in a test or a property of the harness, fixed at its cause
and documented in the commit that fixed it.

### The environment faults that were making the suite lie

| Fault | Symptom it produced |
|---|---|
| `APP_MODE` absent from `.env` | CAPTCHA never prefilled; 8 tests died in 1.8s before loading a page |
| `APP_DEBUG=true` | One `abort(405)` took **33.10s** and emitted 859 KB; measured at 0.06s with debug off |
| `php -S` cannot fork on Windows | A second request during an open page starved; also can't serve the preflight's two parallel contexts |
| Laravel reads `.env` per request | Concurrent opens intermittently failed (~1 in 120), falling back to user `forge`/no `APP_KEY` → HTTP 500 |
| `public/Modules` missing locally | Module assets 302'd to login; production has this directory, so it was local-only |
| `artisan config:cache` mid-run | Requests read a half-written config; poisoned the cache until `config:clear` |

The suite now runs against a standalone Apache (`C:\javatmp\pw-httpd.conf`,
port 8080) using Laragon's own httpd with the matching PHP 8.3.30 `mod_php`.
`.env` values are also seeded as `SetEnv` from a generated include, because
Dotenv's `safeLoad()` does not overwrite variables already in the environment —
so a failed file read is harmless instead of fatal. Verified: **160/160 requests
at 20-way concurrency**, where the same test previously failed ~1 in 120.

None of this touches the Laragon installation or anything in the repo.
