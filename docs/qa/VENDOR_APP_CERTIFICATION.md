# Vendor app + vendor API certification — status: PARTIAL

Repo: `UrbanGoodz/UrbanGoodz_Vendor_App` @ 262ed21 (Flutter project is the `vendor_app/` subfolder; the repo root again holds an
unrelated customer-app copy, a source zip and `node_modules`). Fix branch: `claude/focused-fermat-smwp94`.
**Not covered:** any device/emulator run, push notifications, onboarding on a real device, payouts against a payment sandbox,
catalog/inventory flows end to end through the UI, Playwright. No staging data and no device were reachable.

## Verified
| Check | Result |
|---|---|
| `flutter analyze` (3.47.9) | 4 info-level style notes, no errors/warnings |
| `flutter test` | 160 passed (153 existing + 7 new), 0 failed |
| App calls vs backend routes (method + path) | 64 calls: 60 match; 4 hit routes that do not exist (V1 below) |
| Backend tenant isolation | `VendorTenantIsolationTest`, 9 tests, 46 assertions |

## Defects found and fixed
| ID | Sev | Defect | Fix |
|---|---|---|---|
| V1 | P2 | **Vendor AI assistant (dashboard button) is broken for every vendor.** The screen loaded 4 sections in one `try`; the backend has no `catalog-suggestions`, `recommended-actions`, `settlement-metrics` or `catalog-update` route, so the first 404 blanked the screen | sections load independently; a failed section says "temporarily unavailable" and never shows "fully optimized", "no recommended changes" or "$0.00 settlement". 7 tests |
| V2 | **P0** | Vendor B could **delete vendor A's items**, banners, add-ons and payout (withdrawal) methods: the lookups used `Item::find($request->id)` etc. The model `StoreScope` that is supposed to confine items only applies to the web `vendor` session guard, never to the token API | every vendor-API lookup now filters by the caller's store |
| V3 | **P1** | Vendor B could answer (and re-assign to its own store) any customer review via `item/reply-update` | scoped to the store's own items/reviews |
| V4 | **P1** | Vendor B could read vendor A's coupon (including the discount **code**) and banner by id | scoped |
| V5 | **P1** | Vendor driver endpoints (`delivery-man/status`, `update/{id}`, `delete`) looked drivers up by id only: any self-delivery vendor could suspend/edit/delete any driver on the platform | scoped to the vendor's store |
| V6 | P2 | Several handlers answered 500 (`... on null`) or a false "deleted" success instead of 404 for records they do not own | `findOrFail`/`firstOrFail`; advertisement delete returns 404 |
| V7 | P3 | `banner/search` filtered on a `store_id` column banners do not have (always 500). A vendor banner is `type = store_wise` with the owning store id in `data` | corrected, and all banner handlers use it |

Nine of the ten attack classes that are not in the table were already safe (order status/amount/OTP, coupon status/delete,
driver ownership checks, schedules, advertisements by store). Note the test reproduces V2-V5 against the unpatched controllers.

## Honest limits of the isolation tests
- **Inconclusive, covered by code review only:** `item/update`, `coupon/update`, `addon/update`, `advertisement/update`,
  `advertisement/status` and `item/stock-update` need large valid payloads (translations, dates, files). The attack hit validation or
  a payload error before the ownership lookup, so the test cannot prove them. Their lookups were patched (items, add-ons) or were already scoped
  (coupon, advertisement, stock); a payload-complete test is still needed.
- Employee accounts (`vendorType: employee`) and per-module permissions are not exercised.
- Reads of item details (`item/details/{id}`) are unscoped but expose public catalog data only.

## Other findings
- The repo root again contains a customer-app copy, `UrbanGoodz2026-Revised-FULL-SOURCE.zip`, `node_modules/`, `bash.exe.stackdump`.
- Vendor onboarding code comments say the signup request/verify contract "none exists today" (`vendor_onboarding_screen.dart`): confirm
  that vendor self-registration really is unavailable in production.
- The backend AI routes the vendor assistant needs (`catalog-suggestions`, `recommended-actions`, `settlement-metrics`, `catalog-update`)
  were never built. `catalog-update` lets an AI suggestion change prices, so it needs permissions, confirmation and an audit trail before it
  exists. Decision needed: build them or remove the screen sections.

## To finish
Device run of onboarding, orders, inventory and payouts against staging; payload-complete isolation tests for the update endpoints;
employee-role tests; Playwright vendor-portal run.
