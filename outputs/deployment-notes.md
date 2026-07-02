# Deployment Notes — Urban Goodz Original Landing Copy Final

## Overview

Package updates the public-facing landing page with approved Urban Goodz relaunch copy while preserving the original V3.9 layout and visuals. No redesign.

## Files Changed

| File | Change |
|------|--------|
| `resources/views/home.blade.php` | Title fallback, CTA heading hardcoded, earning_sub_title hardcoded (91 chars > 80 admin max), relaunch + Houston notes updated |
| `database/partial/data_settings.sql` | 10 seed rows updated for fresh installs |
| `resources/lang/en/messages.php` | `'Lets'` translation key updated |
| `public/assets/landing/css/landing.css` | CSS header comment cleaned (6amMart reference removed) |

## Deploy Order

### Step 1 — Update live database (REQUIRED)
The live site reads from the `data_settings` table. The seed SQL file does NOT update the live DB.

**Option A — Run SQL patch** (requires direct DB access):
```sql
source outputs/update-live-landing-copy-data-settings.sql
```
Or copy the UPDATE statements and run them against your database.

**Option B — Use admin panel UI** (safer, no raw SQL):
Follow `outputs/ADMIN-UPDATE-CHECKLIST.md` — update each field through Business Settings → Landing Page Settings in the admin panel.

### Step 2 — Deploy source files
Deploy these changed files to the server:
```
resources/views/home.blade.php
resources/lang/en/messages.php
public/assets/landing/css/landing.css
```

### Step 3 — Clear cache
```
php artisan optimize:clear
php artisan view:clear
php artisan cache:clear
```

### Step 4 — Verify
- Load the public landing page in a browser
- Run the verification checklist in `outputs/ADMIN-UPDATE-CHECKLIST.md`
- Confirm no 6amMart branding visible

## Important Notes

- `earning_sub_title` is hardcoded in Blade (91 chars) because the admin field maxlength is 80 — admin cannot save this value through the UI
- All other fields remain admin-editable via Fixed Data Settings
- The `fixed_header_title` seed value is updated but NOT rendered on the live page (the hero section uses hardcoded Blade text)
- Flutter tester app: NOT touched
- Auth/payment/routes/schema: NOT changed
