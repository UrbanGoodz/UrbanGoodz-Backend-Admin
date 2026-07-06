# Next Admin Foundation Sprint Plan

> **Do not start coding unless explicitly approved.** This document is a planning recommendation only. All tasks must be reviewed, estimated, and approved by the lead developer or project manager before implementation.

---

## Sprint 1: Admin Permission + Business Type Foundation

**Theme:** Lay the groundwork by extending the existing 6amMart permission system and creating the business type registry that all other Urban Goodz features depend on.

### Estimated Duration: 2–3 weeks

### Tasks

#### 1. Add `urban_goodz_*` Module Names to Permission Check System

**Files to edit:**
- `app/Helpers/module_permission_check.php` (or equivalent helper)
- Module permission middleware

**What to do:**
- Extend `Helpers::module_permission_check()` to recognize `urban_goodz_*` module name prefixes
- Add the following module name constants or config entries:
  - `urban_goodz_admin`
  - `urban_goodz_order_anywhere`
  - `urban_goodz_fashion_fit`
  - `urban_goodz_ai_concierge`
  - `urban_goodz_community`
  - `urban_goodz_creator_commerce`
  - `urban_goodz_file_library`
  - `urban_goodz_messages`
  - `urban_goodz_dispatch`
  - `urban_goodz_payments`
  - `urban_goodz_reports`
  - `urban_goodz_settings`
  - `urban_goodz_business_types`
  - `urban_goodz_capabilities`
  - `urban_goodz_earn_money`
  - `urban_goodz_spotlight`
  - `urban_goodz_logistics`
  - `urban_goodz_professional_services`
  - `urban_goodz_events`
- Verify that `AdminRole` and `EmployeeRole` modules JSON columns can store these names
- Verify `ModulePermissionMiddleware` correctly gates routes using the new names

**Acceptance criteria:**
- [ ] `hasModuleAccess('urban_goodz_order_anywhere')` returns true for admins with that module in their role
- [ ] `hasModuleAccess('urban_goodz_order_anywhere')` returns false for admins without it
- [ ] Route-level middleware correctly blocks/grant access for Urban Goodz routes
- [ ] Existing 6amMart module permissions continue to work unchanged

#### 2. Create Migration for `urban_goodz_business_types` Table

**Migration file:** `2026_xx_xx_xxxxxx_create_urban_goodz_business_types_table.php`

**Schema:**
```php
Schema::create('urban_goodz_business_types', function (Blueprint $table) {
    $table->id();
    $table->string('slug', 100)->unique();
    $table->string('display_name', 255);
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Acceptance criteria:**
- [ ] Migration runs successfully up and down
- [ ] Slug is unique and indexed

#### 3. Create Migration for `urban_goodz_capabilities` Table

**Migration file:** `2026_xx_xx_xxxxxx_create_urban_goodz_capabilities_table.php`

**Schema:**
```php
Schema::create('urban_goodz_capabilities', function (Blueprint $table) {
    $table->id();
    $table->string('slug', 100)->unique();
    $table->string('display_name', 255);
    $table->text('description')->nullable();
    $table->string('group', 100)->default('core'); // core, fulfillment, fashion, ai, services, content, social, logistics, monetization, subscription, marketing
    $table->boolean('is_active')->default(true);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

**Acceptance criteria:**
- [ ] Migration runs successfully up and down
- [ ] Slug is unique and indexed

#### 4. Create Migration for `urban_goodz_business_type_capabilities` Pivot Table

**Migration file:** `2026_xx_xx_xxxxxx_create_urban_goodz_business_type_capabilities_table.php`

**Schema:**
```php
Schema::create('urban_goodz_business_type_capabilities', function (Blueprint $table) {
    $table->id();
    $table->foreignId('business_type_id')->constrained('urban_goodz_business_types')->cascadeOnDelete();
    $table->foreignId('capability_id')->constrained('urban_goodz_capabilities')->cascadeOnDelete();
    $table->boolean('is_default')->default(true);
    $table->boolean('is_required')->default(false);
    $table->timestamps();

    $table->unique(['business_type_id', 'capability_id'], 'ug_bt_cap_unique');
});
```

**Acceptance criteria:**
- [ ] Migration runs successfully up and down
- [ ] Unique composite index prevents duplicate mappings

#### 5. Create Migration for Store `business_type_slug` Column

**Migration file:** `2026_xx_xx_xxxxxx_add_business_type_slug_to_stores_table.php`

**Schema:**
```php
Schema::table('stores', function (Blueprint $table) {
    $table->string('business_type_slug', 100)->nullable()->after('zone_id');
    $table->index('business_type_slug');
});
```

**Acceptance criteria:**
- [ ] Migration runs successfully up and down
- [ ] Existing stores are not affected (column is nullable)
- [ ] Index exists on `business_type_slug`

#### 6. Create Seeders for Business Types and Capabilities

**Seeder files:**
- `database/seeders/UrbanGoodzBusinessTypeSeeder.php`
- `database/seeders/UrbanGoodzCapabilitySeeder.php`
- `database/seeders/UrbanGoodzBusinessTypeCapabilityPivotSeeder.php`

**What to do:**
- Seed all 18 business types from the registry with correct slugs and display names
- Seed all 18 capabilities from the registry with correct slugs, display names, and groups
- Seed all business-type-to-capability mappings with correct default/required flags

**Acceptance criteria:**
- [ ] `php artisan db:seed --class=UrbanGoodzBusinessTypeSeeder` inserts 18 rows
- [ ] `php artisan db:seed --class=UrbanGoodzCapabilitySeeder` inserts 18 rows
- [ ] `php artisan db:seed --class=UrbanGoodzBusinessTypeCapabilityPivotSeeder` inserts all default mappings
- [ ] Seeders are idempotent (safe to re-run)

#### 7. Create `config/urban_goodz_admin_sections.php`

**What to do:**
- Define a config array mapping each business type slug to its admin sidebar sections
- Each section entry includes: `route`, `label`, `icon`, `required_module`, `required_capability`, `sort_order`
- This config drives the admin sidebar rendering

**Example structure:**
```php
<?php

return [
    'restaurant' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'tachometer-alt',
            'route' => 'admin.urban-goodz.dashboard',
            'required_module' => 'urban_goodz_admin',
            'required_capability' => null,
            'sort_order' => 10,
        ],
        'orders' => [
            'label' => 'Orders',
            'icon' => 'shopping-cart',
            'route' => 'admin.urban-goodz.orders',
            'required_module' => 'urban_goodz_admin',
            'required_capability' => 'direct-checkout',
            'sort_order' => 20,
        ],
        // ...
    ],
    'fashion-fit' => [
        // ...
    ],
];
```

**Acceptance criteria:**
- [ ] Config file loads correctly via `config('urban_goodz_admin_sections')`
- [ ] All 18 business types have at least dashboard, messages, payouts, reports, settings sections

#### 8. Create `UrbanGoodzAdminController` with Business-Type-Aware Section Routing

**Controller:** `app/Http/Controllers/Admin/UrbanGoodzAdminController.php`

**What to do:**
- Create a single admin controller that renders views based on business type
- `index()` method: loads the store's business type, resolves the config sections for that type, checks module permissions for each section, and returns the filtered sidebar + content
- `section($sectionName)` method: loads and renders a specific admin section view, checks capability middleware

**Acceptance criteria:**
- [ ] Visiting `admin/urban-goodz/dashboard` renders the correct dashboard for the admin's scoped business types
- [ ] Sidebar only shows sections the admin has permission to see
- [ ] Sections not in the business type's config are not rendered

#### 9. Add Admin Sidebar Links for Urban Goodz Sections (Permission-Gated)

**What to do:**
- Edit the admin sidebar view to include a "Urban Goodz" parent menu item
- Add child links for each Urban Goodz section (permission-gated using `hasModuleAccess`)
- Group links logically: Management, Queue, Commerce, Content, System

**Acceptance criteria:**
- [ ] Admin sees "Urban Goodz" in sidebar when they have any `urban_goodz_*` module permission
- [ ] Child links only appear for modules the admin has access to
- [ ] Admin without any `urban_goodz_*` modules does not see the menu item

#### 10. Run Migrations on Staging

**What to do:**
- Deploy all migrations to the staging environment
- Run `php artisan migrate`
- Run seeders
- Verify all tables are created and seeded correctly
- Verify no conflicts with existing 6amMart tables

---

## Sprint 2: Master Admin Order Anywhere + File Library

**Theme:** Build the first major Urban Goodz feature — Order Anywhere — with its admin panel, file storage service, and driver assignment.

### Estimated Duration: 2–3 weeks

### Tasks

#### 1. Complete Order Anywhere Admin Panel

**What to do:**
- Create full admin CRUD/management for `urban_goodz_order_anywhere_requests`
- **List view:** Filterable by status, date range, store, customer. Columns: ID, customer name, request summary, status, created_at, assigned store, quoted amount.
- **Detail view:** Request detail with customer info, request description, itemized pricing, status timeline, photo gallery, proof of delivery, activity log.
- **Actions:** Review (change status to `reviewing`), Quote (enter itemized pricing + total), Assign Store (select from stores with `order-anywhere` capability), Assign Driver (select from available drivers), Mark Fulfilled, Cancel, Refund.

**Files to create:**
- `app/Http/Controllers/Admin/UrbanGoodzOrderAnywhereController.php`
- `resources/views/admin/urban-goodz/order-anywhere/index.blade.php`
- `resources/views/admin/urban-goodz/order-anywhere/show.blade.php`
- Routes in `routes/admin.php`

**Acceptance criteria:**
- [ ] Admin can view the full request queue sorted by pending first
- [ ] Admin can change status through the defined lifecycle
- [ ] Quote action creates a quote record and notifies the customer
- [ ] Assign store filters to stores with `order-anywhere` capability
- [ ] Assign driver filters to available drivers in the zone
- [ ] Proof of delivery photos are visible in the detail view
- [ ] Activity log shows all status changes with timestamps and admin user

#### 2. Add Itemized Pricing Fields Migration

**Migration file:** `2026_xx_xx_xxxxxx_add_itemized_pricing_to_order_anywhere_requests.php`

**Schema:**
```php
Schema::table('urban_goodz_order_anywhere_requests', function (Blueprint $table) {
    $table->json('itemized_items')->nullable()->after('customer_notes');
    $table->decimal('subtotal', 12, 2)->nullable()->after('itemized_items');
    $table->decimal('tax_amount', 12, 2)->nullable()->after('subtotal');
    $table->decimal('delivery_fee', 12, 2)->nullable()->after('tax_amount');
    $table->decimal('total_amount', 12, 2)->nullable()->after('delivery_fee');
    $table->string('currency', 3)->default('USD')->after('total_amount');
    $table->timestamp('quoted_at')->nullable()->after('currency');
    $table->timestamp('accepted_at')->nullable()->after('quoted_at');
    $table->foreignId('quoted_by_admin_id')->nullable()->constrained('users')->after('accepted_at');
});
```

**Acceptance criteria:**
- [ ] Migration runs successfully up and down
- [ ] Itemized pricing stored as JSON with full price breakdown

#### 3. Add Order Anywhere Sidebar Link

**What to do:**
- Register `urban_goodz_order_anywhere` route in admin sidebar
- Add link to Order Anywhere Queue under Urban Goodz → Order Anywhere
- Permission gate with `hasModuleAccess('urban_goodz_order_anywhere')`

**Acceptance criteria:**
- [ ] Admin with `urban_goodz_order_anywhere` permission sees the link
- [ ] Admin without it does not see the link

#### 4. Create File Library Admin View with Filters

**What to do:**
- Create admin view for `urban_goodz_files`
- **List view:** Filterable by file type (`receipt`, `pickup_proof`, `delivery_proof`, `fashion_photo`, `avatar`, `document`), fileable type, date range, uploader
- **Detail/Preview:** Thumbnail preview, download link, file metadata (size, mime type, dimensions, uploaded by, uploaded at, associated entity)
- **Actions:** Delete, Download

**Files to create:**
- `app/Http/Controllers/Admin/UrbanGoodzFileLibraryController.php`
- `resources/views/admin/urban-goodz/files/index.blade.php`
- Routes in `routes/admin.php`

**Acceptance criteria:**
- [ ] Admin can view all uploaded files
- [ ] Filters work independently and combined
- [ ] Preview renders images inline, shows icons for other file types
- [ ] Download triggers file download

#### 5. Add File Upload Endpoints for Receipt / Pickup Proof / Delivery Proof

**What to do:**
- Create API endpoints for file uploads scoped to specific features:
  - `POST /api/v1/urban-goodz/order-anywhere/requests/{id}/receipts` (customer)
  - `POST /api/v1/driver/urban-goodz/tasks/{id}/pickup-proof` (driver)
  - `POST /api/v1/driver/urban-goodz/tasks/{id}/delivery-proof` (driver)
  - `POST /api/v1/urban-goodz/fashion-fit/requests/{id}/photos` (customer)
- Validate file type, size, and count limits per request
- Store in `urban_goodz_files` with correct polymorphic relationship and file type

**Acceptance criteria:**
- [ ] Customers can upload receipts to their Order Anywhere requests
- [ ] Drivers can upload pickup proof to assigned tasks
- [ ] Drivers can upload delivery proof to assigned tasks
- [ ] Customers can upload photos to Fashion Fit requests
- [ ] File type and size validation works
- [ ] Files are immediately available in the File Library admin view

#### 6. Create `UrbanGoodzFileStorageService`

**Service:** `app/Services/UrbanGoodz/UrbanGoodzFileStorageService.php`

**What to do:**
- Create a service class that handles all file storage operations
- Methods:
  - `store(UploadedFile $file, string $fileType, Model $fileable, array $options = [])`: stores file, creates `urban_goodz_files` record
  - `delete($fileId)`: removes file from disk and deletes record
  - `getUrl($fileId)`: returns public URL for the file
  - `getFilesFor(Model $fileable)`: returns collection of files for an entity
  - `validateFile(UploadedFile $file, string $fileType)`: validates mime, size, dimensions
- Storage driver configurable via `.env` (`URBAN_GOODZ_STORAGE_DRIVER` = `local` or `s3`)
- Thumbnail generation for images

**Acceptance criteria:**
- [ ] Service stores files to correct disk path
- [ ] Service creates `urban_goodz_files` record with correct polymorphic data
- [ ] Service validates files before storing
- [ ] Service returns accessible URLs
- [ ] Service handles deletion (file + database record)

---

## Sprint 3: Fashion Fit + AI Concierge Foundation

**Theme:** Build the Fashion Fit admin management features and establish the AI Concierge database, admin management, and API infrastructure.

### Estimated Duration: 2–3 weeks

### Tasks

#### 1. Add Photo Gallery to Fashion Fit Admin Detail View

**What to do:**
- Extend the Fashion Fit admin detail view to display uploaded customer photos
- Gallery layout: thumbnail grid with lightbox preview
- Each photo shows: upload date, file name, dimensions
- Admin can add notes to individual photos
- Photos are fetched from `urban_goodz_files` where `fileable_type` = Fashion Fit request

**Files to edit:**
- `resources/views/admin/urban-goodz/fashion-fit/show.blade.php`
- `app/Http/Controllers/Admin/UrbanGoodzFashionFitController.php`

**Acceptance criteria:**
- [ ] Photos appear in a gallery grid layout
- [ ] Clicking a photo opens a lightbox/modal preview
- [ ] Admin can view photo metadata
- [ ] Gallery is empty-state-aware (shows message when no photos)

#### 2. Add Stylist/Tailor Assignment to Fashion Fit Admin

**What to do:**
- Add a "Assign Stylist" action to the Fashion Fit request detail view
- Stylists/tailors are admin users with `urban_goodz_fashion_fit` module permission
- On assignment: set `assigned_stylist_id`, change status to `measurement_pending`, notify stylist
- Add `assigned_stylist_id` foreign key to `urban_goodz_fashion_fit_requests` table (migration)

**Migration:**
```php
Schema::table('urban_goodz_fashion_fit_requests', function (Blueprint $table) {
    $table->foreignId('assigned_stylist_id')->nullable()->constrained('users')->after('customer_id');
    $table->timestamp('assigned_at')->nullable()->after('assigned_stylist_id');
});
```

**Acceptance criteria:**
- [ ] Admin can assign a stylist from a dropdown of eligible admins
- [ ] Assignment updates the assigned_stylist_id and status
- [ ] Assigned stylist receives notification
- [ ] Stylist assignment is visible in the detail view

#### 3. Create AI Concierge Intents Table + Seeder

**Migration file:** `2026_xx_xx_xxxxxx_create_urban_goodz_ai_intents_table.php`

**Schema:**
```php
Schema::create('urban_goodz_ai_intents', function (Blueprint $table) {
    $table->id();
    $table->string('slug', 100)->unique();
    $table->string('display_name', 255);
    $table->text('description')->nullable();
    $table->string('handler_class', 255)->nullable();
    $table->boolean('is_active')->default(true);
    $table->boolean('requires_escalation')->default(false);
    $table->string('escalation_module', 100)->nullable();
    $table->timestamps();
});
```

**Seeder data:**
- `order_anywhere` — Order Anywhere Request → `UrbanGoodz\Handlers\OrderAnywhereIntentHandler`
- `fashion_fit` — Fashion Fit Inquiry → `UrbanGoodz\Handlers\FashionFitIntentHandler`
- `store_hours` — Store Hours Question → null (AI answers directly)
- `product_question` — Product Question → null (AI answers directly)
- `general` — General Question → null (AI answers directly)
- `complaint` — Complaint → `UrbanGoodz\Handlers\ComplaintIntentHandler` (escalates, requires_escalation = true, escalation_module = `urban_goodz_messages`)

**Acceptance criteria:**
- [ ] Migration runs successfully
- [ ] Seeder inserts 6 default intents
- [ ] Handler classes can be specified for intents that require backend processing

#### 4. Create AI Concierge Conversations Table

**Migration file:** `2026_xx_xx_xxxxxx_create_urban_goodz_ai_conversations_table.php`

**Schema:**
```php
Schema::create('urban_goodz_ai_conversations', function (Blueprint $table) {
    $table->id();
    $table->foreignId('customer_id')->constrained('users');
    $table->foreignId('intent_id')->nullable()->constrained('urban_goodz_ai_intents');
    $table->string('status', 50)->default('open'); // open, resolved, escalated
    $table->text('summary')->nullable();
    $table->integer('message_count')->default(0);
    $table->timestamp('resolved_at')->nullable();
    $table->timestamps();
});

Schema::create('urban_goodz_ai_messages', function (Blueprint $table) {
    $table->id();
    $table->foreignId('conversation_id')->constrained('urban_goodz_ai_conversations')->cascadeOnDelete();
    $table->morphs('sender'); // customer, admin, or system
    $table->text('message');
    $table->json('metadata')->nullable(); // intent_score, suggested_actions, etc.
    $table->timestamps();
});
```

**Acceptance criteria:**
- [ ] Migration runs successfully
- [ ] Conversations and messages are related
- [ ] Sender is polymorphic (customer, admin, or system)

#### 5. Create AI Concierge Admin Management

**What to do:**
- **Intents CRUD:** Admin view to list, create, edit, activate/deactivate intents
  - Fields: slug, display_name, description, handler_class, is_active, requires_escalation, escalation_module
- **Conversations view:** Admin view to list all conversations with filters (intent, status, customer, date range)
  - Click conversation → detail view with full message transcript
  - Admin can reply to escalated conversations
  - Admin can mark conversation as resolved

**Files to create:**
- `app/Http/Controllers/Admin/UrbanGoodzAIConciergeController.php`
- `resources/views/admin/urban-goodz/ai-concierge/intents/index.blade.php`
- `resources/views/admin/urban-goodz/ai-concierge/intents/form.blade.php`
- `resources/views/admin/urban-goodz/ai-concierge/conversations/index.blade.php`
- `resources/views/admin/urban-goodz/ai-concierge/conversations/show.blade.php`
- Routes in `routes/admin.php`

**Acceptance criteria:**
- [ ] Admin can create new intents
- [ ] Admin can edit existing intents (including toggling active/inactive)
- [ ] Admin can view conversation list with working filters
- [ ] Admin can view full conversation transcripts
- [ ] Admin can reply to escalated conversations
- [ ] Admin can mark conversations as resolved

#### 6. Create AI Concierge Customer Query API Endpoint

**Endpoint:** `POST /api/v1/urban-goodz/ai-concierge/ask`

**What to do:**
- Create a controller method that:
  1. Receives customer message
  2. Classifies intent (basic keyword matching or configurable classifier)
  3. Creates/continues conversation
  4. For known intents with handlers: dispatches to handler class
  5. For simple intents: generates AI response (mocked initially, real AI integration later)
  6. For escalation intents: creates admin notification
  7. Returns response to customer

**Files to create:**
- `app/Http/Controllers/Api/V1/UrbanGoodzAIConciergeController.php`
- Routes in `routes/api.php`

**Acceptance criteria:**
- [ ] Customer can send a question and receive a response
- [ ] Intent classification works for the 6 default intents
- [ ] Escalation intents create admin notifications
- [ ] Conversation history is persisted
- [ ] Existing conversation is continued if not resolved

#### 7. Create `urban_goodz_app_config` Endpoint

**Endpoint:** `GET /api/v1/urban-goodz/app-config`

**What to do:**
- Create a dedicated controller method that returns the full app configuration
- Data sources:
  - `enabled_features`: from `urban_goodz_settings` (config-driven)
  - `enabled_modules`: from `urban_goodz_settings`
  - `home_sections`: from `urban_goodz_settings` (JSON config)
  - `business_types`: from `urban_goodz_business_types` table (only active ones)
  - `capabilities`: from `urban_goodz_capabilities` table (only active ones)
  - `feature_routes`: from `config/urban_goodz_app_routes.php`
  - `empty_state_text`: from `urban_goodz_settings`
  - `early_access_labels`: from `urban_goodz_settings`
- Cache the response (cache key: `urban_goodz_app_config`, TTL: 1 hour, invalidate on settings change)

**Files to create:**
- `app/Http/Controllers/Api/V1/UrbanGoodzAppConfigController.php`
- `config/urban_goodz_app_routes.php`
- Routes in `routes/api.php`

**Acceptance criteria:**
- [ ] Endpoint returns valid JSON matching the expected schema
- [ ] Response is cached and invalidated on settings change
- [ ] Only active business types and capabilities are included
- [ ] Flutter app can consume the response without modification
- [ ] Empty state text and early access labels are configurable from admin settings

---

## Appendix: Dependency Graph

```
Sprint 1 ─────────────────────────────────────┐
  ├─ 1. Permission module names               │
  ├─ 2–5. Migrations (business types, blah)   │
  ├─ 6. Seeders                                ├──► All future sprints depend on Sprint 1
  ├─ 7. Admin sections config                  │
  ├─ 8. UrbanGoodzAdminController              │
  ├─ 9. Sidebar links                          │
  └─ 10. Staging deploy                        │
                                               │
Sprint 2 ─────────────────────────────────────┘
  ├─ 1. Order Anywhere admin panel     ──── depends on Sprint 1 (permissions, sections)
  ├─ 2. Itemized pricing migration
  ├─ 3. Sidebar link
  ├─ 4. File library admin view        ──── depends on Sprint 1 (sections)
  ├─ 5. File upload endpoints
  └─ 6. FileStorageService
                                               │
Sprint 3 ─────────────────────────────────────┘
  ├─ 1. Fashion Fit gallery            ──── depends on Sprint 1 (permissions)
  ├─ 2. Stylist/tailor assignment
  ├─ 3. AI Intents table + seeder
  ├─ 4. AI Conversations table
  ├─ 5. AI Concierge admin management  ──── depends on 3 + 4
  ├─ 6. AI Concierge API endpoint      ──── depends on 3 + 4
  └─ 7. App config endpoint            ──── depends on Sprint 1 (business types + capabilities)
```

Any dependencies between sprints are noted above. Sprint 1 is a hard prerequisite for Sprints 2 and 3. Sprint 2 and Sprint 3 can be worked on in parallel once Sprint 1 is complete.
