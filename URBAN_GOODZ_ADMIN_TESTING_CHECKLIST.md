# Urban Goodz Admin Testing Checklist

Date: ___________  Tester: ___________  Environment: ___________

---

## 1. Super Admin Login
- [ ] **URL**: `/admin/auth/login`
- [ ] **Expected**: Login form loads, accepts valid credentials, redirects to dashboard
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 2. Urban Goodz Sidebar
- [ ] **URL**: `/admin/dashboard`
- [ ] **Expected**: Urban Goodz section appears in sidebar with all sub-items:
  Control Center, Rentals (7 sub-items), Business Types, Capabilities, Payments, Order Anywhere, Files, Fashion Fit, AI Concierge, Book Anything, Community, Creator, Logistics, Medical, Earn Money, Events, Urban Goodz+, Spotlight, Discovery
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 3. Control Center Dashboard
- [ ] **URL**: `/admin/urban-goodz`
- [ ] **Expected**: Dashboard loads with section cards/links to all modules
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 4. Business Types — Index
- [ ] **URL**: `/admin/urban-goodz/business-types`
- [ ] **Expected**: Lists all business types with status badges
- [ ] **Actions to test**: Create, Edit, Status toggle, Delete
- [ ] **DB table**: `urban_goodz_business_types`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 5. Business Types — Create
- [ ] **URL**: `/admin/urban-goodz/business-types/create`
- [ ] **Expected**: Form with name, slug, description, icon, status fields
- [ ] **Actions to test**: Submit valid form → redirects to index with success message
- [ ] **DB table**: `urban_goodz_business_types` — new row inserted
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 6. Business Types — Edit
- [ ] **URL**: `/admin/urban-goodz/business-types/{id}/edit`
- [ ] **Expected**: Form pre-filled with existing data
- [ ] **Actions to test**: Change name/status → Submit → verify DB updated
- [ ] **DB table**: `urban_goodz_business_types` — row updated
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 7. Business Types — Capability Mapping
- [ ] **URL**: `/admin/urban-goodz/business-types/{id}/mapping`
- [ ] **Expected**: Shows all capabilities grouped by group, with toggle for required/optional
- [ ] **Actions to test**: Toggle capabilities, save → verify in `urban_goodz_business_type_default_capabilities`
- [ ] **DB table**: `urban_goodz_business_type_default_capabilities`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 8. Capabilities — Index
- [ ] **URL**: `/admin/urban-goodz/capabilities`
- [ ] **Expected**: Lists capabilities with group and section_key columns, filterable by group
- [ ] **Actions to test**: Create, Edit, Delete; filter by group dropdown
- [ ] **DB table**: `urban_goodz_capabilities`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 9. Capabilities — Create
- [ ] **URL**: `/admin/urban-goodz/capabilities/create`
- [ ] **Expected**: Form with name, group, section_key, description, icon fields
- [ ] **Actions to test**: Fill form, submit → row created in DB
- [ ] **DB table**: `urban_goodz_capabilities`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 10. Capabilities — Edit
- [ ] **URL**: `/admin/urban-goodz/capabilities/{id}/edit`
- [ ] **Expected**: Pre-filled form
- [ ] **Actions to test**: Modify, submit → DB updated
- [ ] **DB table**: `urban_goodz_capabilities`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 11. Order Anywhere — Index
- [ ] **URL**: `/admin/urban-goodz/order-anywhere`
- [ ] **Expected**: Lists order-anywhere requests with status badges
- [ ] **Actions to test**: Filter by status, view details
- [ ] **DB table**: `order_anywhere_requests`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 12. Order Anywhere — Show/Details
- [ ] **URL**: `/admin/urban-goodz/order-anywhere/{id}`
- [ ] **Expected**: Full order detail view with customer info, items, pricing, status timeline
- [ ] **Actions to test**: Status update, Quote, Capture, Refund buttons
- [ ] **DB table**: `order_anywhere_requests` — status updated
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 13. Order Anywhere — Quote
- [ ] **URL**: PUT `/admin/urban-goodz/order-anywhere/{id}/quote`
- [ ] **Expected**: Generates quote, sets status to "quoted"
- [ ] **Actions to test**: Click Quote button → verify status change
- [ ] **DB table**: `order_anywhere_requests` — status = 'quoted'
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 14. Order Anywhere — Capture
- [ ] **URL**: PUT `/admin/urban-goodz/order-anywhere/{id}/capture`
- [ ] **Expected**: Captures payment (staged_test mode logs without charging)
- [ ] **Actions to test**: Click Capture → verify status = 'captured'
- [ ] **DB table**: `urban_goodz_payment_ledgers` — new ledger row
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 15. Order Anywhere — Refund
- [ ] **URL**: PUT `/admin/urban-goodz/order-anywhere/{id}/refund`
- [ ] **Expected**: Processes refund (staged_test mode logs without real charge)
- [ ] **Actions to test**: Click Refund → verify status = 'refunded'
- [ ] **DB table**: `urban_goodz_payment_ledgers` — refund ledger row
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 16. Payments / Ledger — Index
- [ ] **URL**: `/admin/urban-goodz/payments`
- [ ] **Expected**: Shows payment ledger with transactions, filters by date/status
- [ ] **Actions to test**: View transactions, verify staged_test entries
- [ ] **DB table**: `urban_goodz_payment_ledgers`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 17. File Library — Index
- [ ] **URL**: `/admin/urban-goodz/files`
- [ ] **Expected**: Lists uploaded files (images, docs) with preview thumbnails
- [ ] **Actions to test**: Upload file, delete file
- [ ] **DB table**: `urban_goodz_files`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 18. Fashion Fit — Index
- [ ] **URL**: `/admin/urban-goodz/fashion-fit`
- [ ] **Expected**: Lists measurement requests with customer info
- [ ] **DB table**: `urban_goodz_measurement_requests`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 19. Fashion Fit — View Details
- [ ] **URL**: `/admin/urban-goodz/fashion-fit/{id}`
- [ ] **Expected**: Shows full measurement details with photo references
- [ ] **Actions to test**: Update status/notes
- [ ] **DB table**: `urban_goodz_measurement_requests`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 20. AI Concierge — Intents
- [ ] **URL**: `/admin/urban-goodz/ai-concierge/intents`
- [ ] **Expected**: Lists AI intents with trigger patterns, can create/edit/delete
- [ ] **DB table**: `urban_goodz_ai_intents`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 21. AI Concierge — Conversations
- [ ] **URL**: `/admin/urban-goodz/ai-concierge/conversations`
- [ ] **Expected**: Lists AI conversations with user info, can view/update
- [ ] **DB table**: `urban_goodz_ai_conversations`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 22. Rentals — Dashboard
- [ ] **URL**: `/admin/urban-goodz/rentals`
- [ ] **Expected**: Shows counts (assets, bookings, inspections), quick action buttons, recent assets/bookings lists
- [ ] **Actions to test**: Click each quick-action button → correct destination
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 23. Rentals — All Assets
- [ ] **URL**: `/admin/urban-goodz/rentals/assets`
- [ ] **Expected**: Lists all rental assets with type, status, price columns
- [ ] **Actions to test**: Create, Edit, Status toggle, Delete
- [ ] **DB table**: `urban_goodz_rental_assets`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 24. Rentals — Create Asset
- [ ] **URL**: `/admin/urban-goodz/rentals/assets/create`
- [ ] **Expected**: Form with name, type (car/vehicle/equipment), description, price, status
- [ ] **Actions to test**: Submit valid form → redirects to assets index
- [ ] **DB table**: `urban_goodz_rental_assets` — new row
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 25. Rentals — Edit Asset
- [ ] **URL**: `/admin/urban-goodz/rentals/assets/{id}/edit`
- [ ] **Expected**: Form pre-filled with existing asset data
- [ ] **Actions to test**: Modify, submit → DB updated
- [ ] **DB table**: `urban_goodz_rental_assets`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 26. Car Rental (type filtered)
- [ ] **URL**: `/admin/urban-goodz/rentals/assets?business_type_slug=car_rental`
- [ ] **Expected**: Shows only car-type rental assets
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 27. Vehicle Rental (type filtered)
- [ ] **URL**: `/admin/urban-goodz/rentals/assets?business_type_slug=vehicle_rental`
- [ ] **Expected**: Shows only vehicle-type rental assets
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 28. Equipment Rental (type filtered)
- [ ] **URL**: `/admin/urban-goodz/rentals/assets?business_type_slug=equipment_rental`
- [ ] **Expected**: Shows only equipment-type rental assets
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 29. Rentals — Bookings Index
- [ ] **URL**: `/admin/urban-goodz/rentals/bookings`
- [ ] **Expected**: Lists all rental bookings with asset name, customer, dates, status, deposit status
- [ ] **Actions to test**: View details, Status update, Verification toggle, Payment toggle, Deposit toggle, Notes
- [ ] **DB table**: `urban_goodz_rental_bookings`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 30. Rentals — Booking Show
- [ ] **URL**: `/admin/urban-goodz/rentals/bookings/{id}`
- [ ] **Expected**: Full booking detail with asset info, customer, dates, pricing, status timeline
- [ ] **Actions to test**: Change status, verification, payment, deposit; add notes
- [ ] **DB table**: `urban_goodz_rental_bookings` — updated
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 31. Rentals — Booking Status Actions
- [ ] **URL**: `/admin/urban-goodz/rentals/bookings/{id}/status/{status}`
- [ ] **Expected**: Status changes between: pending → confirmed → picked_up → returned → completed, or cancelled
- [ ] **Actions to test**: Click status buttons → verify DB updated
- [ ] **DB table**: `urban_goodz_rental_bookings`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 32. Rentals — Booking Verification
- [ ] **URL**: `/admin/urban-goodz/rentals/bookings/{id}/verification/{status}`
- [ ] **Expected**: Toggles identity verification between verified/unverified
- [ ] **DB table**: `urban_goodz_rental_bookings` — verification_status
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 33. Rentals — Booking Deposit
- [ ] **URL**: `/admin/urban-goodz/rentals/bookings/{id}/deposit/{status}`
- [ ] **Expected**: Toggles deposit status between pending/paid/refunded
- [ ] **DB table**: `urban_goodz_rental_bookings` — deposit_status
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 34. Rentals — Booking Payment
- [ ] **URL**: `/admin/urban-goodz/rentals/bookings/{id}/payment/{status}`
- [ ] **Expected**: Toggles payment status between pending/paid/refunded
- [ ] **DB table**: `urban_goodz_rental_bookings` — payment_status
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 35. Rentals — Inspections Index
- [ ] **URL**: `/admin/urban-goodz/rentals/inspections`
- [ ] **Expected**: Lists all rental inspections with asset, booking, inspector, condition notes
- [ ] **Actions to test**: Create, Edit, Delete
- [ ] **DB table**: `urban_goodz_rental_inspections`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 36. Rentals — Create Inspection
- [ ] **URL**: `/admin/urban-goodz/rentals/inspections/create`
- [ ] **Expected**: Form with asset_id, booking_id, inspector_name, condition_notes, status
- [ ] **Actions to test**: Submit → new row in DB
- [ ] **DB table**: `urban_goodz_rental_inspections`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 37. Rentals — Edit Inspection
- [ ] **URL**: `/admin/urban-goodz/rentals/inspections/{id}/edit`
- [ ] **Expected**: Pre-filled form
- [ ] **Actions to test**: Modify, submit → DB updated
- [ ] **DB table**: `urban_goodz_rental_inspections`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 38. Book Anything (Modules) — Index
- [ ] **URL**: `/admin/urban-goodz/modules/book-anything`
- [ ] **Expected**: Lists service requests from the book-anything section
- [ ] **Actions to test**: Create, Edit, Status, Delete
- [ ] **DB table**: `urban_goodz_service_requests`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

## 39. Book Anything — Create
- [ ] **URL**: `/admin/urban-goodz/modules/book-anything/create`
- [ ] **Expected**: Dynamic form based on fillable fields from model
- [ ] **Actions to test**: Fill fields, submit
- [ ] **DB table**: `urban_goodz_service_requests`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 40. Community Marketplace — Index
- [ ] **URL**: `/admin/urban-goodz/modules/community`
- [ ] **Expected**: Lists community posts/comments/marketplace items
- [ ] **DB table**: `urban_goodz_community_posts`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 41. Creator Commerce — Index
- [ ] **URL**: `/admin/urban-goodz/modules/creator`
- [ ] **Expected**: Lists creator applications and products
- [ ] **DB table**: `urban_goodz_creator_applications`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 42. Logistics — Index
- [ ] **URL**: `/admin/urban-goodz/modules/logistics`
- [ ] **Expected**: Lists logistics jobs
- [ ] **DB table**: `urban_goodz_logistics_jobs`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 43. Medical Courier — Index
- [ ] **URL**: `/admin/urban-goodz/modules/medical`
- [ ] **Expected**: Lists medical courier jobs with custody logs
- [ ] **DB table**: `urban_goodz_medical_courier_jobs`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 44. Earn Money — Index
- [ ] **URL**: `/admin/urban-goodz/modules/earn-money`
- [ ] **Expected**: Lists earn money opportunities and applications
- [ ] **DB table**: `urban_goodz_earn_money_opportunities`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 45. Events — Index
- [ ] **URL**: `/admin/urban-goodz/modules/events`
- [ ] **Expected**: Lists events with dates, status
- [ ] **DB table**: `urban_goodz_events`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 46. Urban Goodz+ (Plus Memberships) — Index
- [ ] **URL**: `/admin/urban-goodz/modules/plus`
- [ ] **Expected**: Lists plus memberships with tier, status
- [ ] **DB table**: `urban_goodz_plus_memberships`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 47. Black-Owned Spotlight — Index
- [ ] **URL**: `/admin/urban-goodz/modules/spotlight`
- [ ] **Expected**: Lists spotlight businesses
- [ ] **DB table**: `urban_goodz_spotlight_businesses`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 48. Discovery — Index
- [ ] **URL**: `/admin/urban-goodz/modules/discovery`
- [ ] **Expected**: Lists discovery searches, trending, signals
- [ ] **DB table**: `urban_goodz_discovery_searches`
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## 49. App Config API Endpoint
- [ ] **URL**: `GET /api/v1/urban-goodz/app-config`
- [ ] **Expected**: Returns JSON with all enabled sections, payment config (staged_test), Adyen disabled
- [ ] **Actions to test**: Hit endpoint with curl/browser, verify all 15+ sections in response
- [ ] **Pass/Fail**: ___ / ___
- [ ] **Notes**: _______________________________

---

## Summary

| Section | Pass | Fail | Not Tested |
|---------|------|------|------------|
| Login | ☐ | ☐ | ☐ |
| Sidebar | ☐ | ☐ | ☐ |
| Control Center | ☐ | ☐ | ☐ |
| Business Types | ☐ | ☐ | ☐ |
| Capabilities | ☐ | ☐ | ☐ |
| Mapping | ☐ | ☐ | ☐ |
| Order Anywhere | ☐ | ☐ | ☐ |
| Payments/Ledger | ☐ | ☐ | ☐ |
| File Library | ☐ | ☐ | ☐ |
| Fashion Fit | ☐ | ☐ | ☐ |
| AI Concierge | ☐ | ☐ | ☐ |
| Rentals Dashboard | ☐ | ☐ | ☐ |
| Rental Assets | ☐ | ☐ | ☐ |
| Rental Bookings | ☐ | ☐ | ☐ |
| Rental Inspections | ☐ | ☐ | ☐ |
| Book Anything | ☐ | ☐ | ☐ |
| Community | ☐ | ☐ | ☐ |
| Creator Commerce | ☐ | ☐ | ☐ |
| Logistics | ☐ | ☐ | ☐ |
| Medical Courier | ☐ | ☐ | ☐ |
| Earn Money | ☐ | ☐ | ☐ |
| Events | ☐ | ☐ | ☐ |
| Urban Goodz+ | ☐ | ☐ | ☐ |
| Spotlight | ☐ | ☐ | ☐ |
| Discovery | ☐ | ☐ | ☐ |
| App Config API | ☐ | ☐ | ☐ |

**Total tests**: 49  **Passed**: ___  **Failed**: ___  **Not tested**: ___
