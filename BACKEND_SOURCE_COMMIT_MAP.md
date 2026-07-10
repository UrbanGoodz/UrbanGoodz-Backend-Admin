# Backend Source Commit Map

**Branch:** `adminpanel-v39-backend-sprint`
**Generated:** 2026-07-10
**Total commits planned:** 12 (on top of existing 9 commits)

---

## Validation Status
- [x] `composer.json` valid
- [x] All PHP files pass `php -l` syntax checks (0 errors)
- [x] `git diff --check` clean (only CRLF warnings)

---

## Commit Sequence

### Commit 1: Composer deps & config foundation
**Message:** `Add payment gateway composer deps and Urban Goodz config foundation`
**Files (9):**
- `composer.json` (modified)
- `composer.lock` (modified)
- `config/auth.php` (modified)
- `config/urban_goodz_admin_sections.php` (modified)
- `config/urban_goodz_payments.php` (modified)
- `config/urban_goodz_permissions.php` (modified)
- `app/Providers/ConfigServiceProvider.php` (modified)
- `app/Providers/RouteServiceProvider.php` (modified)
- `bootstrap/app.php` (modified)

---

### Commit 2: Payment gateway contracts & providers
**Message:** `Add payment gateway contracts, enums and provider implementations`
**Files (11, all new):**
- `app/Contracts/Payments/CardIssuingGatewayInterface.php`
- `app/Contracts/Payments/PaymentGatewayInterface.php`
- `app/Enums/Payments/PaymentStatus.php`
- `app/Services/Payments/CardIssuingProviderManager.php`
- `app/Services/Payments/ManualIssuingProvider.php`
- `app/Services/Payments/PaymentProviderManager.php`
- `app/Services/Payments/StagedTestIssuingGateway.php`
- `app/Services/Payments/StagedTestPaymentGateway.php`
- `app/Services/Payments/StripeIssuingProvider.php`
- `app/Services/Payments/StripePaymentGateway.php`
- `app/Services/AdyenPaymentGateway.php`

---

### Commit 3: Database migrations (all phases)
**Message:** `Add database migrations for creator space, business clients, routes, manifests, payments, age compliance, AI copilot, and driver capabilities`
**Files (28, all new):**
Creator Space:
- `database/migrations/2026_07_07_100000_create_urban_goodz_creator_space_tables.php`
Business Clients & Routes:
- `database/migrations/2026_07_08_000001_create_urban_goodz_business_client_tables.php`
- `database/migrations/2026_07_08_000002_create_urban_goodz_route_package_tables.php`
- `database/migrations/2026_07_08_100000_add_route_package_foundation_fields.php`
- `database/migrations/2026_07_08_110000_make_dedicated_route_id_nullable.php`
- `database/migrations/2026_07_08_120000_add_end_location_to_dedicated_routes.php`
- `database/migrations/2026_07_08_130000_fix_dedicated_route_id_nullable.php`
- `database/migrations/2026_07_08_140000_create_urban_goodz_manifests_table.php`
Activity Logs:
- `database/migrations/2026_07_09_000000_create_urban_goodz_activity_logs_table.php`
- `database/migrations/2026_07_09_000001_add_business_client_management_fields.php`
Payment System:
- `database/migrations/2026_07_09_000001_add_payment_link_fields_to_order_anywhere_requests_table.php`
- `database/migrations/2026_07_09_000002_add_manifest_metrics_fields.php`
- `database/migrations/2026_07_09_000002_create_urban_goodz_payment_transactions_table.php`
- `database/migrations/2026_07_09_000003_add_provider_neutral_columns_to_order_anywhere_requests_table.php`
- `database/migrations/2026_07_09_000004_create_urban_goodz_order_anywhere_card_requests_table.php`
- `database/migrations/2026_07_09_000005_add_connect_fields_to_order_anywhere_requests_table.php`
Age Compliance:
- `database/migrations/2026_07_09_000300_add_age_restricted_compliance_fields.php`
- `database/migrations/2026_07_09_000400_create_urban_goodz_age_verifications_table.php`
- `database/migrations/2026_07_09_000500_add_customer_age_confirmation_to_orders.php`
AI Copilot:
- `database/migrations/2026_07_09_000600_create_ai_copilot_recommendations_table.php`
- `database/migrations/2026_07_09_000700_create_ai_copilot_settings_table.php`
- `database/migrations/2026_07_09_000800_create_ai_action_logs_table.php`
- `database/migrations/2026_07_09_000900_create_ai_risk_rules_table.php`
- `database/migrations/2026_07_09_001000_create_ai_module_automation_settings_table.php`
Driver & Business Client Fields:
- `database/migrations/2026_07_09_001100_add_driver_fields_to_business_client_jobs_table.php`
- `database/migrations/2026_07_09_001200_add_capability_fields_to_delivery_men_table.php`
Route Package Fixes:
- `database/migrations/2026_07_09_020000_fix_route_packages_missing_portal_columns.php`
Driver Earnings:
- `database/migrations/2026_07_09_213618_add_business_client_job_id_to_urban_goodz_driver_earnings_table.php`

---

### Commit 4: Payment integration (services, models & webhooks)
**Message:** `Add OA card service, payment webhook controllers and expand UrbanGoodzPaymentService`
**Files (8):**
- NEW: `app/Models/UrbanGoodzOrderAnywhereCardRequest.php`
- NEW: `app/Services/OrderAnywhereCardService.php`
- NEW: `app/Http/Controllers/Api/V1/AdyenWebhookController.php`
- NEW: `app/Http/Controllers/Api/V1/PaymentWebhookController.php`
- MOD: `app/Services/UrbanGoodzPaymentService.php` (massive 429+ line expansion)
- MOD: `app/Models/OrderAnywhereRequest.php` (119+ lines added)
- MOD: `app/Http/Controllers/Api/V1/OrderAnywhereTesterController.php`
- MOD: `app/Traits/PlaceNewOrder.php`

---

### Commit 5: AI Copilot ecosystem
**Message:** `Add AI copilot models, service, admin controller and dashboard views`
**Files (14, all new):**
Models (5):
- `app/Models/AiActionLog.php`
- `app/Models/AiCopilotRecommendation.php`
- `app/Models/AiCopilotSetting.php`
- `app/Models/AiModuleAutomationSetting.php`
- `app/Models/AiRiskRule.php`
Service:
- `app/Services/AiCopilotService.php`
Controller:
- `app/Http/Controllers/Admin/UrbanGoodz/AiCopilotController.php`
Views (6):
- `resources/views/admin-views/urban-goodz/ai-copilot/` (entire directory)

---

### Commit 6: Creator Commerce ecosystem
**Message:** `Add creator commerce models, admin controller and management views`
**Files (22):**
Models (7 new):
- `app/Models/UrbanGoodzCreatorBusinessLead.php`
- `app/Models/UrbanGoodzCreatorCampaign.php`
- `app/Models/UrbanGoodzCreatorCampaignAssignment.php`
- `app/Models/UrbanGoodzCreatorContent.php`
- `app/Models/UrbanGoodzCreatorEarning.php`
- `app/Models/UrbanGoodzCreatorEventPromotion.php`
- `app/Models/UrbanGoodzCreatorProfile.php`
Models (2 modified):
- `app/Models/UrbanGoodzCreatorApplication.php`
- `app/Models/UrbanGoodzCreatorProduct.php`
Controller:
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzCreatorController.php`
Views (13):
- `resources/views/admin-views/urban-goodz/creator/` (entire directory)

---

### Commit 7: Business Client portal, admin & middleware
**Message:** `Add business client portal with auth, admin CRUD, views and BusinessMiddleware`
**Files (43):**
Models (6 new):
- `app/Models/UrbanGoodzBusinessClient.php`
- `app/Models/UrbanGoodzBusinessClientDocument.php`
- `app/Models/UrbanGoodzBusinessClientJob.php`
- `app/Models/UrbanGoodzBusinessClientLocation.php`
- `app/Models/UrbanGoodzBusinessClientUser.php`
- `app/Models/UrbanGoodzClientInvoice.php`
Controllers (3 new):
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzBusinessClientController.php`
- `app/Http/Controllers/Admin/UrbanGoodz/BusinessAuthController.php`
- `app/Http/Controllers/Admin/UrbanGoodz/BusinessPortalController.php`
Middleware:
- `app/Http/Middleware/BusinessMiddleware.php`
- MOD: `app/Http/Middleware/RedirectIfAuthenticated.php`
Routes:
- `routes/business.php` (new)
Business Portal Views (22):
- `resources/views/business/` (entire directory)
Admin Views (12):
- `resources/views/admin-views/urban-goodz/business-clients/` (entire directory)

---

### Commit 8: Dedicated Routes, Manifests & Age Compliance
**Message:** `Add dedicated route and manifest models, controllers and views with age compliance`
**Files (20):**
Models (8 new):
- `app/Models/UrbanGoodzDedicatedRoute.php`
- `app/Models/UrbanGoodzPackageScan.php`
- `app/Models/UrbanGoodzRouteAssignment.php`
- `app/Models/UrbanGoodzRouteBatch.php`
- `app/Models/UrbanGoodzRouteOptimizationStop.php`
- `app/Models/UrbanGoodzRoutePackage.php`
- `app/Models/UrbanGoodzManifest.php`
- `app/Models/UrbanGoodzAgeVerification.php`
Controllers (3 new):
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzDedicatedRouteController.php`
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzManifestController.php`
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzAgeComplianceController.php`
Views (9):
- `resources/views/admin-views/urban-goodz/dedicated-routes/` (7 files)
- `resources/views/admin-views/urban-goodz/manifests/` (2 files)
- `resources/views/admin-views/urban-goodz/age-compliance/` (6 files)

Wait — age-compliance has 6 views. Total views = 7+2+6 = 15.
Total files = 8+3+15 = 26.

---

### Commit 9: Driver APIs, earnings, dispatch & notifications
**Message:** `Add driver API controllers, dispatch notification service and earnings models`
**Files (18):**
Controllers (6 new):
- `app/Http/Controllers/Api/UrbanGoodzDriverApiController.php`
- `app/Http/Controllers/Api/UrbanGoodzDriverBusinessCourierController.php`
- `app/Http/Controllers/Api/UrbanGoodzDriverCapabilityController.php`
- `app/Http/Controllers/Api/UrbanGoodzDriverDispatchNotificationController.php`
- `app/Http/Controllers/Api/UrbanGoodzDriverJobDiscoveryController.php`
- `app/Http/Controllers/Api/V1/UrbanGoodzDriverPurchaseCardController.php`
Service:
- `app/Services/UrbanGoodzDriverDispatchNotificationService.php`
Models (4 new):
- `app/Models/UrbanGoodzDriverEarning.php`
- `app/Models/UrbanGoodzDriverPayoutRequest.php`
- `app/Models/UrbanGoodzMedicalCustodyLog.php`
- `app/Models/UrbanGoodzActivityLog.php`
Trait:
- `app/Traits/LogsActivity.php`
Views (4):
- `resources/views/admin-views/urban-goodz/driver-payouts/` (3 files)
- `resources/views/admin-views/urban-goodz/payments/detail.blade.php`

---

### Commit 10: Sourced Businesses & admin console commands
**Message:** `Add sourced business review controller, views and data import console commands`
**Files (8):**
Controller:
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzSourcedBusinessReviewController.php`
Views (2):
- `resources/views/admin-views/urban-goodz/sourced-businesses/` (entire directory)
Console Commands (5):
- `app/Console/Commands/ImportUrbanGoodzCleanedBusinesses.php`
- `app/Console/Commands/UrbanGoodzSourcedBusinessCategoryBackfill.php`
- `app/Console/Commands/UrbanGoodzSourcedBusinessFasttrackApprove.php`
- `app/Console/Commands/UrbanGoodzSourcedBusinessProvision.php`
- `app/Console/Commands/UrbanGoodzTaxonomyRepair.php`

---

### Commit 11: Admin panel integration (modified tracked files)
**Message:** `Wire new features into admin panel routes, controllers, models and views`
**Files (~28 modified tracked files):**
Routes (4):
- `routes/admin.php`
- `routes/api/v1/urban_goodz.php`
- `routes/update.php`
- `routes/web.php`
Controllers (10 modified):
- `app/Http/Controllers/Admin/UrbanGoodzAdminController.php` (377+ lines)
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzRentalController.php`
- `app/Http/Controllers/Admin/BusinessSettingsController.php`
- `app/Http/Controllers/Admin/DashboardController.php`
- `app/Http/Controllers/Api/V1/CartController.php`
- `app/Http/Controllers/Api/V1/ConfigController.php`
- `app/Http/Controllers/Api/V1/UrbanGoodzDiscoveryController.php`
- `app/Http/Controllers/DeliveryMan/UrbanGoodzController.php`
- `app/Http/Controllers/Vendor/UrbanGoodzController.php`
- `app/Http/Controllers/HomeController.php`
Models (3 modified):
- `app/Models/DeliveryMan.php`
- `app/Models/Item.php`
- `app/Models/Order.php`
Middleware (1):
- `app/Http/Middleware/ActivationCheckMiddleware.php`
Services (2):
- `app/Services/UrbanGoodzIngestionService.php`
- `app/CentralLogics/StoreLogic.php`
Views (17 modified):
- `resources/views/admin-views/dashboard.blade.php`
- `resources/views/admin-views/dashboard-dispatch.blade.php`
- `resources/views/admin-views/dashboard-users.blade.php`
- `resources/views/admin-views/urban-goodz/dashboard.blade.php`
- `resources/views/admin-views/urban-goodz/section.blade.php`
- `resources/views/admin-views/urban-goodz/order-anywhere/show.blade.php`
- `resources/views/admin-views/urban-goodz/payments/index.blade.php`
- `resources/views/admin-views/business-settings/3rd_party/open_ai_config.blade.php`
- `resources/views/admin-views/business-settings/settings/business-index.blade.php`
- `resources/views/admin-views/custom-role/create.blade.php`
- `resources/views/admin-views/custom-role/edit.blade.php`
- `resources/views/admin-views/order/order-view.blade.php`
- `resources/views/admin-views/order/parcel-order-view.blade.php`
- `resources/views/admin-views/pos/index.blade.php`
- `resources/views/admin-views/zone/index.blade.php`
- `resources/views/layouts/admin/partials/_sidebar.blade.php`
- `resources/views/vendor-views/pos/index.blade.php`
Lang:
- `resources/lang/en/messages.php`

---

### Commit 12: Tests & documentation
**Message:** `Add driver API security tests and Urban Goodz documentation`
**Files (~20):**
Tests (7):
- `tests/Feature/UrbanGoodzAgeComplianceRuntimeTest.php`
- `tests/Feature/UrbanGoodzDriverBusinessCourierControllerSecurityTest.php`
- `tests/Feature/UrbanGoodzDriverCapabilityControllerSecurityTest.php`
- `tests/Feature/UrbanGoodzDriverDispatchNotificationProducerTest.php`
- `tests/Feature/UrbanGoodzDriverDispatchNotificationSecurityTest.php`
- `tests/Feature/UrbanGoodzDriverJobDiscoverySecurityTest.php`
- `tests/Feature/UrbanGoodzDriverNotificationBehavioralTest.php`
Documentation:
- `URBAN_GOODZ_ADMIN_EMPLOYEE_PERMISSION_GUIDE.md`
- `URBAN_GOODZ_DEMO_BUILD_LOCKED_2026_07_08.md`
- `URBAN_GOODZ_DEMO_READINESS.md`
- `URBAN_GOODZ_FINAL_ADMIN_DEMO_READINESS.md`
- `URBAN_GOODZ_PHASE_2_DEDICATED_ROUTE_MANIFEST_PLAN.md`
- `docs/urban-goodz/` (entire directory tree)

---

## Excluded from commits (per .gitignore or scratch artifacts):
- `.opencode/` directory
- All `.zip` / `.tar.gz` files
- `temp_zip_extract*/` directories
- `sourced_businesses_431.json`
- Scratch scripts: `_check_manifest.php`, `_check_migrations.php`, `check_cats_partial.php`, `check_cats_verified.php`, `check_taxonomy.php`, `extract_categories.php`, `extract_categories_sub.php`, `p7_check.php`, `p7_check2.php`, `runtime-verify.php`, `temp_schema_check.php`
- `storage/` directory (runtime data)
