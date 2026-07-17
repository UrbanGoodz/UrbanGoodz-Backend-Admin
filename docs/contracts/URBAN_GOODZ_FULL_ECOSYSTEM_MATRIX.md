# URBAN GOODZ FULL ECOSYSTEM MATRIX

**Version:** 3.9  
**Last Updated:** 2026-07-16  
**Purpose:** Master overview of every feature, entity, and surface in the Urban Goodz platform — the definitive map of "what exists where."

---

## 1. PLATFORM SURFACE INVENTORY

| Surface | Technology | Base URL | Auth Guard | Primary Users |
|---|---|---|---|---|
| **Customer App** | Flutter (Dart) | `https://admin.urbangoodzdelivery.com/api/v1` | `auth:api` (Bearer token) | End consumers |
| **Vendor App** | Flutter (Dart) | `https://admin.urbangoodzdelivery.com/api/v1` | `vendor.api` + `vendorType: owner` header | Store owners, stylists, service providers |
| **Driver App** | Flutter (Dart) | `https://admin.urbangoodzdelivery.com/api/v1` | `dm.api` (Bearer token) | Delivery drivers, couriers |
| **Admin Panel** | Laravel + Blade/Vue | `https://admin.urbangoodzdelivery.com` | `auth:admin` (session) | Admins, dispatchers, support |
| **Customer Website** | React (optional) | `https://urbangoodzdelivery.com` | `auth:react_web` | Web customers |

---

## 2. FEATURE DOMAIN MATRIX

### 2.1 MARKETPLACE (Food/Grocery/Pharmacy/E-commerce)

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Browse Stores | ✅ | — | — | ✅ |
| Browse Items | ✅ | — | — | ✅ |
| Search (Items/Stores) | ✅ | — | — | ✅ |
| Item Details | ✅ | ✅ (own items) | — | ✅ |
| Cart Management | ✅ | — | — | — |
| Place Order | ✅ | — | — | ✅ |
| Prescription Order | ✅ | — | — | ✅ |
| Order Tracking | ✅ | ✅ (own) | ✅ (assigned) | ✅ |
| Cancel Order | ✅ (before prep) | ✅ (before driver) | — | ✅ (any time) |
| Rate & Review (Item) | ✅ | ✅ (view/reply) | — | ✅ |
| Rate & Review (Driver) | ✅ | — | — | ✅ |
| Wishlist | ✅ | — | — | — |
| Coupons | ✅ | ✅ (create/manage) | — | ✅ |
| Loyalty Points | ✅ | — | — | ✅ |
| Wallet | ✅ | ✅ | ✅ | ✅ |
| Cashback Offers | ✅ | — | — | ✅ |
| Flash Sales | ✅ | — | — | ✅ |
| Campaigns | ✅ | ✅ (join/leave) | — | ✅ |
| Banners | ✅ | ✅ (create/manage) | — | ✅ |
| Delivery Tips | ✅ | — | ✅ (receive) | — |
| Parcel/Courier | ✅ | — | ✅ (deliver) | ✅ |
| COD Switch | ✅ | — | — | — |
| Offline Payment | ✅ | — | — | ✅ |

### 2.2 ORDER ANYWHERE

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Submit Request | ✅ | — | — | ✅ |
| List Requests | ✅ | — | — | ✅ |
| View Request Detail | ✅ | ✅ (assigned) | ✅ (assigned) | ✅ |
| Provide Quote | — | ✅ | — | ✅ |
| Approve Quote | ✅ | — | — | — |
| Authorize Payment | ✅ | — | — | ✅ |
| Assign Driver | — | — | — | ✅ |
| Purchase Card | — | — | ✅ | — |
| Authorize Purchase | — | — | ✅ | — |
| Complete Purchase | — | — | ✅ | — |
| Upload Receipt | — | — | ✅ | ✅ |
| Reconcile | — | — | — | ✅ |
| Delivery Tracking | ✅ | — | ✅ | ✅ |
| Report Issue | — | — | ✅ | ✅ |

### 2.3 LOAD BOARD

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Browse Loads | ✅ (read-only) | — | ✅ | ✅ |
| Create Load | — | — | — | ✅ |
| AI Parse Load | — | — | — | ✅ (Dispatcher AI) |
| AI Rank Loads | — | — | — | ✅ (Dispatcher AI) |
| AI Match Drivers | — | — | — | ✅ (Dispatcher AI) |
| AI Rate Estimate | — | — | — | ✅ (Dispatcher AI) |
| AI Duplicate Check | — | — | — | ✅ (Dispatcher AI) |
| Bid on Load | — | — | ✅ | — |
| Accept Load | — | — | ✅ | ✅ (override) |
| Update Status | — | — | ✅ (own) | ✅ (any) |
| View POD | — | — | ✅ (own) | ✅ |
| Exception Report | — | — | ✅ | ✅ |
| Analytics | — | — | — | ✅ |

### 2.4 FASHION FIT

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Create Fit Profile | ✅ | — | — | — |
| Upload Photos | ✅ | — | — | — |
| AI Measurement Extraction | ✅ | — | — | — |
| Approve Measurements | ✅ | — | — | — |
| Submit Stylist Request | ✅ | — | — | — |
| View Estimates | ✅ | ✅ (own estimates) | — | ✅ |
| Accept/Reject Estimate | ✅ | — | — | — |
| Staged Payment | ✅ | — | — | — |
| Request Clarification | — | ✅ | — | — |
| Submit Estimate | — | ✅ | — | ✅ (override) |
| View Earnings | — | ✅ | — | ✅ |
| Provider Management | — | ✅ (own profile) | — | ✅ |
| Audit Trail | — | — | — | ✅ |

### 2.5 SERVICE BOOKINGS

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Browse Providers | ✅ | — | — | ✅ |
| View Provider Details | ✅ | ✅ (own profile) | — | ✅ |
| Create Booking | ✅ | — | — | — |
| Submit Quote | — | ✅ | — | ✅ (override) |
| Accept Quote | ✅ | — | — | — |
| Pay | ✅ | — | — | — |
| Confirm Completion | ✅ | — | — | — |
| Cancel | ✅ | ✅ (own) | — | ✅ (override) |
| Reschedule | ✅ | — | — | — |
| Review | ✅ | ✅ (view) | — | ✅ |
| Manage Services | — | ✅ | — | ✅ |
| Manage Availability | — | ✅ | — | ✅ |
| Earnings | — | ✅ | — | ✅ |
| AI Match Providers | ✅ | — | — | — |
| AI Compare Quotes | ✅ | — | — | — |
| AI Prep Time | — | ✅ | — | — |
| AI Alerts | — | ✅ | — | — |
| AI Performance | — | ✅ | — | ✅ |
| AI Promotions | — | ✅ | — | — |
| AI Daily Brief | — | ✅ | — | — |
| AI Reminders | ✅ | — | — | — |
| AI Verify Completion | ✅ | — | — | — |
| AI Find Replacement | ✅ | — | — | — |

### 2.6 BUSINESS COURIER

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| View Assigned Jobs | — | — | ✅ | ✅ |
| Job Detail | — | — | ✅ | ✅ |
| Accept Job | — | — | ✅ | — |
| Start Job | — | — | ✅ | — |
| Mark Pickup | — | — | ✅ | ✅ |
| Mark Delivery | — | — | ✅ | ✅ |
| Submit Proof Pickup | — | — | ✅ | ✅ |
| Submit Proof Delivery | — | — | ✅ | ✅ |
| Report Exception | — | — | ✅ | ✅ |
| Earnings | — | — | ✅ | ✅ |

### 2.7 DRIVER CAPABILITY & JOBS

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Capability Profile | — | — | ✅ | ✅ |
| Vehicle Management | — | — | ✅ | ✅ |
| Cargo Settings | — | — | ✅ | ✅ |
| Zone Settings | — | — | ✅ | ✅ |
| Work Type Settings | — | — | ✅ | ✅ |
| Tags | — | — | ✅ | ✅ |
| Availability Schedule | — | — | ✅ | ✅ |
| Job Discovery | — | — | ✅ | ✅ |
| Active Jobs (Unified) | — | — | ✅ | ✅ |
| Dispatch Notifications | — | — | ✅ | ✅ |
| Vehicles List | — | — | ✅ | ✅ |
| Certifications | — | — | ✅ | ✅ |
| Upload Certification | — | — | ✅ | ✅ |
| Renew Certification | — | — | ✅ | — |

### 2.8 EARNINGS & PAYOUTS

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Earnings Dashboard | — | ✅ | ✅ | ✅ |
| Earning Report | — | ✅ | ✅ | ✅ |
| Income Statement | — | — | ✅ | ✅ |
| Loyalty Report | — | — | ✅ | ✅ |
| Referral Report | — | — | ✅ | ✅ |
| Expense Report | — | ✅ | — | ✅ |
| Tax Report | — | ✅ | — | ✅ |
| Disbursement Report | — | ✅ | ✅ | ✅ |
| Withdrawal Methods | — | ✅ | ✅ | ✅ |
| Request Withdrawal | — | ✅ | ✅ | — |
| Withdrawal History | — | ✅ | ✅ | ✅ |
| Payout Processing | — | — | — | ✅ |
| Wallet Balance | ✅ | ✅ | ✅ | ✅ |
| Wallet Transactions | ✅ | ✅ (limited) | ✅ (limited) | ✅ (full) |
| Add Funds | ✅ | — | — | — |
| Wallet Adjustments | — | ✅ | ✅ | ✅ |

### 2.9 MESSAGING

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Conversation List | ✅ | ✅ | ✅ | ✅ |
| Search Conversations | ✅ | ✅ | ✅ | ✅ |
| View Messages | ✅ | ✅ | ✅ | ✅ |
| Send Message | ✅ | ✅ | ✅ | ✅ |

### 2.10 REELS & CREATOR COMMERCE

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Reel List | ✅ | — | — | ✅ |
| Reel Details | ✅ | — | — | ✅ |
| Reel Stats | ✅ | — | — | ✅ |
| Reel Like | ✅ | — | — | — |
| Reel Visit | ✅ | — | — | — |
| UG Reels Action | ✅ | — | — | — |
| UG Reels Conversion | ✅ | — | — | — |
| Creator Applications | ✅ | — | — | ✅ |
| Featured Reels | ✅ | — | — | ✅ |
| Creator AI (Reel Script) | ✅ | — | — | — |
| Creator AI (Product Tags) | ✅ | — | — | — |
| Creator AI (Caption) | ✅ | — | — | — |
| Creator AI (Performance) | ✅ | — | — | — |
| Creator AI (Brand Match) | ✅ | — | — | — |
| Creator AI (Reel Analytics) | ✅ | — | — | — |

### 2.11 IDENTITY & TRUST

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Identity Profiles | ✅ | — | — | ✅ |
| Fit Profile | ✅ | — | — | — |
| Identity Grants | ✅ | — | — | ✅ |
| Claims | ✅ | — | — | ✅ |
| Trust Assets | ✅ | — | — | ✅ |

### 2.12 AI CONCIERGE

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Query AI | ✅ | ✅ | ✅ | ✅ |
| Chat | ✅ | ✅ | ✅ | ✅ |
| History | ✅ | ✅ | ✅ | ✅ |

### 2.13 CROSS-APP AI

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Customer Query | ✅ | — | — | — |
| Customer History | ✅ | — | — | — |
| Fashion Fit Measurements | ✅ | — | — | — |
| Order Anywhere AI | ✅ | — | — | — |
| Smart Reorder | ✅ | — | — | — |
| Delivery ETA | ✅ | — | — | — |
| Vendor Daily Brief | — | ✅ | — | — |
| Vendor Order Summary | — | ✅ | — | — |
| Vendor Alerts | — | ✅ | — | — |
| Vendor Performance | — | ✅ | — | — |
| Vendor Pricing | — | ✅ | — | — |
| Vendor Promotions | — | ✅ | — | — |
| Vendor Prep Time | — | ✅ | — | — |
| Driver Daily Summary | — | — | ✅ | — |
| Driver Route Optimization | — | — | ✅ | — |
| Driver Verify Package | — | — | ✅ | — |
| Driver Verify Delivery | — | — | ✅ | — |
| Business Manifest Import | — | — | — | ✅ |
| Business Group Packages | — | — | — | ✅ |
| Business Route Create | — | — | — | ✅ |
| Business Route Optimize | — | — | — | ✅ |
| Business Driver Match | — | — | — | ✅ |
| Business Route Predict | — | — | — | ✅ |
| Business Route Risk | — | — | — | ✅ |
| Business Performance | — | — | — | ✅ |
| Business Cost Anomaly | — | — | — | ✅ |
| Business Invoice Support | — | — | — | ✅ |
| Business Delivery Proof | — | — | — | ✅ |
| Dispatcher Load Ranking | — | — | — | ✅ |
| Dispatcher Driver Match | — | — | — | ✅ |
| Dispatcher Rate Estimate | — | — | — | ✅ |
| Dispatcher Duplicate Check | — | — | — | ✅ |
| Dispatcher Ops Summary | — | — | — | ✅ |
| Dispatcher Parse Load | — | — | — | ✅ |
| Dispatcher Parse Email | — | — | — | ✅ |
| Dispatcher Parse Batch | — | — | — | ✅ |
| Dispatcher Source Status | — | — | — | ✅ |
| Dispatcher Sync Source | — | — | — | ✅ |

### 2.14 SPECIALIZED AI

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Route Optimization (Driver) | — | — | ✅ | ✅ |
| Earnings Comparison (Driver) | — | — | ✅ | — |
| Load Recommendations (Driver) | — | — | ✅ | — |
| Verify Pickup (Driver AI) | — | — | ✅ | — |
| Verify Delivery (Driver AI) | — | — | ✅ | — |
| Exception Handling (Driver AI) | — | — | ✅ | — |
| Driver Warnings | — | — | ✅ | — |
| Earnings Per Hour | — | — | ✅ | — |
| ETA Prediction | ✅ | ✅ | ✅ | ✅ |
| Dynamic Pricing | — | ✅ (vendor) | — | — |
| Fraud Detection | — | — | — | ✅ |
| Support AI | ✅ | ✅ | ✅ | ✅ |
| Rental AI | ✅ | — | — | ✅ |

### 2.15 DISCOVERY & EVENTS

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Search Capture | ✅ | — | — | — |
| Discovery Entities | ✅ | — | — | ✅ |
| Entity Detail | ✅ | — | — | ✅ |
| Entity Action | ✅ | — | — | — |
| Discovery Opportunities | ✅ | — | — | ✅ |
| Accept Opportunity | ✅ | — | — | — |
| Earn Money Opportunities | ✅ | — | — | ✅ |
| Logistics Jobs | ✅ | — | — | ✅ |
| Medical Courier Jobs | ✅ | — | — | ✅ |
| Book Anything Records | ✅ | — | — | ✅ |
| Book Anything Request | ✅ | — | — | — |
| Events List | ✅ | — | — | ✅ |
| Event Detail | ✅ | — | — | ✅ |
| Express Interest | ✅ | — | — | — |
| Vendor Opportunity | ✅ | — | — | — |
| Creator Opportunity | ✅ | — | — | — |
| Logistics Support | ✅ | — | — | — |

### 2.16 REVENUE & ANALYTICS

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Revenue Attributions | — | — | — | ✅ |
| Revenue Record | — | — | — | ✅ |
| Revenue Analytics | — | — | — | ✅ |
| Revenue ROS | — | — | — | ✅ |
| Revenue UGES | — | — | — | ✅ |

### 2.17 VENDOR BUSINESS MANAGEMENT

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| Store Registration | ✅ (link) | ✅ | — | ✅ |
| Subscription Packages | ✅ (view) | ✅ | — | ✅ |
| Purchase Plan | — | ✅ | — | ✅ |
| Subscription Payment | — | ✅ | — | ✅ |
| Cancel Subscription | — | ✅ | — | ✅ |
| Product Limits Check | — | ✅ | — | ✅ |
| Business Setup | — | ✅ | — | ✅ |
| Schedule Management | — | ✅ | — | ✅ |
| POS System | — | ✅ | — | ✅ |
| Delivery Man Management | — | ✅ | — | ✅ |
| Advertisement Management | — | ✅ | — | ✅ |
| Addon Management | — | ✅ | — | ✅ |
| Banner Management | — | ✅ | ✅ | ✅ |
| Category Management | — | ✅ | — | ✅ |

### 2.18 CONFIGURATION & SYSTEM

| Feature | Customer App | Vendor App | Driver App | Admin Panel |
|---|---|---|---|---|
| App Config | ✅ | ✅ | ✅ | ✅ |
| Zone Management | ✅ | — | — | ✅ |
| Module Management | ✅ | — | — | ✅ |
| Payment Method Config | ✅ | — | — | ✅ |
| Vehicle List | ✅ | — | ✅ | ✅ |
| Parcel Category | ✅ | — | — | ✅ |
| Terms & Conditions | ✅ | — | — | ✅ |
| Privacy Policy | ✅ | — | — | ✅ |
| Refund Policy | ✅ | — | — | ✅ |
| Shipping Policy | ✅ | — | — | ✅ |
| Cancellation Policy | ✅ | — | — | ✅ |
| About Us | ✅ | — | — | ✅ |
| Landing Page | ✅ | — | — | ✅ |
| App Download Section | ✅ | — | — | ✅ |

---

## 3. ENTITY-TO-TABLE MAPPING

| Entity | Primary Table | Related Tables | Created By |
|---|---|---|---|
| Customer | `customers` | `addresses`, `wallets`, `loyalty_points` | Customer (self) |
| Vendor/Store | `stores` | `store_schedules`, `store_earnings`, `store_payouts` | Vendor (self) + Admin approval |
| Driver/Delivery Man | `delivery_man` | `delivery_man_earnings`, `driver_capabilities`, `driver_certifications` | Driver (self) + Admin approval |
| Admin User | `admins` | — | Super Admin |
| Marketplace Order | `orders` | `order_details`, `order_transitions`, `payments` | Customer |
| Parcel Order | `parcel_orders` | `parcel_details`, `parcel_transitions` | Customer |
| Order Anywhere Request | `order_anywhere_requests` | `order_anywhere_transitions`, `purchase_cards`, `reconciliation` | Customer |
| Load Board Load | `load_board_loads` | `load_transitions`, `load_bids`, `pod_records` | Admin/Dispatcher |
| Fashion Fit Profile | `fashion_fit_profiles` | `fashion_fit_photos`, `fashion_fit_measurements`, `fashion_fit_analyses` | Customer |
| Fashion Fit Request | `fashion_fit_requests` | `fashion_fit_estimates`, `fashion_fit_staged_payments` | Customer |
| Service Booking | `service_bookings` | `service_booking_items`, `service_booking_transitions` | Customer |
| Product/Item | `items` | `item_variations`, `item_add_ons`, `item_reviews` | Vendor |
| Coupon | `coupons` | `coupon_usages` | Vendor/Admin |
| Advertisement | `advertisements` | `advertisement_views` | Vendor |
| Notification | `notifications` | — | System |
| Conversation | `conversations` | `conversation_messages` | System |
| Payment | `payments` | `payment_transitions`, `refunds` | System |
| Earning | `vendor_earnings`, `driver_earnings` | `earning_adjustments` | System |
| Payout | `payouts` | `payout_transitions` | Vendor/Driver (request) + Admin (approve) |
| Wallet Transaction | `wallet_transactions` | — | System |
| Audit Log | `audit_logs` | — | System |
| Identity Profile | `identity_profiles` | `identity_grants`, `identity_claims` | Customer |
| Reel | `reels` | `reel_stats`, `reel_likes` | Customer/Creator |
| Event | `events` | `event_interests`, `event_opportunities` | Admin |
| Opportunity | `opportunities` | `opportunity_claims` | System |
| AI Session | `ai_concierge_sessions` | `ai_concierge_messages` | System |
| Dispatch Notification | `dispatch_notifications` | — | System |
| Business Courier Job | `business_courier_jobs` | `courier_job_transitions`, `courier_proofs` | Admin |
| Driver Active Job (View) | (aggregation view) | — | System (aggregated) |

---

## 4. AUTH GUARD MATRIX

| Guard Name | Applied To | Middleware | Header Required |
|---|---|---|---|
| `auth:api` | Customer-facing routes | Laravel Sanctum/Passport | `Authorization: Bearer {token}` |
| `vendor.api` | Vendor-specific routes | Custom vendor middleware | `Authorization: Bearer {token}` + `vendorType: owner` |
| `dm.api` | Driver-specific routes | Custom driver middleware | `Authorization: Bearer {token}` |
| `auth:admin` | Admin Panel routes | Admin session guard | Session cookie |
| `actch:vendor_app` | Vendor app routes | App type check | `vendorType: owner` |
| `actch:deliveryman_app` | Driver legacy routes | App type check | — |
| `module-check` | Module-gated routes | Module availability | — |
| `localization` | All routes | Language detection | `X-localization: {lang}` |
| `throttle:60,1` | Most API routes | Rate limiting | — |

---

## 5. REALTIME CHANNEL MATRIX

| Channel Pattern | Subscribed By | Events |
|---|---|---|
| `private-{customer_id}-customer` | Customer App | `order-status-updated`, `new-notification`, `payment-update` |
| `private-{vendor_id}-vendor` | Vendor App | `new-order`, `order-status-updated`, `new-notification`, `new-message` |
| `private-{driver_id}-driver` | Driver App | `job-assigned`, `order-status-updated`, `new-notification`, `dispatch-alert`, `load-recommended` |
| `private-{admin_id}-admin` | Admin Panel | `new-order`, `dispatch-alert`, `exception-alert`, `new-notification` |

---

## 6. FILE UPLOAD MATRIX

| Upload Type | Endpoint | Allowed Actors | File Types | Max Size | Storage Path |
|---|---|---|---|---|---|
| Fashion Fit Photo | `POST /api/v1/fashion-fit/profiles/{uuid}/photos` | Customer | jpg, png, heic | 10MB | `storage/app/public/fashion-fit/` |
| Fashion Fit Upload | `POST /api/v1/urban-goodz/fashion-fit/photos/upload` | Customer | jpg, png | 10MB | `storage/app/public/fashion-fit/` |
| General File | `POST /api/v1/urban-goodz/files/upload/{category}` | All authenticated | jpg, png, pdf | 10MB | `storage/app/public/urban-goodz/{category}/` |
| Certification Doc | `POST /api/v1/urban-goodz/driver/certifications/{id}/upload` | Driver | pdf, jpg, png | 5MB | `storage/app/public/certifications/` |
| Delivery Proof | `POST /api/v1/urban-goodz/driver/business-jobs/{id}/proof-delivery` | Driver | jpg, png | 5MB | `storage/app/public/proofs/` |
| Item Image (Vendor) | `POST /api/v1/seller/item/store` (multipart) | Vendor | jpg, png | 5MB | `storage/app/public/items/` |
| Banner Image (Vendor) | `POST /api/v1/seller/banner/store` (multipart) | Vendor | jpg, png | 2MB | `storage/app/public/banners/` |
| Profile Photo | `POST /api/v1/customer/update-profile` (multipart) | Customer | jpg, png | 2MB | `storage/app/public/profile/` |

---

## 7. VERSION & RELEASE TRACKING

| Component | Current Version | Release Date | Update Frequency |
|---|---|---|---|
| Customer App (Flutter) | 3.9 | 2026-07-16 | Per sprint |
| Vendor App (Flutter) | Sprint build | 2026-07-16 | Per sprint |
| Driver App (Flutter) | Sprint build | 2026-07-16 | Per sprint |
| Backend (Laravel) | V39 | 2026-07-16 | Per sprint |
| Admin Panel | V39 | 2026-07-16 | Per sprint |
| API Contract | 3.9 | 2026-07-16 | Per breaking change |
