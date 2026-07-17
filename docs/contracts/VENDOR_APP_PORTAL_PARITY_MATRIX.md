# VENDOR APP / PORTAL PARITY MATRIX

**Version:** 3.9  
**Last Updated:** 2026-07-16  
**Purpose:** Feature-by-feature comparison between the Vendor Flutter App and the Admin Panel vendor management interface, identifying parity gaps.

---

## 1. FEATURE PARITY MATRIX

### Legend
- ✅ = Feature exists and is fully functional
- ⚠️ = Feature exists but is partial/incomplete
- ❌ = Feature does not exist
- 🔒 = Feature exists but requires admin intervention

| Feature Category | Vendor Flutter App | Admin Panel | Parity Status | Notes |
|---|---|---|---|---|
| **VENDOR PROFILE** | | | | |
| View Profile | ✅ `GET /api/v1/vendor/profile` | ✅ Admin vendor detail | ✅ | Both show full profile |
| Update Profile (name, image) | ✅ `PUT /api/v1/vendor/update-profile` | ✅ Admin edit | ✅ | App allows self-service |
| Update Basic Info (store details) | ✅ `PUT /api/v1/vendor/update-basic-info` | ✅ Admin edit | ✅ | — |
| Business Setup (min order, prep time) | ✅ `PUT /api/v1/vendor/update-business-setup` | ✅ Admin edit | ✅ | — |
| Update Bank Info | ✅ `PUT /api/v1/vendor/update-bank-info` | ✅ Admin edit | ✅ | — |
| Update Announcement | ✅ `PUT /api/v1/vendor/update-announcment` | ❌ | ⚠️ | Admin cannot update vendor announcement |
| Active/Inactive Toggle | ✅ `POST /api/v1/vendor/update-active-status` | ✅ Admin toggle | ✅ | — |
| Vendor Schedule (open/close hours) | ✅ `POST /api/v1/vendor/schedule/store` | ✅ Admin CRUD | ✅ | — |
| Delete Schedule | ✅ `DELETE /api/v1/vendor/schedule/{id}` | ✅ Admin delete | ✅ | — |
| Remove Account | ✅ `DELETE /api/v1/vendor/remove-account` | ✅ Admin deactivate | ✅ | App soft-deletes; Admin can deactivate |
| Vendor Unit List | ✅ `GET /api/v1/vendor/unit` | ✅ Admin unit management | ✅ | — |
| Attributes List | ✅ `GET /api/v1/vendor/attributes` | ✅ Admin attribute management | ✅ | — |
| **ORDERS** | | | | |
| View Current Orders | ✅ `GET /api/v1/vendor/current-orders` | ✅ Admin order list | ✅ | — |
| View Completed Orders | ✅ `GET /api/v1/vendor/completed-orders` | ✅ Admin order list (filtered) | ✅ | — |
| View Canceled Orders | ✅ `GET /api/v1/vendor/canceled-orders` | ✅ Admin order list (filtered) | ✅ | — |
| View All Orders | ✅ `GET /api/v1/vendor/all-orders` | ✅ Admin all orders | ✅ | — |
| Order Details | ✅ `GET /api/v1/vendor/order-details` | ✅ Admin order detail | ✅ | — |
| Get Single Order | ✅ `GET /api/v1/vendor/order` | ✅ Admin order detail | ✅ | — |
| Update Order Status | ✅ `PUT /api/v1/vendor/update-order-status` | ✅ Admin override status | ✅ | Admin can override any status |
| Edit Order Amount | ✅ `PUT /api/v1/vendor/update-order-amount` | ✅ Admin edit | ✅ | — |
| Send Order OTP | ✅ `PUT /api/v1/vendor/send-order-otp` | ✅ Admin can send OTP | ✅ | — |
| View Order Tracking | ❌ | ✅ Admin tracking page | ⚠️ | Vendor app does not show driver tracking for their orders |
| Refund Management | ❌ | ✅ Admin refund approve/reject | 🔒 | Vendor cannot initiate refunds; Admin only |
| Dispute Resolution | ❌ | ✅ Admin dispute management | 🔒 | — |
| **PRODUCTS (ITEMS)** | | | | |
| Create Item | ✅ `POST /api/v1/seller/item/store` | ✅ Admin item creation | ✅ | — |
| Update Item | ✅ `PUT /api/v1/seller/item/update` | ✅ Admin edit | ✅ | — |
| Delete Item | ✅ `DELETE /api/v1/seller/item/delete` | ✅ Admin delete | ✅ | — |
| Item Status Toggle | ✅ `GET /api/v1/seller/item/status` | ✅ Admin toggle | ✅ | — |
| Item Details | ✅ `GET /api/v1/seller/item/details/{id}` | ✅ Admin detail | ✅ | — |
| Item Search | ✅ `POST /api/v1/seller/item/search` | ✅ Admin search | ✅ | — |
| Stock Update | ✅ `PUT /api/v1/seller/item/stock-update` | ✅ Admin edit | ✅ | — |
| Stock Limit List | ✅ `GET /api/v1/seller/item/stock-limit-list` | ✅ Admin view | ✅ | — |
| Pending Item List | ✅ `GET /api/v1/seller/item/pending/item/list` | ✅ Admin approval queue | ✅ | — |
| Requested Item View | ✅ `GET /api/v1/seller/item/requested/item/view/{id}` | ✅ Admin detail | ✅ | — |
| Recommended Items | ✅ `GET /api/v1/seller/item/recommended` | ✅ Admin recommendations | ✅ | — |
| Organic Items | ✅ `GET /api/v1/seller/item/organic` | ✅ Admin filter | ✅ | — |
| Item Reviews | ✅ `GET /api/v1/seller/item/reviews` | ✅ Admin reviews | ✅ | — |
| Reply to Review | ✅ `PUT /api/v1/seller/item/reply-update` | ✅ Admin reply | ✅ | — |
| Bulk Item Upload | ❌ | ✅ Admin CSV upload | ⚠️ | Vendor app lacks bulk upload |
| Item Variants Management | ⚠️ Limited | ✅ Admin full variant management | ⚠️ | App supports basic variants only |
| **COUPONS** | | | | |
| View Coupons | ✅ `GET /api/v1/seller/coupon/list` | ✅ Admin list | ✅ | — |
| Create Coupon | ✅ `POST /api/v1/seller/coupon/store` | ✅ Admin create | ✅ | — |
| Update Coupon | ✅ `POST /api/v1/seller/coupon/update` | ✅ Admin edit | ✅ | — |
| Toggle Coupon Status | ✅ `POST /api/v1/seller/coupon/status` | ✅ Admin toggle | ✅ | — |
| Delete Coupon | ✅ `POST /api/v1/seller/coupon/delete` | ✅ Admin delete | ✅ | — |
| Search Coupons | ✅ `POST /api/v1/seller/coupon/search` | ✅ Admin search | ✅ | — |
| View Coupon Usage | ⚠️ Limited | ✅ Admin usage analytics | ⚠️ | App shows basic usage; Admin shows detailed analytics |
| **ADVERTISEMENTS** | | | | |
| View Ads | ✅ `GET /api/v1/seller/advertisement/` | ✅ Admin list | ✅ | — |
| Create Ad | ✅ `POST /api/v1/seller/advertisement/store` | ✅ Admin create | ✅ | — |
| Update Ad | ✅ `POST /api/v1/seller/advertisement/update/{id}` | ✅ Admin edit | ✅ | — |
| Delete Ad | ✅ `DELETE /api/v1/seller/advertisement/delete/{id}` | ✅ Admin delete | ✅ | — |
| Toggle Ad Status | ✅ `PUT /api/v1/seller/advertisement/status` | ✅ Admin toggle | ✅ | — |
| View Ad Details | ✅ `GET /api/v1/seller/advertisement/details/{id}` | ✅ Admin detail | ✅ | — |
| Copy Ad | ✅ `POST /api/v1/seller/advertisement/copy-add-post` | ✅ Admin copy | ✅ | — |
| Ad Analytics | ❌ | ✅ Admin analytics dashboard | ⚠️ | Vendor app lacks ad performance analytics |
| **ADDONS** | | | | |
| View Addons | ✅ `GET /api/v1/seller/addon/` | ✅ Admin list | ✅ | — |
| Create Addon | ✅ `POST /api/v1/seller/addon/store` | ✅ Admin create | ✅ | — |
| Update Addon | ✅ `PUT /api/v1/seller/addon/update` | ✅ Admin edit | ✅ | — |
| Toggle Addon Status | ✅ `GET /api/v1/seller/addon/status` | ✅ Admin toggle | ✅ | — |
| Delete Addon | ✅ `DELETE /api/v1/seller/addon/delete` | ✅ Admin delete | ✅ | — |
| **BANNERS** | | | | |
| View Banners | ✅ `GET /api/v1/seller/banner/` | ✅ Admin list | ✅ | — |
| Create Banner | ✅ `POST /api/v1/seller/banner/store` | ✅ Admin create | ✅ | — |
| Update Banner | ✅ `PUT /api/v1/seller/banner/update` | ✅ Admin edit | ✅ | — |
| Toggle Banner Status | ✅ `GET /api/v1/seller/banner/status` | ✅ Admin toggle | ✅ | — |
| Delete Banner | ✅ `DELETE /api/v1/seller/banner/delete` | ✅ Admin delete | ✅ | — |
| Edit Banner | ✅ `GET /api/v1/seller/banner/edit/{id}` | ✅ Admin edit | ✅ | — |
| **DELIVERY MEN** | | | | |
| View Delivery Men List | ✅ `GET /api/v1/seller/delivery-man/list` | ✅ Admin list | ✅ | — |
| Add Delivery Man | ✅ `POST /api/v1/seller/delivery-man/store` | ✅ Admin create | ✅ | — |
| Update Delivery Man | ✅ `POST /api/v1/seller/delivery-man/update/{id}` | ✅ Admin edit | ✅ | — |
| Delete Delivery Man | ✅ `DELETE /api/v1/seller/delivery-man/delete` | ✅ Admin delete | ✅ | — |
| Delivery Man Status | ✅ `GET /api/v1/seller/delivery-man/status` | ✅ Admin toggle | ✅ | — |
| Delivery Man Preview | ✅ `GET /api/v1/seller/delivery-man/preview` | ✅ Admin detail | ✅ | — |
| Search Delivery Men | ✅ `POST /api/v1/seller/delivery-man/search` | ✅ Admin search | ✅ | — |
| **CATEGORIES** | | | | |
| View Categories | ✅ `GET /api/v1/seller/categories/` | ✅ Admin list | ✅ | — |
| View Child Categories | ✅ `GET /api/v1/seller/categories/childes/{id}` | ✅ Admin tree view | ✅ | — |
| Category-wise Products | ✅ `GET /api/v1/seller/categories/category-wise-products/{id}` | ✅ Admin filter | ✅ | — |
| **EARNINGS & PAYOUTS** | | | | |
| Earning Info | ✅ `GET /api/v1/vendor/earning-info` | ✅ Admin earnings dashboard | ✅ | — |
| Earning Report | ✅ `GET /api/v1/vendor/earning-report` | ✅ Admin reports | ✅ | — |
| Expense Report | ✅ `GET /api/v1/vendor/get-expense` | ✅ Admin reports | ✅ | — |
| Tax Report | ✅ `GET /api/v1/vendor/get-tax-report` | ✅ Admin reports | ✅ | — |
| Disbursement Report | ✅ `GET /api/v1/vendor/get-disbursement-report` | ✅ Admin reports | ✅ | — |
| Withdraw List | ✅ `GET /api/v1/vendor/get-withdraw-list` | ✅ Admin payout management | ✅ | — |
| Request Withdraw | ✅ `POST /api/v1/vendor/request-withdraw` | ✅ Admin manual payout | ✅ | — |
| Withdraw Methods | ✅ `GET /api/v1/vendor/get-withdraw-method-list` | ✅ Admin management | ✅ | — |
| Add Withdraw Method | ✅ `POST /api/v1/vendor/withdraw-method/store` | ✅ Admin add | ✅ | — |
| Set Default Method | ✅ `POST /api/v1/vendor/withdraw-method/make-default` | ✅ Admin set default | ✅ | — |
| Delete Method | ✅ `DELETE /api/v1/vendor/withdraw-method/delete` | ✅ Admin delete | ✅ | — |
| Wallet Transactions | ❌ | ✅ Admin wallet view | ⚠️ | Vendor app shows balance only; Admin shows full transaction log |
| Wallet Adjustments | ✅ `POST /api/v1/vendor/make-wallet-adjustment` | ✅ Admin adjust | ✅ | — |
| Cash Payment Collection | ✅ `POST /api/v1/vendor/make-collected-cash-payment` | ✅ Admin record | ✅ | — |
| Wallet Payment List | ✅ `GET /api/v1/vendor/wallet-payment-list` | ✅ Admin list | ✅ | — |
| **MESSAGING** | | | | |
| Conversation List | ✅ `GET /api/v1/seller/message/list` | ✅ Admin conversations | ✅ | — |
| Search Conversations | ✅ `GET /api/v1/seller/message/search-list` | ✅ Admin search | ✅ | — |
| View Messages | ✅ `GET /api/v1/seller/message/details` | ✅ Admin messages | ✅ | — |
| Send Message | ✅ `POST /api/v1/seller/message/send` | ✅ Admin send | ✅ | — |
| **NOTIFICATIONS** | | | | |
| View Notifications | ✅ `GET /api/v1/vendor/notifications` | ✅ Admin vendor notifications | ✅ | — |
| Unread Count | ✅ `GET /api/v1/vendor/notifications/unread-count` | ✅ Admin count | ✅ | — |
| Mark Read | ✅ `POST /api/v1/vendor/notifications/{id}/read` | ✅ Admin mark | ✅ | — |
| Mark All Read | ✅ `POST /api/v1/vendor/notifications/read-all` | ✅ Admin mark all | ✅ | — |
| Delete Notification | ✅ `DELETE /api/v1/vendor/notifications/{id}` | ✅ Admin delete | ✅ | — |
| Push Notification Settings | ❌ | ✅ Admin settings | ⚠️ | Vendor cannot toggle push settings from app |
| **POS (Point of Sale)** | | | | |
| POS Order List | ✅ `GET /api/v1/seller/pos/orders` | ✅ Admin POS management | ✅ | — |
| POS Place Order | ✅ `POST /api/v1/seller/pos/place-order` | ✅ Admin place order | ✅ | — |
| POS Customers | ✅ `GET /api/v1/seller/pos/customers` | ✅ Admin customer list | ✅ | — |
| **CAMPAIGNS** | | | | |
| View Basic Campaigns | ✅ `GET /api/v1/vendor/get-basic-campaigns` | ✅ Admin campaigns | ✅ | — |
| Join Campaign | ✅ `PUT /api/v1/vendor/campaign-join` | ✅ Admin add store | ✅ | — |
| Leave Campaign | ✅ `PUT /api/v1/vendor/campaign-leave` | ✅ Admin remove store | ✅ | — |
| Campaign Analytics | ❌ | ✅ Admin analytics | ⚠️ | Vendor app lacks campaign performance data |
| **SUBSCRIPTION** | | | | |
| View Packages | ✅ `GET /api/v1/vendor/package-view` | ✅ Admin packages | ✅ | — |
| Purchase Plan | ✅ `POST /api/v1/vendor/business_plan` | ✅ Admin assign plan | ✅ | — |
| Subscription Payment | ✅ `POST /api/v1/vendor/subscription/payment/api` | ✅ Admin payment processing | ✅ | — |
| Check Product Limits | ✅ `GET /api/v1/vendor/check-product-limits` | ✅ Admin limits view | ✅ | — |
| Cancel Subscription | ✅ `POST /api/v1/vendor/cancel-subscription` | ✅ Admin cancel | ✅ | — |
| Subscription Transactions | ✅ `GET /api/v1/vendor/subscription-transaction` | ✅ Admin transactions | ✅ | — |
| **FASHION FIT** | | | | |
| Vendor Profile | ✅ `GET /api/v1/vendor/fashion-fit/profile` | ✅ Admin provider management | ✅ | — |
| View Requests | ✅ `GET /api/v1/vendor/fashion-fit/requests` | ✅ Admin requests list | ✅ | — |
| View Request Detail | ✅ `GET /api/v1/vendor/fashion-fit/requests/{uuid}` | ✅ Admin detail | ✅ | — |
| Submit Estimate | ✅ `POST /api/v1/vendor/fashion-fit/requests/{uuid}/estimates` | ✅ Admin can override | ✅ | — |
| Request Clarification | ✅ `POST /api/v1/vendor/fashion-fit/requests/{uuid}/clarification` | ❌ | ⚠️ | Admin cannot request clarification directly |
| Update Status | ✅ `POST /api/v1/vendor/fashion-fit/requests/{uuid}/status` | ✅ Admin override | ✅ | — |
| View Earnings | ✅ `GET /api/v1/vendor/fashion-fit/earnings` | ✅ Admin earnings | ✅ | — |
| Download Customer Photos | ✅ `GET /api/v1/vendor/fashion-fit/requests/{uuid}/photos/{photo_uuid}` | ✅ Admin download | ✅ | — |
| **SERVICE BOOKINGS** | | | | |
| Provider Profile | ✅ `GET /api/v1/vendor/service-bookings/profile` | ✅ Admin provider management | ✅ | — |
| Manage Services | ✅ CRUD `/api/v1/vendor/service-bookings/services` | ✅ Admin service management | ✅ | — |
| Manage Availability | ✅ `PUT /api/v1/vendor/service-bookings/availability` | ✅ Admin availability | ✅ | — |
| View Bookings | ✅ `GET /api/v1/vendor/service-bookings/bookings` | ✅ Admin bookings list | ✅ | — |
| Submit Quote | ✅ `POST /api/v1/vendor/service-bookings/bookings/{id}/quote` | ✅ Admin can quote | ✅ | — |
| Transition Status | ✅ `POST /api/v1/vendor/service-bookings/bookings/{id}/status` | ✅ Admin override | ✅ | — |
| View Earnings | ✅ `GET /api/v1/vendor/service-bookings/earnings` | ✅ Admin earnings | ✅ | — |
| **DYNAMIC PRICING AI** | | | | |
| Get Price Recommendations | ✅ `POST /api/v1/urban-goodz/pricing/ai/recommend` | ❌ | ⚠️ | Admin cannot access vendor pricing AI |
| Simulate Price Change | ✅ `POST /api/v1/urban-goodz/pricing/ai/simulate` | ❌ | ⚠️ | — |
| Price History | ✅ `GET /api/v1/urban-goodz/pricing/ai/history` | ❌ | ⚠️ | — |
| Rollback Price | ✅ `POST /api/v1/urban-goodz/pricing/ai/rollback` | ❌ | ⚠️ | — |
| **AI CONCIERGE** | | | | |
| Query AI | ✅ `POST /api/v1/urban-goodz/ai-concierge/query` | ✅ Admin AI concierge | ✅ | — |
| Chat History | ✅ `GET /api/v1/urban-goodz/ai-concierge/history` | ✅ Admin history | ✅ | — |
| **CROSS-APP AI** | | | | |
| Daily Brief | ✅ `GET /api/v1/urban-goodz/cross-app/ai/vendor/daily-brief` | ✅ Admin vendor analytics | ✅ | — |
| Order Summary | ✅ `POST /api/v1/urban-goodz/cross-app/ai/vendor/order-summary` | ✅ Admin order analytics | ✅ | — |
| Alerts | ✅ `GET /api/v1/urban-goodz/cross-app/ai/vendor/alerts` | ✅ Admin alerts | ✅ | — |
| Performance | ✅ `GET /api/v1/urban-goodz/cross-app/ai/vendor/performance` | ✅ Admin performance | ✅ | — |
| Promotions | ✅ `GET /api/v1/urban-goodz/cross-app/ai/vendor/promotions` | ✅ Admin promotions | ✅ | — |
| Prep Time | ✅ `POST /api/v1/urban-goodz/cross-app/ai/vendor/prep-time` | ❌ | ⚠️ | Admin cannot query vendor prep time AI |
| **ORDER ANYWHERE** | | | | |
| Vendor Update | ✅ `POST /api/v1/order-anywhere/vendor/requests/{id}/update` | ✅ Admin full management | ✅ | — |
| **LOGOUT** | | | | |
| Logout | ✅ `POST /api/v1/vendor/logout` | ✅ Admin session management | ✅ | — |

---

## 2. PARITY GAP SUMMARY

### Features in Admin Panel but NOT in Vendor App

| # | Feature | Admin Route | Impact | Priority |
|---|---|---|---|---|
| 1 | Bulk Item Upload (CSV) | Admin item import | High — vendors with large catalogs must use web | Medium |
| 2 | Advanced Item Variant Management | Admin item variants | Medium — complex variants need web interface | Low |
| 3 | Ad Performance Analytics | Admin advertisement analytics | Medium — vendors cannot measure ad ROI from app | Medium |
| 4 | Campaign Performance Analytics | Admin campaign analytics | Medium — vendors cannot see campaign metrics | Low |
| 5 | Detailed Wallet Transaction Log | Admin wallet view | Medium — vendors see balance but not full history | High |
| 6 | Push Notification Settings | Admin notification settings | Low — most vendors don't change settings | Low |
| 7 | Vendor Announcement Management (from Admin) | Admin vendor announcement | Low — admin-only feature | N/A |
| 8 | Refund Initiation | Admin refund management | By design — vendors cannot self-refund | N/A |
| 9 | Order Tracking (driver location) | Admin order tracking | Medium — vendors want to see driver progress | High |
| 10 | Coupon Usage Analytics (detailed) | Admin coupon analytics | Low — basic usage shown in app | Low |

### Features in Vendor App but NOT in Admin Panel

| # | Feature | Vendor App Route | Impact | Priority |
|---|---|---|---|---|
| 1 | Update Vendor Announcement | `PUT /api/v1/vendor/update-announcment` | Low — self-service feature | N/A |
| 2 | Dynamic Pricing AI | `/api/v1/urban-goodz/pricing/ai/*` | Medium — AI pricing is vendor-only | Low |
| 3 | AI Concierge (vendor-specific) | `/api/v1/urban-goodz/ai-concierge/query` | Low — available to all roles | N/A |
| 4 | Service Bookings AI (vendor prep time, alerts) | `/api/v1/vendor/service-bookings/ai/*` | Medium — vendor-specific AI insights | Low |

---

## 3. API CLIENT COMPARISON

| Aspect | Vendor Flutter App | Admin Panel (Laravel) |
|---|---|---|
| HTTP Client | `VendorApiClient` (Dart http package) | Laravel HTTP facade / Guzzle |
| Base URL | `https://admin.urbangoodzdelivery.com/api/v1` | Internal API calls |
| Auth Token | Bearer token + `vendorType: owner` header | Session-based + admin middleware |
| Error Handling | `VendorApiException` with status code + message | Laravel exception handler |
| Content Type | `application/json` | `application/json` |
| Retry Logic | Client-side (configurable) | Server-side queue for background jobs |
| Offline Support | Local cache (SharedPreferences) | N/A (web-based) |
| File Upload | Multipart via `VendorApiClient.multipart()` | Laravel file upload |
| Real-time Updates | WebSocket via Pusher | WebSocket via Pusher + polling |

---

## 4. DATA CONSISTENCY CHECKS

| Data Point | Vendor App Source | Admin Panel Source | Consistent? | Notes |
|---|---|---|---|---|
| Vendor Profile | `GET /api/v1/vendor/profile` | Admin vendor model | ✅ | Same database record |
| Order List | `GET /api/v1/vendor/current-orders` | Admin order query (filtered by store) | ✅ | Same query, different filters |
| Earning Balance | `GET /api/v1/vendor/earning-info` | Admin earnings report | ✅ | Calculated from same ledger |
| Product Count | `GET /api/v1/seller/item/stock-limit-list` | Admin item count (filtered) | ✅ | Same items table |
| Coupon List | `GET /api/v1/seller/coupon/list` | Admin coupon list (filtered) | ✅ | Same coupons table |
| Notification Count | `GET /api/v1/vendor/notifications/unread-count` | Admin notification count | ✅ | Same notifications table |
| Withdrawal Status | `GET /api/v1/vendor/get-withdraw-list` | Admin payout list | ✅ | Same payouts table |
