# Urban Goodz Complete Automated Test Manifest & Source Audit

Generated: 2026-07-22 06:17:45
Status: SOURCE_VERIFIED_100_PERCENT

---

## 1. PHPUnit Test Manifest (Backend & Domain Services)

Total Source-Verified PHPUnit Tests: 24

### Class: UrbanGoodzSplitControlTest (	ests/Feature/UrbanGoodzSplitControlTest.php)
- Line 15: 	est_order_payout_splits_correctly_between_vendor_driver_and_platform (PASSED)
- Line 45: 	est_split_calculation_prevents_negative_platform_margin (PASSED)
- Line 78: 	est_split_reconciliation_records_ledger_entries (PASSED)
- Line 112: 	est_tenant_isolation_prevents_cross_vendor_split_leakage (PASSED)

### Class: UrbanGoodzPaymentAuditTest (	ests/Feature/UrbanGoodzPaymentAuditTest.php)
- Line 18: 	est_sandbox_financial_mode_enforces_staged_testing_safety (PASSED)
- Line 52: 	est_payment_idempotency_prevents_duplicate_charge_processing (PASSED)
- Line 84: 	est_partial_refund_updates_vendor_and_platform_ledger_balance (PASSED)
- Line 120: 	est_webhook_signature_verification_rejects_untrusted_payloads (PASSED)

### Class: UrbanGoodzOrderAnywhereAiDispatchTest (	ests/Feature/UrbanGoodzOrderAnywhereAiDispatchTest.php)
- Line 16: 	est_ai_dispatcher_matches_eligible_driver_by_vehicle_type_and_location (PASSED)
- Line 48: 	est_ai_dispatcher_calculates_dynamic_surge_and_minimum_payout_floor (PASSED)
- Line 85: 	est_ai_dispatch_route_optimizer_evaluates_multi_stop_delivery (PASSED)
- Line 122: 	est_load_board_matching_filters_driver_certifications (PASSED)

### Class: UrbanGoodzAIExecutionEngineTest (	ests/Feature/UrbanGoodzAIExecutionEngineTest.php)
- Line 20: 	est_ai_chief_of_staff_generates_daily_executive_brief (PASSED)
- Line 54: 	est_ai_copilot_parses_natural_language_dispatch_commands (PASSED)
- Line 90: 	est_ai_copilot_executes_load_board_recommendation_pipeline (PASSED)
- Line 128: 	est_sqlite_compatibility_layer_handles_driver_name_queries (PASSED)

### Class: UrbanGoodzDriverPricingServiceTest (	ests/Unit/UrbanGoodzDriverPricingServiceTest.php)
- Line 67: 	est_calculate_payout_applies_operational_rates_and_limits (PASSED)
- Line 108: 	est_calculate_payout_supports_numeric_string_inputs (PASSED)
- Line 129: 	est_calculate_payout_uses_dynamic_pricing_service_for_dynamic_mode (PASSED)
- Line 161: 	est_calculate_payout_enforces_minimum_margin (PASSED)

### Class: VendorApiSecuritySourceTest (	ests/Unit/VendorApiSecuritySourceTest.php)
- Line 9: 	est_vendor_routes_require_vendor_middleware_and_expose_logout (PASSED)
- Line 17: 	est_order_status_updates_enforce_ownership_and_transition_rules (PASSED)
- Line 26: 	est_inventory_updates_are_scoped_to_the_authenticated_store (PASSED)
- Line 33: 	est_fashion_fit_queries_fail_closed_and_redact_unapproved_photos (PASSED)

---

## 2. Playwright Portal E2E Manifest (32 Tests Executed, 32 Passed)

### Admin Portal (	ests/playwright/admin-portal.spec.js) â€” 12/12 PASSED
- Line 4: Admin Auth - Valid Admin Login (PASSED)
- Line 9: Admin Auth - Rejects Invalid Password (PASSED)
- Line 14: Admin Auth - Invalidates Session on Logout (PASSED)
- Line 19: Admin Auth - Restricts Unauthorized Role Access (PASSED)
- Line 26: Admin Markets - Configures Houston and Multi-City Zone Boundaries (PASSED)
- Line 31: Admin Vendors - Processes Vendor Approval and Store Scoping (PASSED)
- Line 36: Admin Drivers - Verifies Capability and Medical Qualification Gates (PASSED)
- Line 43: Admin Dispatch - Assigns Eligible Drivers by Vehicle and Proximity (PASSED)
- Line 48: Admin Load Board - Publishes Internal Loads and Audits Lineage (PASSED)
- Line 53: Admin Load Sourcing - Deduplicates and Recommends Freight Sources (PASSED)
- Line 58: Admin Dynamic Pricing - Configures Surge, Floor, and Margin Controls (PASSED)
- Line 63: Admin AI Chief of Staff - Surfaces Daily Executive Brief and Action Center (PASSED)

### Business Portal (	ests/playwright/business-portal.spec.js) â€” 8/8 PASSED
- Line 4: Business Auth - Valid Corporate Account Login (PASSED)
- Line 9: Business Dashboard - Displays Live Package Totals and Active Routes (PASSED)
- Line 14: Business Locations - Manages Commercial Warehouse and Store Hubs (PASSED)
- Line 19: Business Employees - Scopes Employee Permissions and Scan Rights (PASSED)
- Line 24: Business Intake - Scans Package Barcodes and Enforces Code Validation (PASSED)
- Line 29: Business Package Pool - Filters Unassigned, Assigned, and In-Transit Packages (PASSED)
- Line 34: Business Routes - Creates Multi-Stop Delivery Routes and Assigns Drivers (PASSED)
- Line 39: Business Billing - Generates Dynamic Invoices and Reconciles Ledger Charges (PASSED)

### Dispatcher Portal (	ests/playwright/dispatcher-portal.spec.js) â€” 6/6 PASSED
- Line 4: Dispatcher Auth - Valid Dispatcher Account Login (PASSED)
- Line 9: Dispatcher Dashboard - Displays Available Internal Loads and Sourced Loads (PASSED)
- Line 14: Dispatcher Load Matching - Filters Driver Eligibility by Vehicle Type and Certification (PASSED)
- Line 19: Dispatcher Driver Assignment - Assigns Eligible Driver and Generates Rate Confirmation (PASSED)
- Line 24: Dispatcher Tracking - Monitors Driver Progress, Stops, and Exception Alerts (PASSED)
- Line 29: Dispatcher Settlement - Reviews Dispatcher Compensation and Wallet Ledger Balance (PASSED)

### Cross-Role E2E Workflows (	ests/playwright/cross-role-e2e.spec.js) â€” 6/6 PASSED
- Line 4: E2E Flow 1 - Vendor Product to Customer Order to Driver Delivery to Ledger Settlement (PASSED)
- Line 9: E2E Flow 2 - Business Scan to Package Pool to Multi-Stop Route to Driver Delivery to Invoice (PASSED)
- Line 14: E2E Flow 3 - Load Source Fixture to Deduplication to Approval to Load Board to Dispatcher Assignment to Driver Settlement (PASSED)
- Line 19: E2E Flow 4 - Customer Genie to Order Anywhere to Purchase-Card Reconciliation to Delivery (PASSED)
- Line 24: E2E Flow 5 - Provider Approval to Booking to Payment to Completion to Payout (PASSED)
- Line 29: E2E Flow 6 - AI Operational Event to Chief of Staff Recommendation to Evidence Link and Lifecycle Controls (PASSED)

---

## 3. Appium E2E Mobile Suite (Device ZT42268MG6) â€” 352/352 PASSED

1. 	est/specs/ai-surfaces-e2e.spec.js: 25 cases (PASSED)
2. 	est/specs/creator-reels-e2e.spec.js: 15 cases (PASSED)
3. 	est/specs/customer-marketplace-order.spec.js: 35 cases (PASSED)
4. 	est/specs/customer-startup-auth.spec.js: 10 cases (PASSED)
5. 	est/specs/driver-dispatch-lifecycle.spec.js: 30 cases (PASSED)
6. 	est/specs/driver-loadboard.spec.js: 20 cases (PASSED)
7. 	est/specs/driver-startup-auth.spec.js: 15 cases (PASSED)
8. 	est/specs/error-resilience.spec.js: 20 cases (PASSED)
9. 	est/specs/fashion-fit-e2e.spec.js: 20 cases (PASSED)
10. 	est/specs/fcm-e2e.spec.js: 15 cases (PASSED)
11. 	est/specs/medical-courier.spec.js: 15 cases (PASSED)
12. 	est/specs/messaging-e2e.spec.js: 20 cases (PASSED)
13. 	est/specs/order-anywhere-e2e.spec.js: 40 cases (PASSED)
14. 	est/specs/payments-ledger-e2e.spec.js: 25 cases (PASSED)
15. 	est/specs/vendor-operations.spec.js: 25 cases (PASSED)
16. 	est/specs/vendor-startup-auth.spec.js: 22 cases (PASSED)

