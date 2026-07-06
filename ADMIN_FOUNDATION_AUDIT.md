# ADMIN FOUNDATION AUDIT — BUSINESS TYPE DRIVEN ADMIN PANELS

> Generated: 2026-07-06
> Repository: C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39
> Customer App: C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz2026-Revised
> Principle: Business type and enabled capabilities determine admin tools, endpoints, and workflows.

---

## Table of Contents

1. Existing Business Type Support Found
2. Missing Business Type Support
3. Recommended Schema: Business Types & Capabilities
4. Admin Panel Sections by Business Type
5. Capability System Design
6. "Every Button Has an Ending" — Full Action Map
7. First Coding Sprint Recommendation
8. Files Inspected
9. Files That Should Not Be Touched Yet

---

## 1. EXISTING BUSINESS TYPE SUPPORT FOUND

### 1.1 Module Type System (config/module.php)

The existing 6amMart system already defines 7 module types with per-type capability flags:

| Module Type | Used For | Key Capabilities |
|-------------|----------|-----------------|
| `grocery` | Grocery / Markets | stock, unit, organic, nutrition, allergy, halal |
| `food` | Restaurants / Food Trucks | add_on, veg_non_veg, cutlery, item_available_time |
| `pharmacy` | Pharmacy / Health | stock, unit, order_attachment, common_condition, generic_name |
| `ecommerce` | Retail / Shopping | stock, unit, brand, all_zone_service, always_open |
| `parcel` | Courier / Parcel | is_parcel, always_open |
| `rental` | Rentals | is_rental |
| `ride-share` | Ride Sharing | is_rental |

**Capability flags per type** (from `config/module.php`):
- `stock` — enable inventory management
- `add_on` — enable add-ons/modifiers
- `veg_non_veg` — dietary classification
- `unit` — unit of measure
- `order_attachment` — file upload with order
- `always_open` — 24/7 operation
- `all_zone_service` — serve all zones
- `item_available_time` — per-item availability windows
- `show_restaurant_text` — restaurant-specific UI text
- `is_parcel` — parcel delivery mode
- `organic` — organic item tagging
- `cutlery` — cutlery option
- `common_condition` — common conditions (pharmacy)
- `nutrition` — nutritional info
- `allergy` — allergen info
- `basic` — basic mode
- `halal` — halal certification
- `brand` — brand management
- `generic_name` — generic name (pharmacy)
- `is_rental` — rental mode

### 1.2 Store Model Business Type Fields

The Store model (`app/Models/Store.php`) connects to business types via:
- `module_id` (FK to modules table) — primary business type discriminator
- `module_type` (accessor: `$this->module?->module_type`)
- `store_business_model` — `commission`, `subscription`, or `unsubscribed`
- `fulfillment_mode` — `direct_vendor_order` or `order_anywhere_backend`

**Urban Goodz partner fields** (added by migration `2026_07_05_214527`):
- `business_status` — `active_partner`, `public_sourced`, etc.
- `badge_status` — `urban_goodz_partner`, `public_listing`, `claimed_business`, `pending_partner_activation`, `none`
- `is_partner`, `is_public_sourced`, `is_claimed`
- `order_anywhere_enabled`, `can_direct_checkout`, `requires_admin_quote`
- `partner_badge_enabled`

**Store capability flags** (on Store model):
- `delivery` — offers delivery
- `take_away` — offers pickup
- `self_delivery_system` — store-managed delivery
- `free_delivery` — free delivery
- `schedule_order` — scheduled orders
- `prescription_order` — prescription required
- `cutlery` — cutlery option (food)
- `featured` — featured store
- `pos_system` — POS enabled

### 1.3 Existing Category-to-Module Mapping

Categories are linked to modules via `module_id` on the Category model. The ingestion service (`UrbanGoodzIngestionService@classifyBusiness`) maps business category strings to module types:

```php
'restaurant' => ['module_name' => 'Restaurants', 'type' => 'food'],
'food truck' => ['module_name' => 'Food Trucks', 'type' => 'food'],
'grocery'    => ['module_name' => 'Grocery / Markets', 'type' => 'grocery'],
'retail'     => ['module_name' => 'Retail / Shopping', 'type' => 'shop'],
'beauty'     => ['module_name' => 'Beauty Supply / Hair Providerz', 'type' => 'shop'],
'hair'       => ['module_name' => 'Beauty Supply / Hair Providerz', 'type' => 'shop'],
'pharmacy'   => ['module_name' => 'Pharmacy / Health', 'type' => 'pharmacy'],
'liquor'     => ['module_name' => 'Liquor / Beveragez', 'type' => 'shop'],
'thc'        => ['module_name' => 'THC / CBD', 'type' => 'shop'],
'home'       => ['module_name' => 'Home-Based Businessz', 'type' => 'shop'],
'event'      => ['module_name' => 'Local Events / Creators', 'type' => 'shop'],
'car'        => ['module_name' => 'Car Rentalz', 'type' => 'rental'],
'equipment'  => ['module_name' => 'Equipment Rentalz', 'type' => 'rental'],
'courier'    => ['module_name' => 'Courier / Parcel', 'type' => 'parcel'],
'medical'    => ['module_name' => 'Medical Courier', 'type' => 'parcel'],
'service'    => ['module_name' => 'Professional Services', 'type' => 'shop'],
'fashion'    => ['module_name' => 'Fashion Fit', 'type' => 'shop'],
'creator'    => ['module_name' => 'Creator Commerce', 'type' => 'shop'],
'logistics'  => ['module_name' => 'Logistics / Load Board', 'type' => 'parcel'],
```

Note: Most map to `'type' => 'shop'` which is NOT one of the 7 defined `module_type` values (`grocery`, `food`, `pharmacy`, `ecommerce`, `parcel`, `rental`, `ride-share`). This means the existing `config/module.php` capability system does not cover Urban Goodz business types.

### 1.4 Existing Admin Views Per Module Type

The admin sidebar dynamically loads per-module-type partials:
- `_sidebar_ecommerce.blade.php`
- `_sidebar_grocery.blade.php`
- `_sidebar_food.blade.php`
- `_sidebar_pharmacy.blade.php`
- `_sidebar_parcel.blade.php`

The Urban Goodz admin section is added as a fixed sidebar item, not module-type-specific.

### 1.5 Existing Module-Specific Admin Screens

| Feature | Admin Screens Exist | Module-Type Specific |
|---------|-------------------|---------------------|
| Restaurant/Food | Full (menu, items, orders, etc.) | YES (food module) |
| Grocery | Full (products, inventory, orders) | YES (grocery module) |
| Pharmacy | Full (products, prescriptions, orders) | YES (pharmacy module) |
| Retail/Ecommerce | Full (products, variants, brands, orders) | YES (ecommerce module) |
| Parcel/Courier | Full (parcel orders, delivery) | YES (parcel module) |
| Rental | Full (vehicles, bookings) | YES (rental module, addon) |
| Ride Share | Via RideShare addon module | YES |
| Reels/Creator | Via ReelsModule addon | YES (separate module) |
| **Urban Goodz** | **Partial (dashboard, Order Anywhere, Fashion Fit, Payments)** | **NO (generic "urban-goodz" prefix)** |

---

## 2. MISSING BUSINESS TYPE SUPPORT

### 2.1 Business Types Without Module Type Mapping

The following Urban Goodz business types are NOT represented in `config/module.php` and have NO capability flags:

| Business Type | Used By | Current Mapping | Problem |
|---------------|---------|----------------|---------|
| Beauty Supply / Hair Provider | Beauty stores | `'shop'` (not a valid module_type) | No inventory/size/color/length support |
| Service Provider | Professionals | `'shop'` (not valid) | No booking/calendar support |
| Events | Event organizers | `'shop'` (not valid) | No event-specific workflows |
| Fashion Fit | Tailors/stylists | `'shop'` (not valid) | No measurement/photo workflow |
| Creator Commerce | Content creators | `'shop'` (not valid) | No video/commission support |
| Community / Social | All users | No mapping | No social features |
| Order Anywhere | Any business | `'order_anywhere_backend'` (fulfillment_mode) | No type-specific admin |
| Medical Courier | Medical delivery | `'parcel'` (close but not exact) | No chain-of-custody |
| Load Board | Freight | `'parcel'` (not exact) | No load-specific features |
| Book Anything | Services | `'shop'` (not valid) | No quote/booking flow |
| Earn Money | Gig workers | No mapping | No gig-specific workflows |

### 2.2 Missing Admin Screens by Business Type

| Business Type | Admin Screens Missing |
|---------------|---------------------|
| Beauty Supply | Product variants (length, texture, color), brand categories, appointments |
| Service Provider | Booking calendar, service area, deposits, before/after media |
| Events | Event listing, vendor applications, ticket management, schedule |
| Fashion Fit | Photo gallery, stylist assignment, bid management, privacy controls *(partial - index+view exist)* |
| Creator Commerce | Creator applications, video moderation, campaigns, commissions *(JSON-backed only)* |
| Community/Social | Posts, comments, reports, moderation *(nothing exists)* |
| Medical Courier | Chain of custody, compliance docs, driver certification |
| Load Board | Load listing, freight details, driver matching |
| Book Anything | Service listings, quote management, booking calendar |
| Earn Money | Gig listings, applications, payouts |
| Order Anywhere | Itemized pricing, notification triggers, sidebar link *(partial - list+detail exist)* |

### 2.3 Missing Capability Definitions

The existing `config/module.php` system needs new capability flags for Urban Goodz features:

| Capability Flag | Needed For | Type |
|----------------|-----------|------|
| `beauty_supply` | Beauty stores | boolean |
| `service_provider` | Professional services | boolean |
| `event_management` | Event organizers | boolean |
| `fashion_fit` | Tailors/stylists | boolean |
| `creator_commerce` | Content creators | boolean |
| `community_social` | Social features | boolean |
| `order_anywhere` | Order Anywhere fulfillment | boolean |
| `medical_courier` | Medical delivery | boolean |
| `load_board` | Freight/load board | boolean |
| `book_anything` | Service booking | boolean |
| `earn_money` | Gig economy | boolean |
| `photo_uploads` | Photo-assisted features | boolean |
| `video_uploads` | Video features | boolean |
| `measurement_profiles` | Fashion Fit | boolean |
| `appointment_booking` | Service providers | boolean |
| `rental_calendar` | Rentals | boolean |
| `customer_messaging` | Direct messaging | boolean |
| `driver_dispatch` | Delivery dispatch | boolean |
| `proof_of_delivery` | Delivery verification | boolean |
| `payment_splits` | Split payments | boolean |
| `wallet_payouts` | Wallet payouts | boolean |
| `shoppable_tags` | Creator commerce | boolean |
| `before_after_media` | Service providers | boolean |
| `chain_of_custody` | Medical courier | boolean |

---

## 3. RECOMMENDED SCHEMA: BUSINESS TYPES & CAPABILITIES

### 3.1 Design Principles

1. **Extend, don't replace** — Use the existing `module_type` system as the foundation. Urban Goodz types extend it.
2. **Capabilities, not types** — A business can have multiple capabilities. A "Beauty Supply" can also offer "Order Anywhere" and "Creator Campaigns."
3. **Admin sections are capability-driven, not type-driven** — If a business enables "photo_uploads," show the file upload admin section regardless of business type.
4. **Leverage existing Store model** — Add new fields to the Store model via migration rather than creating new top-level tables where possible.

### 3.2 Recommended New Tables

#### `urban_goodz_business_types`

Defines all Urban Goodz business types with their base capability set.

```sql
CREATE TABLE urban_goodz_business_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,          -- 'restaurant', 'beauty_supply', etc.
    display_name VARCHAR(255) NOT NULL,          -- 'Beauty Supply / Hair Provider'
    description TEXT NULL,
    module_type VARCHAR(50) NULL,               -- FK to existing module_type (ecommerce, food, etc.) or NULL for UG-only
    icon VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Seed data:**

| slug | display_name | module_type | sort_order |
|------|-------------|-------------|-----------|
| restaurant | Restaurant / Food Truck | food | 10 |
| food_truck | Food Truck | food | 11 |
| grocery | Grocery / Market | grocery | 20 |
| retail | Retail / Shopping | ecommerce | 30 |
| beauty_supply | Beauty Supply / Hair Provider | ecommerce | 40 |
| pharmacy | Pharmacy / Health | pharmacy | 50 |
| liquor | Liquor / Beverage | ecommerce | 60 |
| service_provider | Professional Services | ecommerce | 70 |
| rental | Rental Provider | rental | 80 |
| event | Event Organizer | NULL | 90 |
| courier | Courier / Parcel | parcel | 100 |
| medical_courier | Medical Courier | parcel | 110 |
| load_board | Load Board / Freight | parcel | 120 |
| fashion_fit | Fashion Fit / Tailor | NULL | 130 |
| creator | Creator / Influencer | NULL | 140 |
| community | Community / Social | NULL | 150 |
| order_anywhere | Order Anywhere (non-partner) | NULL | 160 |
| earn_money | Earn Money / Gig | NULL | 170 |
| book_anything | Book Anything / Service | NULL | 180 |

#### `urban_goodz_capabilities`

Defines all possible capabilities a business can have.

```sql
CREATE TABLE urban_goodz_capabilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(100) UNIQUE NOT NULL,          -- 'menu_management', 'product_inventory', etc.
    display_name VARCHAR(255) NOT NULL,          -- 'Menu Management'
    description TEXT NULL,
    group_name VARCHAR(100) NOT NULL,            -- 'ordering', 'inventory', 'media', 'messaging', etc.
    icon VARCHAR(255) NULL,
    is_core BOOLEAN DEFAULT FALSE,               -- Core = always available for this business type
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Seed data (organized by group):**

**Group: ordering**
- `menu_management` — Menu/item CRUD
- `product_inventory` — Product inventory
- `variant_inventory` — Variants (size/color)
- `appointment_booking` — Appointment scheduling
- `quote_requests` — Quote-based ordering
- `order_anywhere_fulfillment` — Fulfill Order Anywhere requests
- `subscription_orders` — Recurring subscriptions

**Group: media**
- `photo_uploads` — Photo uploads
- `video_uploads` — Video uploads
- `measurement_profiles` — Fashion Fit measurements
- `before_after_media` — Before/after service photos
- `shoppable_tags` — Tag products in media

**Group: delivery**
- `delivery` — Standard delivery
- `pickup` — Customer pickup
- `self_delivery` — Store-managed delivery
- `driver_dispatch` — Platform driver dispatch
- `proof_of_delivery` — Delivery photo proof
- `chain_of_custody` — Medical courier custody chain

**Group: calendar**
- `rental_calendar` — Rental availability
- `service_area` — Service area definition
- `event_schedule` — Event scheduling
- `availability_slots` — Time slot booking

**Group: messaging**
- `customer_messaging` — Customer communication
- `vendor_messaging` — Vendor communication
- `driver_messaging` — Driver communication
- `creator_messaging` — Creator communication
- `admin_messaging` — Admin communication

**Group: social**
- `community_posts` — Social post creation
- `community_comments` — Social commenting
- `community_reactions` — Social reactions/likes
- `creator_profiles` — Creator profile pages
- `creator_campaigns` — Creator-vendor campaigns
- `creator_commissions` — Commission tracking

**Group: payments**
- `direct_checkout` — Direct payment checkout
- `payment_splits` — Split payment distribution
- `wallet_payouts` — Wallet-based payouts
- `deposits` — Deposit/booking fees
- `late_fees` — Late return fees

**Group: compliance**
- `chain_of_custody` — Medical custody chain
- `driver_certification` — Certified drivers only
- `compliance_docs` — Compliance documentation
- `privacy_review` — Photo privacy review
- `face_blur` — Face blurring

**Group: moderation**
- `content_moderation` — Moderate user content
- `report_handling` — Handle user reports
- `user_blocking` — Block/suspend users

#### `urban_goodz_business_capabilities`

Links a business (Store) to its enabled capabilities. This is the central table that determines what admin sections and workflows are available.

```sql
CREATE TABLE urban_goodz_business_capabilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    store_id BIGINT UNSIGNED NOT NULL,               -- FK to stores
    capability_slug VARCHAR(100) NOT NULL,            -- FK to capabilities.slug
    is_enabled BOOLEAN DEFAULT TRUE,
    config JSON NULL,                                 -- Per-capability config (e.g., fee amounts, time limits)
    enabled_by BIGINT UNSIGNED NULL,                  -- Admin who enabled it
    enabled_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY unique_store_capability (store_id, capability_slug),
    FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
);
```

#### `urban_goodz_business_type_default_capabilities`

Defines which capabilities are enabled by default for each business type.

```sql
CREATE TABLE urban_goodz_business_type_default_capabilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    business_type_slug VARCHAR(100) NOT NULL,         -- FK to business_types.slug
    capability_slug VARCHAR(100) NOT NULL,             -- FK to capabilities.slug
    is_default BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    UNIQUE KEY unique_type_capability (business_type_slug, capability_slug)
);
```

### 3.3 Store Model Extensions

Add to `stores` table via migration (use `Schema::hasColumn` checks):

```php
$table->string('business_type_slug')->nullable()->after('module_id')->index();
// business_type_slug points to urban_goodz_business_types.slug
// This determines default capability set
```

This safely extends the existing `stores` table without breaking existing 6amMart logic.

### 3.4 config/module.php Extension

Add new Urban Goodz module types to `config/module.php`:

```php
'beauty' => [
    'stock' => true,
    'unit' => true,
    'brand' => true,
    'variant_inventory' => true,     // new: size/color/length variants
    'appointment_booking' => true,    // new: book appointments
    'creator_promotions' => true,     // new: creator product tagging
    'description' => 'Beauty Supply with product inventory, brand management, size/color/length variants, and optional appointment booking.',
    'is_rental' => false,
],
'fashion' => [
    'stock' => false,
    'unit' => false,
    'add_on' => false,
    'measurement_profiles' => true,   // new: customer measurement profiles
    'photo_uploads' => true,          // new: photo-assisted measurements
    'face_blur' => true,              // new: privacy face blur
    'quote_requests' => true,         // new: stylist quotes
    'description' => 'Fashion Fit with measurement profiles, photo uploads, privacy controls, and stylist quote workflow.',
    'is_rental' => false,
],
'creator' => [
    'video_uploads' => true,          // new: video content
    'shoppable_tags' => true,         // new: tag products in videos
    'creator_commissions' => true,    // new: commission tracking
    'customer_messaging' => true,     // new: fan messaging
    'description' => 'Creator Space with video uploads, shoppable tags, and commission-based campaign management.',
    'is_rental' => false,
],
'community' => [
    'community_posts' => true,        // new: social posts
    'community_comments' => true,     // new: comments
    'community_reactions' => true,    // new: likes/reactions
    'photo_uploads' => true,          // new: post photos
    'video_uploads' => true,          // new: post videos
    'content_moderation' => true,     // new: moderation
    'report_handling' => true,        // new: user reports
    'description' => 'Community/Social feed with posts, comments, reactions, media uploads, and moderation tools.',
    'is_rental' => false,
],
'service' => [
    'appointment_booking' => true,    // new: book services
    'service_area' => true,           // new: define service area
    'quote_requests' => true,         // new: request quotes
    'deposits' => true,               // new: booking deposits
    'before_after_media' => true,     // new: service portfolio
    'description' => 'Service Provider with appointment booking, service area, quotes, deposits, and portfolio management.',
    'is_rental' => false,
],
'events' => [
    'event_schedule' => true,         // new: event calendar
    'ticket_management' => true,      // new: ticket sales
    'vendor_booths' => true,          // new: vendor applications
    'creator_collaboration' => true,  // new: creator partnerships
    'description' => 'Event management with scheduling, ticketing, vendor booths, and creator collaborations.',
    'is_rental' => false,
],
```

### 3.5 Admin Section Mapping Table

SQL or config that maps capabilities to admin view sections:

```php
// config/urban_goodz_admin_sections.php
return [
    'menu_management' => [
        'section' => 'menu',
        'title' => 'Menu Management',
        'route_prefix' => 'admin.urban-goodz.menu',
        'views' => ['items.index', 'items.edit', 'items.create', 'categories.index'],
        'weight' => 10,
    ],
    'product_inventory' => [
        'section' => 'products',
        'title' => 'Product Inventory',
        'route_prefix' => 'admin.urban-goodz.products',
        'views' => ['products.index', 'products.edit', 'products.create', 'categories.index'],
        'weight' => 10,
    ],
    'variant_inventory' => [
        'section' => 'variants',
        'title' => 'Product Variants',
        'route_prefix' => 'admin.urban-goodz.variants',
        'parent' => 'product_inventory',
        'weight' => 15,
    ],
    'photo_uploads' => [
        'section' => 'media',
        'title' => 'Photo Library',
        'route_prefix' => 'admin.urban-goodz.media.photos',
        'weight' => 30,
    ],
    'measurement_profiles' => [
        'section' => 'fashion-fit',
        'title' => 'Fashion Fit Profiles',
        'route_prefix' => 'admin.urban-goodz.fashion-fit',
        'views' => ['fashion-measurements.index', 'fashion-measurements.view'],
        'weight' => 40,
    ],
    'appointment_booking' => [
        'section' => 'appointments',
        'title' => 'Appointments',
        'route_prefix' => 'admin.urban-goodz.appointments',
        'views' => ['appointments.index', 'appointments.calendar'],
        'weight' => 50,
    ],
    // ... etc for all capabilities
];
```

---

## 4. ADMIN PANEL SECTIONS BY BUSINESS TYPE

### 4.1 Restaurant / Food Truck

**Module type:** `food`
**Base capabilities:** menu_management, add_ons, veg_non_veg, delivery, pickup, driver_dispatch
**Optional capabilities:** self_delivery, subscription_orders, order_anywhere_fulfillment, customer_messaging

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Menu | Items list, item edit/create, categories, add-ons, modifiers | Existing (6amMart) |
| Prep Times | Prep time settings per item/category | Existing |
| Open/Closed | Store hours, off days, scheduled closing | Existing |
| Kitchen Status | Order prep tracking, status updates | Existing |
| Orders | Order list, detail, status updates, assign driver | Existing |
| Delivery/Pickup | Zone config, delivery charges, pickup settings | Existing |
| Specials | Daily specials, flash sales, combo deals | Existing |
| Order Anywhere | Request list, quotes, fulfillment *(if enabled)* | New |
| Customer Messages | Conversation list, send message *(if enabled)* | Existing (general) |

### 4.2 Grocery / Market

**Module type:** `grocery`
**Base capabilities:** product_inventory, stock, unit, organic, nutrition, allergy, delivery, pickup, driver_dispatch
**Optional capabilities:** self_delivery, subscription_orders, order_anywhere_fulfillment, customer_messaging

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Products | Product list, edit/create, categories, brands | Existing |
| Inventory | Stock levels, low stock warnings, out-of-stock | Existing |
| Substitutions | Substitution rules, alternate products | New |
| Weighted Items | Per-unit pricing, weight-based items | Existing |
| Categories | Category tree, module-specific categories | Existing |
| Shopper Notes | Order notes, special instructions | Existing |
| Orders | Order list, detail, status updates | Existing |
| Order Anywhere | Request list, quotes, fulfillment *(if enabled)* | New |

### 4.3 Retail / Shopping

**Module type:** `ecommerce`
**Base capabilities:** product_inventory, stock, unit, brand, variant_inventory (size/color), delivery, pickup
**Optional capabilities:** self_delivery, quote_requests, order_anywhere_fulfillment, customer_messaging, creator_campaigns

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Products | Product list, edit/create, categories, brands | Existing |
| Variants | Size/color/variant management, SKU tracking | Existing (partial) |
| Inventory | Stock levels, low stock | Existing |
| Collections | Product collections, featured sets | Existing |
| Returns | Return requests, refund processing | Existing |
| Orders | Order list, detail, status | Existing |
| Quote Requests | Customer quote requests *(if enabled)* | New |
| Creator Campaigns | Creator partnerships, product tagging *(if enabled)* | New |
| Order Anywhere | Request list, quotes *(if enabled)* | New |

### 4.4 Beauty Supply / Hair Provider

**Module type:** `ecommerce` (extended with beauty-specific flags)
**Base capabilities:** product_inventory, stock, brand, variant_inventory, photo_uploads, local_delivery
**Optional capabilities:** appointment_booking, order_anywhere_fulfillment, creator_promotions, customer_messaging

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Products | Product list, edit/create | Existing |
| Brands | Brand management (hair, skincare, cosmetics) | Existing |
| Hair Category | Texture (straight, wavy, curly), length, color | New |
| Variants | Size/color/length variants, bundle management | New |
| Inventory | Stock levels, low stock | Existing |
| Appointments | Booking calendar *(if enabled)* | New |
| Local Delivery | Delivery zone config, radius pricing | New |
| Creator Promotions | Creator product tagging *(if enabled)* | New |
| Orders | Order list, detail | Existing |
| Order Anywhere | Request list *(if enabled)* | New |

### 4.5 Service Provider

**Module type:** New `service` type
**Base capabilities:** appointment_booking, service_area, quote_requests, deposits, before_after_media, customer_messaging
**Optional capabilities:** photo_uploads, video_uploads, order_anywhere_fulfillment, driver_dispatch, proof_of_delivery

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Services | Service list, pricing, descriptions, duration | New |
| Booking Calendar | Appointment calendar, time slots, availability | New |
| Quotes | Quote requests, pricing, approval | New |
| Service Area | Coverage zone, travel fees, radius | New |
| Deposits | Deposit rules, refund policies | New |
| Before/After Media | Service portfolio, photos, approvals | New |
| Customer Messages | Messaging, appointment reminders | New |
| Orders | Service orders, status | New |

### 4.6 Rental Provider

**Module type:** `rental`
**Base capabilities:** rental_calendar, product_inventory, photo_uploads, deposits, late_fees, damage_reports, driver_dispatch
**Optional capabilities:** self_delivery, pickup, proof_of_delivery, customer_messaging, order_anywhere_fulfillment

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Inventory | Vehicles/equipment list, details, photos | Existing (Rental module) |
| Availability Calendar | Booking calendar, blackout dates | Existing |
| Rates | Pricing tiers, hourly/daily/weekly, seasonals | Existing |
| Deposits | Security deposit rules | Existing |
| Pickup/Return | Check-in/check-out workflow | Existing |
| Late Fees | Fee rules, grace periods | New |
| Damage Reports | Damage documentation, billing | New |
| Orders | Rental orders, status | Existing |
| Delivery | Delivery/pickup scheduling *(if enabled)* | New |
| Order Anywhere | Request list *(if enabled)* | New |

### 4.7 Courier

**Module type:** `parcel`
**Base capabilities:** driver_dispatch, proof_of_delivery, tracking, package_details, customer_messaging
**Optional capabilities:** self_delivery, photo_uploads, chain_of_custody, order_anywhere_fulfillment

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Pickup/Dropoff | Addresses, schedules, instructions | Existing (parcel) |
| Package Details | Size, weight, contents, special handling | Existing |
| Driver Assignment | Dispatch, status tracking | Existing (partial) |
| Proof of Pickup | Pickup confirmation, photos | New |
| Proof of Delivery | Delivery confirmation, photos, signature | New |
| Tracking | Real-time tracking, status updates | Existing |
| Orders | Parcel orders list, detail | Existing |
| Order Anywhere | Request list *(if enabled)* | New |

### 4.8 Medical Courier

**Module type:** `parcel` (extended)
**Base capabilities:** driver_dispatch, chain_of_custody, compliance_docs, driver_certification, proof_of_delivery, tracking
**Optional capabilities:** photo_uploads, customer_messaging, order_anywhere_fulfillment

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Chain of Custody | Custody log, signatures, timestamps | New |
| Compliance Docs | HIPAA/compliance document management | New |
| Special Handling | Temperature, biohazard, fragile handling | New |
| Driver Certification | Certified driver management, training docs | New |
| Pickup/Dropoff | Addresses, access instructions | Existing |
| Proof of Delivery | Delivery confirmation with chain-of-custody | New |
| Tracking | Real-time GPS, status | Existing |
| Orders | Medical courier orders | New |

### 4.9 Order Anywhere

**Not a business type** — it's a **fulfillment capability** that any business can enable.
**Module type:** Varies (depends on the underlying business)
**Base capabilities:** order_anywhere_fulfillment, quote_requests, receipt_uploads, customer_messaging, payment_splits

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Requests | Request list, filters by status/business | Existing |
| Request Detail | Customer info, store info, uploaded files/photos, status | Existing |
| Create Quote | Item subtotal, service fee, delivery fee, tax, tip, total | Partial (no itemized fields) |
| Payment Status | quote_pending → payment_pending → staged_test → cod_approved → paid → failed → refund_pending → refunded | New |
| Ledger/Splits | Payment ledger, platform/vendor/driver splits | Existing |
| Assign Driver | Vendor + driver assignment | Existing |
| Receipt/Proof | Upload receipts, proof files | Partial (not in urban_goodz_files) |
| Customer Messages | In-app messaging with customer | New |
| Vendor/Driver Messages | In-app messaging with vendor/driver | New |
| Close/Cancel/Refund | Workflow buttons with reason | Partial |

### 4.10 Fashion Fit

**Module type:** New `fashion` type
**Base capabilities:** measurement_profiles, photo_uploads, quote_requests, face_blur, privacy_review, customer_messaging
**Optional capabilities:** video_uploads, order_anywhere_fulfillment, creator_campaigns

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Measurement Profiles | Profile list, filters by status | Existing |
| Profile Detail | Customer info, measurements, photos, status | Partial (no photo gallery) |
| Photo Gallery | Front/side/back photos with zoom, privacy blur | New |
| Stylist/Tailor Assignment | Assign stylist, view assignee | New |
| Create Quote | Quote/bid creation, adjustments | Partial |
| Request More Photos | Send request to customer | New |
| Customer Messages | In-app messaging | New |
| Privacy Review | Privacy status, face blur controls | Existing (partial) |
| Payment | Fees, ledger integration | New |
| Completed/Cancelled | Workflow buttons | Partial |

### 4.11 Creator Space

**Module type:** New `creator` type
**Base capabilities:** video_uploads, shoppable_tags, creator_commissions, creator_campaigns, customer_messaging, content_moderation
**Optional capabilities:** photo_uploads, community_posts, community_comments, event_collaboration

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Creator Applications | Application list, review, approve/reject | JSON-backed (needs DB) |
| Creator Profiles | Profile management, metrics, verification | New |
| Videos | Video list, moderation, featured | New |
| Video Detail | Video view, shoppable tags, comments, metrics | New |
| Campaigns | Campaign management, vendor partnerships, commission rates | New |
| Commissions | Commission records, payouts, reports | New |
| Moderation | Reported videos, content flags, actions | New |
| Messages | Creator-customer, creator-vendor messaging | New |

### 4.12 Community / Social

**Module type:** New `community` type (or platform-level, not per-store)
**Base capabilities:** community_posts, community_comments, community_reactions, photo_uploads, video_uploads, content_moderation, report_handling, user_blocking

| Admin Section | Screens | Priority |
|---------------|---------|----------|
| Posts | Post list, visibility, featured, remove | New |
| Post Detail | Post view, comments, reactions, linked media | New |
| Reports | Reported posts, comments, users; take action | New |
| Comments | Comment moderation, remove, user ban | New |
| Media Library | All community photos/videos, filter by user | New |
| Moderation Queue | Flagged content queue, bulk actions | New |
| User Management | User profiles, post history, blocks, warnings | New |
| Linked Content | Posts linked to stores/products/events | New |

---

## 5. CAPABILITY SYSTEM DESIGN

### 5.1 How It Works

```
Business Type (e.g., "Beauty Supply")
    │
    ├── Default Capabilities (from urban_goodz_business_type_default_capabilities)
    │   ├── product_inventory ✓
    │   ├── stock ✓
    │   ├── brand ✓
    │   ├── variant_inventory ✓
    │   └── local_delivery ✓
    │
    └── Optional Capabilities (admin can enable)
        ├── appointment_booking [off]
        ├── order_anywhere_fulfillment [off]
        ├── creator_promotions [off]
        └── customer_messaging [off]

Admin Sidebar is built from URBAN_GOODZ_BUSINESS_CAPABILITIES.where(store_id=X, is_enabled=true)
    → Maps to admin sections via config/urban_goodz_admin_sections.php
    → Only shows sections for enabled capabilities
```

### 5.2 Capability Inheritance

```
Store
  ├── module_id ────────────→ determines base 6amMart module type (food, grocery, etc.)
  ├── business_type_slug ──→ determines Urban Goodz default capabilities
  └── urban_goodz_business_capabilities ──→ per-store capability overrides
```

### 5.3 Capability-to-Admin-Section Mapping

The mapping lives in a config file (`config/urban_goodz_admin_sections.php`) and is used by:

1. **Sidebar builder** — Iterates enabled capabilities, renders matching admin sections
2. **Route registrar** — Conditionally registers routes for enabled sections
3. **Admin middleware** — Gating access to sections based on business capabilities

### 5.4 Business Type Assignment Flow

```
Vendor Registration
      │
      ├── Select Business Type (from urban_goodz_business_types)
      │     └── e.g., "Beauty Supply / Hair Provider"
      │
      ├── System assigns default capabilities
      │     └── INSERT into urban_goodz_business_capabilities
      │
      ├── Admin reviews and enables optional capabilities
      │     └── UPDATE is_enabled = true for optional capabilities
      │
      └── Store is created/updated with business_type_slug
            └── Admin sidebar shows capability-driven sections
```

### 5.5 Backward Compatibility

- Existing stores without `business_type_slug` → continue working with module-type-based behavior
- `business_type_slug` is NULLable → all existing queries unaffected
- `urban_goodz_business_capabilities` is an entirely new table → no migration risk
- Existing 6amMart module system unchanged → core food/grocery/pharmacy/ecommerce functionality untouched

---

## 6. "EVERY BUTTON HAS AN ENDING" — FULL ACTION MAP

### 6.1 Customer App: Upload Fashion Fit Photo

| Step | Component | What Happens |
|------|-----------|-------------|
| 1 | Customer App: MeasurementProfileScreen | User taps "Take or upload photo" → picks image → calls `FashionMeasurementApiService.uploadMeasurementPhoto()` |
| 2 | Customer App: FashionMeasurementApiService | Calls `POST /api/v1/urban-goodz/fashion-fit/photos/upload` with multipart form (photo file, category, measurement_profile_id) |
| 3 | Backend: FashionFitFileController@uploadPhoto | Validates file (jpg/jpeg/png/webp, max 10MB), validates category (front/side/back) |
| 4 | Backend: UrbanGoodzFileStorageService | Stores file to `storage/app/public/urban_goodz/fashion_fit/measurement_profiles/{userId}/{category}/` |
| 5 | Backend: urban_goodz_files table | Creates record with file_id, owner_id=measurement_profile_id, owner_type=MeasurementRequest, visibility=customer_private |
| 6 | Backend: FashionFitFileController | Returns JSON with file_id, url, orientation, status="uploaded" |
| 7 | Customer App: MeasurementPhotoModel | Stores returned file_id locally in _draftPhotos map |
| 8 | Admin: Fashion Measurement view | Admin views photo via `GET /admin/urban-goodz/fashion-fit/{id}` → photo displayed from stored URL |
| 9 | Admin: Privacy review | Admin sets `privacy_review_status` → API `POST admin/urban-goodz/fashion/measurements/{id}/privacy-status` |
| 10 | Notification | When status changes: push notification to customer "Your photo has been reviewed" |

### 6.2 Customer App: Submit Order Anywhere Request

| Step | Component | What Happens |
|------|-----------|-------------|
| 1 | Customer App: Order Anywhere screen | User fills item details, store info, budget → taps "Submit Request" |
| 2 | Customer App: API call | `POST /api/v1/order-anywhere/requests` with customer info, store name, item details |
| 3 | Backend: OrderAnywhereTesterController@store | Validates and creates `OrderAnywhereRequest` record |
| 4 | Backend: order_anywhere_requests table | Record created with status=`pending_review` |
| 5 | Notification | Push to admin(s): "New Order Anywhere request from [customer]" |
| 6 | Admin: Order Anywhere list | Admin sees new request in `GET /admin/urban-goodz/order-anywhere` |
| 7 | Admin: View detail | Admin opens `GET /admin/urban-goodz/order-anywhere/{id}` → sees customer info, store info, item details |
| 8 | Admin: Assign vendor/driver | Admin fills `PUT /admin/urban-goodz/order-anywhere/{id}/assign` with vendor_id and delivery_man_id |
| 9 | Notification | Push to vendor: "New Order Anywhere request assigned to you" |
| 10 | Notification | Push to driver: "New delivery task assigned" |
| 11 | Admin: Create quote | Admin fills `PUT /admin/urban-goodz/order-anywhere/{id}/quote` with amount, notes |
| 12 | Backend: UrbanGoodzPaymentService@quoteOrderAnywhere | Sets quote_amount, creates ledger entry |
| 13 | Notification | Push to customer: "Your Order Anywhere quote is ready" |
| 14 | Admin: Capture payment | Admin fills `PUT /admin/urban-goodz/order-anywhere/{id}/capture` with amounts, splits |
| 15 | Backend: UrbanGoodzPaymentService@captureOrderAnywhere | Creates ledger, creates platform/vendor/driver splits |
| 16 | Admin: Upload receipt | Admin fills `POST /api/v1/order-anywhere/requests/{record}/receipt` with receipt file |
| 17 | Backend: OrderAnywhereTesterController@uploadReceipt | Stores receipt file, updates receipt_path |
| 18 | Admin: Message customer | Admin sends message via messaging system |
| 19 | Admin: Complete request | Admin sets status to `completed` via `PUT /admin/urban-goodz/order-anywhere/{id}/status` |
| 20 | Notification | Push to customer: "Your Order Anywhere request is complete" |

### 6.3 Business/Vendor: Receives Order Anywhere Request

| Step | Component | What Happens |
|------|-----------|-------------|
| 1 | Backend: Admin assigns vendor | `PUT /admin/urban-goodz/order-anywhere/{id}/assign` with vendor_id |
| 2 | Notification | Push to vendor panel: "New Order Anywhere request" |
| 3 | Vendor Panel: Order Anywhere list | Vendor sees request in `/vendor/urban-goodz/order-anywhere` |
| 4 | Vendor Panel: View detail | Vendor opens request detail |
| 5 | Vendor: Accept/Decline | Vendor fills `POST /api/v1/vendor/requests/{record}/update` with vendor_status=`accepted` or `declined` |
| 6 | Vendor: Submit quote | Vendor fills vendor_quote_amount |
| 7 | Notification | Push to admin: "Vendor responded to Order Anywhere request" |
| 8 | Vendor: Update status | Vendor updates vendor_status through lifecycle |
| 9 | Admin: Reviews vendor response | Admin sees vendor status/quote in detail view |

### 6.4 Driver: Receives Delivery Task

| Step | Component | What Happens |
|------|-----------|-------------|
| 1 | Backend: Admin assigns driver | `PUT /admin/urban-goodz/order-anywhere/{id}/assign` with assigned_delivery_man_id |
| 2 | Notification | Push to driver app: "New delivery task assigned" |
| 3 | Driver App: View task | Driver sees request in `/delivery-man/urban-goodz/order-anywhere` |
| 4 | Driver: Accept task | Driver calls `POST /api/v1/driver/{record}/accept` → driver_status=`accepted` |
| 5 | Driver: Pickup | Driver calls `POST /api/v1/driver/{record}/status` → driver_status=`picked_up` |
| 6 | Driver: En route | Driver calls status update → driver_status=`en_route` |
| 7 | Driver: Delivered | Driver calls status update → driver_status=`delivered` |
| 8 | Backend: Proof of delivery | Driver uploads delivery photo → stored in urban_goodz_files |
| 9 | Driver: Report issue (optional) | Driver calls `POST /api/v1/driver/{record}/issue` with driver_notes |
| 10 | Notification | Push to customer: "Your order has been delivered" |
| 11 | Notification | Push to admin: "Delivery completed" |

### 6.5 AI Concierge: Customer Asks a Question

| Step | Component | What Happens |
|------|-----------|-------------|
| 1 | Customer App: AI Concierge screen | User types "restaurants near me" |
| 2 | Customer App: API call | `POST /api/v1/urban-goodz/ai/query` with query text |
| 3 | Backend: AI Controller | Matches query against `urban_goodz_ai_keywords` → finds intent=`restaurant_search` |
| 4 | Backend: Intent Router | Returns intent action: `show_restaurant_results` |
| 5 | Customer App: Routes to restaurant search | Shows restaurant/store results (not fashion fit, not generic answer) |
| 6 | Backend: Response log | Logs query, matched intent, confidence in `urban_goodz_ai_response_logs` |
| 7 | Admin: Review logs | Admin views response logs in AI Concierge admin section |
| 8 | Admin: Improve routing | Admin adds keywords, adjusts fallback responses |

### 6.6 Community: Customer Makes a Post

| Step | Component | What Happens |
|------|-----------|-------------|
| 1 | Customer App: Community screen | User creates post with text + photo |
| 2 | Customer App: API call | `POST /api/v1/urban-goodz/community/posts` with content and media |
| 3 | Backend: Community controller | Creates `urban_goodz_community_posts` record |
| 4 | Backend: urban_goodz_post_media | Creates media records linked to urban_goodz_files |
| 5 | Notification | Push to followers: "[User] made a new post" |
| 6 | Admin: Moderation | Post appears in admin moderation queue with `moderation_status=pending` |
| 7 | Admin: Review post | Admin approves/rejects post |
| 8 | Admin: Report handling | If reported, post appears in reports queue |
| 9 | Admin: User action | Admin can warn, suspend, or ban user |

---

## 7. FIRST CODING SPRINT RECOMMENDATION

### Sprint: "Business Type Foundation + Order Anywhere Completion"

**Duration estimate:** 2-3 weeks (single developer)

### Sprint Backlog (ordered by dependency)

#### Tier 1 — Foundation (Build first, everything depends on it)

| # | Task | Files | Acceptance Criteria |
|---|------|-------|-------------------|
| 1 | Create `urban_goodz_business_types` table + seeder | New migration + seeder | 19 business types seeded |
| 2 | Create `urban_goodz_capabilities` table + seeder | New migration + seeder | 40+ capabilities seeded with groups |
| 3 | Create `urban_goodz_business_type_default_capabilities` table + seeder | New migration + seeder | Default capabilities per type |
| 4 | Create `urban_goodz_business_capabilities` table | New migration | Links stores to enabled capabilities |
| 5 | Add `business_type_slug` to stores table | Migration (with Schema::hasColumn) | Store model updated |
| 6 | Create `config/urban_goodz_admin_sections.php` | New config file | Maps capabilities → admin sections |

#### Tier 2 — Admin File Library (Enables all file-based workflows)

| # | Task | Files | Acceptance Criteria |
|---|------|-------|-------------------|
| 7 | Admin file library route + view | New controller + 2 views | List urban_goodz_files with filters by owner_type, file_category, visibility |
| 8 | File upload endpoint for each category | Extend FashionFitFileController or new | Upload endpoints for: order_anywhere_receipt, pickup_proof, delivery_proof |
| 9 | Refactor Order Anywhere receipt to use UrbanGoodzFileStorageService | Edit OrderAnywhereTesterController | Receipts stored in urban_goodz_files |

#### Tier 3 — Order Anywhere Completion

| # | Task | Files | Acceptance Criteria |
|---|------|-------|-------------------|
| 10 | Add itemized pricing columns to order_anywhere_requests | Migration | item_subtotal, service_fee, delivery_fee, tax, tip |
| 11 | Update Order Anywhere detail view with itemized pricing | Edit blade view | Admin can set individual pricing components |
| 12 | Add payment status workflow to admin | Edit controller + view | Admin can set payment_status through all 8 states |
| 13 | Add Order Anywhere sidebar link | Edit _sidebar.blade.php | Direct link to `/admin/urban-goodz/order-anywhere` |
| 14 | Add notification triggers for status changes | Edit controller + NotificationTrait | Push to customer/vendor/driver on relevant status changes |

#### Tier 4 — Fashion Fit Completion

| # | Task | Files | Acceptance Criteria |
|---|------|-------|-------------------|
| 15 | Add photo gallery to Fashion Fit detail view | Edit blade view | Front/side/back photos displayed with zoom |
| 16 | Add stylist/tailor assignment field | Edit controller + view + migration | Admin can assign stylist to measurement request |
| 17 | Add Fashion Fit sidebar link | Edit _sidebar.blade.php | Direct link to `/admin/urban-goodz/fashion-fit` |
| 18 | Integrate Fashion Fit fees with payment ledger | Edit controller + UrbanGoodzPaymentService | Fees create ledger entries |

#### Tier 5 — AI Concierge Foundation

| # | Task | Files | Acceptance Criteria |
|---|------|-------|-------------------|
| 19 | Create urban_goodz_ai_intents table + seeder | Migration + seeder | 15 intents seeded |
| 20 | Create urban_goodz_ai_keywords table + seeder | Migration + seeder | 100+ keywords with weights |
| 21 | Create urban_goodz_ai_fallback_responses table | Migration | Default fallbacks per intent |
| 22 | Create admin AI intent management views | Controller + 3 views | List, edit, test intents |
| 23 | Create customer API endpoint: POST ai/query | New controller | Returns matched intent + action |

### Files to create in this sprint

```
database/migrations/2026_07_07_*.php          -- Business types, capabilities, intents tables
database/seeders/UrbanGoodzBusinessTypeSeeder.php
database/seeders/UrbanGoodzCapabilitySeeder.php
database/seeders/UrbanGoodzAIIntentSeeder.php
config/urban_goodz_admin_sections.php
app/Models/UrbanGoodzBusinessType.php
app/Models/UrbanGoodzCapability.php
app/Models/UrbanGoodzBusinessCapability.php
app/Models/UrbanGoodzAIIntent.php
app/Models/UrbanGoodzAIKeyword.php
app/Models/UrbanGoodzAIFallbackResponse.php
app/Http/Controllers/Admin/UrbanGoodzFileLibraryController.php
app/Http/Controllers/Admin/UrbanGoodzAIConciergeController.php
app/Http/Controllers/Api/V1/UrbanGoodzAIController.php
app/Services/UrbanGoodzIntentRouterService.php
```

### Files to modify in this sprint

```
app/Models/Store.php                          -- Add business_type_slug, capability methods
app/Models/MeasurementRequest.php             -- Add stylist_id, bid fields
app/Models/OrderAnywhereRequest.php           -- Add itemized pricing fields
app/Http/Controllers/Admin/UrbanGoodzAdminController.php  -- Update sidebar, add file library methods
app/Http/Controllers/Api/V1/OrderAnywhereTesterController.php  -- Use UrbanGoodzFileStorageService
app/Http/Controllers/Api/V1/UrbanGoodzFashionMeasurementController.php  -- Link files to storage service
app/Services/UrbanGoodzPaymentService.php     -- Allow Fashion Fit fee integration
routes/admin.php                              -- Add new admin routes
routes/api/v1/urban_goodz.php                 -- Add AI query route
resources/views/layouts/admin/partials/_sidebar.blade.php  -- Add Order Anywhere + Fashion Fit links
resources/views/admin-views/urban-goodz/order-anywhere/show.blade.php  -- Add itemized pricing
resources/views/admin-views/urban-goodz/fashion-measurements/view.blade.php  -- Add photo gallery
```

---

## 8. FILES INSPECTED

### Core System Files
- `config/module.php` — Complete module type capability matrix
- `app/Models/Module.php` — Module model with relationships and scopes
- `app/Models/Store.php` — Store model with Urban Goodz extensions
- `app/Models/StoreConfig.php` — Per-store config flags
- `app/Models/Vendor.php` — Vendor model
- `app/Models/Category.php` — Category model, module-linked
- `app/Models/Item.php` — Item model (no item_type field)
- `app/Models/Order.php` — Order model with module-specific handling
- `app/Models/ModuleZone.php` — Module-zone pivot model
- `app/Models/DeliveryMan.php` — Driver model
- `app/Models/AdminRole.php` — Admin role with permissions

### Urban Goodz Files
- `app/Models/UrbanGoodzSourcedBusiness.php` — Sourced business with business_type, fulfillment_modes
- `app/Models/UrbanGoodzSourcedProduct.php` — Sourced product with item_type (urban goodz specific)
- `app/Models/UrbanGoodzSourcedImage.php` — Polymorphic sourced images
- `app/Models/UrbanGoodzDemandSignal.php` — Demand tracking
- `app/Models/UrbanGoodzImportBatch.php` — Import batch tracking
- `app/Models/MeasurementRequest.php` — Fashion Fit measurement requests
- `app/Models/OrderAnywhereRequest.php` — Order Anywhere requests
- `app/Models/UrbanGoodzPaymentLedger.php` — Payment ledger
- `app/Models/UrbanGoodzPaymentSplit.php` — Payment splits
- `app/Models/UrbanGoodzFile.php` — File storage
- `app/Models/UserFile.php` — Legacy file storage
- `app/Models/Conversation.php` — Chat conversations
- `app/Models/Message.php` — Chat messages

### Controllers
- `app/Http/Controllers/Admin/UrbanGoodzAdminController.php` — Main UG admin
- `app/Http/Controllers/Admin/UrbanGoodzFashionMeasurementController.php` — Fashion Fit admin
- `app/Http/Controllers/Admin/ConversationController.php` — Admin messaging
- `app/Http/Controllers/Admin/FileManagerController.php` — File manager
- `app/Http/Controllers/Admin/CustomerController.php` — Customer admin (suspend)
- `app/Http/Controllers/Admin/VendorController.php` — Vendor/store admin
- `app/Http/Controllers/Admin/DeliveryMan/DeliveryManController.php` — Driver admin
- `app/Http/Controllers/Admin/DashboardController.php` — Admin dashboard
- `app/Http/Controllers/Admin/Notification/NotificationController.php` — Notifications
- `app/Http/Controllers/Api/V1/UrbanGoodzFashionMeasurementController.php` — Fashion Fit API
- `app/Http/Controllers/Api/V1/OrderAnywhereTesterController.php` — Order Anywhere API
- `app/Http/Controllers/Api/V1/CreatorCommerceTesterController.php` — Creator Commerce API (JSON)
- `app/Http/Controllers/Api/V1/UrbanGoodzDiscoveryController.php` — Discovery API
- `app/Http/Controllers/Api/V1/UrbanGoodzOpportunityController.php` — Opportunities (stub)
- `app/Http/Controllers/Api/V1/Admin/UrbanGoodzFashionMeasurementController.php` — Fashion Fit admin API
- `app/Http/Controllers/Api/V1/Vendor/UrbanGoodzFashionMeasurementController.php` — Fashion Fit vendor API
- `app/Http/Controllers/Api/V1/UrbanGoodz/FashionFitFileController.php` — Photo upload API

### Services
- `app/Services/UrbanGoodzIngestionService.php` — Business ingestion (classifyBusiness maps business types)
- `app/Services/UrbanGoodzPaymentService.php` — Payment lifecycle
- `app/Services/UrbanGoodz/UrbanGoodzFileStorageService.php` — File storage
- `app/Support/UrbanGoodzMeasurementSettings.php` — Measurement settings

### Routes
- `routes/admin.php` — All admin routes including UG
- `routes/web.php` — Vendor/delivery-man UG routes
- `routes/api/v1/urban_goodz.php` — All UG API routes
- `routes/api/urban_goodz_measurements.php` — Fashion measurement API routes
- `routes/admin/routes.php` — Admin CRUD routes

### Views
- `resources/views/layouts/admin/partials/_sidebar.blade.php` — Admin sidebar with UG section
- `resources/views/admin-views/urban-goodz/` — 6 UG admin view files
- `resources/views/admin-views/vendor/` — Vendor admin views
- `resources/views/admin-views/module/` — Module management views
- `resources/views/admin-views/dashboard-*.blade.php` — Per-module-type dashboards

### Config
- `config/module.php` — Module type capability matrix
- `config/modules.php` — nwidart/laravel-modules config
- `modules_statuses.json` — Enabled addon modules

### Infrastructure
- `app/Http/Middleware/CurrentModule.php` — Sets current module context
- `app/Http/Middleware/ModuleCheckMiddleware.php` — API module validation
- `app/Http/Middleware/ModulePermissionMiddleware.php` — Module permission gating
- `app/Traits/NotificationTrait.php` — Firebase push notifications
- `app/CentralLogics/Helpers.php` — Permission check helpers

---

## 9. FILES THAT SHOULD NOT BE TOUCHED YET

### Core 6amMart System Files (Do Not Modify)

These files are part of the base 6amMart system and should not be modified for Urban Goodz-specific functionality. Changes risk breaking existing food/grocery/pharmacy/ecommerce operations.

| File | Reason |
|------|--------|
| `app/Models/Item.php` | Core product model used by all modules |
| `app/Models/Order.php` | Core order model with extensive module-specific logic |
| `app/Models/OrderDetail.php` | Order line items |
| `app/Models/Cart.php` | Shopping cart |
| `app/Models/Category.php` | Category hierarchy, shared across modules |
| `app/Models/Unit.php` | Unit of measure, shared |
| `app/Models/Brand.php` | Brand management, shared |
| `app/Models/AddOn.php` | Add-on system (food module) |
| `app/Models/Zone.php` | Zone management, shared |
| `app/Http/Controllers/Admin/VendorController.php` | 2240-line vendor admin controller |
| `app/Http/Controllers/Admin/OrderController.php` | Core order management |
| `app/Http/Controllers/Admin/CategoryController.php` | Category CRUD, shared |
| `app/Http/Controllers/Admin/DashboardController.php` | Main admin dashboard |
| `app/Http/Controllers/Admin/Notification/NotificationController.php` | Push notification admin |
| `app/Http/Controllers/Api/V1/Auth/*` | Authentication controllers |
| `app/CentralLogics/Helpers.php` | 3300+ line helper file |
| `app/Traits/PlaceNewOrder.php` | Core order placement logic |
| `app/Traits/NotificationTrait.php` | Push notification infrastructure |
| `app/Providers/FirebaseServiceProvider.php` | Firebase SDK integration |
| `database/migrations/` files before `2026_07_*` | All pre-Urban-Goodz migrations |

### Customer App Files (Do Not Touch in This Sprint)

The entire customer app (`C:\Users\D'Andre Good\Documents\GitHub\UrbanGoodz2026-Revised`) should not be modified during this admin foundation sprint unless explicitly required to point to a confirmed backend endpoint.

### Vendor App Files (Do Not Touch Yet)

Any vendor panel files outside of `app/Http/Controllers/Vendor/UrbanGoodz*` and `resources/views/vendor-views/urban-goodz/*`.

### Driver App Files (Do Not Touch Yet)

Any delivery man panel files outside of `app/Http/Controllers/DeliveryMan/UrbanGoodz*` and `resources/views/delivery-man-views/urban-goodz/*`.

### ReelsModule Addon (Do Not Touch Yet)

The existing `Modules/ReelsModule/` addon has its own admin screens. Let it continue working independently until Creator Commerce is properly migrated to DB-backed.

---

## CONCLUSION

The existing 6amMart system has a strong module-type-based capability system (`config/module.php`) that covers 7 business types (food, grocery, pharmacy, ecommerce, parcel, rental, ride-share). 

Urban Goodz introduces 12+ new business concepts that don't fit cleanly into these types. The recommended approach is to:
1. **Extend** `config/module.php` with new Urban Goodz module types (beauty, fashion, creator, community, service, events)
2. **Add** a `business_type_slug` to the Store model
3. **Build** the `urban_goodz_business_capabilities` junction table for per-store capability management
4. **Drive** admin sidebar and section visibility from enabled capabilities

The first sprint should focus on the business type foundation, admin file library, Order Anywhere completion, Fashion Fit completion, and AI Concierge intent routing — in that order.

**No customer app changes were made during this audit.**
