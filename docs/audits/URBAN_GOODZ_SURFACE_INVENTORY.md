# URBAN GOODZ SURFACE INVENTORY

**Version:** 3.9  
**Last Updated:** 2026-07-16  
**Purpose:** Complete audit of every API surface, screen, and integration point across all Urban Goodz apps.

---

## 1. BACKEND ROUTE INVENTORY

### 1.1 Route File Summary

| # | Route File | File Path | Approx. Route Count | Primary Domain |
|---|---|---|---|---|
| 1 | Core API | `routes/api/v1/api.php` | ~180 | Auth, Customer, Vendor, Items, Stores, Orders, Cart, Messaging |
| 2 | Urban Goodz | `routes/api/v1/urban_goodz.php` | ~130 | Discovery, Opportunities, Load Board, Medical Courier, Events, AI, Fashion, Order Anywhere, Driver, Dispatcher, Cross-App AI |
| 3 | Fashion Fit | `routes/api/v1/fashion_fit.php` | ~30 | Fashion Fit profiles, requests, measurements, AI, Vendor/Admin workflows |
| 4 | Service Bookings | `routes/api/v1/service_bookings.php` | ~25 | Service booking CRUD, Vendor services, Admin management |
| 5 | Fashion Measurements | `routes/api/urban_goodz_measurements.php` | ~12 | Fashion measurement profiles, requests, vendor review |
| 6 | V2 API | `routes/api/v2/api.php` | 1 | Library update |
| 7 | Web Routes | `routes/web.php` | — | Admin Panel web |
| 8 | Admin Routes | `routes/admin/routes.php` | — | Admin Panel admin section |
| 9 | Vendor Routes | `routes/vendor.php` | — | Vendor panel web |

### 1.2 Route Count by Auth Guard

| Auth Guard | Approx. Route Count | Example Routes |
|---|---|---|
| No auth (public) | ~45 | Config, zone list, terms, parcel categories, item browsing |
| `auth:api` (customer) | ~90 | Profile, orders, address, wallet, loyalty, reels, discovery |
| `vendor.api` + `actch:vendor_app` | ~85 | Vendor profile, orders, items, coupons, ads, earnings, payouts |
| `dm.api` (driver) | ~70 | Driver profile, active jobs, capability, load board, earnings |
| `auth:admin` | ~40 | Admin fashion fit, service bookings, Order Anywhere admin, Dispatcher AI |
| `apiGuestCheck` (order placement) | ~20 | Order place, cancel, payment methods, cart |
| Cross-auth (AI routes) | ~50 | Cross-app AI for all roles, specialized AI |

---

## 2. CUSTOMER APP SURFACE INVENTORY

### 2.1 Screen Inventory

| # | Screen | API Calls Used | Status |
|---|---|---|---|
| 1 | Splash/Loading | `configUri` | ✅ |
| 2 | Onboarding | — (local) | ✅ |
| 3 | Login | `loginUri` | ✅ |
| 4 | Register | `registerUri` | ✅ |
| 5 | Forgot Password | `forgetPasswordUri`, `verifyTokenUri`, `resetPasswordUri` | ✅ |
| 6 | Phone Verification | `verifyPhoneUri` | ✅ |
| 7 | Social Login | `socialLoginUri` | ✅ |
| 8 | Guest Mode | `guestLoginUri` | ✅ |
| 9 | Home (Landing) | `flutterLandingPageUri`, `bannerUri`, `storeItemUri`, `categoryUri` | ✅ |
| 10 | Store List | `storeUri`, `popularStoreUri`, `latestStoreUri` | ✅ |
| 11 | Store Details | `storeDetailsUri`, `storeReviewUri` | ✅ |
| 12 | Item Details | `itemDetailsUri`, `reviewUri` | ✅ |
| 13 | Search | `searchUri`, `searchSuggestionsUri` | ✅ |
| 14 | Category List | `categoryUri` | ✅ |
| 15 | Category Items | `categoryItemUri` | ✅ |
| 16 | Cart | `getCartListUri`, `addCartUri`, `updateCartUri`, `removeAllCartUri` | ✅ |
| 17 | Checkout | `placeOrderUri`, `couponApplyUri`, `getOrderTaxUri`, `getSurgePriceUri` | ✅ |
| 18 | Order Confirmation | — (post-place) | ✅ |
| 19 | Order Tracking | `trackUri`, `lastLocationUri` | ✅ |
| 20 | Order List | `historyOrderListUri`, `runningOrderListUri`, `dashboardOrderUri` | ✅ |
| 21 | Order Details | `orderDetailsUri` | ✅ |
| 22 | Cancel Order | `orderCancelUri`, `orderCancellationUri` | ✅ |
| 23 | Refund Request | `refundRequestUri`, `refundReasonUri` | ✅ |
| 24 | Profile | `customerInfoUri`, `updateProfileUri` | ✅ |
| 25 | Address Management | `addressListUri`, `addAddressUri`, `updateAddressUri`, `removeAddressUri` | ✅ |
| 26 | Wallet | `walletTransactionUri`, `addFundUri`, `walletBonusUri` | ✅ |
| 27 | Loyalty Points | `loyaltyTransactionUri`, `loyaltyPointTransferUri` | ✅ |
| 28 | Wishlist | `wishListGetUri`, `addWishListUri`, `removeWishListUri` | ✅ |
| 29 | Notifications | `notificationUri` | ✅ |
| 30 | Messaging | `conversationListUri`, `messageListUri`, `sendMessageUri` | ✅ |
| 31 | Saved Files | `savedFilesUri`, `storeSavedFilesUri`, `deleteSavedFilesUri` | ✅ |
| 32 | Coupon List | `couponUri` | ✅ |
| 33 | Flash Sales | `flashSaleUri`, `flashSaleProductsUri` | ✅ |
| 34 | Campaigns | `basicCampaignUri`, `itemCampaignUri` | ✅ |
| 35 | Cashback | `cashBackOfferListUri`, `getCashBackAmountUri` | ✅ |
| 36 | Parcel Module | `parcelCategoryUri`, `parcelInstructionUri` | ✅ |
| 37 | Taxi/Rental | Rental routes (multiple) | ✅ |
| 38 | Ride Share | Ride share routes (multiple) | ✅ |
| 39 | Service Module | Service routes (multiple) | ✅ |
| 40 | Reels | `reelListUri`, `reelDetailsUri`, `reelStatsUri`, `reelLikeUri` | ✅ |
| 41 | Order Anywhere | `orderAnywhereRequestUri`, `orderAnywhereListUri` | ✅ |
| 42 | Creator Commerce | `creatorCommerceApplicationsUri`, `creatorCommercePromotionsUri` | ✅ |
| 43 | Fashion Fit (UG) | `ugFitProfileUri`, `ugIdentityProfilesUri` | ✅ |
| 44 | AI Concierge | `ugAiConciergeUri` | ✅ |
| 45 | Discovery | `ugDiscoverySearchCaptureUri`, `ugDiscoveryEntitiesUri` | ✅ |
| 46 | Earn Money | `ugEarnMoneyOpportunitiesUri` | ✅ |
| 47 | Load Board (Browse) | `ugLoadBoardLoadsUri` | ✅ |
| 48 | Logistics Jobs | `ugLogisticsJobsUri` | ✅ |
| 49 | Medical Courier | `ugMedicalCourierJobsUri` | ✅ |
| 50 | Book Anything | `ugBookAnythingRecordsUri`, `ugBookAnythingRequestUri` | ✅ |
| 51 | Events | `ugEventsUri` | ✅ |
| 52 | Settings | `aboutUsUri`, `privacyPolicyUri`, `termsAndConditionUri` | ✅ |
| 53 | Remove Account | `customerRemoveUri` | ✅ |

### 2.2 Customer App API Constants File

**File:** `lib/util/app_constants.dart`  
**Total URI Constants:** ~220  
**Total Status Constants:** ~15 (order statuses)  
**Module Constants:** 8 (pharmacy, food, parcel, ecommerce, grocery, taxi, ride, service)

---

## 3. VENDOR APP SURFACE INVENTORY

### 3.1 Screen Inventory

| # | Screen | API Calls Used | Status |
|---|---|---|---|
| 1 | Splash | Config | ✅ |
| 2 | Login | `auth/vendor/login` | ✅ |
| 3 | Forgot Password | `auth/vendor/forgot-password` | ✅ |
| 4 | Dashboard | `vendor/profile`, `vendor/current-orders`, `vendor/earning-info` | ✅ |
| 5 | Profile | `vendor/profile`, `vendor/update-profile` | ✅ |
| 6 | Business Setup | `vendor/update-basic-info`, `vendor/update-business-setup` | ✅ |
| 7 | Schedule | `vendor/schedule/store`, `vendor/schedule/{id}` | ✅ |
| 8 | Orders (Current) | `vendor/current-orders` | ✅ |
| 9 | Orders (Completed) | `vendor/completed-orders` | ✅ |
| 10 | Orders (Canceled) | `vendor/canceled-orders` | ✅ |
| 11 | Orders (All) | `vendor/all-orders` | ✅ |
| 12 | Order Detail | `vendor/order-details`, `vendor/order` | ✅ |
| 13 | Update Order Status | `vendor/update-order-status` | ✅ |
| 14 | Edit Order Amount | `vendor/update-order-amount` | ✅ |
| 15 | Send OTP | `vendor/send-order-otp` | ✅ |
| 16 | Items List | `seller/item/*` (CRUD) | ✅ |
| 17 | Item Create/Edit | `seller/item/store`, `seller/item/update` | ✅ |
| 18 | Stock Management | `seller/item/stock-update`, `seller/item/stock-limit-list` | ✅ |
| 19 | Coupons | `seller/coupon/*` (CRUD) | ✅ |
| 20 | Advertisements | `seller/advertisement/*` (CRUD) | ✅ |
| 21 | Addons | `seller/addon/*` (CRUD) | ✅ |
| 22 | Banners | `seller/banner/*` (CRUD) | ✅ |
| 23 | Categories | `seller/categories/*` | ✅ |
| 24 | Delivery Men | `seller/delivery-man/*` (CRUD) | ✅ |
| 25 | Earnings | `vendor/earning-info`, `vendor/earning-report` | ✅ |
| 26 | Withdrawals | `vendor/get-withdraw-list`, `vendor/request-withdraw` | ✅ |
| 27 | Withdraw Methods | `vendor/withdraw-method/*` | ✅ |
| 28 | Reports | `vendor/get-expense`, `vendor/get-tax-report`, `vendor/get-disbursement-report` | ✅ |
| 29 | Subscriptions | `vendor/business_plan`, `vendor/subscription/payment/api` | ✅ |
| 30 | Messaging | `seller/message/*` | ✅ |
| 31 | Notifications | `vendor/notifications/*` | ✅ |
| 32 | POS | `seller/pos/*` | ✅ |
| 33 | Campaigns | `vendor/get-basic-campaigns`, `vendor/campaign-join`, `vendor/campaign-leave` | ✅ |
| 34 | Bank Info | `vendor/update-bank-info` | ✅ |
| 35 | Fashion Fit | `vendor/fashion-fit/*` | ✅ |
| 36 | Service Bookings | `vendor/service-bookings/*` | ✅ |
| 37 | Dynamic Pricing AI | `urban-goodz/pricing/ai/*` | ✅ |
| 38 | AI Concierge | `urban-goodz/ai-concierge/query` | ✅ |
| 39 | Cross-App AI | `urban-goodz/cross-app/ai/vendor/*` | ✅ |
| 40 | Logout | `vendor/logout` | ✅ |
| 41 | Remove Account | `vendor/remove-account` | ✅ |

### 3.2 Vendor App API Client

**File:** `lib/services/vendor_api_client.dart`  
**Base URL:** `https://admin.urbangoodzdelivery.com/api/v1`  
**HTTP Methods:** GET, POST, PUT, DELETE, Multipart  
**Auth Headers:** `Authorization: Bearer {token}`, `vendorType: owner`, `languageCode: en`, `Accept: application/json`  
**Error Handling:** `VendorApiException` with status code + message  
**Unauthorized Handling:** `onUnauthorized` callback for token refresh

---

## 4. DRIVER APP SURFACE INVENTORY

### 4.1 Screen Inventory

| # | Screen | API Calls Used | Status |
|---|---|---|---|
| 1 | Splash | Config | ✅ |
| 2 | Login | `auth/delivery-man/login` | ✅ |
| 3 | Forgot Password | `auth/delivery-man/forgot-password` | ✅ |
| 4 | Home/Dashboard | `active-jobs`, `capability-summary`, `dispatch-notifications/unread-count` | ✅ |
| 5 | Profile | `delivery-man/profile`, `delivery-man/update-profile` | ✅ |
| 6 | Active Jobs List | `urban-goodz/driver/active-jobs` | ✅ |
| 7 | Active Job Detail | `urban-goodz/driver/active-jobs/{id}` | ✅ |
| 8 | Start Job | `urban-goodz/driver/active-jobs/{id}/start` | ✅ |
| 9 | Complete Job | `urban-goodz/driver/active-jobs/{id}/complete` | ✅ |
| 10 | Cancel Job | `urban-goodz/driver/active-jobs/{id}/cancel` | ✅ |
| 11 | Update Job Status | `urban-goodz/driver/active-jobs/{id}/status` | ✅ |
| 12 | Business Courier Jobs | `urban-goodz/driver/business-jobs` | ✅ |
| 13 | Business Job Detail | `urban-goodz/driver/business-jobs/{id}` | ✅ |
| 14 | Accept Business Job | `urban-goodz/driver/business-jobs/{id}/accept` | ✅ |
| 15 | Start Business Job | `urban-goodz/driver/business-jobs/{id}/start` | ✅ |
| 16 | Mark Pickup/Delivery | `urban-goodz/driver/business-jobs/{id}/pickup`, `/delivery` | ✅ |
| 17 | Proof Pickup/Delivery | `urban-goodz/driver/business-jobs/{id}/proof-pickup`, `/proof-delivery` | ✅ |
| 18 | Report Exception | `urban-goodz/driver/business-jobs/{id}/exception` | ✅ |
| 19 | Capability Profile | `urban-goodz/driver/capability-profile` | ✅ |
| 20 | Capability Summary | `urban-goodz/driver/capability-summary` | ✅ |
| 21 | Update Vehicle | `urban-goodz/driver/capability-profile/vehicle` | ✅ |
| 22 | Update Cargo | `urban-goodz/driver/capability-profile/cargo` | ✅ |
| 23 | Update Zones | `urban-goodz/driver/capability-profile/zones` | ✅ |
| 24 | Update Work Types | `urban-goodz/driver/capability-profile/work-types` | ✅ |
| 25 | Update Tags | `urban-goodz/driver/capability-profile/tags` | ✅ |
| 26 | Update Availability | `urban-goodz/driver/capability-profile/availability` | ✅ |
| 27 | Job Discovery | `urban-goodz/driver/job-discovery` | ✅ |
| 28 | Job Discovery Summary | `urban-goodz/driver/job-discovery/summary` | ✅ |
| 29 | Job Discovery Detail | `urban-goodz/driver/job-discovery/{type}/{id}` | ✅ |
| 30 | Dispatch Notifications | `urban-goodz/driver/dispatch-notifications` | ✅ |
| 31 | Unread Count | `urban-goodz/driver/dispatch-notifications/unread-count` | ✅ |
| 32 | Read All | `urban-goodz/driver/dispatch-notifications/read-all` | ✅ |
| 33 | Mark Read/Dismiss | `urban-goodz/driver/dispatch-notifications/{id}/read`, `/dismiss` | ✅ |
| 34 | Earnings | `urban-goodz/driver/earnings` | ✅ |
| 35 | Payout Request | `urban-goodz/driver/payout-request` | ✅ |
| 36 | Payout History | `urban-goodz/driver/payout-history` | ✅ |
| 37 | Load Board | `urban-goodz/driver/load-board` | ✅ |
| 38 | Load Board Bid | `urban-goodz/driver/load-board/{id}/bid` | ✅ |
| 39 | Load Board Accept | `urban-goodz/driver/load-board/{id}/accept` | ✅ |
| 40 | Opportunities | `urban-goodz/driver/opportunities` | ✅ |
| 41 | Claim Opportunity | `urban-goodz/driver/opportunities/{id}/claim` | ✅ |
| 42 | Vehicles | `urban-goodz/driver/vehicles` | ✅ |
| 43 | Certifications | `urban-goodz/driver/certifications` | ✅ |
| 44 | Upload Certification | `urban-goodz/driver/certifications/{id}/upload` | ✅ |
| 45 | Renew Certification | `urban-goodz/driver/certifications/{id}/renew` | ✅ |
| 46 | Routes (Assigned) | `urban-goodz/driver/routes` | ✅ |
| 47 | Route Detail | `urban-goodz/driver/routes/{id}` | ✅ |
| 48 | Route Started/Completed | `urban-goodz/driver/routes/{id}/started`, `/completed` | ✅ |
| 49 | Scan Pickup/Dropoff | `urban-goodz/driver/routes/{id}/scan-pickup`, `/scan-dropoff` | ✅ |
| 50 | Scan Exception | `urban-goodz/driver/routes/{id}/scan-exception` | ✅ |
| 51 | Age Verify/Refuse | `urban-goodz/driver/routes/{id}/age-verify`, `/age-refuse` | ✅ |
| 52 | Purchase Card (OA) | `urban-goodz/driver/order-anywhere/{id}/purchase-card` | ✅ |
| 53 | Authorize Purchase | `urban-goodz/driver/order-anywhere/{id}/purchase-card/authorize` | ✅ |
| 54 | Complete Purchase | `urban-goodz/driver/order-anywhere/{id}/purchase-card/complete` | ✅ |
| 55 | Location Recording | `delivery-man/record-location-data` | ✅ |
| 56 | Current Orders | `delivery-man/current-orders` | ✅ |
| 57 | All Orders | `delivery-man/all-orders` | ✅ |
| 58 | Accept Order | `delivery-man/accept-order` | ✅ |
| 59 | Update Order Status | `delivery-man/update-order-status` | ✅ |
| 60 | Order Details | `delivery-man/order-details` | ✅ |
| 61 | Earning Report | `delivery-man/earning-report` | ✅ |
| 62 | Income Statement | `delivery-man/income-statement` | ✅ |
| 63 | Withdrawals | `delivery-man/get-withdraw-list`, `delivery-man/request-withdraw` | ✅ |
| 64 | Withdraw Methods | `delivery-man/withdraw-method/*` | ✅ |
| 65 | Notifications | `delivery-man/notifications` | ✅ |
| 66 | Messaging | `delivery-man/message/*` | ✅ |
| 67 | AI Concierge | `urban-goodz/ai-concierge/query` | ✅ |
| 68 | Cross-App AI | `urban-goodz/cross-app/ai/driver/*` | ✅ |
| 69 | Driver AI | `ai/*` (route optimization, earnings comparison, etc.) | ✅ |
| 70 | Logout/Remove | `delivery-man/remove-account` | ✅ |

### 4.2 Driver App API Config

**File:** `lib/config/api_config.dart`  
**Base URL:** `https://admin.urbangoodzdelivery.com`  
**Driver API Prefix:** `/api/v1/urban-goodz/driver`  
**Total Route Constants:** 47 named constants + 20+ dynamic methods  
**Dynamic Methods:** `businessJobDetail(id)`, `activeJobDetail(id)`, `loadBoardBid(loadId)`, etc.

---

## 5. ADMIN PANEL SURFACE INVENTORY

### 5.1 Admin Panel Feature Areas

| # | Feature Area | Key Routes | Status |
|---|---|---|---|
| 1 | Dashboard | Admin web routes | ✅ |
| 2 | Customer Management | Admin CRUD | ✅ |
| 3 | Vendor/Store Management | Admin CRUD | ✅ |
| 4 | Driver/Delivery Man Management | Admin CRUD | ✅ |
| 5 | Order Management | Admin CRUD + overrides | ✅ |
| 6 | Item/Product Management | Admin CRUD | ✅ |
| 7 | Category Management | Admin CRUD | ✅ |
| 8 | Coupon Management | Admin CRUD | ✅ |
| 9 | Banner Management | Admin CRUD | ✅ |
| 10 | Campaign Management | Admin CRUD | ✅ |
| 11 | Payment Management | Admin CRUD | ✅ |
| 12 | Refund Management | Admin approve/reject | ✅ |
| 13 | Payout Management | Admin approve/process | ✅ |
| 14 | Withdrawal Management | Admin approve/process | ✅ |
| 15 | Subscription Management | Admin CRUD | ✅ |
| 16 | Notification Management | Admin send/manage | ✅ |
| 17 | Report Generation | Admin analytics | ✅ |
| 18 | Business Settings | Admin configuration | ✅ |
| 19 | Zone Management | Admin CRUD | ✅ |
| 20 | Module Management | Admin configuration | ✅ |
| 21 | Fashion Fit Admin | `admin/fashion-fit/*` | ✅ |
| 22 | Service Bookings Admin | `admin/service-bookings/*` | ✅ |
| 23 | Order Anywhere Admin | `order-anywhere/admin/*` | ✅ |
| 24 | Load Board Admin | Admin web routes | ✅ |
| 25 | Dispatcher AI | `urban-goodz/dispatcher/ai/*` | ✅ |
| 26 | Fraud Detection AI | `urban-goodz/fraud/ai/*` | ✅ |
| 27 | Fashion Measurements Admin | `admin/urban-goodz/fashion/*` | ✅ |
| 28 | AI Operations | `admin_ai_operations.php` routes | ✅ |

---

## 6. INTEGRATION POINTS

### 6.1 External Service Integrations

| Service | Integration Point | Purpose |
|---|---|---|
| Firebase Cloud Messaging | Customer/Vendor/Driver apps | Push notifications |
| Pusher | All apps via Laravel Echo | Real-time WebSocket events |
| Google Maps | All apps | Geocoding, routing, distance matrix |
| Adyen | Payment processing | Payment capture, refunds, webhooks |
| Stripe | Payment processing (alternative) | Payment capture, refunds, webhooks |
| Google Sign-In | Customer app | Social authentication |
| Firebase Auth | Customer app | Phone verification, password reset |

### 6.2 Payment Webhook Routes

| Route | Provider | Purpose |
|---|---|---|
| `POST /adyen/webhook` | Adyen | Adyen payment status updates |
| `POST /payments/webhooks/{provider}` | Adyen/Stripe/Staged Test | Unified payment webhook handler |

### 6.3 Pusher Channel Configuration

| Config Key | Value |
|---|---|
| Broadcast URL | `/api/v1/broadcasting/user-auth` |
| Channel Pattern | `private-{user_id}-{user_type}` |
| Events Broadcast | Order status, notifications, dispatch alerts, load recommendations |

---

## 7. AUDIT TRAIL INVENTORY

### 7.1 Audit Log Coverage

| Entity | Audited Events | Log Fields |
|---|---|---|
| Order | Status change, amount edit, cancellation, refund | entity_type, entity_id, action, old_value, new_value, actor_type, actor_id, ip, user_agent, timestamp |
| Payment | Authorization, capture, failure, refund | entity_type, entity_id, action, amount, gateway_id, status, timestamp |
| Payout | Request, approval, processing, completion, failure, reversal | entity_type, entity_id, action, amount, method, status, timestamp |
| Vendor Profile | Profile update, bank info update, status change | entity_type, entity_id, action, changed_fields, timestamp |
| Driver Profile | Profile update, capability update, certification upload | entity_type, entity_id, action, changed_fields, timestamp |
| Fashion Fit | Profile create, photo upload, measurement save, request submit, estimate | entity_type, entity_id, action, timestamp |
| Service Booking | Create, quote, accept, pay, complete, cancel | entity_type, entity_id, action, old_status, new_status, timestamp |
| Load Board | Create, approve, assign, status change, exception | entity_type, entity_id, action, old_status, new_status, actor_type, timestamp |
| Order Anywhere | Submit, review, quote, approve, purchase, deliver | entity_type, entity_id, action, old_status, new_status, amount, timestamp |
| Admin Action | Any admin override, status change, refund approval | entity_type, entity_id, action, details, admin_id, timestamp |

### 7.2 Audit Log Query Endpoints

| Endpoint | Access | Purpose |
|---|---|---|
| Admin Panel audit views | `auth:admin` | View audit logs for any entity |
| `GET /api/v1/admin/fashion-fit/audits` | `auth:admin` | Fashion Fit audit trail |
| `GET /api/v1/admin/service-bookings/audit` | `auth:admin` | Service Booking audit trail |

---

## 8. ERROR HANDLING MATRIX

| HTTP Status | Meaning | Standard Response | Client Behavior |
|---|---|---|---|
| 200 | Success | Standard success response | Process data |
| 201 | Created | Standard success with created entity | Navigate to entity detail |
| 400 | Bad Request | `{"success": false, "message": "Bad request"}` | Show generic error |
| 401 | Unauthorized | `{"success": false, "message": "Unauthenticated"}` | Redirect to login |
| 403 | Forbidden | `{"success": false, "message": "Forbidden"}` | Show permission error |
| 404 | Not Found | `{"success": false, "message": "Not found"}` | Show not found screen |
| 422 | Validation Error | `{"success": false, "errors": {"field": ["message"]}}` | Highlight invalid fields |
| 429 | Rate Limited | `{"success": false, "message": "Too many requests"}` | Retry after delay |
| 409 | Conflict | `{"success": false, "message": "Conflict: {details}"}` | Show conflict resolution |
| 500 | Server Error | `{"success": false, "message": "Server error"}` | Retry or contact support |

---

## 9. SECURITY AUDIT CHECKLIST

| Check | Customer | Vendor | Driver | Admin |
|---|---|---|---|---|
| Token-based auth | ✅ | ✅ | ✅ | ✅ (session) |
| HTTPS enforced | ✅ | ✅ | ✅ | ✅ |
| Rate limiting | ✅ (60/min) | ✅ (60/min) | ✅ (60/min) | ✅ (60/min) |
| Input validation | ✅ | ✅ | ✅ | ✅ |
| SQL injection protection | ✅ (Eloquent) | ✅ (Eloquent) | ✅ (Eloquent) | ✅ (Eloquent) |
| XSS protection | ✅ (JSON API) | ✅ (JSON API) | ✅ (JSON API) | ✅ (Blade escaping) |
| CSRF protection | N/A (API) | N/A (API) | N/A (API) | ✅ (web forms) |
| File upload validation | ✅ (type/size) | ✅ (type/size) | ✅ (type/size) | ✅ (type/size) |
| Authorization checks | ✅ (owner only) | ✅ (store owner) | ✅ (assigned only) | ✅ (admin guard) |
| Audit logging | ✅ | ✅ | ✅ | ✅ |
| Secret/key protection | ✅ | ✅ | ✅ | ✅ |
