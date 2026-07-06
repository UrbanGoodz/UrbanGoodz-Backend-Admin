# Urban Goodz Admin Architecture

## 1. Architectural Principles

### Backend-First
Every customer-facing feature requires three things before launch: a backend endpoint, an admin panel, and the appropriate permissions. Nothing is built for the customer app first. The admin always knows about and controls every feature before customers see it.

### White-Label / Config-Driven
Apps consume configuration from the backend rather than hardcoding feature visibility, labels, or ordering. The backend endpoint `GET /api/v1/urban-goodz/app-config` returns a complete feature manifest. The Flutter app renders what the backend says is enabled. No feature is ever visible purely because the app binary contains it.

### Extend 6amMart, Don't Replace It
Urban Goodz builds on top of the existing 6amMart foundation. Core module permission checking, role management, zone scoping, and middleware chains are reused. New Urban Goodz modules register alongside existing module names rather than creating a parallel auth system.

### Business-Type-Driven Admin Panels
Admin sections are not one-size-fits-all. A restaurant admin panel differs from a car rental panel differs from a fashion fit panel. The backend determines which sections render based on the store's `business_type_slug` and the enabled capabilities for that business type.

### Order Anywhere Ownership
Order Anywhere is a capability owned by Master Admin / Urban Goodz Operations, not by individual stores or vendors. Vendors do not receive Order Anywhere admin panels. The flow is: Customer submits request -> AI Concierge processes -> Master Admin queue -> review/quote/fulfill.

---

## 2. Permission Architecture (Extending 6amMart)

Urban Goodz extends the existing 6amMart dual-role system without breaking it:

| Existing 6amMart | Urban Goodz Extension |
|---|---|
| AdminRole with `modules` JSON column | Add `urban_goodz_*` module names to the modules array |
| EmployeeRole with `modules` JSON column | Add `urban_goodz_*` module names to the modules array |
| ZoneScope restricts data by zone | Add `business_type_scope` and `capability_scope` |
| ModulePermissionMiddleware gates routes | Gate using `urban_goodz_*` module names |
| ModulePermissionMiddleware checks `modules` array | `hasModuleAccess('urban_goodz_order_anywhere')` |

### Module Names Registered
- `urban_goodz_admin` — top-level Urban Goodz admin access
- `urban_goodz_order_anywhere` — Order Anywhere queue management
- `urban_goodz_fashion_fit` — Fashion Fit request management
- `urban_goodz_ai_concierge` — AI Concierge management
- `urban_goodz_community` — Community post moderation
- `urban_goodz_creator_commerce` — Creator commerce management
- `urban_goodz_file_library` — File/media library
- `urban_goodz_messages` — Cross-platform messaging
- `urban_goodz_dispatch` — Driver dispatch management
- `urban_goodz_payments` — Payment ledger management
- `urban_goodz_reports` — Reports and analytics
- `urban_goodz_settings` — Urban Goodz settings
- `urban_goodz_business_types` — Business type management
- `urban_goodz_capabilities` — Capability management
- `urban_goodz_earn_money` — Earn money opportunity management
- `urban_goodz_spotlight` — Spotlight/featured management
- `urban_goodz_logistics` — Logistics/load board management
- `urban_goodz_professional_services` — Professional services management
- `urban_goodz_events` — Event management

---

## 3. Route Middleware Chain (Proposed Extension)

```
Route Request
  → admin middleware (auth + admin check)
  → current-module (sets active module context)
  → actch (access control — checks module in user's modules array)
  → [NEW: urban-goodz-scope] (resolves business_type_scope + capability_scope)
  → module: (module name, e.g., urban_goodz_order_anywhere)
  → [NEW: capability:] (capability check, e.g., order-anywhere)
  → [NEW: subscription:] (subscription tier check, e.g., plus required)
  → Controller
```

The `urban-goodz-scope` middleware introspects the authenticated admin's role, resolves the business types and capabilities they are permitted to manage, and attaches these to the request. Controllers then filter queries by these scopes rather than requiring explicit parameter passing.

The `capability:` middleware checks whether the store's business type has a specific capability enabled before allowing the action. If the capability is disabled globally or for that business type, the middleware returns a 403.

The `subscription:` middleware checks whether the store or admin has the required subscription tier (e.g., `plus`) for premium features.

---

## 4. Config-Driven App Design

### Backend Endpoint
```
GET /api/v1/urban-goodz/app-config
```

### Response Shape
```json
{
  "enabled_features": ["order_anywhere", "fashion_fit", "ai_concierge"],
  "enabled_modules": ["urban_goodz_order_anywhere", "urban_goodz_fashion_fit"],
  "home_sections": [
    { "type": "featured_stores", "visible": true, "order": 1 },
    { "type": "order_anywhere", "visible": true, "order": 2, "label": "Need Something Special?" }
  ],
  "business_types": [
    {
      "slug": "restaurant",
      "name": "Restaurants / Food Trucks",
      "capabilities": ["direct-checkout", "order-anywhere", "public-listing"]
    }
  ],
  "capabilities": {
    "order-anywhere": { "enabled": true, "label": "Order Anywhere" },
    "fashion-fit": { "enabled": true, "label": "Fashion Fit" }
  },
  "feature_routes": {
    "order_anywhere": "/order-anywhere/request",
    "fashion_fit": "/fashion-fit/measurements"
  },
  "empty_state_text": {
    "order_anywhere": "No requests yet. Submit your first order anywhere request.",
    "fashion_fit": "No measurement requests yet."
  },
  "early_access_labels": {
    "order_anywhere": "Early Access",
    "ai_concierge": "Beta"
  }
}
```

### Flutter Contract
- The Flutter app fetches `/app-config` on startup and on pull-to-refresh
- Feature visibility is driven entirely by `enabled_features`
- Section ordering is driven by `home_sections[].order`
- Labels and empty-state text come from the config
- Reusable screens/components are used everywhere; the backend decides what to show
- If a feature is not in `enabled_features`, the Flutter app renders nothing for it
- New features can be added to the backend config without an app store release

---

## 5. Shared Platform Systems

### File / Media Storage
- **Table:** `urban_goodz_files` (polymorphic via `fileable_type` + `fileable_id`)
- **Types:** receipt, pickup_proof, delivery_proof, fashion_photo, avatar, document
- **Storage:** Local disk or S3, configurable per environment
- **Admin view:** Filterable file library with preview, download, and metadata

### Messaging / Conversations
- **Table:** `urban_goodz_conversations` + `urban_goodz_messages`
- **Participants:** Customer, Vendor, Driver, Admin (any combination)
- **Scope:** Each conversation is scoped to an `order_id` or `request_id`
- **Admin view:** Unified inbox with participant filtering

### AI Concierge Intent Router
- **Table:** `urban_goodz_ai_intents` (id, name, slug, description, handler_class, is_active)
- **Flow:** Customer input → intent classification → handler dispatch → response
- **Intents:** order_anywhere, fashion_fit, store_hours, product_question, general, complaint
- **Admin view:** Intent CRUD, conversation history viewer, response templates

### Status Lifecycle Manager
- **Service:** `UrbanGoodzStatusManager`
- **Tables:** Each feature has its own status column (e.g., `order_anywhere_status`, `fashion_fit_status`)
- **Valid transitions:** Defined in config, enforced by the service
- **Admin view:** Status timeline with transition history

### Notifications
- **Channel:** Push (Firebase), SMS (Twilio), Email (SendGrid/Mailgun)
- **Triggers:** Status change, message received, assignment, quote issued
- **Templates:** Configurable per notification type

### Audit Logs
- **Table:** `urban_goodz_audit_logs`
- **Actions:** create, update, delete, assign, refund, cancel
- **Subject:** Polymorphic reference to any Urban Goodz entity
- **Admin view:** Searchable, filterable audit trail

---

## 6. Admin Panel Structure by Business Type

Each business type renders admin sections based on its enabled capabilities, not a generic panel:

| Business Type | Admin Sections |
|---|---|
| Restaurant | Dashboard, Orders (direct), Menu/Products, Inventory, Staff, Messages, Payouts, Reports, Settings |
| Grocery | Dashboard, Orders (direct), Products, Inventory, Staff, Messages, Payouts, Reports, Settings |
| Retail | Dashboard, Orders (direct), Products, Inventory, Staff, Messages, Payouts, Reports, Settings |
| Beauty Supply | Dashboard, Orders (direct), Products, Staff, Messages, Payouts, Reports, Settings |
| Pharmacy | Dashboard, Orders (direct + delivery), Products, Inventory, Staff, Messages, Payouts, Reports, Settings |
| Liquor | Dashboard, Orders (direct + delivery), Products, Inventory, Staff, Messages, Payouts, Reports, Settings |
| THC/CBD | Dashboard, Orders (direct + delivery), Products, Inventory, Staff, Messages, Payouts, Reports, Settings |
| Home-Based | Dashboard, Orders (direct), Products, Staff, Messages, Payouts, Reports, Settings |
| Events | Dashboard, Event Listings, Orders, Attendees, Messages, Payouts, Reports, Settings |
| Car Rental | Dashboard, Rentals (bookings), Fleet, Customers, Messages, Payouts, Reports, Settings |
| Equipment Rental | Dashboard, Rentals (bookings), Inventory/Equipment, Customers, Messages, Payouts, Reports, Settings |
| Courier | Dashboard, Tasks, Dispatch, Drivers, Messages, Payouts, Reports, Settings |
| Medical Courier | Dashboard, Tasks, Dispatch, Drivers, Certifications, Messages, Payouts, Reports, Settings |
| Professional Services | Dashboard, Bookings, Services, Staff, Clients, Messages, Payouts, Reports, Settings |
| Fashion Fit | Dashboard, Measurement Requests, Gallery, Tailors/Stylists, Orders, Messages, Payouts, Reports, Settings |
| Creator Commerce | Dashboard, Content, Products, Orders, Fans/Followers, Messages, Payouts, Reports, Settings |
| General / Order Anywhere | Dashboard, Order Anywhere Requests (view only), Messages, Payouts, Reports, Settings |
| Logistics / Load Board | Dashboard, Loads, Carriers, Dispatch, Drivers, Messages, Payouts, Reports, Settings |
