# Order Anywhere + Digital Humans certification

## How Order Anywhere is meant to work (owner's description, confirmed against the code)
1. A customer requests an item from anywhere.
2. It is **sourced and priced with Urban Goodz's markup**; the customer pays the quoted price.
3. The order goes **automatically** to the **nearest driver to the pickup location**, then the next closest, cycling
   through every driver inside an admin-adjustable radius until one accepts, an admin steps in, or it is cancelled.
4. The accepting driver is issued a **temporary tap-to-pay purchase card**, buys/collects the item, pays, delivers.
5. No driver available: admins are told; if it must be cancelled, the customer gets their money back.

## Money timing (recommendation, implemented)
Authorize at checkout, **capture when a driver accepts**, release to the driver's card then. If nobody accepts, the
authorization is **voided**: nothing was charged, nothing to refund, no processor fee, no multi-day wait. Capturing
at checkout would charge every customer up front and then refund the ones nobody served (fees are not returned on refunds).
After capture, an admin cancel refunds in full.

## Defects fixed
| # | Defect |
|---|--------|
| 1 | Customer could set `vendor_id` and `admin_notes` on their own request; raw request body stored in metadata. |
| 2 | **Customer chose the amount authorized** (`authorized_amount`): $0.01 authorized against a $60 quote. Now always the quote. |
| 3 | Driver flow broken: accepting a job and finishing a delivery returned 422 (mapping disagreed with the state machine). Rebuilt as one legal step at a time per fulfilment path. |
| 4 | Driver could mark "purchased" without spending on the issued card. Now requires a recorded card purchase when a card exists. |
| 5 | `expireStaleOffers` ungrouped `orWhere`: **in-progress deliveries were marked expired** whenever any driver opened their dispatch list. |
| 6 | `authorized -> approved` was manual, so nothing dispatched after payment. Now automatic (external merchant). |
| 7 | No-driver case was silent and never retried; a decliner could be re-picked forever. Now cycles nearest-first with a per-driver window, new rounds, admin alert, customer notice, timed cancel + void. |
| 8 | Admin cancel never told the payment provider (hold lingered). Now voids or refunds. |
| 9 | Refund after capture threw *after* the provider refunded, rolling back the DB (double-refund hazard). Unallocated remainder is now ledgered, refund stands. |
| 10 | Customer receipt upload accepted any `receipt_path` string. Restricted to the upload folder. |
| 11 | `createFromOption` (unrouted) trusted client fees. Server-priced. |
| 12 | No markup computation existed: admin typed a total. Added `OrderAnywhereQuotePricing` (item cost -> markup, delivery, tax -> quote). |

## Controls (admin-adjustable, `GET/PUT order-anywhere/admin/dispatch-settings`)
`auto_dispatch`, `radius_miles` (default 25), `offer_minutes` (3), `escalate_after_minutes` (10), `cancel_after_minutes` (30; 0 = never auto-cancel).
Env defaults: `ORDER_ANYWHERE_*`. Markup: `ORDER_ANYWHERE_MARKUP_PERCENT` or business setting `order_anywhere_markup_percent` (no silent default).

## Tests
`OrderAnywhereJourneyTest` (10), `OrderAnywhereAutoDispatchTest` (16). Full suite 1,420 passing.

## Open / not verified
- **"Anywhere" pickup location:** dispatch uses `pickup_latitude/longitude`; if sourcing never sets them the code falls back to the zone centre, so "nearest to the pickup" is only as good as that data. Needs a geocoding/sourcing step.
- Real Stripe Issuing: cards spend from the platform's Issuing balance. Capture funds arrive with delay; the platform fronts the float. Needs a finance decision.
- Customer cancel after a driver accepted (money already captured, driver may have bought goods) is refused with 422; policy needed.
- Admin quote form needs an `item_cost` field (backend accepts it; the blade form was not changed). Admin alerts are stored as in-app `admin_alert` rows; confirm the admin panel displays them.
- `order-anywhere/driver/*` endpoints are correct but no app calls them; the driver app uses AI dispatches + purchase-card routes.
- Delivery proof not enforced (see punch list B2). Real provider calls not made.

## Digital humans
Existing tests pass (emotion engine, viseme orchestrator, ElevenLabs/Qwen fail closed). Fixed: `state` and `visemes` accepted unbounded text on an open route (now max 2000, as `speak`). Not done: a global spend cap for guest `speak` (only per-client throttle), persona/brand register audit, real TTS calls.
