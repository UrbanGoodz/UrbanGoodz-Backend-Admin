# Urban Goodz — Punch List

Known debt and unfinished work, with enough detail to act on without
rediscovering the problem. Written 2026-08-07.

---

## 1. Retire the 6amMart driver wallet (deferred, deliberate)

**State:** driver payouts now read `urban_goodz_driver_earnings` only.
`delivery_man_wallets` is inert for payouts but still live for other flows.

**Why it is not finished:** 6amMart's order settlement still *writes*
`delivery_man_wallets`, and the admin disbursement screens still *read* it.
Removing the table wholesale breaks both. The two ledgers were found
disagreeing by an order of magnitude on production — driver 15 showed
$120.00 in Urban Goodz earnings and $2.00 in the 6amMart wallet.

**What full retirement requires:**
1. Change order settlement to write `urban_goodz_driver_earnings` at the point
   it currently writes `delivery_man_wallets`.
2. Repoint the admin disbursement screens (`Admin/DeliveryMan/DeliveryManController`,
   `StoreDisbursementController`) at the Urban Goodz ledger.
3. Backfill: reconcile existing `delivery_man_wallets` balances into
   `urban_goodz_driver_earnings` as opening entries, one per driver, so no
   historic money disappears.
4. Only then drop the wallet reads.

**Risk if skipped:** two systems of record for driver money. They have already
diverged once.

---

## 2. Vendor balance parity

`store_wallets` is the source of truth and is wired. Two follow-ups:

- `collected_cash` (COD money already in a vendor's hands) is surfaced but not
  reconciled by the payout flow. The platform's own wallet-adjustment flow
  handles it; the payout path should eventually respect it so a vendor holding
  significant cash cannot also draw the full balance.
- Vendor payouts write `urban_goodz_driver_payout_requests` (now generalised to
  either payee). The 6amMart `withdraw_requests` table is untouched and still
  used by admin disbursement. Same divergence shape as item 1, smaller.

---

## 3. Stranded — not yet functional end to end

Built and deployed: schema, catalogue, settings, dispatch/broadcast worker,
responder presence, accept/decline, customer selection, messaging,
notifications, ID verification, consent, disclosure logging.

Missing before a real rescue can happen:

- **No payment provider on the $5 fee or the escrow.** Nothing charges, nothing
  releases. `help_request_fee_status` only ever reads `unpaid` or `waived`.
- **No client UI at all** — no Services card, no "I'm Stranded" flow, no
  responder app.
- **No admin screens** for Samaritans, professional providers, verification
  review, trust scores, or live requests. Settings page exists; nothing else.
- **Trust score is a column nobody writes.**
- **Masked calling** deliberately not built — requires a telephony provider.

---

## 4. Operational tables that are still empty

These are schema-only. Anything built on top of them renders zeros.

| Table | Rows | Fills when |
|---|---|---|
| `urban_goodz_package_scans` | 0 | a driver scans in the field |
| `urban_goodz_route_operational_metrics` | 0 | a driver *runs* a route |
| `urban_goodz_route_execution_versions` | 0 | same |
| `urban_goodz_dispatch_audit_logs` | 0 | dispatch runs for real |
| `urban_goodz_payment_transactions` | 0 | a card payment is processed |

**No card payment has ever been processed.** PayPal and Stripe are both active
with live credentials; all 12 real orders were cash on delivery. That is the
largest untested risk in the system.

---

## 5. Admin panel

- **6amMart teal `#00868F`** still throughout: `theme.minc619.css` (85
  occurrences), `style.css` (12). `ug-admin.css` loads 11th of 12 and is the
  override point. A brand layer was written and reverted 2026-08-07 to avoid
  colliding with another builder — see commit `dd1f7f4`.
- Some teal is url-encoded **inside SVG data URIs** on checkboxes and switches.
  CSS colour overrides cannot reach it; those rules must be restated.
- **Thyga Semirounded and Arial Nova are not bundled anywhere in the project.**
  The typography spec cannot be delivered until the woff2 files exist.
- **Military time**: `timeformat` is already `12` in `business_settings` and is
  correct. Screens showing 24-hour time bypass `Helpers::time_date_format()`
  and call `->format('H:i')` directly. 26 admin blades contain `H:i`; **4 are
  `<input type="time">` and must stay 24-hour** — the HTML spec requires it.
  The offending display screens were never identified in the running admin, so
  this needs verification before anyone edits blades.

---

## 6. Client (shopper app)

- Events, Community, Urban Goodz Plus, Creator Commerce screens still make
  **zero API calls**.
- `packages/ug_design_system/` is built and analyzer-clean but **not imported
  into any `main.dart`**.
- Onboarding still ships the stock 6amMart cartoon illustrations
  (`assets/image/onboard_1..3.png`).
- In-app update client is wired but **no release has ever been published**, so
  the update path has never fired.

---

## 7. Testing

- `ug-playwright` suite (41 routes) exists and has never run authenticated.
  Admin auth works through an existing Chrome session — no password needed.
- No `integration_test` harness for the Flutter app. Release builds strip
  `ApiClient` logging entirely (`kDebugMode`), so on-device network behaviour
  can only be observed from a debug build.
