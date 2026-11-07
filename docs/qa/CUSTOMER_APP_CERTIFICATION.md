# Customer app + customer API certification — status: PARTIAL

Repos: `UrbanGoodz/UrbanGoodz2026-Revised` (Flutter, `main` @ 64f6e87; fixes on branch `claude/focused-fermat-smwp94`)
and this backend. **Not covered:** real device/emulator runs, the Playwright/Appium suites (they target the live
`test.urbangoodzdelivery.com` host and need devices), payment-sandbox flows, push notifications, live tracking, and the
full customer journey end to end. No browser, emulator or staging data was available.

## Verified
| Check | Result |
|---|---|
| `flutter analyze` (Flutter 3.47.9) | 183 findings: 1 error (fixed), 130 `avoid_print`, rest info-level style. 6 real logic bugs found (see C1) |
| `flutter test` | 388 passed, 0 failed |
| `flutter build web --release` | builds successfully |
| App endpoints vs backend routes | 215 endpoint constants, 35 with no backend route (below) |

## Defects found and fixed
| ID | Sev | Defect | Fix | Regression |
|---|---|---|---|---|
| C1 | P2 | Web landing page store-download buttons never rendered: `int? == '1'` is always false (6 places) | compare with `1` | analyzer `unrelated_type_equality_checks` now clean |
| C2 | P3 | `integration_test/recon_test.dart` did not compile (`RouteHelper.getCurrentRoute`) | `Get.currentRoute` | `flutter analyze` clean |
| C3 | **P0** | `POST customer/order/wallet-payment`: any caller, even unauthenticated, could name any `order_id`; server debited that order's owner's wallet and marked it paid; unknown id gave 500 | requires signed-in owner; unknown/canceled orders return 404/400 | `CustomerOrderOwnershipTest` |
| C4 | **P0** | `GET customer/order/details`: for guests no ownership filter, so any order (items, parcel addresses) was readable by incrementing `order_id` | guests must send `guest_id` and see only their own orders | `CustomerOrderOwnershipTest` |
| C5 | **P0/P1** | Guest ids and customer ids share one numeric space (`orders.user_id` bigint). A guest with `guest_id` equal to a customer's id could list/read/cancel/change payment of that customer's orders (list, running, details, cancel, refund, payment-method), and offline-payment, offline-payment-update, parcel-return and payment-failed took any `order_id` with no scoping at all | guest calls limited to `is_guest = 1`; the unscoped endpoints now use an `ownedOrders()` helper | `CustomerOrderOwnershipTest` (9 of 14 tests fail on the old code) |

| C6 | **P0** | `cart/update` loaded any cart line by id, changed its price/quantity, and then **reassigned it to the caller** (`user_id = caller`): one customer could take over another's cart line. `cart/remove-item` deleted any line by id | both scoped to the caller's own lines (404 otherwise) | `CustomerCartAndChatOwnershipTest` |
| C7 | **P1** | The guest-cart merge on login/registration (4 places, plus rental carts) selected carts by `user_id = <client-supplied guest_id>` without `is_guest = 1`. Logging in with a victim's customer id as `guest_id` moved the victim's cart into the attacker's account | merges restricted to real guest carts | same |
| C8 | **P0** | **Private chats were readable and writable by any customer.** `message/details` returned any conversation by id; `message/send` accepted any `conversation_id` and treated a non-participant as the other party, so anyone could read vendor/driver conversations or inject messages ("send payment to me") into them. The vendor and driver messaging controllers had the same lookups | `Conversation::involving()` participant scope on all six lookups (customer, driver and vendor, read and send) | same (6 of 9 tests fail on the old code) |

Order totals are priced from the real product, not from the cart's client-supplied `price`, so a spoofed cart price cannot change the amount charged. Address update/delete were already scoped.

C3-C5 and C6-C8 are authorization flaws on live customer endpoints. If this build is already deployed, treat them as an
incident: review wallet transactions and order status changes made by unauthenticated/guest callers.

## Disconnected functionality (app calls an endpoint the backend does not have)
- 29 of the 35 are dead constants nothing calls.
- Live but dormant: the **ride-share** screens (`trip/list`, `ride/payment`, `review/store`, `coupon/list/taxi`). They appear only if an
  admin creates a module of type `ride`; the backend has no ride-share API, so enabling it would break. Decision needed:
  remove the screens or build the API.

## Not yet reviewed (same class as C3-C5, needs the same pass)
`track_order` (guest lookup by phone number is public by design), cart endpoints, `refund_request` for guests, vendor/driver
order endpoints, and every other `APIGuestMiddleware` route that trusts a caller-supplied `guest_id`.

## To finish customer certification
1. Staging backend + seeded stores/items/zone, then run the Playwright suites in `tests/` against it.
2. Android/iOS emulator or device runs (Appium in `tests/appium`), including permissions, push, deep links.
3. Scenario A end to end (register -> order -> pay (sandbox) -> vendor -> driver -> delivery -> reconcile).
4. Money checks: totals, tax, fees, wallet, refunds against backend calculations.
