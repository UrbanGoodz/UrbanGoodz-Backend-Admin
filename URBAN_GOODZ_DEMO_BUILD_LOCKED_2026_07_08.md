# Urban Goodz Delivery — Demo Build Locked

**Date:** 2026-07-08
**Status:** DEMO READY — BUILD LOCKED
**Documentation of final build state before next-phase development.**

---

## 1. Final Working URLs

| Service | URL |
|---------|-----|
| Root Landing | `https://admin.urbangoodzdelivery.com` |
| Admin Panel | `https://admin.urbangoodzdelivery.com/admin` |
| Urban Goodz Command Center | `https://admin.urbangoodzdelivery.com/admin/urban-goodz` |
| Business Portal | `https://admin.urbangoodzdelivery.com/business/login` |

---

## 2. Confirmed Working Admin Features

- [x] Admin dashboard
- [x] Urban Goodz Command Center
- [x] Payment Center
- [x] Employee/role visibility
- [x] Business Clients management (create, edit, approve, suspend, reactivate)
- [x] Order Anywhere admin foundation

---

## 3. Confirmed Working Business Portal Features

- [x] Login / authentication
- [x] Business dashboard
- [x] Locations management
- [x] Courier Routes (create, list, view)
- [x] Multi-stop / drop-off route creation
- [x] Route edit
- [x] Route delete / cancel
- [x] Package scan intake (/business/packages/scan)
- [x] Mobile / camera scanner (phone camera integration)
- [x] Employee/business scan scoping (no dropdown, no cross-business visibility)
- [x] Package Pool (/business/packages/pool)
- [x] Assign package to route
- [x] End-location optimization foundation (optimize button with end-location sorting)
- [x] Business documents upload (upload form, list, viewing)

---

## 4. Workflow Separation

### Courier Routes (Current — Working)
- **Pattern:** Route-first.
- **Flow:** Business creates a route, adds pickup/drop-off stops.
- **Users:** Boutiques, restaurants, food trucks, retail stores, event vendors, small local businesses, independent couriers.
- **Pay model:** Mileage-based.

### Dedicated Routes (Future — Not Built)
- **Pattern:** Package-first manifest/intake model.
- **Flow:** Business scans/imports packages first. System validates/geocodes. System groups packages into route batches. Driver gets assigned generated route batch.
- **Users:** Trucking, medical courier/labs, fulfillment centers, warehouses, distribution centers, high-volume package operations.
- **Pay model:** Package-based or fixed route/batch payout.

### Last-Mile Delivery
- **Pay model:** Mileage-based.

---

## 5. Known Technical Notes

### Route Cache
**Do not run:**
```
php artisan route:cache
```

**Reason:** Existing duplicate route name conflict:
```
admin.rental.provider.status
```

**Use instead:**
```
php artisan route:clear
```

### Cache-Safe Commands
```
php artisan optimize:clear
php artisan view:clear
php artisan view:cache
php artisan route:clear
```

---

## 6. Do Not Touch Files (Demo Locked)

These files are frozen unless a verified bug is found:

### Controllers
- `app/Http/Controllers/Admin/UrbanGoodz/BusinessPortalController.php`

### Routes
- `routes/business.php`
- `routes/web.php`
- `routes/update.php`
- `routes/admin.php`

### Scanner / Package Views
- `resources/views/business/routes/packages/scan.blade.php`
- `resources/views/business/routes/packages/pool.blade.php`
- `resources/views/business/routes/packages/index.blade.php`
- `resources/views/business/routes/packages/create.blade.php`
- `resources/views/business/routes/packages/upload.blade.php`

### Courier Route Views
- `resources/views/business/routes/index.blade.php`
- `resources/views/business/routes/create.blade.php`
- `resources/views/business/routes/show.blade.php`
- `resources/views/business/routes/edit.blade.php`

### Documents Views
- `resources/views/business/documents/index.blade.php`
- `resources/views/business/documents/create.blade.php`

### Dashboard / Layout
- `resources/views/business/dashboard.blade.php`
- `resources/views/business/layouts/app.blade.php`

### Models
- `app/Models/UrbanGoodzDedicatedRoute.php`
- `app/Models/UrbanGoodzRoutePackage.php`
- `app/Models/UrbanGoodzBusinessClientUser.php`

### Payment Center / Sidebar
- Payment center files
- Sidebar/navigation file

---

## 7. Next-Phase Punch List (Do Not Build Before Demo)

The following are scoped for the next development phase after demo delivery:

- [ ] Dedicated Route Manifest Builder
  - Business selects pickup location/date
  - Business scans/imports all packages
  - Packages go into unassigned manifest/package pool
  - Addresses validated/geocoded
  - Business/admin chooses grouping rules (packages per route, max radius, max miles, ZIP/city/proximity, delivery window, vehicle type)
  - System generates Route A/B/C batches
  - Stops ordered
  - Admin/business approves
  - Drivers assigned
  - Driver scans pickup and delivery
  - Proof/return/payout logic

- [ ] OCR / package label extraction
- [ ] Geocoding provider integration
- [ ] Real AI route batching
- [ ] Driver proof of delivery
- [ ] Failed delivery / return workflow
- [ ] Payout rules and automation
- [ ] Admin payout review
- [ ] Medical courier chain-of-custody expansion
- [ ] High-volume route contract pricing

---

## 8. Migrations Ran

- `2026_07_08_100000_add_route_package_foundation_fields`
- `2026_07_08_110000_make_dedicated_route_id_nullable` (fixed FK issue, ran successfully)
- `2026_07_08_120000_add_end_location_to_dedicated_routes`

---

*End of demo build documentation.*
