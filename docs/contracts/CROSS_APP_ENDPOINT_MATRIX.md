# CROSS-APP ENDPOINT MATRIX

**Version:** 3.9  
**Last Updated:** 2026-07-16  
**Purpose:** Complete mapping of every shared feature to backend routes, Flutter callers, auth requirements, and contract status.

---

## LEGEND

| Column | Description |
|---|---|
| **FEATURE** | Business capability name |
| **BACKEND ROUTE** | Actual Laravel route path |
| **HTTP METHOD** | GET, POST, PUT, DELETE |
| **AUTH ROLE** | Required auth guard (auth:api, vendor.api, dm.api, auth:admin) |
| **CUSTOMER FILE/CALLER** | Flutter customer app constant/method that calls this route |
| **VENDOR FILE/CALLER** | Flutter vendor app file that calls this route |
| **DRIVER FILE/CALLER** | Flutter driver app constant that calls this route |
| **REQUEST CONTRACT** | Expected request body shape (see URBAN_GOODZ_CROSS_APP_CONTRACT.md) |
| **RESPONSE CONTRACT** | Standard response shape (success/error/list) |
| **STATUS** | ✅ Production / ⚠️ Partial / ❌ Not Implemented / 🔒 Blocked |
| **BLOCKER** | Known issues preventing implementation |

---

## 1. AUTHENTICATION

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Customer Register | `/api/v1/auth/sign-up` | POST | None | `registerUri` | — | — | `{name, phone, email, password, phone_code}` | Success (user object) | ✅ | — |
| Customer Login | `/api/v1/auth/login` | POST | None | `loginUri` | — | — | `{phone/email, password}` | Success (token + user) | ✅ | — |
| Vendor Register | `/api/v1/auth/vendor/register` | POST | None | `storeRegisterUri` | Via `VendorApiClient.post()` | — | `{business_name, phone, email, ...}` | Success (vendor object) | ✅ | — |
| Vendor Login | `/api/v1/auth/vendor/login` | POST | vendor_app middleware | — | `VendorApiClient.post('auth/vendor/login')` | — | `{email, password}` | Success (token + vendor) | ✅ | — |
| Driver Login | `/api/v1/auth/delivery-man/login` | POST | deliveryman_app middleware | — | — | `driverLogin` | `{phone, password}` | Success (token + driver) | ✅ | — |
| Forgot Password (Customer) | `/api/v1/auth/forgot-password` | POST | None | `forgetPasswordUri` | — | — | `{phone/email}` | Success | ✅ | — |
| Reset Password (Customer) | `/api/v1/auth/reset-password` | PUT | None | `resetPasswordUri` | — | — | `{token, password, password_confirmation}` | Success | ✅ | — |
| Verify Phone | `/api/v1/auth/verify-phone` | POST | None | `verifyPhoneUri` | — | — | `{phone, phone_code, otp}` | Success | ✅ | — |
| Social Login | `/api/v1/auth/social-login` | POST | None | `socialLoginUri` | — | — | `{provider, token}` | Success (token + user) | ✅ | — |
| Firebase Token Verify | `/api/v1/auth/firebase-verify-token` | POST | None | `firebaseAuthVerify` | — | — | `{id_token}` | Success | ✅ | — |
| Guest Request | `/api/v1/auth/guest/request` | POST | None | `guestLoginUri` | — | — | `{device_id}` | Success (guest token) | ✅ | — |
| Update FCM Token (Vendor) | `PUT /api/v1/vendor/update-fcm-token` | PUT | vendor.api | — | `VendorApiClient.put('vendor/update-fcm-token')` | — | `{fcm_token}` | Success | ✅ | — |
| Update FCM Token (Driver) | `PUT /api/v1/delivery-man/update-fcm-token` | PUT | dm.api | — | — | `updateFcmToken` | `{fcm_token}` | Success | ✅ | — |

---

## 2. CONFIGURATION

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| App Config | `GET /api/v1/config` | GET | None | `configUri` | — | — | — | Success (config object) | ✅ | — |
| Get Zone ID | `GET /api/v1/config/get-zone-id` | GET | None | `zoneUri` | — | — | `?lat=&lng=` | Success (zone_id) | ✅ | — |
| Check Zone | `GET /api/v1/zone/check` | GET | None | `checkZoneUri` | — | — | `?lat=&lng=` | Success (zone_info) | ✅ | — |
| Place Autocomplete | `GET /api/v1/config/place-api-autocomplete` | GET | None | `searchLocationUri` | — | — | `?input=` | Success (predictions) | ✅ | — |
| Place Details | `GET /api/v1/config/place-api-details` | GET | None | `placeDetailsUri` | — | — | `?place_id=` | Success (address) | ✅ | — |
| Distance Matrix | `GET /api/v1/config/distance-api` | GET | None | `distanceMatrixUri` | — | — | `?origin=&destination=` | Success (distance) | ✅ | — |
| Direction API | `GET /api/v1/config/direction-api` | GET | None | `directionUri` | — | — | `?origin=&destination=` | Success (route) | ✅ | — |
| Geocode | `GET /api/v1/config/geocode-api` | GET | None | `geocodeUri` | — | — | `?lat=&lng=` | Success (address) | ✅ | — |
| Vehicle Options | `GET /api/v1/urban-goodz/driver/vehicle-options` | GET | None | — | — | — | — | Success (vehicle list) | ✅ | — |
| Payment Methods | `GET /api/v1/config/get-PaymentMethods` | GET | None | — | — | — | — | Success (methods) | ✅ | — |

---

## 3. CUSTOMER PROFILE & ADDRESS

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Get Profile | `GET /api/v1/customer/info` | GET | auth:api | `customerInfoUri` | — | — | — | Success (user) | ✅ | — |
| Update Profile | `POST /api/v1/customer/update-profile` | POST | auth:api | `updateProfileUri` | — | — | `{name, email, image}` | Success | ✅ | — |
| Address List | `GET /api/v1/customer/address/list` | GET | auth:api | `addressListUri` | — | — | — | Success (addresses) | ✅ | — |
| Add Address | `POST /api/v1/customer/address/add` | POST | auth:api | `addAddressUri` | — | — | `{address, lat, lng, ...}` | Success (address) | ✅ | — |
| Update Address | `PUT /api/v1/customer/address/update/{id}` | PUT | auth:api | `updateAddressUri` | — | — | `{address, lat, lng, ...}` | Success | ✅ | — |
| Delete Address | `DELETE /api/v1/customer/address/delete` | DELETE | auth:api | `removeAddressUri` | — | — | `?address_id=` | Success | ✅ | — |
| Saved Files | `GET /api/v1/customer/saved-files` | GET | auth:api | `savedFilesUri` | — | — | — | Success (files) | ✅ | — |
| Store Saved Files | `POST /api/v1/customer/saved-files/store` | POST | auth:api | `storeSavedFilesUri` | — | — | `{files[]}` | Success | ✅ | — |
| Update Zone | `GET /api/v1/customer/update-zone` | GET | auth:api | `updateZoneUri` | — | — | `?zone_id=` | Success | ✅ | — |

---

## 4. MARKETPLACE ORDERS

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Place Order | `POST /api/v1/customer/order/place` | POST | apiGuestCheck | `placeOrderUri` | — | — | `{store_id, address_id, items[], ...}` | Success (order_id) | ✅ | — |
| Prescription Place | `POST /api/v1/customer/order/prescription/place` | POST | apiGuestCheck | `placePrescriptionOrderUri` | — | — | `{store_id, prescription_files[]}` | Success (order_id) | ✅ | — |
| Order List | `GET /api/v1/customer/order/list` | GET | apiGuestCheck | `historyOrderListUri` | — | — | `?page=&status=` | List (orders) | ✅ | — |
| Running Orders | `GET /api/v1/customer/order/running-orders` | GET | apiGuestCheck | `runningOrderListUri` | — | — | — | List (running orders) | ✅ | — |
| All Running Orders | `GET /api/v1/customer/order/all-running-orders` | GET | apiGuestCheck | `dashboardOrderUri` | — | — | — | List | ✅ | — |
| Order Details | `GET /api/v1/customer/order/details` | GET | apiGuestCheck | `orderDetailsUri` | — | — | `?order_id=` | Success (order detail) | ✅ | — |
| Cancel Order | `PUT /api/v1/customer/order/cancel` | PUT | apiGuestCheck | `orderCancelUri` | — | — | `{order_id, reason}` | Success | ✅ | — |
| Track Order | `GET /api/v1/customer/order/track` | GET | None | `trackUri` | — | — | `?order_id=` | Success (tracking) | ✅ | — |
| Payment Method | `PUT /api/v1/customer/order/payment-method` | PUT | apiGuestCheck | `codSwitchUri` | — | — | `{order_id, payment_method}` | Success | ✅ | — |
| Wallet Payment | `POST /api/v1/customer/order/wallet-payment` | POST | apiGuestCheck | `walletSwitchUri` | — | — | `{order_id}` | Success | ✅ | — |
| Offline Payment | `PUT /api/v1/customer/order/offline-payment` | PUT | apiGuestCheck | `offlinePaymentSaveInfoUri` | — | — | `{order_id, ...}` | Success | ✅ | — |
| Get Tax | `POST /api/v1/customer/order/get-Tax` | POST | apiGuestCheck | `getOrderTaxUri` | — | — | `{cart_items}` | Success (tax) | ✅ | — |
| Surge Price | `POST /api/v1/customer/order/get-surge-price` | POST | apiGuestCheck | `getSurgePriceUri` | — | — | `{store_id, ...}` | Success (surge) | ✅ | — |
| Refund Request | `POST /api/v1/customer/order/refund-request` | POST | apiGuestCheck | `refundRequestUri` | — | — | `{order_id, reason, images[]}` | Success | ✅ | — |
| Refund Reasons | `GET /api/v1/customer/order/refund-reasons` | GET | apiGuestCheck | `refundReasonUri` | — | — | — | List (reasons) | ✅ | — |
| Cancellation Reasons | `GET /api/v1/customer/order/cancellation-reasons` | GET | None | `orderCancellationUri` | — | — | — | List (reasons) | ✅ | — |
| Parcel Return | `POST /api/v1/customer/order/parcel-return` | POST | apiGuestCheck | `customerParcelReturn` | — | — | `{order_id, reason}` | Success | ✅ | — |
| Payment Failed | `GET /api/v1/customer/order/payment-failed` | GET | apiGuestCheck | `paymentFailedDetailsUri` | — | — | `?order_id=` | Success (error details) | ✅ | — |

---

## 5. VENDOR ORDERS & OPERATIONS

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Vendor Profile | `GET /api/v1/vendor/profile` | GET | vendor.api | — | `VendorApiClient.get('vendor/profile')` | — | — | Success (vendor profile) | ✅ | — |
| Update Profile | `PUT /api/v1/vendor/update-profile` | PUT | vendor.api | — | `VendorApiClient.put('vendor/update-profile')` | — | `{name, image, ...}` | Success | ✅ | — |
| Current Orders | `GET /api/v1/vendor/current-orders` | GET | vendor.api | — | `VendorApiClient.get('vendor/current-orders')` | — | — | List (orders) | ✅ | — |
| Completed Orders | `GET /api/v1/vendor/completed-orders` | GET | vendor.api | — | `VendorApiClient.get('vendor/completed-orders')` | — | — | List | ✅ | — |
| Canceled Orders | `GET /api/v1/vendor/canceled-orders` | GET | vendor.api | — | `VendorApiClient.get('vendor/canceled-orders')` | — | — | List | ✅ | — |
| All Orders | `GET /api/v1/vendor/all-orders` | GET | vendor.api | — | `VendorApiClient.get('vendor/all-orders')` | — | — | List | ✅ | — |
| Update Order Status | `PUT /api/v1/vendor/update-order-status` | PUT | vendor.api | — | `VendorApiClient.put('vendor/update-order-status')` | — | `{order_id, status}` | Success | ✅ | — |
| Edit Order Amount | `PUT /api/v1/vendor/update-order-amount` | PUT | vendor.api | — | `VendorApiClient.put('vendor/update-order-amount')` | — | `{order_id, amount}` | Success | ✅ | — |
| Order Details | `GET /api/v1/vendor/order-details` | GET | vendor.api | — | `VendorApiClient.get('vendor/order-details')` | — | `?order_id=` | Success (detail) | ✅ | — |
| Get Order | `GET /api/v1/vendor/order` | GET | vendor.api | — | `VendorApiClient.get('vendor/order')` | — | `?order_id=` | Success | ✅ | — |
| Send Order OTP | `PUT /api/v1/vendor/send-order-otp` | PUT | vendor.api | — | `VendorApiClient.put('vendor/send-order-otp')` | — | `{order_id}` | Success | ✅ | — |
| Earning Info | `GET /api/v1/vendor/earning-info` | GET | vendor.api | — | `VendorApiClient.get('vendor/earning-info')` | — | — | Success (earnings) | ✅ | — |
| Earning Report | `GET /api/v1/vendor/earning-report` | GET | vendor.api | — | `VendorApiClient.get('vendor/earning-report')` | — | `?start=&end=` | Success (report) | ✅ | — |
| Withdraw List | `GET /api/v1/vendor/get-withdraw-list` | GET | vendor.api | — | `VendorApiClient.get('vendor/get-withdraw-list')` | — | — | List (withdrawals) | ✅ | — |
| Request Withdraw | `POST /api/v1/vendor/request-withdraw` | POST | vendor.api | — | `VendorApiClient.post('vendor/request-withdraw')` | — | `{amount, method}` | Success | ✅ | — |
| Active Status | `POST /api/v1/vendor/update-active-status` | POST | vendor.api | — | `VendorApiClient.post('vendor/update-active-status')` | — | `{is_active: 0/1}` | Success | ✅ | — |
| Notifications | `GET /api/v1/vendor/notifications` | GET | vendor.api | — | `VendorApiClient.get('vendor/notifications')` | — | — | List (notifications) | ✅ | — |
| Unread Count | `GET /api/v1/vendor/notifications/unread-count` | GET | vendor.api | — | `VendorApiClient.get('vendor/notifications/unread-count')` | — | — | Success (count) | ✅ | — |
| Mark Read | `POST /api/v1/vendor/notifications/{id}/read` | POST | vendor.api | — | `VendorApiClient.post('vendor/notifications/{id}/read')` | — | — | Success | ✅ | — |
| Mark All Read | `POST /api/v1/vendor/notifications/read-all` | POST | vendor.api | — | `VendorApiClient.post('vendor/notifications/read-all')` | — | — | Success | ✅ | — |
| Logout | `POST /api/v1/vendor/logout` | POST | vendor.api | — | `VendorApiClient.post('vendor/logout')` | — | — | Success | ✅ | — |

---

## 6. VENDOR PRODUCTS (ITEMS)

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Create Item | `POST /api/v1/seller/item/store` | POST | vendor.api | — | `VendorApiClient.post('seller/item/store')` | — | `{name, price, category_id, ...}` | Success (item) | ✅ | — |
| Update Item | `PUT /api/v1/seller/item/update` | PUT | vendor.api | — | `VendorApiClient.put('seller/item/update')` | — | `{item_id, ...}` | Success | ✅ | — |
| Delete Item | `DELETE /api/v1/seller/item/delete` | DELETE | vendor.api | — | `VendorApiClient.delete('seller/item/delete')` | — | `{item_id}` | Success | ✅ | — |
| Item Status | `GET /api/v1/seller/item/status` | GET | vendor.api | — | `VendorApiClient.get('seller/item/status')` | — | `?item_id=` | Success (status) | ✅ | — |
| Item Details | `GET /api/v1/seller/item/details/{id}` | GET | vendor.api | — | `VendorApiClient.get('seller/item/details/{id}')` | — | — | Success (item) | ✅ | — |
| Stock Update | `PUT /api/v1/seller/item/stock-update` | PUT | vendor.api | — | `VendorApiClient.put('seller/item/stock-update')` | — | `{item_id, stock}` | Success | ✅ | — |
| Stock Limit List | `GET /api/v1/seller/item/stock-limit-list` | GET | vendor.api | — | `VendorApiClient.get('seller/item/stock-limit-list')` | — | — | List | ✅ | — |
| Pending Items | `GET /api/v1/seller/item/pending/item/list` | GET | vendor.api | — | `VendorApiClient.get('seller/item/pending/item/list')` | — | — | List (pending) | ✅ | — |
| Item Search | `POST /api/v1/seller/item/search` | POST | vendor.api | — | `VendorApiClient.post('seller/item/search')` | — | `{query}` | List (results) | ✅ | — |
| Recommended Items | `GET /api/v1/seller/item/recommended` | GET | vendor.api | — | `VendorApiClient.get('seller/item/recommended')` | — | — | List | ✅ | — |
| Item Reviews | `GET /api/v1/seller/item/reviews` | GET | vendor.api | — | `VendorApiClient.get('seller/item/reviews')` | — | — | List (reviews) | ✅ | — |
| Customer Browse Latest | `GET /api/v1/items/latest` | GET | None | `storeItemUri` | — | — | — | List (items) | ✅ | — |
| Customer Browse Popular | `GET /api/v1/items/popular` | GET | None | `popularItemUri` | — | — | — | List (items) | ✅ | — |
| Customer Item Detail | `GET /api/v1/items/details/{id}` | GET | None | `searchItemUri` | — | — | — | Success (item) | ✅ | — |
| Customer Search | `GET /api/v1/items/search` | GET | None | `searchUri` | — | — | `?name=` | List (items) | ✅ | — |
| Submit Review | `POST /api/v1/items/reviews/submit` | POST | auth:api | `reviewUri` | — | — | `{item_id, rating, comment}` | Success | ✅ | — |

---

## 7. VENDOR COUPONS & ADVERTISEMENTS

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Vendor Coupon List | `GET /api/v1/seller/coupon/list` | GET | vendor.api | — | `VendorApiClient.get('seller/coupon/list')` | — | — | List (coupons) | ✅ | — |
| Vendor Coupon Store | `POST /api/v1/seller/coupon/store` | POST | vendor.api | — | `VendorApiClient.post('seller/coupon/store')` | — | `{code, discount, ...}` | Success | ✅ | — |
| Vendor Coupon Update | `POST /api/v1/seller/coupon/update` | POST | vendor.api | — | `VendorApiClient.post('seller/coupon/update')` | — | `{id, ...}` | Success | ✅ | — |
| Vendor Coupon Status | `POST /api/v1/seller/coupon/status` | POST | vendor.api | — | `VendorApiClient.post('seller/coupon/status')` | — | `{id, status}` | Success | ✅ | — |
| Vendor Coupon Delete | `POST /api/v1/seller/coupon/delete` | POST | vendor.api | — | `VendorApiClient.post('seller/coupon/delete')` | — | `{id}` | Success | ✅ | — |
| Customer Coupon List | `GET /api/v1/coupon/list` | GET | auth:api | `couponUri` | — | — | — | List (coupons) | ✅ | — |
| Customer Coupon Apply | `GET /api/v1/coupon/apply` | GET | auth:api | `couponApplyUri` | — | — | `?code=` | Success (discount) | ✅ | — |
| Vendor Advertisement List | `GET /api/v1/seller/advertisement/` | GET | vendor.api | — | `VendorApiClient.get('seller/advertisement/')` | — | — | List (ads) | ✅ | — |
| Vendor Advertisement Store | `POST /api/v1/seller/advertisement/store` | POST | vendor.api | — | `VendorApiClient.post('seller/advertisement/store')` | — | `{title, ...}` | Success | ✅ | — |
| Customer Advertisement List | `GET /api/v1/advertisement/list` | GET | None | `advertisementListUri` | — | — | — | List (ads) | ✅ | — |

---

## 8. DRIVER DELIVERY-MAN (LEGACY)

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Driver Profile | `GET /api/v1/delivery-man/profile` | GET | dm.api | — | — | `driverProfile` | — | Success (profile) | ✅ | — |
| Update Profile | `PUT /api/v1/delivery-man/update-profile` | PUT | dm.api | — | — | Via api client | `{name, image, ...}` | Success | ✅ | — |
| Record Location | `POST /api/v1/delivery-man/record-location-data` | POST | dm.api | — | — | `recordLocation` | `{lat, lng, timestamp}` | Success | ✅ | — |
| Current Orders | `GET /api/v1/delivery-man/current-orders` | GET | dm.api | — | — | Via api client | — | List (orders) | ✅ | — |
| All Orders | `GET /api/v1/delivery-man/all-orders` | GET | dm.api | — | — | Via api client | — | List | ✅ | — |
| Accept Order | `PUT /api/v1/delivery-man/accept-order` | PUT | dm.api | — | — | Via api client | `{order_id}` | Success | ✅ | — |
| Update Order Status | `PUT /api/v1/delivery-man/update-order-status` | PUT | dm.api | — | — | Via api client | `{id, status}` | Success | ✅ | — |
| Order Details | `GET /api/v1/delivery-man/order-details` | GET | dm.api | — | — | Via api client | `?order_id=` | Success (detail) | ✅ | — |
| Earning Report | `GET /api/v1/delivery-man/earning-report` | GET | dm.api | — | — | Via api client | — | Success (report) | ✅ | — |
| Income Statement | `GET /api/v1/delivery-man/income-statement` | GET | dm.api | — | — | Via api client | — | Success (statement) | ✅ | — |
| Withdraw List | `GET /api/v1/delivery-man/get-withdraw-list` | GET | dm.api | — | — | Via api client | — | List | ✅ | — |
| Request Withdraw | `POST /api/v1/delivery-man/request-withdraw` | POST | dm.api | — | — | Via api client | `{amount, method}` | Success | ✅ | — |
| Active Status | `POST /api/v1/delivery-man/update-active-status` | POST | dm.api | — | — | Via api client | `{is_active}` | Success | ✅ | — |
| Send OTP | `PUT /api/v1/delivery-man/send-order-otp` | PUT | dm.api | — | — | Via api client | `{order_id}` | Success | ✅ | — |
| Parcel Return | `POST /api/v1/delivery-man/parcel-return` | POST | dm.api | — | — | Via api client | `{order_id, reason}` | Success | ✅ | — |
| Notifications | `GET /api/v1/delivery-man/notifications` | GET | dm.api | — | — | Via api client | — | List | ✅ | — |
| Customer Last Location | `GET /api/v1/delivery-man/last-location` | GET | auth:api | `lastLocationUri` | — | — | `?order_id=` | Success (location) | ✅ | — |

---

## 9. DRIVER URBAN GOODZ (NEW)

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| **Active Jobs** | | | | | | | | | | |
| List Active Jobs | `GET /api/v1/urban-goodz/driver/active-jobs` | GET | dm.api | — | — | `activeJobs` | — | List (jobs) | ✅ | — |
| Active Job Detail | `GET /api/v1/urban-goodz/driver/active-jobs/{id}` | GET | dm.api | — | — | `activeJobDetail(id)` | — | Success (job) | ✅ | — |
| Start Job | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/start` | POST | dm.api | — | — | `activeJobStart(id)` | — | Success | ✅ | — |
| Complete Job | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/complete` | POST | dm.api | — | — | `activeJobComplete(id)` | — | Success | ✅ | — |
| Cancel Job | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/cancel` | POST | dm.api | — | — | `activeJobCancel(id)` | — | Success | ✅ | — |
| Update Job Status | `POST /api/v1/urban-goodz/driver/active-jobs/{id}/status` | POST | dm.api | — | — | `activeJobStatus(id)` | `{status}` | Success | ✅ | — |
| **Business Courier Jobs** | | | | | | | | | | |
| List Business Jobs | `GET /api/v1/urban-goodz/driver/business-jobs` | GET | dm.api | — | — | `businessJobs` | — | List (jobs) | ✅ | — |
| Business Job Detail | `GET /api/v1/urban-goodz/driver/business-jobs/{id}` | GET | dm.api | — | — | `businessJobDetail(id)` | — | Success (job) | ✅ | — |
| Accept Business Job | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/accept` | POST | dm.api | — | — | `businessJobAccept(id)` | — | Success | ✅ | — |
| Start Business Job | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/start` | POST | dm.api | — | — | `businessJobStart(id)` | — | Success | ✅ | — |
| Mark Pickup | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/pickup` | POST | dm.api | — | — | `businessJobPickup(id)` | — | Success | ✅ | — |
| Mark Delivery | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/delivery` | POST | dm.api | — | — | `businessJobDelivery(id)` | — | Success | ✅ | — |
| Proof Pickup | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/proof-pickup` | POST | dm.api | — | — | `businessJobProofPickup(id)` | `{image, notes}` | Success | ✅ | — |
| Proof Delivery | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/proof-delivery` | POST | dm.api | — | — | `businessJobProofDelivery(id)` | `{image, notes}` | Success | ✅ | — |
| Report Exception | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/exception` | POST | dm.api | — | — | `businessJobException(id)` | `{reason, details}` | Success | ✅ | — |
| **Capability Profile** | | | | | | | | | | |
| Get Profile | `GET /api/v1/urban-goodz/driver/capability-profile` | GET | dm.api | — | — | `capabilityProfile` | — | Success (profile) | ✅ | — |
| Summary | `GET /api/v1/urban-goodz/driver/capability-summary` | GET | dm.api | — | — | `capabilitySummary` | — | Success (summary) | ✅ | — |
| Update Vehicle | `POST /api/v1/urban-goodz/driver/capability-profile/vehicle` | POST | dm.api | — | — | `capabilityVehicle` | `{vehicle_type, ...}` | Success | ✅ | — |
| Update Cargo | `POST /api/v1/urban-goodz/driver/capability-profile/cargo` | POST | dm.api | — | — | `capabilityCargo` | `{cargo_types}` | Success | ✅ | — |
| Update Zones | `POST /api/v1/urban-goodz/driver/capability-profile/zones` | POST | dm.api | — | — | `capabilityZones` | `{zone_ids}` | Success | ✅ | — |
| Update Work Types | `POST /api/v1/urban-goodz/driver/capability-profile/work-types` | POST | dm.api | — | — | `capabilityWorkTypes` | `{types}` | Success | ✅ | — |
| Update Tags | `POST /api/v1/urban-goodz/driver/capability-profile/tags` | POST | dm.api | — | — | `capabilityTags` | `{tags}` | Success | ✅ | — |
| Update Availability | `POST /api/v1/urban-goodz/driver/capability-profile/availability` | POST | dm.api | — | — | `capabilityAvailability` | `{schedule}` | Success | ✅ | — |
| **Job Discovery** | | | | | | | | | | |
| Discovery Index | `GET /api/v1/urban-goodz/driver/job-discovery` | GET | dm.api | — | — | `jobDiscovery` | — | List (jobs) | ✅ | — |
| Discovery Summary | `GET /api/v1/urban-goodz/driver/job-discovery/summary` | GET | dm.api | — | — | `jobDiscoverySummary` | — | Success (summary) | ✅ | — |
| Discovery Detail | `GET /api/v1/urban-goodz/driver/job-discovery/{type}/{id}` | GET | dm.api | — | — | `jobDiscoveryDetail(type, id)` | — | Success (detail) | ✅ | — |
| **Dispatch Notifications** | | | | | | | | | | |
| Notification List | `GET /api/v1/urban-goodz/driver/dispatch-notifications` | GET | dm.api | — | — | `dispatchNotifications` | — | List | ✅ | — |
| Unread Count | `GET /api/v1/urban-goodz/driver/dispatch-notifications/unread-count` | GET | dm.api | — | — | `dispatchUnreadCount` | — | Success (count) | ✅ | — |
| Read All | `POST /api/v1/urban-goodz/driver/dispatch-notifications/read-all` | POST | dm.api | — | — | `dispatchReadAll` | — | Success | ✅ | — |
| Mark Read | `POST /api/v1/urban-goodz/driver/dispatch-notifications/{id}/read` | POST | dm.api | — | — | `dispatchRead(id)` | — | Success | ✅ | — |
| Dismiss | `POST /api/v1/urban-goodz/driver/dispatch-notifications/{id}/dismiss` | POST | dm.api | — | — | `dispatchDismiss(id)` | — | Success | ✅ | — |
| **Earnings & Payouts** | | | | | | | | | | |
| Earnings | `GET /api/v1/urban-goodz/driver/earnings` | GET | dm.api | — | — | `earnings` | — | Success (earnings) | ✅ | — |
| Payout Request | `POST /api/v1/urban-goodz/driver/payout-request` | POST | dm.api | — | — | `payoutRequest` | `{amount, method}` | Success | ✅ | — |
| Payout History | `GET /api/v1/urban-goodz/driver/payout-history` | GET | dm.api | — | — | `payoutHistory` | — | List (payouts) | ✅ | — |
| **Load Board** | | | | | | | | | | |
| Available Loads | `GET /api/v1/urban-goodz/driver/load-board` | GET | dm.api | — | — | `loadBoard` | — | List (loads) | ✅ | — |
| Bid on Load | `POST /api/v1/urban-goodz/driver/load-board/{id}/bid` | POST | dm.api | — | — | `loadBoardBid(id)` | `{amount, notes}` | Success | ✅ | — |
| Accept Load | `POST /api/v1/urban-goodz/driver/load-board/{id}/accept` | POST | dm.api | — | — | `loadBoardAccept(id)` | — | Success | ✅ | — |
| **Opportunities** | | | | | | | | | | |
| List Opportunities | `GET /api/v1/urban-goodz/driver/opportunities` | GET | dm.api | — | — | `opportunities` | — | List | ✅ | — |
| Claim Opportunity | `POST /api/v1/urban-goodz/driver/opportunities/{id}/claim` | POST | dm.api | — | — | `opportunityClaim(id)` | — | Success | ✅ | — |
| **Vehicles & Certifications** | | | | | | | | | | |
| List Vehicles | `GET /api/v1/urban-goodz/driver/vehicles` | GET | dm.api | — | — | `vehicles` | — | List (vehicles) | ✅ | — |
| List Certifications | `GET /api/v1/urban-goodz/driver/certifications` | GET | dm.api | — | — | `certifications` | — | List (certs) | ✅ | — |
| Upload Cert | `POST /api/v1/urban-goodz/driver/certifications/{id}/upload` | POST | dm.api | — | — | `certificationUpload(id)` | `{file}` | Success | ✅ | — |
| Renew Cert | `POST /api/v1/urban-goodz/driver/certifications/{id}/renew` | POST | dm.api | — | — | `certificationRenew(id)` | — | Success | ✅ | — |
| **Purchase Card (Order Anywhere)** | | | | | | | | | | |
| Get Card | `GET /api/v1/urban-goodz/driver/order-anywhere/{id}/purchase-card` | GET | dm.api | — | — | Via api client | — | Success (card) | ✅ | — |
| Authorize Purchase | `POST /api/v1/urban-goodz/driver/order-anywhere/{id}/purchase-card/authorize` | POST | dm.api | — | — | Via api client | — | Success | ✅ | — |
| Complete Purchase | `POST /api/v1/urban-goodz/driver/order-anywhere/{id}/purchase-card/complete` | POST | dm.api | — | — | Via api client | `{amount, receipt}` | Success | ✅ | — |
| **Routes** | | | | | | | | | | |
| Assigned Routes | `GET /api/v1/urban-goodz/driver/routes` | GET | dm.api | — | — | Via api client | — | List (routes) | ✅ | — |
| Route Detail | `GET /api/v1/urban-goodz/driver/routes/{id}` | GET | dm.api | — | — | Via api client | — | Success (route) | ✅ | — |
| Route Started | `POST /api/v1/urban-goodz/driver/routes/{id}/started` | POST | dm.api | — | — | Via api client | — | Success | ✅ | — |
| Route Completed | `POST /api/v1/urban-goodz/driver/routes/{id}/completed` | POST | dm.api | — | — | Via api client | — | Success | ✅ | — |
| Scan Pickup | `POST /api/v1/urban-goodz/driver/routes/{id}/scan-pickup` | POST | dm.api | — | — | Via api client | `{package_id}` | Success | ✅ | — |
| Scan Dropoff | `POST /api/v1/urban-goodz/driver/routes/{id}/scan-dropoff` | POST | dm.api | — | — | Via api client | `{package_id}` | Success | ✅ | — |
| Scan Exception | `POST /api/v1/urban-goodz/driver/routes/{id}/scan-exception` | POST | dm.api | — | — | Via api client | `{package_id, reason}` | Success | ✅ | — |
| Age Verify | `POST /api/v1/urban-goodz/driver/routes/{id}/age-verify` | POST | dm.api | — | — | Via api client | `{verified: true}` | Success | ✅ | — |
| Age Refuse | `POST /api/v1/urban-goodz/driver/routes/{id}/age-refuse` | POST | dm.api | — | — | Via api client | `{reason}` | Success | ✅ | — |
| Age Status | `GET /api/v1/urban-goodz/driver/routes/{id}/age-status` | GET | dm.api | — | — | Via api client | — | Success (status) | ✅ | — |

---

## 10. ORDER ANYWHERE

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Customer Submit Request | `POST /api/v1/order-anywhere/requests` | POST | auth:api | `orderAnywhereRequestUri` | — | — | `{description, budget, ...}` | Success (request) | ✅ | — |
| Customer List Requests | `GET /api/v1/order-anywhere/customer/requests` | GET | auth:api | `orderAnywhereListUri` | — | — | — | List (requests) | ✅ | — |
| Customer View Request | `GET /api/v1/order-anywhere/requests/{id}` | GET | auth:api | Via service | — | — | — | Success (detail) | ✅ | — |
| Estimate | `GET /api/v1/order-anywhere/requests/estimate` | GET | auth:api | `orderAnywhereEstimateUri` | — | — | `?description=` | Success (estimate) | ✅ | — |
| Authorize Payment | `POST /api/v1/order-anywhere/requests/{id}/authorize-payment` | POST | auth:api | Via service | — | — | `{payment_method}` | Success | ✅ | — |
| Upload Receipt | `POST /api/v1/order-anywhere/requests/{id}/receipt` | POST | auth:api | — | — | Via api client | `{image, amount}` | Success | ✅ | — |
| Vendor Update | `POST /api/v1/order-anywhere/vendor/requests/{id}/update` | POST | vendor.api | — | Via VendorApiClient | — | `{status, notes}` | Success | ✅ | — |
| Admin List Requests | `GET /api/v1/order-anywhere/admin/requests` | GET | auth:admin | — | — | — | — | List (requests) | ✅ | — |
| Admin Update Status | `POST /api/v1/order-anywhere/admin/requests/{id}/status` | POST | auth:admin | — | — | — | `{status, notes}` | Success | ✅ | — |
| Admin Assign Driver | `POST /api/v1/order-anywhere/admin/requests/{id}/assign-driver` | POST | auth:admin | — | — | — | `{driver_id}` | Success | ✅ | — |
| Admin Add Notes | `POST /api/v1/order-anywhere/admin/requests/{id}/notes` | POST | auth:admin | — | — | — | `{notes}` | Success | ✅ | — |
| Admin Payment Link | `POST /api/v1/order-anywhere/admin/requests/{id}/payment-link` | POST | auth:admin | — | — | — | — | Success (url) | ✅ | — |
| Driver Available | `GET /api/v1/order-anywhere/driver/available` | GET | dm.api | — | — | `orderAnywhereDriverAvailableUri` | — | List (requests) | ✅ | — |
| Driver Accept | `POST /api/v1/order-anywhere/driver/{id}/accept` | POST | dm.api | — | — | `orderAnywhereDriverAcceptUri` | — | Success | ✅ | — |
| Driver Status Update | `POST /api/v1/order-anywhere/driver/{id}/status` | POST | dm.api | — | — | Via api client | `{status}` | Success | ✅ | — |
| Driver Report Issue | `POST /api/v1/order-anywhere/driver/{id}/issue` | POST | dm.api | — | — | Via api client | `{issue_type, details}` | Success | ✅ | — |

---

## 11. FASHION FIT

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| **Customer Profile** | | | | | | | | | | |
| List Profiles | `GET /api/v1/fashion-fit/profiles` | GET | auth:api | Via service | — | — | — | List (profiles) | ✅ | — |
| Create Profile | `POST /api/v1/fashion-fit/profiles` | POST | auth:api | Via service | — | — | `{name, height, weight, ...}` | Success (profile) | ✅ | — |
| View Profile | `GET /api/v1/fashion-fit/profiles/{uuid}` | GET | auth:api | Via service | — | — | — | Success (profile) | ✅ | — |
| Update Profile | `PUT /api/v1/fashion-fit/profiles/{uuid}` | PUT | auth:api | Via service | — | — | `{...}` | Success | ✅ | — |
| Delete Profile | `DELETE /api/v1/fashion-fit/profiles/{uuid}` | DELETE | auth:api | Via service | — | — | — | Success | ✅ | — |
| Update Consent | `POST /api/v1/fashion-fit/profiles/{uuid}/consent` | POST | auth:api | Via service | — | — | `{consented: true}` | Success | ✅ | — |
| Upload Photo | `POST /api/v1/fashion-fit/profiles/{uuid}/photos` | POST | auth:api | Via service | — | — | `{photo: file}` | Success (photo) | ✅ | — |
| Download Photo | `GET /api/v1/fashion-fit/profiles/{uuid}/photos/{photo_uuid}` | GET | auth:api | Via service | — | — | — | File stream | ✅ | — |
| Delete Photo | `DELETE /api/v1/fashion-fit/profiles/{uuid}/photos/{photo_uuid}` | DELETE | auth:api | Via service | — | — | — | Success | ✅ | — |
| Submit Analysis | `POST /api/v1/fashion-fit/profiles/{uuid}/analyses` | POST | auth:api | Via service | — | — | — | Success (analysis) | ✅ | — |
| View Analysis | `GET /api/v1/fashion-fit/profiles/{uuid}/analyses/{analysis_uuid}` | GET | auth:api | Via service | — | — | — | Success (analysis) | ✅ | — |
| Correct Measurement | `PUT /api/v1/fashion-fit/profiles/{uuid}/measurements/{id}` | PUT | auth:api | Via service | — | — | `{value}` | Success | ✅ | — |
| Approve Measurements | `POST /api/v1/fashion-fit/profiles/{uuid}/approve` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| **Customer Requests** | | | | | | | | | | |
| List Requests | `GET /api/v1/fashion-fit/requests` | GET | auth:api | Via service | — | — | — | List (requests) | ✅ | — |
| Create Request | `POST /api/v1/fashion-fit/requests` | POST | auth:api | Via service | — | — | `{profile_uuid, description}` | Success (request) | ✅ | — |
| View Request | `GET /api/v1/fashion-fit/requests/{uuid}` | GET | auth:api | Via service | — | — | — | Success (request) | ✅ | — |
| Decide Estimate | `POST /api/v1/fashion-fit/requests/{uuid}/estimates/{id}/decision` | POST | auth:api | Via service | — | — | `{decision: accept/reject}` | Success | ✅ | — |
| Staged Payment | `POST /api/v1/fashion-fit/requests/{uuid}/staged-payment` | POST | auth:api | Via service | — | — | `{amount}` | Success | ✅ | — |
| Revoke | `POST /api/v1/fashion-fit/requests/{uuid}/revoke` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| **Vendor** | | | | | | | | | | |
| Vendor Profile | `GET /api/v1/vendor/fashion-fit/profile` | GET | vendor.api | — | `VendorApiClient.get('vendor/fashion-fit/profile')` | — | — | Success (profile) | ✅ | — |
| Update Vendor Profile | `PUT /api/v1/vendor/fashion-fit/profile` | PUT | vendor.api | — | `VendorApiClient.put('vendor/fashion-fit/profile')` | — | `{...}` | Success | ✅ | — |
| Vendor List Requests | `GET /api/v1/vendor/fashion-fit/requests` | GET | vendor.api | — | `VendorApiClient.get('vendor/fashion-fit/requests')` | — | — | List | ✅ | — |
| Vendor View Request | `GET /api/v1/vendor/fashion-fit/requests/{uuid}` | GET | vendor.api | — | `VendorApiClient.get('vendor/fashion-fit/requests/{uuid}')` | — | — | Success (request) | ✅ | — |
| Vendor Request Clarification | `POST /api/v1/vendor/fashion-fit/requests/{uuid}/clarification` | POST | vendor.api | — | `VendorApiClient.post(...)` | — | `{message}` | Success | ✅ | — |
| Vendor Submit Estimate | `POST /api/v1/vendor/fashion-fit/requests/{uuid}/estimates` | POST | vendor.api | — | `VendorApiClient.post(...)` | — | `{amount, items[], timeline}` | Success | ✅ | — |
| Vendor Update Status | `POST /api/v1/vendor/fashion-fit/requests/{uuid}/status` | POST | vendor.api | — | `VendorApiClient.post(...)` | — | `{status}` | Success | ✅ | — |
| Vendor Earnings | `GET /api/v1/vendor/fashion-fit/earnings` | GET | vendor.api | — | `VendorApiClient.get(...)` | — | — | Success (earnings) | ✅ | — |
| **Admin** | | | | | | | | | | |
| Admin Dashboard | `GET /api/v1/admin/fashion-fit/dashboard` | GET | auth:admin | — | — | — | — | Success (dashboard) | ✅ | — |
| Admin Requests | `GET /api/v1/admin/fashion-fit/requests` | GET | auth:admin | — | — | — | — | List (requests) | ✅ | — |
| Admin Audits | `GET /api/v1/admin/fashion-fit/audits` | GET | auth:admin | — | — | — | — | List (audits) | ✅ | — |
| Admin Provider Status | `POST /api/v1/admin/fashion-fit/providers/{vendor}/status` | POST | auth:admin | — | — | — | `{status}` | Success | ✅ | — |

---

## 12. SERVICE BOOKINGS

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Customer Providers List | `GET /api/v1/customer/service-bookings/providers` | GET | None | Via service | — | — | — | List (providers) | ✅ | — |
| Customer Provider Detail | `GET /api/v1/customer/service-bookings/providers/{id}` | GET | None | Via service | — | — | — | Success (provider) | ✅ | — |
| Customer List Bookings | `GET /api/v1/customer/service-bookings` | GET | auth:api | Via service | — | — | — | List (bookings) | ✅ | — |
| Customer Create Booking | `POST /api/v1/customer/service-bookings` | POST | auth:api | Via service | — | — | `{provider_id, service_id, date}` | Success (booking) | ✅ | — |
| Customer View Booking | `GET /api/v1/customer/service-bookings/{id}` | GET | auth:api | Via service | — | — | — | Success (booking) | ✅ | — |
| Customer Accept Quote | `POST /api/v1/customer/service-bookings/{id}/accept-quote` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| Customer Pay | `POST /api/v1/customer/service-bookings/{id}/payment` | POST | auth:api | Via service | — | — | `{payment_method}` | Success | ✅ | — |
| Customer Confirm | `POST /api/v1/customer/service-bookings/{id}/confirm` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| Customer Cancel | `POST /api/v1/customer/service-bookings/{id}/cancel` | POST | auth:api | Via service | — | — | `{reason}` | Success | ✅ | — |
| Customer Reschedule | `POST /api/v1/customer/service-bookings/{id}/reschedule` | POST | auth:api | Via service | — | — | `{new_date}` | Success | ✅ | — |
| Customer Review | `POST /api/v1/customer/service-bookings/{id}/review` | POST | auth:api | Via service | — | — | `{rating, comment}` | Success | ✅ | — |
| Vendor Profile | `GET /api/v1/vendor/service-bookings/profile` | GET | vendor.api | — | Via VendorApiClient | — | — | Success (profile) | ✅ | — |
| Vendor Update Profile | `PUT /api/v1/vendor/service-bookings/profile` | PUT | vendor.api | — | Via VendorApiClient | — | `{...}` | Success | ✅ | — |
| Vendor Services CRUD | `GET/POST/PUT/DELETE /api/v1/vendor/service-bookings/services` | CRUD | vendor.api | — | Via VendorApiClient | — | Various | Various | ✅ | — |
| Vendor Availability | `PUT /api/v1/vendor/service-bookings/availability` | PUT | vendor.api | — | Via VendorApiClient | — | `{schedule}` | Success | ✅ | — |
| Vendor Bookings | `GET /api/v1/vendor/service-bookings/bookings` | GET | vendor.api | — | Via VendorApiClient | — | — | List (bookings) | ✅ | — |
| Vendor View Booking | `GET /api/v1/vendor/service-bookings/bookings/{id}` | GET | vendor.api | — | Via VendorApiClient | — | — | Success (booking) | ✅ | — |
| Vendor Quote | `POST /api/v1/vendor/service-bookings/bookings/{id}/quote` | POST | vendor.api | — | Via VendorApiClient | — | `{amount, items[], notes}` | Success | ✅ | — |
| Vendor Status Transition | `POST /api/v1/vendor/service-bookings/bookings/{id}/status` | POST | vendor.api | — | Via VendorApiClient | — | `{status}` | Success | ✅ | — |
| Vendor Earnings | `GET /api/v1/vendor/service-bookings/earnings` | GET | vendor.api | — | Via VendorApiClient | — | — | Success (earnings) | ✅ | — |
| Admin Providers | `GET /api/v1/admin/service-bookings/providers` | GET | auth:admin | — | — | — | — | List | ✅ | — |
| Admin Provider Status | `PUT /api/v1/admin/service-bookings/providers/{id}/status` | PUT | auth:admin | — | — | — | `{status}` | Success | ✅ | — |
| Admin Bookings | `GET /api/v1/admin/service-bookings/bookings` | GET | auth:admin | — | — | — | — | List | ✅ | — |
| Admin Earnings | `GET /api/v1/admin/service-bookings/earnings` | GET | auth:admin | — | — | — | — | Success | ✅ | — |
| Admin Audit | `GET /api/v1/admin/service-bookings/audit` | GET | auth:admin | — | — | — | — | List (audits) | ✅ | — |

---

## 13. URBAN GOODZ PLATFORM FEATURES

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| App Config | `GET /api/v1/urban-goodz/app-config` | GET | auth:api | Via service | Via VendorApiClient | Via api client | — | Success (config) | ✅ | — |
| **Discovery** | | | | | | | | | | |
| Search Capture | `POST /api/v1/urban-goodz/discovery/search-capture` | POST | auth:api | `ugDiscoverySearchCaptureUri` | — | — | `{query, context}` | Success | ✅ | — |
| Discovery Entities | `GET /api/v1/urban-goodz/discovery/entities` | GET | auth:api | `ugDiscoveryEntitiesUri` | — | — | — | List (entities) | ✅ | — |
| Entity Detail | `GET /api/v1/urban-goodz/discovery/entities/{id}` | GET | auth:api | Via service | — | — | — | Success (entity) | ✅ | — |
| Entity Action | `POST /api/v1/urban-goodz/discovery/entities/{id}/action` | POST | auth:api | Via service | — | — | `{action_type}` | Success | ✅ | — |
| Opportunities | `GET /api/v1/urban-goodz/discovery/opportunities` | GET | auth:api | `ugDiscoveryOpportunitiesUri` | — | — | — | List | ✅ | — |
| Accept Opportunity | `POST /api/v1/urban-goodz/discovery/opportunities/{id}/accept` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| **Earn Money** | | | | | | | | | | |
| Opportunities | `GET /api/v1/urban-goodz/earn-money/opportunities` | GET | auth:api | `ugEarnMoneyOpportunitiesUri` | — | — | — | List | ✅ | — |
| Opportunity Detail | `GET /api/v1/urban-goodz/earn-money/opportunities/{id}` | GET | auth:api | Via service | — | — | — | Success | ✅ | — |
| Accept | `POST /api/v1/urban-goodz/earn-money/opportunities/{id}/accept` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| **Logistics** | | | | | | | | | | |
| Jobs | `GET /api/v1/urban-goodz/logistics/jobs` | GET | auth:api | `ugLogisticsJobsUri` | — | — | — | List | ✅ | — |
| Job Detail | `GET /api/v1/urban-goodz/logistics/jobs/{id}` | GET | auth:api | Via service | — | — | — | Success | ✅ | — |
| Accept Job | `POST /api/v1/urban-goodz/logistics/jobs/{id}/accept` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| Update Status | `POST /api/v1/urban-goodz/logistics/jobs/{id}/status` | POST | auth:api | Via service | — | — | `{status}` | Success | ✅ | — |
| **Load Board** | | | | | | | | | | |
| Loads | `GET /api/v1/urban-goodz/load-board/loads` | GET | auth:api | `ugLoadBoardLoadsUri` | — | — | — | List (loads) | ✅ | — |
| Load Detail | `GET /api/v1/urban-goodz/load-board/loads/{id}` | GET | auth:api | Via service | — | — | — | Success (load) | ✅ | — |
| Accept Load | `POST /api/v1/urban-goodz/load-board/loads/{id}/accept` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| Update Status | `POST /api/v1/urban-goodz/load-board/loads/{id}/status` | POST | auth:api | Via service | — | — | `{status}` | Success | ✅ | — |
| **Medical Courier** | | | | | | | | | | |
| Jobs | `GET /api/v1/urban-goodz/medical-courier/jobs` | GET | auth:api | `ugMedicalCourierJobsUri` | — | — | — | List | ✅ | — |
| Job Detail | `GET /api/v1/urban-goodz/medical-courier/jobs/{id}` | GET | auth:api | Via service | — | — | — | Success | ✅ | — |
| Accept | `POST /api/v1/urban-goodz/medical-courier/jobs/{id}/accept` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| Status | `POST /api/v1/urban-goodz/medical-courier/jobs/{id}/status` | POST | auth:api | Via service | — | — | `{status}` | Success | ✅ | — |
| Custody | `POST /api/v1/urban-goodz/medical-courier/jobs/{id}/custody` | POST | auth:api | Via service | — | — | `{custody_data}` | Success | ✅ | — |
| **Book Anything** | | | | | | | | | | |
| Records | `GET /api/v1/urban-goodz/book-anything/records` | GET | auth:api | `ugBookAnythingRecordsUri` | — | — | — | List | ✅ | — |
| Record Detail | `GET /api/v1/urban-goodz/book-anything/records/{id}` | GET | auth:api | Via service | — | — | — | Success | ✅ | — |
| Submit Request | `POST /api/v1/urban-goodz/book-anything/request` | POST | auth:api | `ugBookAnythingRequestUri` | — | — | `{description, ...}` | Success | ✅ | — |
| **Events** | | | | | | | | | | |
| List Events | `GET /api/v1/urban-goodz/events` | GET | auth:api | `ugEventsUri` | — | — | — | List (events) | ✅ | — |
| Event Detail | `GET /api/v1/urban-goodz/events/{id}` | GET | auth:api | Via service | — | — | — | Success (event) | ✅ | — |
| Express Interest | `POST /api/v1/urban-goodz/events/{id}/interest` | POST | auth:api | Via service | — | — | — | Success | ✅ | — |
| Vendor Opportunity | `POST /api/v1/urban-goodz/events/{id}/vendor-opportunity` | POST | auth:api | Via service | — | — | `{proposal}` | Success | ✅ | — |
| Creator Opportunity | `POST /api/v1/urban-goodz/events/{id}/creator-opportunity` | POST | auth:api | Via service | — | — | `{proposal}` | Success | ✅ | — |
| Logistics Support | `POST /api/v1/urban-goodz/events/{id}/logistics-support` | POST | auth:api | Via service | — | — | `{requirements}` | Success | ✅ | — |
| **AI Concierge** | | | | | | | | | | |
| Query | `POST /api/v1/urban-goodz/ai-concierge/query` | POST | auth:api | `ugAiConciergeUri` | — | — | `{message, context}` | Success (response) | ✅ | — |
| Chat | `POST /api/v1/urban-goodz/ai-concierge/chat` | POST | auth:api | Via service | — | — | `{message, session_id}` | Success | ✅ | — |
| History | `GET /api/v1/urban-goodz/ai-concierge/history` | GET | auth:api | Via service | — | — | — | List (sessions) | ✅ | — |
| **Reels** | | | | | | | | | | |
| Customer Reels List | `GET /api/v1/customer/reels/list` | GET | auth:api | `reelListUri` | — | — | — | List (reels) | ✅ | — |
| Reel Details | `GET /api/v1/customer/reels/details` | GET | auth:api | `reelDetailsUri` | — | — | `?reel_id=` | Success (reel) | ✅ | — |
| Reel Stats | `GET /api/v1/customer/reels/stats` | GET | auth:api | `reelStatsUri` | — | — | `?reel_id=` | Success (stats) | ✅ | — |
| Reel Like | `GET /api/v1/customer/reels/like` | GET | auth:api | `reelLikeUri` | — | — | `?reel_id=` | Success | ✅ | — |
| Reel Visit | `GET /api/v1/customer/reels/visit` | GET | auth:api | `reelVisitUri` | — | — | `?reel_id=` | Success | ✅ | — |
| UG Reels Action | `POST /api/v1/urban-goodz/reels/action` | POST | auth:api | `ugReelsActionUri` | — | — | `{action, reel_id}` | Success | ✅ | — |
| UG Reels Conversion | `POST /api/v1/urban-goodz/reels/conversion` | POST | auth:api | `ugReelsConversionUri` | — | — | `{reel_id, conversion_type}` | Success | ✅ | — |
| **Creator Commerce** | | | | | | | | | | |
| Applications | `POST /api/v1/urban-goodz/creator-commerce/applications` | POST | auth:api | `creatorCommerceApplicationsUri` | — | — | `{...}` | Success | ✅ | — |
| Customer Applications | `GET /api/v1/urban-goodz/creator-commerce/customer/applications` | GET | auth:api | `creatorCommerceCustomerApplicationsUri` | — | — | — | List | ✅ | — |
| Featured Reels | `GET /api/v1/urban-goodz/creator-commerce/featured-reels` | GET | auth:api | `ugCreatorCommerceFeaturedReelsUri` | — | — | — | List | ✅ | — |
| **Identity & Trust** | | | | | | | | | | |
| Identity Profiles | `GET /api/v1/urban-goodz/identity/profiles` | GET | auth:api | `ugIdentityProfilesUri` | — | — | — | List | ✅ | — |
| Fit Profile | `GET /api/v1/urban-goodz/identity/fit-profile` | GET | auth:api | `ugFitProfileUri` | — | — | — | Success | ✅ | — |
| Identity Grants | `GET /api/v1/urban-goodz/identity/grants` | GET | auth:api | `ugIdentityGrantsUri` | — | — | — | List | ✅ | — |
| Claims | `GET /api/v1/urban-goodz/identity/claims` | GET | auth:api | `ugClaimsUri` | — | — | — | List | ✅ | — |
| Trust Assets | `GET /api/v1/urban-goodz/trust/assets` | GET | auth:api | `ugTrustAssetsUri` | — | — | — | List | ✅ | — |
| **Revenue** | | | | | | | | | | |
| Attributions | `GET /api/v1/urban-goodz/revenue/attributions` | GET | auth:api | `ugRevenueAttributionsUri` | — | — | — | List | ✅ | — |
| Record Revenue | `POST /api/v1/urban-goodz/revenue/record` | POST | auth:api | `ugRevenueRecordUri` | — | — | `{amount, source}` | Success | ✅ | — |
| Analytics | `GET /api/v1/urban-goodz/revenue/analytics` | GET | auth:api | `ugRevenueAnalyticsUri` | — | — | — | Success (analytics) | ✅ | — |
| ROS | `GET /api/v1/urban-goodz/revenue/ros` | GET | auth:api | `ugRevenueRosUri` | — | — | — | Success | ✅ | — |
| UGES | `GET /api/v1/urban-goodz/revenue/uges` | GET | auth:api | `ugRevenueUgesUri` | — | — | — | Success | ✅ | — |
| **File Upload** | | | | | | | | | | |
| Upload File | `POST /api/v1/urban-goodz/files/upload/{category}` | POST | auth:api | Via service | Via VendorApiClient | Via api client | `{file: multipart}` | Success (url) | ✅ | — |
| Fashion Fit Photo Upload | `POST /api/v1/urban-goodz/fashion-fit/photos/upload` | POST | auth:api | Via service | — | — | `{photo: multipart}` | Success (url) | ✅ | — |

---

## 14. VENDOR BUSINESS SETTINGS

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Update Basic Info | `PUT /api/v1/vendor/update-basic-info` | PUT | vendor.api | — | `VendorApiClient.put(...)` | — | `{name, phone, ...}` | Success | ✅ | — |
| Update Business Setup | `PUT /api/v1/vendor/update-business-setup` | PUT | vendor.api | — | `VendorApiClient.put(...)` | — | `{min_order, delivery_time, ...}` | Success | ✅ | — |
| Add Schedule | `POST /api/v1/vendor/schedule/store` | POST | vendor.api | — | `VendorApiClient.post(...)` | — | `{day, start, end}` | Success | ✅ | — |
| Delete Schedule | `DELETE /api/v1/vendor/schedule/{id}` | DELETE | vendor.api | — | `VendorApiClient.delete(...)` | — | — | Success | ✅ | — |
| Attributes | `GET /api/v1/vendor/attributes` | GET | vendor.api | — | `VendorApiClient.get(...)` | — | — | List (attributes) | ✅ | — |
| Units | `GET /api/v1/vendor/unit` | GET | vendor.api | — | `VendorApiClient.get(...)` | — | — | List (units) | ✅ | — |
| Update Bank Info | `PUT /api/v1/vendor/update-bank-info` | PUT | vendor.api | — | `VendorApiClient.put(...)` | — | `{bank_name, account_no, ...}` | Success | ✅ | — |
| Vendor Remove Account | `DELETE /api/v1/vendor/remove-account` | DELETE | vendor.api | — | `VendorApiClient.delete(...)` | — | — | Success | ✅ | — |

---

## 15. SUBSCRIPTION

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Package View | `GET /api/v1/vendor/package-view` | GET | None | `storePackagesUri` | Via VendorApiClient | — | — | List (packages) | ✅ | — |
| Business Plan | `POST /api/v1/vendor/business_plan` | POST | auth:api | `businessPlanUri` | Via VendorApiClient | — | `{package_id}` | Success | ✅ | — |
| Subscription Payment | `POST /api/v1/vendor/subscription/payment/api` | POST | auth:api | `businessPlanPaymentUri` | Via VendorApiClient | — | `{payment_method, ...}` | Success | ✅ | — |
| Check Product Limits | `GET /api/v1/vendor/check-product-limits` | GET | vendor.api | — | Via VendorApiClient | — | — | Success (limits) | ✅ | — |
| Subscription Transaction | `GET /api/v1/vendor/subscription-transaction` | GET | vendor.api | — | `VendorApiClient.get(...)` | — | — | List | ✅ | — |

---

## 16. MESSAGING

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Conversation List | `GET /api/v1/customer/message/list` | GET | auth:api | `conversationListUri` | — | — | — | List (conversations) | ✅ | — |
| Search Conversations | `GET /api/v1/customer/message/search-list` | GET | auth:api | `searchConversationListUri` | — | — | `?search=` | List | ✅ | — |
| Message Details | `GET /api/v1/customer/message/details` | GET | auth:api | `messageListUri` | — | — | `?conversation_id=` | List (messages) | ✅ | — |
| Send Message | `POST /api/v1/customer/message/send` | POST | auth:api | `sendMessageUri` | — | — | `{conversation_id, message}` | Success | ✅ | — |
| Vendor Conversation List | `GET /api/v1/seller/message/list` | GET | vendor.api | — | `VendorApiClient.get(...)` | — | — | List | ✅ | — |
| Vendor Send Message | `POST /api/v1/seller/message/send` | POST | vendor.api | — | `VendorApiClient.post(...)` | — | `{conversation_id, message}` | Success | ✅ | — |
| DM Conversation List | `GET /api/v1/delivery-man/message/list` | GET | dm.api | — | — | Via api client | — | List | ✅ | — |
| DM Send Message | `POST /api/v1/delivery-man/message/send` | POST | dm.api | — | — | Via api client | `{conversation_id, message}` | Success | ✅ | — |

---

## 17. DRIVER AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Route Optimization | `GET /api/v1/ai/route-optimization` | GET | dm.api | — | — | Via api client | `?route_id=` | Success (optimized) | ✅ | — |
| Earnings Comparison | `GET /api/v1/ai/earnings-comparison` | GET | dm.api | — | — | Via api client | — | Success (comparison) | ✅ | — |
| Load Recommendations | `GET /api/v1/ai/load-recommendations` | GET | dm.api | — | — | Via api client | — | List (loads) | ✅ | — |
| Verify Pickup | `POST /api/v1/ai/verify-pickup` | POST | dm.api | — | — | Via api client | `{package_id, image}` | Success | ✅ | — |
| Verify Delivery | `POST /api/v1/ai/verify-delivery` | POST | dm.api | — | — | Via api client | `{package_id, image}` | Success | ✅ | — |
| Handle Exception | `POST /api/v1/ai/exception` | POST | dm.api | — | — | Via api client | `{job_id, reason, details}` | Success | ✅ | — |
| Warnings | `GET /api/v1/ai/warnings` | GET | dm.api | — | — | Via api client | — | List (warnings) | ✅ | — |
| Earnings Per Hour | `GET /api/v1/ai/earnings-per-hour` | GET | dm.api | — | — | Via api client | — | Success (rate) | ✅ | — |

---

## 18. DISPATCHER AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Load Ranking | `POST /api/v1/urban-goodz/dispatcher/ai/load-ranking` | POST | auth:admin | — | — | — | `{filters}` | Success (ranked) | ✅ | — |
| Driver Match | `POST /api/v1/urban-goodz/dispatcher/ai/driver-match` | POST | auth:admin | — | — | — | `{load_id}` | Success (drivers) | ✅ | — |
| Rate Estimate | `POST /api/v1/urban-goodz/dispatcher/ai/rate-estimate` | POST | auth:admin | — | — | — | `{origin, destination, weight}` | Success (rate) | ✅ | — |
| Duplicate Check | `POST /api/v1/urban-goodz/dispatcher/ai/duplicate-check` | POST | auth:admin | — | — | — | `{load_data}` | Success (duplicates) | ✅ | — |
| Ops Summary | `GET /api/v1/urban-goodz/dispatcher/ai/ops-summary` | GET | auth:admin | — | — | — | — | Success (summary) | ✅ | — |
| Parse Load | `POST /api/v1/urban-goodz/dispatcher/ai/parse-load` | POST | auth:admin | — | — | — | `{raw_text}` | Success (parsed) | ✅ | — |
| Parse Email | `POST /api/v1/urban-goodz/dispatcher/ai/parse-email` | POST | auth:admin | — | — | — | `{email_content}` | Success (parsed) | ✅ | — |
| Parse Batch | `POST /api/v1/urban-goodz/dispatcher/ai/parse-batch` | POST | auth:admin | — | — | — | `{emails[]}` | Success (parsed[]) | ✅ | — |
| Source Status | `GET /api/v1/urban-goodz/dispatcher/ai/source-status` | GET | auth:admin | — | — | — | — | Success (status) | ✅ | — |
| Sync Source | `POST /api/v1/urban-goodz/dispatcher/ai/sync-source` | POST | auth:admin | — | — | — | `{source_id}` | Success | ✅ | — |

---

## 19. FASHION FIT MEASUREMENTS (LEGACY)

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Customer Profile | `GET /api/v1/urban-goodz/fashion/measurements/profile` | GET | auth:api | Via service | — | — | — | Success (profile) | ✅ | — |
| Save Profile | `POST /api/v1/urban-goodz/fashion/measurements/profile` | POST | auth:api | Via service | — | — | `{measurements}` | Success | ✅ | — |
| Create Request | `POST /api/v1/urban-goodz/fashion/measurements/request` | POST | auth:api | Via service | — | — | `{description}` | Success | ✅ | — |
| Upload Photos | `POST /api/v1/urban-goodz/fashion/measurements/photos` | POST | auth:api | Via service | — | — | `{photos[]}` | Success | ✅ | — |
| View Request | `GET /api/v1/urban-goodz/fashion/measurements/{id}` | GET | auth:api | Via service | — | — | — | Success | ✅ | — |
| Vendor List | `GET /api/v1/vendor/urban-goodz/fashion/measurements` | GET | vendor.api | — | Via VendorApiClient | — | — | List | ✅ | — |
| Vendor View | `GET /api/v1/vendor/urban-goodz/fashion/measurements/{id}` | GET | vendor.api | — | Via VendorApiClient | — | — | Success | ✅ | — |
| Vendor Review | `POST /api/v1/vendor/urban-goodz/fashion/measurements/{id}/review` | POST | vendor.api | — | Via VendorApiClient | — | `{review, measurements}` | Success | ✅ | — |
| Vendor Settings | `POST /api/v1/vendor/urban-goodz/fashion/measurement-settings` | POST | vendor.api | — | Via VendorApiClient | — | `{settings}` | Success | ✅ | — |
| Admin List | `GET /api/v1/admin/urban-goodz/fashion/measurements` | GET | auth:admin | — | — | — | — | List | ✅ | — |
| Admin View | `GET /api/v1/admin/urban-goodz/fashion/measurements/{id}` | GET | auth:admin | — | — | — | — | Success | ✅ | — |
| Admin Settings | `POST /api/v1/admin/urban-goodz/fashion/measurement-settings` | POST | auth:admin | — | — | — | `{settings}` | Success | ✅ | — |
| Admin Privacy | `POST /api/v1/admin/urban-goodz/fashion/measurements/{id}/privacy-status` | POST | auth:admin | — | — | — | `{status}` | Success | ✅ | — |
| Admin Status | `POST /api/v1/admin/urban-goodz/fashion/measurements/{id}/status` | POST | auth:admin | — | — | — | `{status}` | Success | ✅ | — |

---

## 20. CROSS-APP AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Customer Query | `POST /api/v1/urban-goodz/cross-app/ai/customer/query` | POST | auth:api | Via service | — | — | `{message}` | Success | ✅ | — |
| Customer History | `GET /api/v1/urban-goodz/cross-app/ai/customer/history` | GET | auth:api | Via service | — | — | — | List | ✅ | — |
| Fashion Fit Measurements | `POST /api/v1/urban-goodz/cross-app/ai/customer/fashion-fit/measurements` | POST | auth:api | Via service | — | — | `{photos}` | Success | ✅ | — |
| Order Anywhere | `POST /api/v1/urban-goodz/cross-app/ai/customer/order-anywhere` | POST | auth:api | Via service | — | — | `{description}` | Success | ✅ | — |
| Smart Reorder | `POST /api/v1/urban-goodz/cross-app/ai/customer/smart-reorder` | POST | auth:api | Via service | — | — | `{order_id}` | Success | ✅ | — |
| Delivery ETA | `POST /api/v1/urban-goodz/cross-app/ai/customer/delivery-eta` | POST | auth:api | Via service | — | — | `{order_id}` | Success | ✅ | — |
| Vendor Daily Brief | `GET /api/v1/urban-goodz/cross-app/ai/vendor/daily-brief` | GET | auth:api | — | Via VendorApiClient | — | — | Success | ✅ | — |
| Vendor Order Summary | `POST /api/v1/urban-goodz/cross-app/ai/vendor/order-summary` | POST | auth:api | — | Via VendorApiClient | — | `{date}` | Success | ✅ | — |
| Vendor Alerts | `GET /api/v1/urban-goodz/cross-app/ai/vendor/alerts` | GET | auth:api | — | Via VendorApiClient | — | — | List | ✅ | — |
| Vendor Performance | `GET /api/v1/urban-goodz/cross-app/ai/vendor/performance` | GET | auth:api | — | Via VendorApiClient | — | — | Success | ✅ | — |
| Vendor Pricing | `GET /api/v1/urban-goodz/cross-app/ai/vendor/pricing` | GET | auth:api | — | Via VendorApiClient | — | — | Success | ✅ | — |
| Vendor Promotions | `GET /api/v1/urban-goodz/cross-app/ai/vendor/promotions` | GET | auth:api | — | Via VendorApiClient | — | — | List | ✅ | — |
| Vendor Prep Time | `POST /api/v1/urban-goodz/cross-app/ai/vendor/prep-time` | POST | auth:api | — | Via VendorApiClient | — | `{order_id}` | Success | ✅ | — |
| Driver Daily Summary | `GET /api/v1/urban-goodz/cross-app/ai/driver/daily-summary` | GET | auth:api | — | — | Via api client | — | Success | ✅ | — |
| Driver Route Optimization | `POST /api/v1/urban-goodz/cross-app/ai/driver/route-optimization` | POST | auth:api | — | — | Via api client | `{route_id}` | Success | ✅ | — |
| Driver Verify Package | `POST /api/v1/urban-goodz/cross-app/ai/driver/verify-package` | POST | auth:api | — | — | Via api client | `{package_id, image}` | Success | ✅ | — |
| Driver Verify Delivery | `POST /api/v1/urban-goodz/cross-app/ai/driver/verify-delivery` | POST | auth:api | — | — | Via api client | `{package_id, image}` | Success | ✅ | — |
| Business Import Manifest | `POST /api/v1/urban-goodz/cross-app/ai/business/manifest/import` | POST | auth:api | — | — | — | `{file}` | Success | ✅ | — |
| Business Group Packages | `POST /api/v1/urban-goodz/cross-app/ai/business/packages/group` | POST | auth:api | — | — | — | `{package_ids[]}` | Success | ✅ | — |
| Business Create Route | `POST /api/v1/urban-goodz/cross-app/ai/business/route/create` | POST | auth:api | — | — | — | `{packages[]}` | Success | ✅ | — |
| Business Optimize Route | `POST /api/v1/urban-goodz/cross-app/ai/business/route/optimize` | POST | auth:api | — | — | — | `{route_id}` | Success | ✅ | — |
| Business Match Driver | `POST /api/v1/urban-goodz/cross-app/ai/business/driver/match` | POST | auth:api | — | — | — | `{route_id}` | Success | ✅ | — |
| Business Predict | `POST /api/v1/urban-goodz/cross-app/ai/business/route/predict` | POST | auth:api | — | — | — | `{route_id}` | Success | ✅ | — |
| Business Risk | `POST /api/v1/urban-goodz/cross-app/ai/business/route/risk` | POST | auth:api | — | — | — | `{route_id}` | Success | ✅ | — |
| Business Performance | `GET /api/v1/urban-goodz/cross-app/ai/business/performance` | GET | auth:api | — | — | — | — | Success | ✅ | — |
| Business Cost Anomaly | `GET /api/v1/urban-goodz/cross-app/ai/business/cost-anomaly` | GET | auth:api | — | — | — | — | List | ✅ | — |
| Business Invoice Support | `POST /api/v1/urban-goodz/cross-app/ai/business/invoice-support` | POST | auth:api | — | — | — | `{invoice_id}` | Success | ✅ | — |
| Business Delivery Proof | `POST /api/v1/urban-goodz/cross-app/ai/business/delivery-proof` | POST | auth:api | — | — | — | `{job_id}` | Success | ✅ | — |
| Dispatcher Load Ranking | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/load-ranking` | POST | auth:api | — | — | — | `{filters}` | Success | ✅ | — |
| Dispatcher Driver Match | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/driver-match` | POST | auth:api | — | — | — | `{load_id}` | Success | ✅ | — |
| Dispatcher Rate Estimate | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/rate-estimate` | POST | auth:api | — | — | — | `{origin, dest}` | Success | ✅ | — |
| Dispatcher Duplicate Check | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/duplicate-check` | POST | auth:api | — | — | — | `{load_data}` | Success | ✅ | — |
| Dispatcher Ops Summary | `GET /api/v1/urban-goodz/cross-app/ai/dispatcher/ops-summary` | GET | auth:api | — | — | — | — | Success | ✅ | — |
| Dispatcher Parse Load | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/parse-load` | POST | auth:api | — | — | — | `{raw_text}` | Success | ✅ | — |
| Dispatcher Parse Email | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/parse-email` | POST | auth:api | — | — | — | `{email}` | Success | ✅ | — |
| Dispatcher Parse Batch | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/parse-batch` | POST | auth:api | — | — | — | `{emails[]}` | Success | ✅ | — |
| Dispatcher Source Status | `GET /api/v1/urban-goodz/cross-app/ai/dispatcher/source-status` | GET | auth:api | — | — | — | — | Success | ✅ | — |
| Dispatcher Sync Source | `POST /api/v1/urban-goodz/cross-app/ai/dispatcher/sync-source` | POST | auth:api | — | — | — | `{source_id}` | Success | ✅ | — |

---

## 21. RENTAL AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Search Assets | `POST /api/v1/urban-goodz/rentals/ai/search` | POST | auth:api | Via service | — | — | `{query}` | Success | ✅ | — |
| Match Assets | `POST /api/v1/urban-goodz/rentals/ai/match` | POST | auth:api | Via service | — | — | `{requirements}` | Success | ✅ | — |
| Check Availability | `POST /api/v1/urban-goodz/rentals/ai/availability` | POST | auth:api | Via service | — | — | `{asset_id, dates}` | Success | ✅ | — |
| Get Quote | `POST /api/v1/urban-goodz/rentals/ai/quote` | POST | auth:api | Via service | — | — | `{asset_id, dates}` | Success | ✅ | — |
| Extend Rental | `POST /api/v1/urban-goodz/rentals/ai/extension` | POST | auth:api | Via service | — | — | `{rental_id, new_end_date}` | Success | ✅ | — |
| Late Return | `POST /api/v1/urban-goodz/rentals/ai/late-return` | POST | auth:api | Via service | — | — | `{rental_id}` | Success | ✅ | — |
| Damage Report | `POST /api/v1/urban-goodz/rentals/ai/damage-report` | POST | auth:api | Via service | — | — | `{rental_id, details}` | Success | ✅ | — |
| Return Inspection | `POST /api/v1/urban-goodz/rentals/ai/return-inspection` | POST | auth:api | Via service | — | — | `{rental_id, condition}` | Success | ✅ | — |

---

## 22. SUPPORT AI & FRAUD AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Classify Issue | `POST /api/v1/urban-goodz/support/ai/classify` | POST | auth:api | Via service | — | — | `{description}` | Success | ✅ | — |
| Auto Resolve | `POST /api/v1/urban-goodz/support/ai/auto-resolve` | POST | auth:api | Via service | — | — | `{issue_id}` | Success | ✅ | — |
| Escalate | `POST /api/v1/urban-goodz/support/ai/escalate` | POST | auth:api | Via service | — | — | `{issue_id}` | Success | ✅ | — |
| Knowledge Base | `GET /api/v1/urban-goodz/support/ai/knowledge-base` | GET | auth:api | Via service | — | — | `?query=` | Success | ✅ | — |
| Feedback | `POST /api/v1/urban-goodz/support/ai/feedback` | POST | auth:api | Via service | — | — | `{issue_id, rating}` | Success | ✅ | — |
| Scan Transaction | `POST /api/v1/urban-goodz/fraud/ai/scan-transaction` | POST | auth:admin | — | — | — | `{transaction_id}` | Success | ✅ | — |
| Scan Account | `POST /api/v1/urban-goodz/fraud/ai/scan-account` | POST | auth:admin | — | — | — | `{account_id}` | Success | ✅ | — |
| Fraud Flags | `GET /api/v1/urban-goodz/fraud/ai/flags` | GET | auth:admin | — | — | — | — | List | ✅ | — |
| Review Flag | `POST /api/v1/urban-goodz/fraud/ai/review` | POST | auth:admin | — | — | — | `{flag_id, decision}` | Success | ✅ | — |
| Risk Score | `GET /api/v1/urban-goodz/fraud/ai/risk-score/{type}/{id}` | GET | auth:admin | — | — | — | — | Success (score) | ✅ | — |
| Fraud Dashboard | `GET /api/v1/urban-goodz/fraud/ai/dashboard` | GET | auth:admin | — | — | — | — | Success (dashboard) | ✅ | — |

---

## 23. ETA PREDICTION

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Predict ETA | `POST /api/v1/urban-goodz/eta/ai/predict` | POST | auth:api | Via service | — | — | `{origin, dest}` | Success (eta) | ✅ | — |
| Batch Predict | `POST /api/v1/urban-goodz/eta/ai/batch-predict` | POST | auth:api | Via service | — | — | `{routes[]}` | Success (etas) | ✅ | — |
| Driver ETA | `GET /api/v1/urban-goodz/eta/ai/driver/{id}` | GET | auth:api | Via service | — | — | — | Success (eta) | ✅ | — |
| Order ETA | `GET /api/v1/urban-goodz/eta/ai/order/{id}` | GET | auth:api | Via service | — | — | — | Success (eta) | ✅ | — |

---

## 24. DYNAMIC PRICING

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Recommend Prices | `POST /api/v1/urban-goodz/pricing/ai/recommend` | POST | vendor.api | — | Via VendorApiClient | — | `{item_ids[]}` | Success | ✅ | — |
| Simulate Change | `POST /api/v1/urban-goodz/pricing/ai/simulate` | POST | vendor.api | — | Via VendorApiClient | — | `{item_id, new_price}` | Success | ✅ | — |
| Price History | `GET /api/v1/urban-goodz/pricing/ai/history` | GET | vendor.api | — | Via VendorApiClient | — | `?item_id=` | Success | ✅ | — |
| Rollback | `POST /api/v1/urban-goodz/pricing/ai/rollback` | POST | vendor.api | — | Via VendorApiClient | — | `{item_id}` | Success | ✅ | — |

---

## 25. BOOK SERVICES AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Get Providers | `GET /api/v1/customer/service-bookings/ai/providers` | GET | auth:api | Via service | — | — | — | List | ✅ | — |
| Match Providers | `POST /api/v1/customer/service-bookings/ai/match` | POST | auth:api | Via service | — | — | `{requirements}` | Success | ✅ | — |
| Compare Quotes | `POST /api/v1/customer/service-bookings/ai/compare-quotes` | POST | auth:api | Via service | — | — | `{booking_ids[]}` | Success | ✅ | — |
| Reminders | `GET /api/v1/customer/service-bookings/ai/reminders` | GET | auth:api | Via service | — | — | — | List | ✅ | — |
| Verify Completion | `POST /api/v1/customer/service-bookings/ai/verify` | POST | auth:api | Via service | — | — | `{booking_id}` | Success | ✅ | — |
| Find Replacement | `POST /api/v1/customer/service-bookings/ai/replacement` | POST | auth:api | Via service | — | — | `{booking_id}` | Success | ✅ | — |
| Vendor Prep Time | `POST /api/v1/vendor/service-bookings/ai/prep-time` | POST | vendor.api | — | Via VendorApiClient | — | `{service_id}` | Success | ✅ | — |
| Vendor Alerts | `GET /api/v1/vendor/service-bookings/ai/alerts` | GET | vendor.api | — | Via VendorApiClient | — | — | List | ✅ | — |
| Vendor Performance | `GET /api/v1/vendor/service-bookings/ai/performance` | GET | vendor.api | — | Via VendorApiClient | — | — | Success | ✅ | — |
| Vendor Promotions | `GET /api/v1/vendor/service-bookings/ai/promotions` | GET | vendor.api | — | Via VendorApiClient | — | — | List | ✅ | — |
| Vendor Daily Brief | `GET /api/v1/vendor/service-bookings/ai/daily-brief` | GET | vendor.api | — | Via VendorApiClient | — | — | Success | ✅ | — |

---

## 26. FASHION FIT AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Extract Measurements | `POST /api/v1/fashion-fit/ai/extract-measurements` | POST | auth:api | Via service | — | — | `{photos[]}` | Success | ✅ | — |
| Match Size | `POST /api/v1/fashion-fit/ai/match-size` | POST | auth:api | Via service | — | — | `{measurements, brand}` | Success | ✅ | — |
| Suggest Adjustments | `POST /api/v1/fashion-fit/ai/suggest-adjustments` | POST | auth:api | Via service | — | — | `{measurements, garment}` | Success | ✅ | — |
| Size Profile | `POST /api/v1/fashion-fit/ai/size-profile` | POST | auth:api | Via service | — | — | `{profile_uuid}` | Success | ✅ | — |
| Match Providers | `GET /api/v1/fashion-fit/ai/providers` | GET | auth:api | Via service | — | — | — | List | ✅ | — |
| Request Quote | `POST /api/v1/fashion-fit/ai/quote-request` | POST | auth:api | Via service | — | — | `{profile_uuid, description}` | Success | ✅ | — |
| Get Requests | `GET /api/v1/fashion-fit/ai/requests` | GET | auth:api | Via service | — | — | — | List | ✅ | — |
| Update Measurements | `PUT /api/v1/fashion-fit/ai/measurements` | PUT | auth:api | Via service | — | — | `{profile_uuid, measurements}` | Success | ✅ | — |

---

## 27. CREATOR AI

| FEATURE | BACKEND ROUTE | METHOD | AUTH ROLE | CUSTOMER CALLER | VENDOR CALLER | DRIVER CALLER | REQUEST | RESPONSE | STATUS | BLOCKER |
|---|---|---|---|---|---|---|---|---|---|---|
| Generate Reel Script | `POST /api/v1/urban-goodz/creator/ai/reel-script` | POST | auth:api | Via service | — | — | `{topic, style}` | Success | ✅ | — |
| Generate Product Tags | `POST /api/v1/urban-goodz/creator/ai/product-tags` | POST | auth:api | Via service | — | — | `{product_id}` | Success | ✅ | — |
| Generate Caption | `POST /api/v1/urban-goodz/creator/ai/caption` | POST | auth:api | Via service | — | — | `{reel_id, style}` | Success | ✅ | — |
| Analyze Performance | `POST /api/v1/urban-goodz/creator/ai/performance` | POST | auth:api | Via service | — | — | `{reel_id}` | Success | ✅ | — |
| Match Brand | `GET /api/v1/urban-goodz/creator/ai/brand-matches` | GET | auth:api | Via service | — | — | — | List | ✅ | — |
| Reel Analytics | `POST /api/v1/urban-goodz/creator/ai/reel-analytics` | POST | auth:api | Via service | — | — | `{reel_id}` | Success | ✅ | — |

---

## ROUTE COUNT SUMMARY

| Route File | File Path | Approximate Route Count |
|---|---|---|
| api.php (core) | `routes/api/v1/api.php` | ~180 routes |
| urban_goodz.php | `routes/api/v1/urban_goodz.php` | ~130 routes |
| fashion_fit.php | `routes/api/v1/fashion_fit.php` | ~30 routes |
| service_bookings.php | `routes/api/v1/service_bookings.php` | ~25 routes |
| urban_goodz_measurements.php | `routes/api/urban_goodz_measurements.php` | ~12 routes |
| **Total Backend API Routes** | | **~377 routes** |
