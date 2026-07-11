URBAN GOODZ — FRESH OPENCODE / BIG PICKLE HANDOFF
DEADLINE MODE — DRIVER/VENDOR/BUSINESS/CUSTOMER QA + BRANDING CLEANUP

OWNER:
D'Andre Good

PROJECT:
Urban Goodz

BACKEND REPO:
C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39

BRANCH:
adminpanel-v39-backend-sprint

REMOTE:
https://github.com/UrbanGoodz/back-end.git

LIVE BACKEND:
https://admin.urbangoodzdelivery.com

============================================================
ACCEPTED STATE — DO NOT REBUILD
============================================================

All prior work is committed and pushed through commit 2647269.

Already completed in prior sessions:
- Backend recovery: 306 files, 13 commits, ~39,700 lines, pushed through 8054958
- Driver vehicle/trailer/load-board addendum: 22 fields, 9 tests, pushed through 8d4bec2
- Login Remember Me + reCAPTCHA bug: FIXED (d0c8c67)
- SMTP email runtime: FIXED (f66d7a1, a24cc1f)
- Email templates branding: FIXED — "6ammart" replaced with "Urban Goodz" in 9 templates (2711e87)
- Error pages branding: FIXED — "Stack Food" replaced with "Urban Goodz" in 404/500 (2711e87)
- Email OTP brute-force protection: UNCOMMENTED and migration added (2711e87)
- TOTP/2FA: FULLY IMPLEMENTED (2647269)
  - TotpService: RFC 6238 pure PHP, QR enrollment, recovery codes
  - TwoFactorAuthController: setup, confirm, disable, recovery codes
  - TwoFactorLoginController: login-time TOTP verification
  - Migration: 2026_07_10_000004 adds 2FA columns to admins
  - 6 views: index, setup, verify, disable, recovery-codes, verify-recovery
  - LoginController updated: tfa_required redirect on login
  - Admin model updated: 2FA fields in $fillable
- DCP report generated: docs/dcp/DCP_BACKEND_QA_RUNTIME_PHASE.md

============================================================
CURRENT TEST STATE
============================================================

45 pass / 7 fail (292 assertions)
7 failures are all in UrbanGoodzAgeComplianceRuntimeTest
Reason: PDO connection error (local dev DB credentials don't match production)
NOT code bugs — will pass on staging/production

============================================================
REMAINING WORK — THIS SESSION'S MISSION
============================================================

Priority 1: BRANDING CLEANUP (quick wins)
Priority 2: DRIVER QA VERIFICATION
Priority 3: VENDOR QA VERIFICATION
Priority 4: BUSINESS PORTAL QA VERIFICATION
Priority 5: CUSTOMER FLOW QA VERIFICATION
Priority 6: COMMIT, PUSH, DCP UPDATE

============================================================
PRIORITY 1: BRANDING CLEANUP
============================================================

Still remaining 6amMart remnants:

A) Email format editor placeholders (40+ files)
   Pattern: placeholder="Ex:_Copyright_2024_6amMart._All_right_reserved"
   Location: resources/views/admin-views/business-settings/email-format-setting/user-email-formats/
   Location: resources/views/admin-views/business-settings/email-format-setting/store-email-formats/
   Location: resources/views/admin-views/business-settings/email-format-setting/dm-email-formats/
   Location: resources/views/admin-views/business-settings/email-format-setting/admin-email-formats/
   Fix: Replace "6amMart" with "Urban Goodz" in all placeholder attributes

B) Payment settings help text
   Location: resources/views/admin-views/business-settings/payment-index.blade.php (line 238)
   Pattern: "6ammart supports multiple payment methods"
   Fix: Replace "6ammart" with "Urban Goodz"

C) External/drivemond config pages
   Location: resources/views/admin-views/business-settings/external-index.blade.php
   Pattern: "connect_drivemond_system_with_6ammart" (translation key, may not need changing)
   Pattern: "6amMart System token"
   Fix: Replace visible "6amMart" text with "Urban Goodz"

D) Documentation links (LOW PRIORITY — may keep as-is since they link to original vendor docs)
   Multiple files link to docs.6amtech.com, support.6amtech.com
   Decision: Keep as-is or replace with your own support URL

============================================================
PRIORITY 2: DRIVER QA VERIFICATION
============================================================

Verify the driver backend works end-to-end:

Files to check:
- app/Services/DeliveryManService.php (create/update with 22 new fields)
- app/Http/Requests/Admin/DeliveryManUpdateRequest.php (validation)
- app/Http/Controllers/Admin/DeliveryMan/ (all controllers)
- resources/views/admin-views/delivery-man/ (list, create, edit views)

Verify:
- Driver list shows vehicle column
- Driver create form has all 22 capability fields
- Driver edit form loads existing capability data
- AddRequest/UpdateRequest validation works
- Vehicle types, trailer types, hitch types all populate
- CDL, DOT, MC fields save correctly
- Load board eligibility toggle works
- Existing drivers are preserved (no data loss)

Test routes:
php artisan route:list --path=delivery-man

============================================================
PRIORITY 3: VENDOR QA VERIFICATION
============================================================

Verify vendor panel end-to-end:

Files to check:
- app/Http/Controllers/Vendor/ (all vendor controllers)
- resources/views/vendor-views/ (all vendor views)
- routes/vendor.php

Verify:
- Vendor login works (Remember Me, reCAPTCHA)
- Vendor dashboard loads
- Vendor can manage store settings
- Vendor product management works
- Vendor order management works
- Vendor employee management works
- Vendor withdrawal/request features work
- No 6amMart branding remnants in vendor views
- Firebase FCM token registration works (PUT api/v1/update-fcm-token)

============================================================
PRIORITY 4: BUSINESS PORTAL QA VERIFICATION
============================================================

Verify business client portal:

Files to check:
- app/Http/Controllers/Admin/UrbanGoodz/BusinessAuthController.php
- app/Http/Controllers/Admin/UrbanGoodz/Business*Controller.php
- resources/views/business/ (all business views)
- routes/business.php
- app/Http/Middleware/BusinessMiddleware.php

Verify:
- Business login works (Remember Me, no reCAPTCHA)
- Business dashboard loads
- Business can manage locations, routes, packages
- Package scanning works
- Document management works
- Package pool works
- Cross-business denial enforced
- No 6amMart branding in business views

============================================================
PRIORITY 5: CUSTOMER FLOW QA VERIFICATION
============================================================

Verify customer-facing backend flows:

Files to check:
- app/Http/Controllers/Api/V1/CustomerAuthController.php
- app/Http/Controllers/Api/V1/CustomerController.php
- routes/api/v1/api.php (customer routes)

Verify:
- Customer registration with email OTP works
- Customer login works
- Customer profile update with email OTP works
- Firebase FCM token registration works (PUT api/v1/cm-firebase-token)
- Email OTP brute-force protection is active (5 attempts, 60s window, 600s block)
- Zone lookup works
- Location selection works
- Order history works

============================================================
PRIORITY 6: COMMIT, PUSH, DCP UPDATE
============================================================

After all work:
1. Run: php artisan test --filter=UrbanGoodz
2. Run PHP syntax check on all changed files
3. git add, commit, push
4. Update DCP report at docs/dcp/DCP_BACKEND_QA_RUNTIME_PHASE.md

============================================================
GIT RULES
============================================================

Before work:
git status --short
git branch --show-current
git remote -v
git log --oneline -20

Do NOT:
- reset, stash, force push, git add .
- commit secrets, .env, storage runtime data
- deploy, merge to main, run production migrations

Push: git push origin adminpanel-v39-backend-sprint

============================================================
MANDATORY DCP UPDATE
============================================================

After all work, update docs/dcp/DCP_BACKEND_QA_RUNTIME_PHASE.md with:
- Branding cleanup result (all files fixed)
- Driver QA result (pass/fail per item)
- Vendor QA result (pass/fail per item)
- Business portal QA result (pass/fail per item)
- Customer flow QA result (pass/fail per item)
- New tests run/passed/failed
- New commits and push result
- Remaining blockers (if any)
- Updated ready-to-merge status
- Updated ready-to-deploy status

============================================================
CONTEXT REMINDER
============================================================

This is Laravel 12 + PHP 8.2+ project.
Original codebase: 6amMart/StackMart commercial delivery platform.
Customized for Urban Goodz delivery service.
All accepted work is committed — do not rebuild anything.
Focus on verification, branding cleanup, and QA only.
