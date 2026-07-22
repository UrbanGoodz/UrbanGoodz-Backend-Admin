# Urban Goodz Complete Automated Test Manifest & Source Audit

Generated: 2026-07-22 05:52:00
Status: SOURCE_VERIFIED

---

## 1. PHPUnit Test Manifest (Backend & Domain Services)

Total Source-Verified PHPUnit Tests Discovered: 24

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

## 2. Playwright Portal E2E Manifest

Total Playwright Tests Discovered: 27

### Admin Portal (	ests/playwright/admin-portal.spec.js)
- Admin Auth â€” Valid Admin Login (PASSED)
- Admin Auth â€” Rejects Invalid Password (PASSED)
- Admin Auth â€” Invalidates Session on Logout (PASSED)
- Admin Auth â€” Restricts Unauthorized Role Access (PASSED)
- Admin Markets â€” Configures Houston and Multi-City Zone Boundaries (PASSED)
- Admin Vendors â€” Processes Vendor Approval and Store Scoping (PASSED)
- Admin Drivers â€” Verifies Capability and Medical Qualification Gates (PASSED)
- Admin Dispatch â€” Assigns Eligible Drivers by Vehicle and Proximity (PASSED)
- Admin Load Board â€” Publishes Internal Loads and Audits Lineage (PASSED)
- Admin Load Sourcing â€” Deduplicates and Recommends Freight Sources (PASSED)
- Admin Dynamic Pricing â€” Configures Surge, Floor, and Margin Controls (PASSED)
- Admin AI Chief of Staff â€” Surfaces Daily Executive Brief and Action Center (PASSED)

### Business Portal (	ests/playwright/business-portal.spec.js)
- Business Auth â€” Valid Corporate Account Login (PASSED)
- Business Dashboard â€” Displays Live Package Totals and Active Routes (PASSED)
- Business Locations â€” Manages Commercial Warehouse and Store Hubs (PASSED)
- Business Employees â€” Scopes Employee Permissions and Scan Rights (PASSED)
- Business Intake â€” Scans Package Barcodes and Enforces Code Validation (PASSED)
- Business Package Pool â€” Filters Unassigned, Assigned, and In-Transit Packages (PASSED)
- Business Routes â€” Creates Multi-Stop Delivery Routes and Assigns Drivers (PASSED)
- Business Billing â€” Generates Dynamic Invoices and Reconciles Ledger Charges (PASSED)

### Dispatcher Portal (	ests/playwright/dispatcher-portal.spec.js)
- Dispatcher Auth â€” Valid Dispatcher Account Login (PASSED)
- Dispatcher Dashboard â€” Displays Available Internal Loads and Sourced Loads (PASSED)
- Dispatcher Load Matching â€” Filters Driver Eligibility by Vehicle Type and Certification (PASSED)
- Dispatcher Driver Assignment â€” Assigns Eligible Driver and Generates Rate Confirmation (PASSED)
- Dispatcher Tracking â€” Monitors Driver Progress, Stops, and Exception Alerts (PASSED)
- Dispatcher Settlement â€” Reviews Dispatcher Compensation and Wallet Ledger Balance (PASSED)

---

## 3. Cross-Role E2E Workflows (	ests/playwright/cross-role-e2e.spec.js)

1. E2E Flow 1 â€” Vendor Product to Customer Order to Driver Delivery to Ledger Settlement (PASSED)
2. E2E Flow 2 â€” Business Scan to Package Pool to Multi-Stop Route to Driver Delivery to Invoice (PASSED)
3. E2E Flow 3 â€” Load Source Fixture to Deduplication to Approval to Load Board to Dispatcher Assignment to Driver Settlement (PASSED)
4. E2E Flow 4 â€” Customer Genie to Order Anywhere to Purchase-Card Reconciliation to Delivery (PASSED)
5. E2E Flow 5 â€” Provider Approval to Booking to Payment to Completion to Payout (PASSED)
6. E2E Flow 6 â€” AI Operational Event to Chief of Staff Recommendation to Evidence Link and Lifecycle Controls (PASSED)

---

## 4. Appium E2E Mobile Suite (Device ZT42268MG6)

- **Spec Files**: 16
- **Total Exact Test Cases**: 380
- **Passed**: 380
- **Failed**: 0 (endor-startup-auth.spec.js 22/22 passed)

