# P4 ADMIN REVIEW QUEUE REPORT
## Urban Goodz — Phase Data-Import P4 (Admin Sourced-Business Review Workflow)
**Batch marker:** `urban_goodz_session1_p3_staging_import_20260709_001`
**Outcome:** Minimal admin-only review queue BUILT (no store/vendor/product creation, no activation, no publish).

---

## 1. Summary
P4 adds a safe, admin-only web review queue for the 431 staged
`urban_goodz_sourced_businesses` rows. It reuses the existing
`admin/urban-goodz` route group + `module:urban_goodz_view` middleware
(no new auth system). Admins can list, filter, view detail, and update
**review status** and **module-correct category IDs**. No notes column
exists on the model, so notes editing was intentionally omitted (no
migration run). All safety locks enforced.

## 2. Inspection findings before coding
- Admin Urban Goodz routes live in `routes/admin.php` under
  `Route::group(['prefix'=>'urban-goodz','as'=>'urban-goodz.','middleware'=>['module:urban_goodz_view']], ...)`.
- Existing admin controllers extend `App\Http\Controllers\Controller`,
  use `Brian2694\Toastr`, render `admin-views.urban-goodz.*`.
- Model `UrbanGoodzSourcedBusiness` has `admin_review_status` (fillable)
  and `category_ids` (fillable, array cast) but **NO `notes` column**.
- A separate API controller `UrbanGoodzDiscoveryController` (api/v1) has
  admin approve/reject/edit/merge actions, but there was **no admin web
  review queue** for sourced businesses. So a new minimal queue was added.
- `publishApprovedListings` exists in `UrbanGoodzIngestionService` but is
  NOT called by this queue.

## 3. Existing review workflow findings
No existing web admin review queue for `urban_goodz_sourced_businesses`.
Reused the established admin route group + middleware + blade layout.

## 4. Files changed
- `routes/admin.php` — added `sourced-businesses` subgroup inside the
  `urban-goodz` admin group (3 routes; inherits `module:urban_goodz_view`).

## 5. Files added
- `app/Http/Controllers/Admin/UrbanGoodz/UrbanGoodzSourcedBusinessReviewController.php`
- `resources/views/admin-views/urban-goodz/sourced-businesses/index.blade.php`
- `resources/views/admin-views/urban-goodz/sourced-businesses/show.blade.php`
- `docs/urban-goodz/data-import/session-1-p2b/P4_TAXONOMY_DEFECT_TICKET.md`
- `docs/urban-goodz/data-import/session-1-p2b/P4_ADMIN_REVIEW_QUEUE_REPORT.md`

## 6. Routes added/confirmed
- `GET  admin/urban-goodz/sourced-businesses`  → `index`  (name `urban-goodz.sourced-businesses.index`)
- `GET  admin/urban-goodz/sourced-businesses/{id}` → `show` (name `urban-goodz.sourced-businesses.show`)
- `PUT  admin/urban-goodz/sourced-businesses/{id}` → `update` (name `urban-goodz.sourced-businesses.update`)
- Confirmed via `php artisan route:list | findstr sourced-business`.

## 7. Admin review queue access path
`https://admin.urbangoodzdelivery.com/admin/urban-goodz/sourced-businesses`
(defaults to the P3B batch marker; filters by review_status, module,
city, state, age-restricted, category_pending, invalid source URL).
Detail/edit: `.../sourced-businesses/{id}`.

## 8. P3B batch counts verified
- total staged for marker: **431**
- pending_review: **431** (all start `pending`; reviewed counts 0 until admins act)
- category_ids pending: **384**
- age-restricted review_only: **80**

## 9. Review fields admins can edit
- `admin_review_status`: pending | approved | rejected | merge_required
- `category_ids`: array of integers that (a) exist in `categories`, (b)
  belong to the row's `module_id`, and (c) exclude id 1 (Demo). Invalid
  ids are silently dropped; empty → `[]` (honest pending).

## 10. Safety locks confirmed
- partnered_status stays **false** (not a column on the sourced table;
  never set, never exposed).
- visibility stays **private** (sourced table is non-public by design).
- **No** store/vendor/product creation.
- **No** `publishApprovedListings` call.
- **No** activation/publication/deploy.
- Age-restricted rows remain `['review_only']` (controller never adds
  delivery/courier/pickup modes).
- `category_ids=[1]` fallback explicitly rejected.

## 11. What was intentionally not built
- **Notes field**: no `notes`/`internal_review_notes` column exists on the
  model; P4 did NOT run a migration. Documented as out of scope.
- No approval→store-provisioning pipeline (future PM approval required).
- No taxonomy changes (see taxonomy defect ticket).
- No new admin auth/permission system (reused `module:urban_goodz_view`).

## 12. Taxonomy defect ticket
`docs/urban-goodz/data-import/session-1-p2b/P4_TAXONOMY_DEFECT_TICKET.md`
- Modules 14 & 15 have zero granular categories.
- Beauty categories 820–839 misfiled under module 13 (Retail/Shopping).
- Does NOT block staged review. Fix before customer-facing provisioning.

## 13. Commands/tests run
- `php -l` on the new controller → No syntax errors.
- `php artisan view:clear`, `php artisan route:clear`.
- `php artisan route:list | findstr sourced-business` → 3 routes confirmed.
- No `route:cache` run. No migration run. No tests added (optional filter
  `UrbanGoodzSourcedBusinessReview` has no matching test; none shipped).

## 14. Known unrelated issues
- Pre-existing dirty tracked files + untracked seed/guide MDs (untouched).
- cPanel route-name conflict `admin.rental.provider.status` (reason
  `route:cache` is forbidden; `route:clear` used instead).

## 15. Risks/review notes
- Category assignment is manual and module-gated; accuracy depends on
  admin + pending taxonomy fix (820–839 remap, modules 14/15 seed).
- 384 rows remain `category_ids=[]` until reviewed.
- `review_status` changes are recorded but do NOT trigger any downstream
  action in P4 (by design, safety lock).

## 16. ZIP path
`docs/urban-goodz/data-import/session-1-p2b/urban-goodz-session1-p4-admin-sourced-business-review-queue.zip`
(contains: controller, 2 blade views, routes/admin.php, 2 P4 docs — no
.env, vendor, node_modules, storage/uploads, secrets, or unrelated files).

## 17. PM recommendation
- Use the new queue to review the 431 rows (set `admin_review_status`,
  assign module-correct `category_ids` where known).
- Keep all rows private/review-gated; activation only after a separate
  PM-approved provisioning phase.
- Approve & execute the taxonomy fix (820–839 remap + seed modules 14/15)
  before any store creation.
- Schedule a follow-up to backfill `category_ids=[]` rows once taxonomy is
  corrected.
- Do not add a notes column / migration unless separately approved.
