# Urban Goodz — Complete Control Inventory

Status: **SCAFFOLD — NOT POPULATED.** PM baseline 2026-07-25.

## Why this file is empty of rows

A control-level inventory of Urban Goodz means every page, screen, modal, card, menu, tab, button, icon button, toggle, checkbox, radio, dropdown, filter, search field, form, file upload, link, table action, bulk action, and status action across six product surfaces — with, for each one, its route, source file, endpoint, HTTP method, controller, middleware, permission, database tables, notification effect, payment effect, audit effect, expected and actual behaviour, and evidence.

That is thousands of rows. **Every row must come from source inspection or automated discovery — none may be guessed.** Writing plausible-looking rows without opening the code would be exactly the fabrication class this recovery effort exists to eliminate, and it would be worse than an empty file because it would look like evidence.

So this file defines the discovery method and the schema. Population is assigned work in Phase 3, distributed across the lanes that own each surface. `INV-1` in the master matrix tracks it as `NOT_AUDITED`.

## Schema

Columns, matching `urban_goodz_complete_control_inventory.csv`:

`product_surface, role, page_or_screen, route, source_file, control_label, control_type, expected_action, backend_endpoint, http_method, controller_or_service, middleware, permission, db_tables_affected, notification_expected, payment_effect, audit_log_effect, expected_success_state, expected_validation, expected_failure_state, actual_result, evidence, severity_if_broken, assigned_agent, fix_commit`

## Discovery method — automated, not manual

### Web portals (Admin, Business, Dispatcher) — Lane 3 / Backend
1. `php artisan route:list --json` → every route, method, middleware, controller. This is the spine; join everything else to it.
2. Blade templates: extract `<button>`, `<a href>`, `<form action>`, `<select>`, `<input type=submit>`, modal triggers.
3. Sidebar/menu partials and layouts → navigation tree.
4. JS/AJAX: `fetch(`, `$.ajax`, `axios.` call sites → endpoints not reachable from route-list alone.
5. Policies, gates, and middleware per route → the permission column.
6. Reconcile: **any route with no UI control, and any UI control with no route, is a finding.**

### Mobile apps (Shopper, Vendor, Driver) — Lanes 1, 2, + Shopper owner
1. Named routes and GetX bindings → screen list.
2. `Navigator.push` / `Get.to` / `Get.toNamed` call sites → navigation graph.
3. Widget tree scan for `ElevatedButton`, `TextButton`, `IconButton`, `Switch`, `Checkbox`, `Radio`, `DropdownButton`, `TextFormField`, `InkWell`, `GestureDetector` with an `onPressed`/`onTap`.
4. Trace each handler to its controller → repository → API client method → endpoint constant.
5. Flag every control whose handler is empty, is a `TODO`, shows a snackbar without a network call, or sets local state only. **These are the fabricated-success class already found in Shopper and Vendor and are P0 by default.**
6. Deep links and permission prompts.

### Backend — Lane 3
API routes, web routes, controllers, services, jobs, commands, middleware, policies, events, listeners, models, migrations, seeders, webhooks, scheduled tasks. Cross-reference against the mobile and web control lists to find endpoints nothing calls and calls nothing serves.

## Surface assignment

| Surface | Owner | Status |
|---|---|---|
| Shopper app | **UNASSIGNED** | NOT_AUDITED |
| Vendor app | Lane 1 | NOT_AUDITED |
| Driver app | Lane 2 | NOT_AUDITED |
| Admin portal | Lane 3 | NOT_AUDITED |
| Business portal | Lane 3 | NOT_AUDITED |
| Dispatcher portal | Lane 3 | NOT_AUDITED |
| Backend API | Lane 3 | NOT_AUDITED |

## Partial prior work — verify before trusting

`UrbanGoodz2026-Revised` has 15 **untracked** audit documents from an earlier session, including `*_SCREEN_CONTROL_CENSUS.csv/.md` and `*_WIRING_MATRIX.md` for Shopper, Vendor, and Driver, plus `URBAN_GOODZ_CROSS_ROLE_E2E_MATRIX` and `URBAN_GOODZ_MOBILE_API_CONTRACT_MATRIX`.

These may be a substantial head start on the mobile surfaces. They are **untracked, uncommitted, and unverified**, and one of them (`DRIVER_APP_LEGACY_CONTAMINATION.md`) sits alongside a prior Vendor audit that a later session found to be wrong — commit `2da0481` is literally titled *"independently verified P0 census correcting the prior audit."*

**Treat them as leads, not evidence.** Spot-check each claim against source before importing any row. Commit them once verified so they stop being loose files.
