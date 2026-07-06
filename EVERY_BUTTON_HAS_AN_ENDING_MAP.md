# Every Button Has an Ending — Action Map

## Customer App Actions

### 1. Submit Order Anywhere Request
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Submit Request" on Order Anywhere form |
| **Feature** | Order Anywhere |
| **Required capability** | `order-anywhere` |
| **Required permission module** | — (customer action, no module check) |
| **Backend endpoint** | `POST /api/v1/urban-goodz/order-anywhere/requests` |
| **Database table / model** | `urban_goodz_order_anywhere_requests` |
| **Admin panel page** | Admin → Urban Goodz → Order Anywhere → Request Detail |
| **Status lifecycle** | `pending` → `reviewing` → `quoted` → `accepted` → `assigned` → `fulfilled` / `cancelled` / `refunded` |
| **Notification trigger** | Status change to `pending` (notifies admin queue) |
| **Completion path** | Customer receives quote → accepts → admin assigns store/driver → fulfillment → proof delivered |

### 2. Ask AI Concierge a Question
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Ask" on AI Concierge chat |
| **Feature** | AI Concierge |
| **Required capability** | `ai-concierge` |
| **Required permission module** | — (customer action) |
| **Backend endpoint** | `POST /api/v1/urban-goodz/ai-concierge/ask` |
| **Database table / model** | `urban_goodz_ai_conversations`, `urban_goodz_ai_messages` |
| **Admin panel page** | Admin → Urban Goodz → AI Concierge → Conversations |
| **Status lifecycle** | `open` → `resolved` / `escalated` |
| **Notification trigger** | Escalation to admin (intent `complaint` or `escalate`) |
| **Completion path** | AI responds inline; if escalated, admin responds via conversation |

### 3. View AI Concierge History
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "My Conversations" in AI Concierge |
| **Feature** | AI Concierge |
| **Required capability** | `ai-concierge` |
| **Backend endpoint** | `GET /api/v1/urban-goodz/ai-concierge/conversations` |
| **Database table / model** | `urban_goodz_ai_conversations` |
| **Admin panel page** | Admin → Urban Goodz → AI Concierge → Conversations (filtered by customer) |
| **Completion path** | Customer views paginated conversation list; drills into individual conversations |

### 4. Submit Fashion Fit Measurement Request
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Request Measurements" on product page |
| **Feature** | Fashion Fit |
| **Required capability** | `fashion-fit` |
| **Required permission module** | — (customer action) |
| **Backend endpoint** | `POST /api/v1/urban-goodz/fashion-fit/requests` |
| **Database table / model** | `urban_goodz_fashion_fit_requests` |
| **Admin panel page** | Admin → Urban Goodz → Fashion Fit → Request Detail |
| **Status lifecycle** | `draft` → `submitted` → `measurement_pending` → `quoting` → `quoted` → `accepted` → `in_production` → `shipped` / `cancelled` |
| **Notification trigger** | Status change to `submitted` (notifies admin/stylist) |
| **Completion path** | Customer submits measurements → stylist reviews → quote issued → customer pays → production → shipped |

### 5. Upload Fashion Fit Photos
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Add Photo" on Fashion Fit request |
| **Feature** | Fashion Fit |
| **Required capability** | `fashion-fit` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/fashion-fit/requests/{id}/photos` |
| **Database table / model** | `urban_goodz_files` (fileable: fashion_fit_request) |
| **Admin panel page** | Admin → Urban Goodz → Fashion Fit → Request Detail → Photo Gallery |
| **Completion path** | Photo uploaded, visible in admin gallery, attached to request record |

### 6. View Fashion Fit Request Status
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "My Requests" in Fashion Fit section |
| **Feature** | Fashion Fit |
| **Required capability** | `fashion-fit` |
| **Backend endpoint** | `GET /api/v1/urban-goodz/fashion-fit/requests/{id}` |
| **Database table / model** | `urban_goodz_fashion_fit_requests` |
| **Admin panel page** | Admin → Urban Goodz → Fashion Fit → Request Detail |
| **Completion path** | Customer views timeline, current status, next steps |

### 7. Browse/Search Stores
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Browse" on home screen, "Search" on search tab |
| **Feature** | Discovery |
| **Required capability** | `public-listing` (store must have it published) |
| **Required permission module** | — (customer action) |
| **Backend endpoint** | `GET /api/v1/urban-goodz/stores`, `GET /api/v1/urban-goodz/stores/search` |
| **Database table / model** | `stores` (filtered by `business_type_slug` and `public_listing` flag) |
| **Admin panel page** | Admin → Stores → Store List |
| **Completion path** | Customer views store cards → taps store → enters store detail/direct checkout |

### 8. Place Direct Order
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Place Order" on checkout screen |
| **Feature** | Direct Checkout |
| **Required capability** | `direct-checkout` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/orders` |
| **Database table / model** | `orders` (or `urban_goodz_orders`) |
| **Admin panel page** | Admin → Urban Goodz → Orders → Order Detail |
| **Status lifecycle** | `pending` → `confirmed` → `preparing` → `ready` → `out_for_delivery` → `delivered` / `cancelled` / `refunded` |
| **Notification trigger** | Status change to `confirmed` (notifies vendor), `out_for_delivery` (notifies customer) |
| **Completion path** | Order placed → vendor confirms → prepared → driver picks up → delivered → customer confirms receipt |

### 9. View Order Status
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "My Orders" in profile |
| **Feature** | Direct Checkout / Order Anywhere |
| **Required capability** | `direct-checkout` or `order-anywhere` |
| **Backend endpoint** | `GET /api/v1/urban-goodz/orders/{id}` |
| **Database table / model** | `orders` / `urban_goodz_order_anywhere_requests` |
| **Admin panel page** | Admin → Urban Goodz → Orders → Order Detail |
| **Completion path** | Customer views real-time status, timeline, driver location if applicable |

### 10. Book a Service Appointment
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Book Appointment" on service detail |
| **Feature** | Book Anything |
| **Required capability** | `book-anything` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/bookings` |
| **Database table / model** | `urban_goodz_bookings` |
| **Admin panel page** | Admin → Urban Goodz → Bookings |
| **Status lifecycle** | `requested` → `confirmed` → `in_progress` → `completed` / `cancelled` / `no_show` |
| **Notification trigger** | Status change to `requested` (notifies service provider), `confirmed` (notifies customer) |
| **Completion path** | Customer requests time → provider confirms → service rendered → completed → payment released |

### 11. Submit Creator Application
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Become a Creator" in Creator Commerce section |
| **Feature** | Creator Commerce |
| **Required capability** | `creator-commerce` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/creator-applications` |
| **Database table / model** | `urban_goodz_creator_applications` |
| **Admin panel page** | Admin → Urban Goodz → Creator Commerce → Applications |
| **Status lifecycle** | `pending` → `reviewing` → `approved` → `onboarding` → `active` / `rejected` |
| **Notification trigger** | Status change to `pending` (notifies admin), `approved` (notifies applicant) |
| **Completion path** | Application submitted → admin reviews → approved → onboarding → creator store launched |

### 12. Post to Community
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Create Post" in Community section |
| **Feature** | Community Marketplace |
| **Required capability** | `community-marketplace` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/community/posts` |
| **Database table / model** | `urban_goodz_community_posts` |
| **Admin panel page** | Admin → Urban Goodz → Community → Posts |
| **Status lifecycle** | `pending_review` → `published` / `flagged` / `removed` |
| **Notification trigger** | Post flagged (notifies admin moderator) |
| **Completion path** | Post created → auto-published or admin reviewed → visible to community |

### 13. Browse Events
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Events" on home screen or events tab |
| **Feature** | Events |
| **Required capability** | `events` |
| **Backend endpoint** | `GET /api/v1/urban-goodz/events` |
| **Database table / model** | `urban_goodz_events` |
| **Admin panel page** | Admin → Urban Goodz → Events |
| **Completion path** | Customer views event list → taps event → views detail → purchases ticket (if applicable) |

### 14. Track Delivery
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Track Order" on order detail |
| **Feature** | Delivery Tracking |
| **Required capability** | `direct-checkout` or `order-anywhere` |
| **Backend endpoint** | `GET /api/v1/urban-goodz/delivery/{order_id}/track` |
| **Database table / model** | `delivery_tasks` / `urban_goodz_delivery_tracking` |
| **Admin panel page** | Admin → Urban Goodz → Dispatch → Task Detail |
| **Completion path** | Customer views live map with driver location, status updates, estimated arrival |

### 15. View Receipts / Proofs
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "View Receipt" on order detail |
| **Feature** | File / Receipt Management |
| **Required capability** | `direct-checkout` or `order-anywhere` |
| **Backend endpoint** | `GET /api/v1/urban-goodz/orders/{id}/receipts`, `GET /api/v1/urban-goodz/files/{id}` |
| **Database table / model** | `urban_goodz_files` (type: receipt, delivery_proof) |
| **Admin panel page** | Admin → Urban Goodz → File Library |
| **Completion path** | Customer opens receipt PDF/image or delivery proof photo |

### 16. Message Vendor
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Message Vendor" on order or store detail |
| **Feature** | Messaging |
| **Required capability** | `direct-checkout` or `order-anywhere` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/messages`, `GET /api/v1/urban-goodz/conversations` |
| **Database table / model** | `urban_goodz_conversations`, `urban_goodz_messages` |
| **Admin panel page** | Admin → Urban Goodz → Messages → Conversation Detail |
| **Completion path** | Customer sends message → vendor receives notification → vendor replies → conversation thread |

### 17. Message Driver
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Message Driver" on order tracking screen |
| **Feature** | Messaging |
| **Required capability** | `direct-checkout` or `order-anywhere` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/messages` (with driver participant) |
| **Database table / model** | `urban_goodz_conversations`, `urban_goodz_messages` |
| **Admin panel page** | Admin → Urban Goodz → Messages → Conversation Detail |
| **Completion path** | Customer sends message → driver receives notification → driver replies |

### 18. Upload Receipt for Order Anywhere
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Upload Receipt" on Order Anywhere request detail |
| **Feature** | Order Anywhere |
| **Required capability** | `order-anywhere` |
| **Backend endpoint** | `POST /api/v1/urban-goodz/order-anywhere/requests/{id}/receipts` |
| **Database table / model** | `urban_goodz_files` (fileable: order_anywhere_request, type: receipt) |
| **Admin panel page** | Admin → Urban Goodz → File Library (filtered by Order Anywhere) |
| **Completion path** | Receipt uploaded → attached to request → admin reviews → reconciled |

### 19. View Earn Money Opportunities
| Field | Value |
|---|---|
| **Source app** | Customer |
| **Button / action** | "Earn Money" in profile or home screen |
| **Feature** | Earn Money |
| **Required capability** | `earn-money` |
| **Backend endpoint** | `GET /api/v1/urban-goodz/earn-money/opportunities` |
| **Database table / model** | `urban_goodz_earn_money_opportunities` |
| **Admin panel page** | Admin → Urban Goodz → Earn Money → Opportunities |
| **Completion path** | Customer views list → taps opportunity → views details/requirements → applies or refers |

---

## Vendor App Actions

### 1. View Dashboard
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Dashboard" on vendor nav |
| **Feature** | Vendor Dashboard |
| **Required permission module** | `dashboard` |
| **Backend endpoint** | `GET /api/v1/vendor/urban-goodz/dashboard` |
| **Database table / model** | Aggregate queries on orders, requests, earnings |
| **Admin panel page** | Admin → Vendors → Vendor Detail → Dashboard preview |
| **Completion path** | Vendor sees key metrics: orders today, revenue, pending requests, messages |

### 2. View Orders
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Orders" in vendor nav |
| **Feature** | Order Management |
| **Required permission module** | `orders` |
| **Backend endpoint** | `GET /api/v1/vendor/urban-goodz/orders` |
| **Database table / model** | `orders` / `urban_goodz_order_anywhere_requests` |
| **Admin panel page** | Admin → Urban Goodz → Orders (filtered by vendor) |
| **Completion path** | Vendor views order list → taps order → order detail → manage status |

### 3. Manage Products / Menu
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Products" or "Menu" in vendor nav |
| **Feature** | Product Management |
| **Required permission module** | `products` |
| **Backend endpoint** | `GET/POST/PUT /api/v1/vendor/urban-goodz/products` |
| **Database table / model** | `products` / `urban_goodz_products` |
| **Admin panel page** | Admin → Vendors → Vendor Detail → Products |
| **Completion path** | Vendor adds/edits/removes products → changes reflected in customer app |

### 4. Manage Inventory
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Inventory" in vendor nav |
| **Feature** | Inventory Management |
| **Required permission module** | `inventory` |
| **Backend endpoint** | `PUT /api/v1/vendor/urban-goodz/inventory` |
| **Database table / model** | `product_stocks` / `urban_goodz_inventory` |
| **Admin panel page** | Admin → Vendors → Vendor Detail → Inventory |
| **Completion path** | Vendor updates stock levels → availability synced to customer app |

### 5. Manage Staff (Vendor Employees)
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Staff" in vendor settings |
| **Feature** | Staff Management |
| **Required permission module** | `employees` |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/vendor/staff` |
| **Database table / model** | `employee_roles` / `users` (with vendor_id) |
| **Admin panel page** | Admin → Vendors → Vendor Detail → Staff |
| **Completion path** | Vendor invites/edits/removes staff → staff receive login credentials |

### 6. View Messages
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Messages" in vendor nav |
| **Feature** | Messaging |
| **Required permission module** | `messages` |
| **Backend endpoint** | `GET /api/v1/vendor/urban-goodz/conversations` |
| **Database table / model** | `urban_goodz_conversations`, `urban_goodz_messages` |
| **Admin panel page** | Admin → Urban Goodz → Messages |
| **Completion path** | Vendor views conversations → opens thread → replies → mark as read |

### 7. View Payouts / Wallet
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Wallet" or "Payouts" in vendor nav |
| **Feature** | Payout Management |
| **Required permission module** | `wallet` |
| **Backend endpoint** | `GET /api/v1/vendor/wallet` |
| **Database table / model** | `wallet_transactions` / `payout_requests` |
| **Admin panel page** | Admin → Vendors → Vendor Detail → Wallet/Payouts |
| **Completion path** | Vendor views balance, transaction history, requests payout |

### 8. Respond to Partner Request
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Partner Requests" in vendor nav |
| **Feature** | Partnership |
| **Required permission module** | `partners` |
| **Backend endpoint** | `PUT /api/v1/vendor/urban-goodz/partner-requests/{id}/respond` |
| **Database table / model** | `urban_goodz_partner_requests` |
| **Admin panel page** | Admin → Urban Goodz → Partner Requests |
| **Completion path** | Vendor accepts/rejects partnership → partner linked or request closed |

### 9. Creator Campaigns
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Campaigns" in vendor nav (creator commerce vendors) |
| **Feature** | Creator Commerce |
| **Required permission module** | `campaigns` |
| **Backend endpoint** | `GET/POST/PUT /api/v1/vendor/urban-goodz/campaigns` |
| **Database table / model** | `urban_goodz_campaigns` |
| **Admin panel page** | Admin → Urban Goodz → Creator Commerce → Campaigns |
| **Completion path** | Vendor creates campaign → sets commission → creators apply → campaign runs |

### 10. View Store Profile
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Store Profile" in vendor settings |
| **Feature** | Store Management |
| **Required permission module** | `store` |
| **Backend endpoint** | `GET/PUT /api/v1/vendor/store` |
| **Database table / model** | `stores` |
| **Admin panel page** | Admin → Stores → Store Detail |
| **Completion path** | Vendor views/edits store name, description, logo, cover image, hours, contact info |

### 11. Manage Business Settings
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Settings" in vendor nav |
| **Feature** | Business Settings |
| **Required permission module** | `settings` |
| **Backend endpoint** | `GET/PUT /api/v1/vendor/settings` |
| **Database table / model** | `vendor_settings` / `store_settings` |
| **Admin panel page** | Admin → Vendors → Vendor Detail → Settings |
| **Completion path** | Vendor manages business hours, delivery zones, payment methods, tax settings |

### 12. View Reports
| Field | Value |
|---|---|
| **Source app** | Vendor |
| **Button / action** | "Reports" in vendor nav |
| **Feature** | Reporting |
| **Required permission module** | `reports` |
| **Backend endpoint** | `GET /api/v1/vendor/urban-goodz/reports` |
| **Database table / model** | Aggregate queries + `urban_goodz_audit_logs` |
| **Admin panel page** | Admin → Urban Goodz → Reports (filtered by vendor) |
| **Completion path** | Vendor views sales, order, and performance reports with date range filtering |

---

## Driver App Actions

### 1. View Available Tasks
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Available Tasks" on driver home |
| **Feature** | Dispatch |
| **Required permission module** | — (driver action) |
| **Backend endpoint** | `GET /api/v1/driver/urban-goodz/tasks/available` |
| **Database table / model** | `delivery_tasks` / `urban_goodz_dispatch_tasks` |
| **Admin panel page** | Admin → Urban Goodz → Dispatch → Task List |
| **Completion path** | Driver views list of available tasks → taps to see detail → accepts |

### 2. Accept Task
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Accept Task" on task detail |
| **Feature** | Dispatch |
| **Backend endpoint** | `POST /api/v1/driver/urban-goodz/tasks/{id}/accept` |
| **Database table / model** | `urban_goodz_dispatch_tasks` (status + driver_id updated) |
| **Admin panel page** | Admin → Urban Goodz → Dispatch → Task Detail |
| **Status lifecycle** | `available` → `assigned` |
| **Notification trigger** | Task assigned to driver (notifies driver) |
| **Completion path** | Driver accepts → task removed from available pool, added to driver's active list |

### 3. Upload Pickup Proof
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Confirm Pickup — Add Photo" on active task |
| **Feature** | File Management |
| **Backend endpoint** | `POST /api/v1/driver/urban-goodz/tasks/{id}/pickup-proof` |
| **Database table / model** | `urban_goodz_files` (type: pickup_proof) |
| **Admin panel page** | Admin → Urban Goodz → File Library (filtered by task) |
| **Status lifecycle** | `assigned` → `picked_up` |
| **Notification trigger** | Status change to `picked_up` (notifies customer + admin) |
| **Completion path** | Driver uploads photo → task status advances → proof visible in admin and customer views |

### 4. Upload Delivery Proof
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Confirm Delivery — Add Photo/Signature" on active task |
| **Feature** | File Management |
| **Backend endpoint** | `POST /api/v1/driver/urban-goodz/tasks/{id}/delivery-proof` |
| **Database table / model** | `urban_goodz_files` (type: delivery_proof) |
| **Admin panel page** | Admin → Urban Goodz → File Library (filtered by task) |
| **Status lifecycle** | `picked_up` → `delivered` |
| **Notification trigger** | Status change to `delivered` (notifies customer + admin) |
| **Completion path** | Driver uploads photo/signature → task completed → payment released |

### 5. Update Delivery Status
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Update Status" on active task (e.g., en route, delayed, arrived) |
| **Feature** | Dispatch |
| **Backend endpoint** | `PUT /api/v1/driver/urban-goodz/tasks/{id}/status` |
| **Database table / model** | `urban_goodz_dispatch_tasks` |
| **Admin panel page** | Admin → Urban Goodz → Dispatch → Task Detail → Timeline |
| **Completion path** | Driver updates intermediate status → reflected on customer tracking + admin view |

### 6. Message Admin
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Contact Support" or "Message Admin" in driver app |
| **Feature** | Messaging |
| **Backend endpoint** | `POST /api/v1/driver/urban-goodz/messages` |
| **Database table / model** | `urban_goodz_conversations`, `urban_goodz_messages` |
| **Admin panel page** | Admin → Urban Goodz → Messages → Conversation Detail |
| **Completion path** | Driver sends message → admin receives notification → admin replies |

### 7. Message Customer
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Call" or "Message Customer" on active task |
| **Feature** | Messaging |
| **Backend endpoint** | `POST /api/v1/driver/urban-goodz/messages` (with customer participant) |
| **Database table / model** | `urban_goodz_conversations`, `urban_goodz_messages` |
| **Admin panel page** | Admin → Urban Goodz → Messages → Conversation Detail |
| **Completion path** | Driver sends message → customer receives notification → customer replies |

### 8. View Earnings
| Field | Value |
|---|---|
| **Source app** | Driver |
| **Button / action** | "Earnings" in driver profile |
| **Feature** | Driver Payouts |
| **Backend endpoint** | `GET /api/v1/driver/earnings` |
| **Database table / model** | `wallet_transactions` / `driver_earnings` |
| **Admin panel page** | Admin → Drivers → Driver Detail → Earnings |
| **Completion path** | Driver views trip earnings, tips, bonuses, total balance |

---

## Admin App Actions

### 1. Manage Business Types
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Business Types" under Urban Goodz admin nav |
| **Feature** | Business Type Management |
| **Required permission module** | `urban_goodz_business_types` |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/urban-goodz/business-types` |
| **Database table / model** | `urban_goodz_business_types` |
| **Admin panel page** | Admin → Urban Goodz → Business Types |
| **Completion path** | Admin lists, creates, edits, enables/disables business types and their capability mappings |

### 2. Manage Capabilities
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Capabilities" under Urban Goodz admin nav |
| **Feature** | Capability Management |
| **Required permission module** | `urban_goodz_capabilities` |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/urban-goodz/capabilities` |
| **Database table / model** | `urban_goodz_capabilities` |
| **Admin panel page** | Admin → Urban Goodz → Capabilities |
| **Completion path** | Admin lists, creates, edits capabilities and toggles default/required flags |

### 3. Manage Order Anywhere Queue
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Order Anywhere Queue" under Urban Goodz admin nav |
| **Feature** | Order Anywhere |
| **Required permission module** | `urban_goodz_order_anywhere` |
| **Backend endpoint** | `GET/PUT /api/v1/admin/urban-goodz/order-anywhere/requests` |
| **Database table / model** | `urban_goodz_order_anywhere_requests` |
| **Admin panel page** | Admin → Urban Goodz → Order Anywhere → Queue |
| **Status lifecycle** | `pending` → `reviewing` → `quoted` → `accepted` → `assigned` → `fulfilled` / `cancelled` / `refunded` |
| **Completion path** | Admin views queue → reviews request → enters quote → assigns store/driver → monitors fulfillment → confirms delivery |

### 4. Manage Fashion Fit Requests
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Fashion Fit" under Urban Goodz admin nav |
| **Feature** | Fashion Fit |
| **Required permission module** | `urban_goodz_fashion_fit` |
| **Backend endpoint** | `GET/PUT /api/v1/admin/urban-goodz/fashion-fit/requests` |
| **Database table / model** | `urban_goodz_fashion_fit_requests` |
| **Admin panel page** | Admin → Urban Goodz → Fashion Fit → Requests |
| **Status lifecycle** | `submitted` → `measurement_pending` → `quoting` → `quoted` → `accepted` → `in_production` → `shipped` / `cancelled` |
| **Completion path** | Admin views new requests → assigns stylist/tailor → reviews measurements → issues quote → monitors production → confirms shipment |

### 5. Manage Community Posts
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Community" under Urban Goodz admin nav |
| **Feature** | Community Marketplace |
| **Required permission module** | `urban_goodz_community` |
| **Backend endpoint** | `GET/PUT/DELETE /api/v1/admin/urban-goodz/community/posts` |
| **Database table / model** | `urban_goodz_community_posts` |
| **Admin panel page** | Admin → Urban Goodz → Community → Posts |
| **Status lifecycle** | `pending_review` → `published` / `flagged` / `removed` |
| **Completion path** | Admin reviews flagged/pending posts → approves, flags, or removes → content moderated |

### 6. Manage Creator Commerce
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Creator Commerce" under Urban Goodz admin nav |
| **Feature** | Creator Commerce |
| **Required permission module** | `urban_goodz_creator_commerce` |
| **Backend endpoint** | `GET/PUT /api/v1/admin/urban-goodz/creator-commerce/applications` |
| **Database table / model** | `urban_goodz_creator_applications`, `urban_goodz_campaigns` |
| **Admin panel page** | Admin → Urban Goodz → Creator Commerce → Applications / Campaigns |
| **Completion path** | Admin reviews creator applications, approves/rejects, monitors active campaigns |

### 7. Manage File Library
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "File Library" under Urban Goodz admin nav |
| **Feature** | File Management |
| **Required permission module** | `urban_goodz_file_library` |
| **Backend endpoint** | `GET/DELETE /api/v1/admin/urban-goodz/files` |
| **Database table / model** | `urban_goodz_files` |
| **Admin panel page** | Admin → Urban Goodz → File Library |
| **Completion path** | Admin views all uploaded files, filters by type/entity, previews, downloads, deletes stale files |

### 8. Manage Messages
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Messages" under Urban Goodz admin nav |
| **Feature** | Messaging |
| **Required permission module** | `urban_goodz_messages` |
| **Backend endpoint** | `GET /api/v1/admin/urban-goodz/conversations`, `POST /api/v1/admin/urban-goodz/messages` |
| **Database table / model** | `urban_goodz_conversations`, `urban_goodz_messages` |
| **Admin panel page** | Admin → Urban Goodz → Messages |
| **Completion path** | Admin views all conversations, filters by participant/entity, reads/replies, escalates if needed |

### 9. Manage Dispatch
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Dispatch" under Urban Goodz admin nav |
| **Feature** | Dispatch |
| **Required permission module** | `urban_goodz_dispatch` |
| **Backend endpoint** | `GET/PUT/POST /api/v1/admin/urban-goodz/dispatch/tasks` |
| **Database table / model** | `urban_goodz_dispatch_tasks` / `delivery_tasks` |
| **Admin panel page** | Admin → Urban Goodz → Dispatch |
| **Status lifecycle** | `pending` → `available` → `assigned` → `picked_up` → `delivered` / `cancelled` |
| **Completion path** | Admin creates tasks, assigns drivers, monitors in-progress deliveries, handles exceptions |

### 10. Manage Payment Ledger
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Payments" under Urban Goodz admin nav |
| **Feature** | Payment Ledger |
| **Required permission module** | `urban_goodz_payments` |
| **Backend endpoint** | `GET /api/v1/admin/urban-goodz/payments` |
| **Database table / model** | `wallet_transactions`, `payment_logs`, `urban_goodz_audit_logs` |
| **Admin panel page** | Admin → Urban Goodz → Payments |
| **Completion path** | Admin views payment ledger, filters by vendor/driver/customer, reconciles transactions, issues refunds |

### 11. Manage AI Concierge Intents
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "AI Concierge → Intents" under Urban Goodz admin nav |
| **Feature** | AI Concierge |
| **Required permission module** | `urban_goodz_ai_concierge` |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/urban-goodz/ai-concierge/intents` |
| **Database table / model** | `urban_goodz_ai_intents` |
| **Admin panel page** | Admin → Urban Goodz → AI Concierge → Intents |
| **Completion path** | Admin views, creates, edits, enables/disables intents and their handler mappings |

### 12. Manage AI Concierge Conversations
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "AI Concierge → Conversations" under Urban Goodz admin nav |
| **Feature** | AI Concierge |
| **Required permission module** | `urban_goodz_ai_concierge` |
| **Backend endpoint** | `GET /api/v1/admin/urban-goodz/ai-concierge/conversations` |
| **Database table / model** | `urban_goodz_ai_conversations`, `urban_goodz_ai_messages` |
| **Admin panel page** | Admin → Urban Goodz → AI Concierge → Conversations |
| **Completion path** | Admin views conversation history, filters by intent/customer, reads transcripts, marks as resolved |

### 13. Manage Admin Employees (Roles + Permissions)
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Admin Employees" in system settings |
| **Feature** | Admin Role Management |
| **Required permission module** | `admin_employee` (existing 6amMart module) |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/employees` |
| **Database table / model** | `admin_roles`, `users` (admin type) |
| **Admin panel page** | Admin → System → Admin Employees |
| **Completion path** | Admin creates/edits admin users, assigns roles, configures module permissions including Urban Goodz modules |

### 14. Manage Vendor Employees (via Vendor Panel)
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Vendor Staff" from vendor detail view |
| **Feature** | Vendor Employee Management |
| **Required permission module** | `vendor_employee` (existing 6amMart module) |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/vendors/{id}/staff` |
| **Database table / model** | `employee_roles`, `users` (vendor employee type) |
| **Admin panel page** | Admin → Vendors → Vendor Detail → Staff |
| **Completion path** | Admin views vendor staff, edits permissions, adds/removes employees on behalf of vendor |

### 15. View Reports
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Reports" under Urban Goodz admin nav |
| **Feature** | Reporting |
| **Required permission module** | `urban_goodz_reports` |
| **Backend endpoint** | `GET /api/v1/admin/urban-goodz/reports` |
| **Database table / model** | Aggregate queries + `urban_goodz_audit_logs` |
| **Admin panel page** | Admin → Urban Goodz → Reports |
| **Completion path** | Admin views platform-wide reports: orders, revenue, requests, users, with filters and export |

### 16. Manage Settings
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Settings" under Urban Goodz admin nav |
| **Feature** | Urban Goodz Settings |
| **Required permission module** | `urban_goodz_settings` |
| **Backend endpoint** | `GET/PUT /api/v1/admin/urban-goodz/settings` |
| **Database table / model** | `urban_goodz_settings` |
| **Admin panel page** | Admin → Urban Goodz → Settings |
| **Completion path** | Admin manages global Urban Goodz config: feature toggles, commission rates, default intents, etc. |

### 17. Manage Zones
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Zones" in system settings |
| **Feature** | Zone Management |
| **Required permission module** | `zone` (existing 6amMart module) |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/zone` |
| **Database table / model** | `zones` |
| **Admin panel page** | Admin → System → Zones |
| **Completion path** | Admin manages delivery/service zones, zone-level pricing, and zone-level feature availability |

### 18. Manage Modules
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Modules" in system settings |
| **Feature** | Module Management |
| **Required permission module** | `module` (existing 6amMart module) |
| **Backend endpoint** | `GET/POST/PUT /api/v1/admin/module` |
| **Database table / model** | `modules` |
| **Admin panel page** | Admin → System → Modules |
| **Completion path** | Admin manages registered modules, including adding Urban Goodz module entries to the module registry |

### 19. Manage Stores
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Stores" in admin nav |
| **Feature** | Store Management |
| **Required permission module** | `store` (existing 6amMart module, extended with urban_goodz_* checks) |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/store` |
| **Database table / model** | `stores` |
| **Admin panel page** | Admin → Stores |
| **Completion path** | Admin lists, creates, edits stores, assigns business_type_slug, manages vendor accounts |

### 20. Manage Drivers
| Field | Value |
|---|---|
| **Source app** | Admin |
| **Button / action** | "Drivers" in admin nav |
| **Feature** | Driver Management |
| **Required permission module** | `driver` (existing 6amMart module, extended with urban_goodz_dispatch) |
| **Backend endpoint** | `GET/POST/PUT/DELETE /api/v1/admin/driver` |
| **Database table / model** | `users` (driver type), `drivers` |
| **Admin panel page** | Admin → Drivers |
| **Completion path** | Admin lists, creates, edits, approves/rejects drivers, manages certifications, views earnings |
