# Device test runbook (the checks nobody could run from the cloud)

Everything in the certification was verified by automated tests. This is the short list of things that only a real
phone, browser or payment sandbox can prove. Each row maps to a behaviour that was changed, with what to do and what
"pass" looks like. Budget: about 90 minutes for the phone checks, 30 for the browser checks.

## 0. Setup (once)
1. **Backend on staging**, migrations run (see the deployment checklist in `FINAL_CERTIFICATION_REPORT.md`), `schedule:run` on cron.
   Set `map_api_key_server`, `MAIL_FROM_ADDRESS`, `ORDER_ANYWHERE_MARKUP_PERCENT`. Stripe and Adyen in sandbox mode.
2. **Phone**: USB debugging on, `adb devices` shows it as `device`. For a local backend: `adb reverse tcp:8000 tcp:8000`.
3. **Run an app against staging** (each app takes the API base URL at build time):
   - Driver: `cd driver_app && flutter run -d <device> --dart-define=UG_API_BASE_URL=https://<staging>`
   - Customer and vendor: use their configured base URL (see each app's `app_constants` / `api_config`).
4. Seed: one approved driver with a device token, one customer, one vendor with a store, one zone with a module.
5. **To let Claude run this for you:** start a session ON the computer that has the phone (Claude desktop app, or run
   `claude` in the app folder). That session can run `adb` and `flutter` directly. The cloud session cannot reach the phone.

## 1. Driver app (highest value: money and dispatch)
| # | Do | Pass |
|---|----|------|
| D1 | Log in as the approved driver; go online | Location pings reach the server (driver appears on the admin live map) |
| D2 | Suspend that driver from the admin panel, then use the app | Next API call is refused and the app signs out or shows a clear message (token revoked) |
| D3 | Place an Order Anywhere request as a customer and pay (sandbox). Driver nearest the pickup should get an offer | Offer arrives (push + in app) within ~1 minute; decline moves it to the next driver; a second driver gets it |
| D4 | Accept the offer | Customer is charged only now (check the payment intent: captured at accept, not at checkout); a purchase card is issued |
| D5 | Use the card, mark picked up, out for delivery, delivered | Each step succeeds in order; skipping a step is refused; request ends `completed` |
| D6 | Complete a dedicated route with a package still undelivered | Refused with the pending count. Complete it properly: paid once. Try to start it again | Second start refused (409); no second payout |
| D7 | Let an offer expire untouched | It passes to the next driver after the offer window (default 3 min) |
| D8 | (After the app wires the receiver prompt) complete a delivery | A "who received it" prompt appears; the name shows in the order's driver notes. Only then set `UG_REQUIRE_DELIVERY_PROOF=true` |
| D9 | Open two job types that share an id (rare) | The correct one opens (the app sends `source`) |

## 2. Customer app
| # | Do | Pass |
|---|----|------|
| C1 | Place an Order Anywhere request; admin quotes with item cost and markup | The price shown is the quoted total; changing it client-side cannot change the charge |
| C2 | Cancel before the driver buys | Full refund; the driver's job disappears |
| C3 | Cancel after the driver has bought | Refused with the "contact support" message |
| C4 | Start a Stranded request, pay the fee, then kill the app and reopen the Stranded screen | A "Continue your request" banner returns you to it |
| C5 | Settings > Notification preferences | Four switches; SMS and promotions start OFF; toggling persists after reopening |
| C6 | Open the Urban Goodz hub and menu | No Medical Courier, Load Board, Logistics or ride-share entries; no module list entry for ride-share |
| C7 | Push: place an order and watch the notifications | The push shows the real title and message, not "Urban Goodz" |
| C8 | As a second customer, try to open the first customer's order id (via a proxy or the API) | 404, no data |

## 3. Vendor app
| # | Do | Pass |
|---|----|------|
| V1 | Edit an item, coupon, add-on, banner | Saves; a malformed save shows a validation message, not an error screen |
| V2 | Try another store's item id (API) | 404 |
| V3 | Vendor AI assistant | Sections load independently; a missing one shows an error, not an empty list |

## 4. Browser (admin, vendor and business portals)
| # | Do | Pass |
|---|----|------|
| B1 | **The 419**: log in to the admin panel from a fresh browser behind the real load balancer, wait, navigate, submit a form | No "Page Expired". If it appears: check `TRUSTED_PROXIES`, `APP_URL`, `SESSION_SECURE_COOKIE`, shared session store |
| B2 | Admin: suspend, approve and deny a driver; vendor panel: suspend a driver | Works via the confirm dialog (these are POST forms now) |
| B3 | Admin > Order Anywhere: open the list | "Automatic dispatch" card saves radius and timings; the request page shows item cost, markup and pickup fields |
| B4 | Business portal: create a route with "Repeat: every weekday" | Run `php artisan routes:generate-recurring`; runs appear for the coming week, unassigned |
| B5 | Admin notifications | Order Anywhere alerts (no driver, capture failed) are visible to admins (stored as in-app alerts; confirm the panel shows them) |

## 5. Payments sandbox (needs provider keys)
| # | Do | Pass |
|---|----|------|
| P1 | Authorize then cancel before any driver accepts | Hold is voided; no charge, no refund |
| P2 | Authorize, driver accepts, admin cancels | Captured amount refunded in full |
| P3 | Issuing: card tap at a merchant | Spend recorded; receipt reconciliation flags overage |
| P4 | Webhook replay | Capture webhook delivered twice changes nothing the second time |
| P5 | Stranded: pay the help fee with a declined card, then a good card | Exactly one charge, request unlocks |

## 6. After the first night in production
Check `php artisan schedule:list` shows the jobs, then confirm: Stranded tick ran without messaging about old
requests, history prune removed rows in batches, `order-anywhere:dispatch-pending` is running every minute,
`php artisan order-anywhere:audit-splits` reports `mismatched=0`.

## Existing automated device suites
- Customer app: `integration_test/recon_test.dart`.
- Appium and Playwright specs exist under `urbangoodz_vendor_app/vendor_app/` and the customer repo `tests/`; they target a live host, so point them at staging first.
