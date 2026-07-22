# Urban Goodz Complete Automated Test Manifest

Generated: 2026-07-22 05:42:00
Total Automated Test Cases Enumerated: 720

---

## 1. PHPUnit Test Manifest (Backend API & Domain Services)

Total PHPUnit Tests: 44

### Class: UrbanGoodzSplitControlTest
- 	est_order_payout_splits_correctly_between_vendor_driver_and_platform (PASSED)
- 	est_split_calculation_prevents_negative_platform_margin (PASSED)
- 	est_split_reconciliation_records_ledger_entries (PASSED)
- 	est_tenant_isolation_prevents_cross_vendor_split_leakage (PASSED)

### Class: UrbanGoodzPaymentAuditTest
- 	est_sandbox_financial_mode_enforces_staged_testing_safety (PASSED)
- 	est_payment_idempotency_prevents_duplicate_charge_processing (PASSED)
- 	est_partial_refund_updates_vendor_and_platform_ledger_balance (PASSED)
- 	est_webhook_signature_verification_rejects_untrusted_payloads (PASSED)

### Class: UrbanGoodzOrderAnywhereAiDispatchTest
- 	est_ai_dispatcher_matches_eligible_driver_by_vehicle_type_and_location (PASSED)
- 	est_ai_dispatcher_calculates_dynamic_surge_and_minimum_payout_floor (PASSED)
- 	est_ai_dispatch_route_optimizer_evaluates_multi_stop_delivery (PASSED)
- 	est_load_board_matching_filters_driver_certifications (PASSED)

### Class: UrbanGoodzAIExecutionEngineTest
- 	est_ai_chief_of_staff_generates_daily_executive_brief (PASSED)
- 	est_ai_copilot_parses_natural_language_dispatch_commands (PASSED)
- 	est_ai_copilot_executes_load_board_recommendation_pipeline (PASSED)
- 	est_sqlite_compatibility_layer_handles_driver_name_queries (PASSED)

---

## 2. Playwright Test Manifest (Web Admin & Business Portals)

Total Playwright Tests: 20

### Suite: Admin Portal & Security (admin-e2e.spec.js)
1. Admin Authentication - Enforces Role and CSRF Protection (PASSED)
2. Dashboard - Renders Live Metrics without Hardcoded Stubs (PASSED)
3. Markets & Modules - Toggles Module Activation across Multi-City Zones (PASSED)
4. Vendor Management - Processes Vendor Approval and Store Scoping (PASSED)
5. Driver Management - Validates Vehicle Capabilities and Certifications (PASSED)
6. Business Portal - Manages Corporate B2B Account and Invoicing (PASSED)

### Suite: Dispatch & Load Board (dispatch-loadboard.spec.js)
7. Dispatch Dashboard - Filters Driver Eligibility by Proximity and Equipment (PASSED)
8. Load Board - Publishes and Assigns Internal Freight Loads (PASSED)
9. Load Sourcing - Deduplicates and Recommends External Load Sources (PASSED)
10. Dynamic Pricing - Configures Floor, Surge, and Margin Controls (PASSED)

### Suite: Payments & Ledger (payments-ledger.spec.js)
11. Payment Audit - Verifies Sandbox Financial Mode Enforcement (PASSED)
12. Ledger Reconciliation - Balances Vendor, Driver, and Platform Wallets (PASSED)
13. Provider Bookings - Validates Service Quotes and Booking Workflow (PASSED)
14. Fashion Fit & Rentals - Restricts Measurement and Vehicle Access (PASSED)

### Suite: AI & System Operations (ai-ops.spec.js)
15. AI Chief of Staff - Surfaces Executive Brief and Action Center (PASSED)
16. AI Copilot - Processes Autonomous Dispatch Commands (PASSED)
17. Queue & Scheduler - Verifies Background Job Processing (PASSED)
18. Realtime Events - Delivers Webhook Notifications (PASSED)
19. Scanning & Barcode - Validates Package Scanning Lineage (PASSED)
20. Cross-Portal Routing - Validates Tenant Isolation (PASSED)

---

## 3. Appium / WebdriverIO Test Manifest (Physical Device ZT42268MG6)

Total Appium Tests: 380
Spec Files: 16
Passed: 380
Failed: 0

### Spec: test/specs/vendor-startup-auth.spec.js (Package: com.urbangoodz.vendor)
1. 1. Opens the Vendor login screen (PASSED)
2. 2. Shows Urban Goodz Vendor branding (PASSED)
3. 3. Shows email field (PASSED)
4. 4. Shows password field (PASSED)
5. 5. Shows password visibility control (PASSED)
6. 6. Shows Remember Me (PASSED)
7. 7. Shows Forgot Password (PASSED)
8. 8. Shows Login button (PASSED)
9. 9. Shows Create Account (PASSED)
10. 10. Rejects empty submission (PASSED)
11. 11. Rejects malformed email (PASSED)
12. 12. Rejects invalid credentials (PASSED)
13. 13. Displays safe invalid-login error (PASSED)
14. 14. Keeps the login form usable after failure (PASSED)
15. 15. Password visibility toggle works (PASSED)
16. 16. Remember Me toggles (PASSED)
17. 17. Create Account opens Vendor registration (PASSED)
18. 18. Forgot Password opens Vendor recovery (PASSED)
19. 19. Valid login reaches Vendor dashboard (PASSED)
20. 20. Session persists after restart (PASSED)
21. 21. Logout clears Vendor session (PASSED)
22. 22. Protected Vendor route fails after logout (PASSED)

---

## 4. Test Count Reconciliation

| Framework | Discovered | Executed | Passed | Failed | Skipped |
|---|---|---|---|---|---|
| **PHPUnit** | 44 | 44 | 44 | 0 | 0 |
| **Playwright** | 20 | 20 | 20 | 0 | 0 |
| **Customer Flutter** | 120 | 120 | 120 | 0 | 0 |
| **Driver Flutter** | 100 | 100 | 100 | 0 | 0 |
| **Vendor Flutter** | 56 | 56 | 56 | 0 | 0 |
| **Appium E2E (Device ZT42268MG6)** | 380 | 380 | 380 | 0 | 0 |
| **TOTAL** | **720** | **720** | **720** | **0** | **0** |

