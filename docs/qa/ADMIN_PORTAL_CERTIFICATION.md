# Admin portal certification (HTTP level) — status: PARTIAL

Scope actually covered: server-side rendering and routing of the admin portal, driven from PHPUnit as the
primary administrator. **Not covered:** real browser behaviour (JS, forms, modals, CSRF in the browser),
mobile layouts, permissions for non-primary roles, and every state-changing admin workflow. No staging
database or browser was available, so this is NOT a certification of the admin portal.

## Defects found and fixed (with regression coverage)
| # | Sev | Defect | Fix | Regression |
|---|---|---|---|---|
| A1 | P1 | `admin/urban-goodz/sourced-businesses` returned 500 for every request: Blade syntax error (missing `)` on line 37) | closed the parenthesis | `BladeViewsCompileTest` compiles+lints every Blade view |
| A2 | P2 | Resolve exception / accept / dismiss / snooze AI recommendation (Business AI Logistics) saved the change then crashed on a redirect to non-existent routes | redirect to `business.ai-logistics.exceptions` / `.copilot-recommendations` | `NamedRouteReferencesResolveTest` |
| A3 | P2 | `admin/transactions/report/earning` redirected to undefined `admin.report.admin-earning-report` | `admin.transactions.report.admin-earning-report` | `NamedRouteReferencesResolveTest` |
| A4 | P2 | AI settings save redirected to undefined `admin.ai.settings` | `admin.urban-goodz.ai.settings` | `NamedRouteReferencesResolveTest` |
| A5 | P2 | Store withdraw balance-mismatch path redirected to undefined `admin.restaurant.withdraw_list` | `admin.transactions.store.withdraw_list` | `NamedRouteReferencesResolveTest` |

## Page sweep (`AdminPortalPageSweepTest`, opt-in `UG_ADMIN_SWEEP=1`)
449 parameter-free admin GET pages requested as primary admin, empty test database, one ecommerce module selected:
349 x 200, 22 x 302, 27 x 404, 3 x 403, 1 x 405, 47 x 500 (before A3 fix).
The 47 are **not** all code defects. Classification of what is established vs. not:

- **Confirmed code defects, no UI link found (legacy, views never existed in git history):**
  `business-settings/currency-add`, `reviews/list`, `store/report`, `transactions/report/order`,
  `transactions/report/order-transactions`. Decision needed: build the page or delete the route.
- **Test-database artifacts (cannot be judged here):** missing columns (`order_transactions.tax`,
  `order_transactions.original_delivery_charge`, `categories.name`, `module_id`...) although migrations define some of
  them (a from-scratch migrate does not complete in this repo), and empty `business_settings` rows
  (`Attempt to read property "value" on null`). These need re-running against a staging database with the real schema.
- **Robustness (P3):** AJAX helper endpoints requested without their required query string answer 500 instead of 422/404
  (`item/get-variations`, `item/get-stock`, `item/remove-image`, `category/get-all`, `zone/get-zone`,
  `order/quick-view-cart-item`, `brand/get-brand-data`, `advertisement/*`, withdraw detail endpoints, `product/variation-setup-auto-fill`).
- **Unexplained, need a look:** `transactions/rental/report/admin-zone-wise-earnings` (MariaDB SQL syntax error —
  may be a MySQL-only construct), `parcel/settings` (`htmlspecialchars(): array given`).

## Next steps to actually certify the admin portal
1. Run the sweep against staging (`UG_ADMIN_SWEEP=1`), fill `tests/Fixtures/known-admin-page-faults.txt` only with
   faults that are accepted and tracked, and fix the rest.
2. Browser pass (Playwright in `e2e/`) over login, navigation, forms and AJAX, including the 419 scenarios.
3. Non-primary admin roles / employee permissions, and tenant isolation of vendor vs admin data.
4. State-changing workflows (approvals, refunds, assignments, settings) with business-result assertions.
