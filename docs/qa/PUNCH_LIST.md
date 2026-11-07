# Urban Goodz certification punch list

Single source of truth for everything **not fully resolved**. Updated whenever an item opens or closes.
Release decision today: **NOT READY FOR PRODUCTION**.

Legend: `OWNER` needs a decision from you · `ENV` needs a device/browser/production access I do not have ·
`TODO` I can do next · `DONE` closed (kept briefly for traceability).

## A. Blocks release
| # | Item | Type | Notes |
|---|------|------|-------|
| A1 | Admin 419 fix not verified in production | ENV | Fix + tests are unit-level. Production needs `TRUSTED_PROXIES`, correct `APP_URL`, `SESSION_SECURE_COOKIE`, a session driver shared across servers. See `ADMIN_419_INCIDENT.md`. |
| A2 | Migrations must run in production | ENV | `2026_11_07_000001_make_payout_request_delivery_man_nullable` (vendor payouts fail without it) plus all earlier ones. |
| A3 | Stranded helper rewards are not real money movement | OWNER | `selectOffer` marks escrow `held`, `releaseEscrow` writes a "completed" ledger row; nothing is authorized/captured/transferred at Stripe. Decide: Stripe Connect vs manual settlement. |
| A4 | No device / Playwright / Appium runs | ENV | A device was attached once; no browser or device reachable from this container now. All app-level behaviour is unit/feature-test verified only. |
| A5 | Real Stripe / Adyen never called | ENV | Provider seams are scripted in tests; needs sandbox-key runs. |

## B. Product decisions (OWNER)
| # | Item |
|---|------|
| B1 | Recurring medical courier routes are not implemented. Build, or remove from the UI/marketing? |
| B2 | Delivery proof / signature: enforce server-side, or optional? |
| B3 | Driver app flow: `/sequence` vs `/finish`; `routes/from-batch` is not called by the app. Pick one path. |
| B4 | Customer-app Medical Courier and Logistics screens now get 401 after the auth fix: give customers a real entry path or hide the screens. |
| B5 | Ride-share screens have no backend API: build or remove. |
| B6 | Orphan legacy routes (see `known-broken-routes.txt`): delete or restore. |
| B7 | Admin suspend/approve are GET links: convert to POST (needs view edits + CSRF). |

## C. Certification lanes not finished (TODO)
| # | Lane | Status |
|---|------|--------|
| C1 | AI actions + Concierge | DONE with notes: see `AI_CONCIERGE_CERTIFICATION.md` (guest session-id entropy, guest chat retention, shared cache in prod) |
| C2 | Digital humans / avatar | DONE with notes: text cap fixed; guest `speak` has no global spend cap; persona register + real TTS not audited |
| C3 | Notifications (push/email/SMS, dedup, opt-out) | TODO |
| C4 | Remaining customer/guest endpoints: wallet, track_order, refunds | TODO |
| C5 | Dispatch commission + financial reconciliation | TODO |
| C6 | Creator/social deep pass | TODO |
| C10 | Order Anywhere | DONE with notes: see `ORDER_ANYWHERE_CERTIFICATION.md` |
| C7 | Vendor update endpoints: payload-complete tests | TODO |
| C8 | Stranded: wire `getActiveRequest()` into the customer-app start-up flow (backend + API call done) | TODO |
| C9 | Final certification report | TODO (last) |

## E. Order Anywhere follow-ups
| # | Item | Type |
|---|------|------|
| E1 | Real pickup coordinates for "anywhere" merchants (geocode at sourcing); today falls back to zone centre | TODO/OWNER |
| E2 | Stripe Issuing float: who funds the issuing balance while customer captures settle | OWNER |
| E3 | Customer cancel after driver accepted (money captured, goods possibly bought): refund policy | OWNER |
| E4 | Admin quote form needs an `item_cost` field; set `ORDER_ANYWHERE_MARKUP_PERCENT`; confirm admin sees `admin_alert` notices | TODO |
| E5 | Tune defaults: radius 25 mi, offer window 3 min, escalate 10 min, auto-cancel 30 min | OWNER |
| E6 | Guest `digital-human/speak` global spend cap | TODO |

## D. Closed so far (for traceability)
Order Anywhere: customer-chosen charge, vendor/admin_notes injection, broken driver flow, in-progress dispatches expiring, manual approval, silent no-driver, admin cancel not voiding, refund rollback hazard, markup computation.
Concierge video-avatar ownership + billed-room resume.
Admin 419 mechanism, captcha bypass + remember-me cookie removed, driver auth/token, customer order/cart/chat ownership,
vendor tenancy, logistics tenancy, route optimization quality, OTR/medical races + custody, payout race + vendor payout
NOT NULL, Stranded fee double-charge / escrow release / double close.
