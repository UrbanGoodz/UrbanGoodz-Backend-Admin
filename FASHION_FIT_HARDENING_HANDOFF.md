# Fashion Fit Hardening — Handoff for Claude

Audit, verify, fix anything you find, and finish the remaining items. Everything below was verified green as of this handoff.

## Objective
Harden the Urban Goodz Fashion Fit measurement pipeline: per-measurement provenance, quality status, multi-person / foreign-object / dark / blurry rejection, content-hash idempotency, measurement version history, and cross-user isolation. Engine is `SilhouetteMeasurementEngine` v2.0.0.

## Environment (Windows, cmd)
- Workspace: `C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39`
- PHP 8.3.30, Laravel 12.50.0, PHPUnit 11.5.50, GD available.
- **cmd gotcha:** `set DB_HOST=127.0.0.1` adds a trailing space and fails DNS. Use quoted `set "DB_HOST=127.0.0.1"` and `set "DB_DATABASE=..."` (see commands below).
- Tests use a dedicated allowlisted DB `urban_goodz_local_test_20260807` (phpunit.xml + `tests\CreatesApplication.php` UG_TEST_DB_ALLOWLIST). Queue runs sync in tests.
- `php artisan route:list` needs the env vars set, else it dies on `Unknown database 'urban_goodz_local'`.

## Status: ALL GREEN
```
vendor\bin\phpunit tests\Unit\FashionFitSilhouetteEngineTest.php tests\Unit\FashionFitAiContractTest.php tests\Unit\FashionFitHardeningTest.php tests\Feature\FashionFitSilhouetteE2ETest.php tests\Feature\FashionFitHardeningE2ETest.php
=> OK (24 tests, 420 assertions)
```
- `php artisan route:list --path=fashion-fit` shows `GET|HEAD api/v1/fashion-fit/profiles/{uuid}/history › FashionFitCustomerController@history`. Registered.
- `php -l` clean on every changed/new PHP file.

## Files created
- `database\migrations\2026_08_15_100000_harden_fashion_fit_provenance_and_history.php` — applied to the test DB already (405ms DONE). Adds: `fashion_fit_measurements.provenance` (string, indexed), `.status` (enum `measured|needs_better_photo|customer_confirmation_required`, default `measured`, indexed), `.landmark_sources` (json), `.calculation` (json); `fashion_fit_analyses.quality_warnings`, `.unavailable_measurements` (json), `.source_hash` (string 64), `.source_files` (json), index `(profile_id, source_hash)`; new table `fashion_fit_measurement_versions` (measurement_id, analysis_id nullable, value decimal(10,3), unit enum `in|cm`, source enum `ai|manual_correction|provider_adjustment`, provenance, confidence decimal(5,4), actor_type, actor_id, corrected bool, approved bool, timestamps, index (measurement_id, created_at)). Down() drops table then columns.
- `app\Models\FashionFitMeasurementVersion.php` — casts value/confidence decimal, corrected/approved bool; `measurement()` belongsTo.
- `app\Services\FashionFit\PhotoQualityAnalyzer.php` — upload-time advisory checks (WORK_WIDTH 480, DARK_LUMA 0.15, OVEREXPOSED_RATIO 0.25, EDGE_STRENGTH 24, BLUR_EDGE_RATIO 0.005). Returns mean_luma/dark/overexposed_ratio/overexposed/edge_ratio/blurry/warnings. Not authoritative; engine is.
- `tests\Unit\FashionFitHardeningTest.php` — 7 synthetic-render tests (provenance, two-person retake, foreign-object retake, covered-legs unavailable inseam, unpinched-waist never "detected", dark fail-closed, quality analyzer). All pass.
- `tests\Feature\FashionFitHardeningE2ETest.php` — 3 E2E tests (idempotency, correction/approval/history preservation, cross-user 404 isolation). All pass.

## Files modified
- `app\Models\FashionFitProfile.php` — **`latestAnalysis()` is now a real `hasOne(...)->latest()` relation.** (It previously returned a `Model`, which throws `LogicException` in `approve()` — fixed during this handoff's prep.)
- `app\Models\FashionFitMeasurement.php` — casts landmark_sources/calculation as array; `analysis()`, `versions()`; `isLocked()` = source !== 'ai' || approved_at !== null.
- `app\Models\FashionFitAnalysis.php` — casts for quality_warnings/unavailable_measurements/source_files; `profile()`, `measurements()`.
- `app\Services\FashionFit\FashionFitAnalysisService.php` — full rewrite. Key behavior:
  - `process()` early-returns for terminal statuses (completed/needs_retake/failed); consent + required-views guard; failure codes `provider_not_configured` / `provider_or_validation_failure`.
  - `validateResult()` accepts new optional fields: provenance, status, landmark_sources, calculation, unavailable_measurements (name/reason/code), quality_warnings, retake_requirements.
  - `sourceSignature()` = sha256 of profile id + per-view `view:contentHash` (reads bytes from Storage disk); returns `{hash, files:{view:{file_id,hash}}}`.
  - `findTerminalForSignature()` = latest completed/needs_retake with same profile_id + source_hash.
  - `persist()`: needs_retake updates analysis + profile status; completed upserts measurements inside a transaction, **skipping locked rows** (their analysis_id/values preserved), writes an AI `FashionFitMeasurementVersion` (actor_type system, corrected/approved false), marks unavailable measurements' status, sets response_hash, sets profile → customer_review, audits `analysis_completed`, notifies.
- `app\Http\Controllers\Api\V1\FashionFitCustomerController.php`:
  - `uploadPhoto` runs `PhotoQualityAnalyzer` and merges `{width,height,mean_luma,dark,overexposed_ratio,overexposed,edge_ratio,blurry,warnings}` into `quality`.
  - `submitAnalysis` computes sourceSignature; if a terminal analysis exists for the hash → returns it (200) without creating a new row; keeps profile `approved` as-is; else creates analysis with source_hash/source_files.
  - `correctMeasurement` records a `manual_correction` version (actor customer, corrected true) and downgrades status to customer_review.
  - `approve` guards needs_better_photo / unavailable_measurements / requires_confirmation, approves measurements + latest version per measurement, sets status approved.
  - `history()` returns latest-200 versions (with measurement name) for the profile.
- `routes\api\v1\fashion_fit.php` — added `GET profiles/{uuid}/history` inside the `auth:api` group.

## Key semantics to preserve
- Locked measurement = `source !== 'ai' || approved_at !== null`. Re-analysis skips locked rows.
- Re-submitting identical photos returns the existing terminal analysis (idempotent); approved profiles are never downgraded by a re-submit.
- Provenance values: `detected|estimated|fallback|unknown|direct` (MeasurementQuality). `estimated`/`fallback` force `requires_confirmation`; `unknown` → needs_better_photo.
- Engine outputs rounded-to-1 value, confidence, requires_confirmation, provenance, landmark_sources, calculation, unavailable_measurements, quality_warnings, retake_requirements.
- Contract fixture `tests\Fixtures\fashion_fit_ai_completed.json` is intentionally legacy-shaped; new fields are nullable so contract tests stay green.

## Known issues / audit findings
1. **FIXED — throttle bucket collision.** In this Laravel 12, the unnamed `throttle:N,M` middleware keys by `$prefix . sha1(user_id)` (`vendor/laravel/framework/src/Illuminate/Routing/Middleware/ThrottleRequests.php::resolveRequestSignature`, `$prefix` defaults to `''`). All four unprefixed `throttle:N,M` routes in the customer group shared ONE bucket per user — not just photo-upload vs. submit-analysis as first suspected, but **also `submitAnalysis` (`throttle:3,1`) and `stagedPayment` (`throttle:3,1`)**. Fixed by adding a distinct third-arg prefix to each: `ff-photo-upload` (10,1), `ff-photo-download` (30,1), `ff-analysis-submit` (3,1), `ff-staged-payment` (3,1) — see `routes/api/v1/fashion_fit.php`. No named limiters were registered anywhere for Fashion Fit, so this keeps the existing raw `throttle:N,M[,prefix]` style rather than introducing `RateLimiter::for()`. Verified: removed the `Cache::flush()` workaround from `FashionFitHardeningE2ETest.php` (and the now-unused `Cache` import) and the idempotency/correction-survives-reanalysis tests still pass on their own without it — confirming the buckets are genuinely separated, not just papered over.
2. **Two unrelated pending migrations** exist in the test DB (from other work) — not ours; do not touch.
3. **`php artisan migrate:status` / `route:list`** fail if `DB_HOST`/`DB_DATABASE` aren't set in the shell env — see Commands below. (The earlier note about a cmd.exe trailing-space bug was specific to unquoted `set`; using `export` in bash or quoted `set "VAR=val"` in cmd both work.)

## What is left to be done
1. **Manual inspect / cleanup (optional):** the `straightTorsoProfile()` and two-person/foreign-object fixtures in `FashionFitHardeningTest` carry deliberate design choices (wide shoulder to keep arms connected, wide canvas for a distinct second person, box at x 690-790 clear of the subject). Keep those invariants if you touch them.
2. **Optional hardening additions:** E2E for retake-flow (two-person photos through the API → profile stays needs_retake), history pagination, and provider-adjustment version source. Not written yet — not required for correctness, just extra coverage.
3. **Do NOT commit** unless explicitly asked.

## Completed this pass (on top of the state above)
- Audited every changed/new file (service, controller, models, migration, engine, routes, tests) line by line — no defects found beyond the throttle collision.
- Ran the broader regression sweep: `MobileReleaseApiTest`, `UrbanGoodzAIExecutionEngineTest`, `UrbanGoodzCommissionResolverTest`, `UrbanGoodzEcosystemIntegrationTest`, `VendorApiSecuritySourceTest` — 102 tests, 272 assertions, all green, no fallout from the Fashion Fit rewrite.
- Fixed the throttle bucket collision (see finding #1) and simplified the E2E tests accordingly.
- Re-ran the full targeted Fashion Fit suite after all changes: still 24/24, 420 assertions.
- Confirmed `route:list --path=fashion-fit` resolves cleanly (51 routes, history route present, no regressions).

## Commands
```
cd /d "C:\Users\D'Andre Good\Documents\GitHub\AdminPanel_Update_V39"
set "DB_HOST=127.0.0.1" && set "DB_DATABASE=urban_goodz_local_test_20260807" && php artisan route:list --path=fashion-fit
vendor\bin\phpunit tests\Unit\FashionFitSilhouetteEngineTest.php tests\Unit\FashionFitAiContractTest.php tests\Unit\FashionFitHardeningTest.php tests\Feature\FashionFitSilhouetteE2ETest.php tests\Feature\FashionFitHardeningE2ETest.php
php -l app\Services\FashionFit\FashionFitAnalysisService.php app\Services\FashionFit\PhotoQualityAnalyzer.php app\Models\FashionFitMeasurementVersion.php app\Models\FashionFitProfile.php app\Models\FashionFitMeasurement.php app\Models\FashionFitAnalysis.php app\Http\Controllers\Api\V1\FashionFitCustomerController.php routes\api\v1\fashion_fit.php database\migrations\2026_08_15_100000_harden_fashion_fit_provenance_and_history.php tests\Unit\FashionFitHardeningTest.php tests\Feature\FashionFitHardeningE2ETest.php
```
