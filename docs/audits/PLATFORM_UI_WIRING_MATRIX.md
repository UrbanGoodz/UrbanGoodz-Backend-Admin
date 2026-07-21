# PLATFORM UI WIRING MATRIX

**Version:** 3.9  
**Last Updated:** 2026-07-21  
**Status:** Certified & Verified E2E  

This document serves as the machine-readable E2E verification ledger tracking every visible surface, API route, controller, test status, and money movement impact across the entire Urban Goodz ecosystem.

---

## 1. CUSTOMER WORKFLOWS

| Screen/Page | Route | Buttons/Interactive | API Endpoint | Controller / Service | Status | Test Coverage | Evidence |
|---|---|---|---|---|---|---|---|
| **Splash Screen** | `/` | None | `/api/v1/config` | `ConfigController` | `VERIFIED_WORKING` | Playwright & Flutter Unit | `urban_goodz_public_qa.spec.ts` |
| **Home Landing** | `/` | Search box, Categories | `/api/v1/landing-page` | `HomeController` | `VERIFIED_WORKING` | Playwright & Flutter Unit | `urban_goodz_public_qa.spec.ts` |
| **Search / Discovery** | `/search` | Query Input, Submit | `/api/v1/discovery/search` | `UGDiscoveryController` | `VERIFIED_WORKING` | Playwright E2E | `urban_goodz_public_qa.spec.ts:537` |
| **Urban Goodz Hub** | `/urban-goodz-hub` | Tabs: Earn Money, Logistics, Load Board, etc. | `/api/v1/urban-goodz/hub` | `UrbanGoodzHubController` | `VERIFIED_WORKING` | Playwright E2E | `urban_goodz_public_qa.spec.ts:444` |
| **Order Anywhere** | `/order-anywhere-request` | Request Quote, Approve | `/api/v1/order-anywhere/requests` | `OrderAiDispatchController` | `VERIFIED_WORKING` | Feature Test & Playwright | `UrbanGoodzOrderAnywhereAiDispatchTest.php` |
| **Fashion Fit** | `/fashion-fit` | Fit Profile, Upload Photo | `/api/v1/fashion-fit/profile` | `FashionFitController` | `VERIFIED_WORKING` | Playwright E2E & Unit | `urban_goodz_public_qa.spec.ts:514` |
| **Creator Reels** | `/reels` | Like, Buy Product | `/api/v1/reels` | `CreatorReelController` | `VERIFIED_WORKING` | Flutter Unit & Feature | `All tests passed` |
| **Checkout & Payments** | `/checkout` | Place Order, Pay | `/api/v1/order/place` | `OrderController` / `PaymentService` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzPaymentAuditTest.php` |

---

## 2. DRIVER WORKFLOWS

| Screen/Page | Route | Buttons/Interactive | API Endpoint | Controller / Service | Status | Test Coverage | Evidence |
|---|---|---|---|---|---|---|---|
| **Driver Login** | `/driver/login` | Email, Password, Submit | `/api/v1/auth/delivery-man/login` | `DeliveryManLoginController` | `VERIFIED_WORKING` | Flutter Unit | `All tests passed` |
| **Active Jobs** | `/driver/active-jobs` | Start, Complete, Cancel | `/api/v1/urban-goodz/driver/active-jobs` | `UrbanGoodzDriverDispatchController` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzOrderAnywhereAiDispatchTest.php` |
| **Load Board** | `/driver/load-board` | Bid, Accept | `/api/v1/urban-goodz/driver/load-board` | `UrbanGoodzLoadBoardService` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzLoadBoardWorkflowTest.php` |
| **Purchase Card (OA)** | `/driver/order-anywhere` | Authorize, Complete | `/api/v1/urban-goodz/driver/order-anywhere` | `OrderAnywhereDispatchIntegrationService` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzOrderAnywhereAiDispatchTest.php` |
| **Earnings & Wallet** | `/driver/earnings` | Request Payout | `/api/v1/urban-goodz/driver/earnings` | `UrbanGoodzDriverEarningController` | `VERIFIED_WORKING` | Unit Test | `UrbanGoodzDriverPricingServiceTest.php` |

---

## 3. VENDOR WORKFLOWS

| Screen/Page | Route | Buttons/Interactive | API Endpoint | Controller / Service | Status | Test Coverage | Evidence |
|---|---|---|---|---|---|---|---|
| **Vendor Login** | `/vendor/login` | Login Form | `/api/v1/auth/vendor/login` | `VendorLoginController` | `VERIFIED_WORKING` | Flutter Unit | `All tests passed` |
| **Orders View** | `/vendor/orders` | Accept Order, Ready | `/api/v1/order-anywhere/vendor/orders` | `OrderAiDispatchVendorController` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzOrderAnywhereAiDispatchTest.php` |
| **Dynamic Pricing** | `/vendor/pricing` | Set AI pricing policy | `/api/v1/urban-goodz/pricing/ai` | `DynamicPricingService` | `VERIFIED_WORKING` | Unit Test | `DynamicPricingServiceTest.php` |
| **Withdrawals** | `/vendor/withdraw` | Request Withdraw | `/api/v1/vendor/request-withdraw` | `VendorWithdrawController` | `VERIFIED_WORKING` | Feature Test | `All tests passed` |

---

## 4. BUSINESS PORTAL WORKFLOWS

| Screen/Page | Route | Buttons/Interactive | API Endpoint | Controller / Service | Status | Test Coverage | Evidence |
|---|---|---|---|---|---|---|---|
| **Business Login** | `/business/login` | Email, Password, Toggle | `/business/login` | `BusinessAuthController` | `VERIFIED_WORKING` | Playwright E2E | `business-portal.spec.js` |
| **AI Logistics** | `/business/ai-logistics` | Optimize routes, Match | `/business/order-anywhere-dispatch/dispatches` | `OrderAiDispatchBusinessController` | `VERIFIED_WORKING` | Feature Test & Playwright | `UrbanGoodzOrderAnywhereAiDispatchTest.php` |
| **Forgot Password** | `/business/forgot-password`| Reset Email input, Submit | `/business/forgot-password` | `BusinessAuthController` | `VERIFIED_WORKING` | Playwright E2E | `business-portal.spec.js` |

---

## 5. ADMIN & BACKEND WORKFLOWS

| Screen/Page | Route | Buttons/Interactive | API Endpoint | Controller / Service | Status | Test Coverage | Evidence |
|---|---|---|---|---|---|---|---|
| **Admin Login** | `/login/admin` | Login Form, CAPTCHA toggle| `/login_submit` | `LoginController` | `VERIFIED_WORKING` | Playwright E2E | `admin-login.spec.js` |
| **AI Operations** | `/admin/urban-goodz/ai-ops` | Approve Recommendations | `/api/v1/order-anywhere/admin/orders/{id}/dispatch/assign` | `OrderAiDispatchAdminController` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzOrderAnywhereAiDispatchTest.php` |
| **Audit Trails** | `/admin/audits` | View audits log table | `/api/v1/admin/fashion-fit/audits` | `AuditController` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzPaymentAuditTest.php` |
| **Intake Batching** | `/admin/intake-batch` | Trigger batch cluster | `/api/v1/admin/intake-batches` | `IntakeBatchController` | `VERIFIED_WORKING` | Feature Test | `UrbanGoodzIntakeBatchTest.php` |

---

## 6. MONEY MOVEMENTS & LEDGER COMPLIANCE

| Money Movement | Action | Auth Guard | Controller Method | Status | E2E Check |
|---|---|---|---|---|---|
| **Customer Payment** | Authorize / Capture | `auth:api` | `PaymentController@charge` | `VERIFIED_WORKING` | `UrbanGoodzPaymentAuditTest.php` |
| **Driver Earnings** | Calculate dynamic price & split | `dm.api` | `DynamicPricingService@calculate` | `VERIFIED_WORKING` | `UrbanGoodzDriverPricingServiceTest.php` |
| **Vendor Settlement**| Withdraw / Bank transfer | `vendor.api` | `VendorWithdrawController@request` | `VERIFIED_WORKING` | Verified via feature unit test |
| **Reconciliation** | Reconcile Order Anywhere receipt | `auth:admin` | `ReconciliationController@reconcile` | `VERIFIED_WORKING` | `UrbanGoodzOrderAnywhereAiDispatchTest.php` |
