# Urban Goodz Legacy 6amMart Contamination Audit

Audit basis: Admin/backend SHA `6937e5d5c8d7bbf0c6405eda8a6c1d0e922033c9`, Shopper SHA `663f4dba719250e86222578ee22e6b0e6f355a24`, Vendor local SHA `51dd3e820a57085768d0f2988991a845fd28ea2d`, and Driver local SHA `90d0bf7cf7a5764a4e2217a08e24f2f69ac22acc`.

This was a source-and-existing-evidence audit. It did not query or change production, run a database, seed data, build an app, clear a cache, change Firebase, or inspect secret values. Current live database branding, server duplicates, cache contents, cron, workers, and Firebase ownership remain `UNKNOWN` unless existing evidence proves them.

The authoritative component register is [URBAN_GOODZ_6AMMART_CONTAMINATION_INVENTORY.csv](URBAN_GOODZ_6AMMART_CONTAMINATION_INVENTORY.csv).

## Scope and completeness boundary

There is no committed pristine upstream 6amMart manifest or vendor baseline against which every line can be attributed. Therefore, “inherited” can be proved in two ways only:

1. direct 6amMart/6amTech/sixam identifiers; or
2. architecture that current Urban Goodz documentation and active source explicitly identify as the inherited foundation.

The audit classified all directly located runtime/source identifier families and every requested component category. It does not label an unmarked file as inherited merely because its design resembles a marketplace product. Such provenance stays `UNKNOWN`.

Direct source census:

- Backend: 77 non-vendor/non-node/non-storage files contain a direct 6amMart, 6amTech, or sixam identifier. This includes historical output files; runtime families are itemized separately in the CSV.
- Shopper: 755 files contain a direct identifier. Of those, 734 Dart files contain 5,698 `package:sixam_mart/` import occurrences. That package namespace is one required compatibility component, not 5,698 independent branding defects.
- Vendor app: no direct 6amMart identifier was found outside excluded build output.
- Driver app: no direct 6amMart identifier was found outside excluded build output.

## Classification totals

| Classification | Components |
|---|---:|
| REQUIRED FOUNDATION | 8 |
| CUSTOMIZED AND ACTIVE | 10 |
| LEGACY BUT STILL REFERENCED | 7 |
| LEGACY DATA | 4 |
| DEMO OR TEST DATA | 4 |
| DUPLICATE IMPLEMENTATION | 3 |
| OBSOLETE | 4 |
| UNSAFE | 12 |
| UNKNOWN | 4 |

The counts are component families in the CSV, not source-file counts.

## P0 conclusions

### Required foundation is not contamination to delete

The inherited route/controller/middleware/model/order/store/vendor/module/zone foundation is still the active substrate for Urban Goodz. The `sixam_mart` Dart package name is likewise the Shopper app’s internal import namespace. Removing or globally renaming these without a dedicated migration would break the platform. They are `REQUIRED FOUNDATION`, not a branding defect.

The inherited module-permission helper is `CUSTOMIZED AND ACTIVE`. It is the authorization boundary for Urban Goodz role permissions and must not be replaced by a simple “is authenticated” check.

### Runtime-reachable unsafe contamination

1. Two subscription-cancellation mail classes fall back to the literal `6am Mart` when the email-address setting is missing.
2. The Shopper app writes the remembered password into ordinary `SharedPreferences`.
3. Shopper WebSocket configuration contains a hard-coded `6ammart` key and a private-LAN fallback host.
4. Shopper iOS still uses the vendor bundle `com.sixamtech.6amMart`; its committed Firebase plist is bound to that bundle and a legacy project.
5. `.env.example` supplies `6ammart` as example realtime app id/key/secret instead of requiring explicit provisioning.
6. The default Laravel `DatabaseSeeder` calls synthetic user, test-vendor, live-tester, and load-board seeders. A normal seed command can therefore contaminate any connected database.
7. The test-vendor and legacy Admin seeders contain fixed, disclosed credentials.
8. `script.sh` targets a legacy MAMP `Backend-6amMart` cron path and is unsafe as an operational script.
9. Competing inherited SMS gateway implementations can drift, and 2Factor can report transport success without provider acceptance.
10. The Admin custom CAPTCHA fallback is client-selectable, reusable, and outside the login-attempt limiter on invalid challenges.
11. The inherited filesystem/S3 selector always resolves to local, while private-media paths and public business/driver document storage are inconsistent.

These are blockers; none were modified because this phase was an audit and several require product, signing, infrastructure, or data-migration decisions.

### Legacy data and current data truth

`database/partial/email_tempaltes.sql` and `database/partial/data_settings.sql` contain 6amMart copyrights, descriptions, vendor demo URLs, and obsolete mobile-store links. They are `LEGACY DATA` and must not be imported into a production installation.

Live screenshots show `Demo Product` and `Carrot Imported` in marketplace rankings. They are `DEMO OR TEST DATA` by visible naming, but their creation path and ownership cannot be inferred from screenshots. The database itself was not inspected. The 130-vendor/0-store/1,116-item inconsistency is also not enough to claim orphan rows; it proves inconsistent dashboard scoping and triggers a read-only relationship/provenance audit.

### External 6amTech dependencies

License checks still post to `store.6amtech.com` and reference `check.6amtech.com`. Admin setup pages also link to 6amTech documentation/support. These are `LEGACY BUT STILL REFERENCED`, not automatically obsolete: the license calls may be a vendor contract. Their payload, failure behavior, ownership, and replacement require explicit legal/operational review.

The Tax module’s `6ammart` project discriminator is a `REQUIRED FOUNDATION` compatibility contract. Renaming it without tracing vendor module branches would change tax behavior.

### Duplicate implementations

The new standalone Vendor and Driver Flutter apps contain no direct 6amMart identifier, but each duplicates capabilities already represented in the inherited backend/web/mobile model. Both are `DUPLICATE IMPLEMENTATION` until a canonical API/domain contract is selected. Their operational repositories are currently dominated by mock data, so they cannot replace the inherited workflows yet.

## Requested-category disposition

| Requested category | Finding |
|---|---|
| Routes | Core marketplace routes are required; Urban Goodz route overlays are customized. Route existence is not E2E proof. |
| Controllers | Core controllers remain required; several Urban Goodz controllers extend rather than replace them. |
| Middleware | Required foundation; module/role/zone/ownership middleware must remain authoritative. |
| Services | Mixed required core and customized Urban Goodz services; financial overlaps need one canonical settlement boundary. |
| Models/tables | Core models/tables are required; clean schema reproducibility is blocked by missing `orders` creation baseline. |
| Seeders | Default chain is unsafe because it creates synthetic/test operational data. |
| Blade/layouts | Admin layout/login are customized; email placeholders and setup links retain legacy references. |
| Assets/JS/CSS | No legacy-named runtime image was found; metadata/comments and lockfile name remain. Laravel Mix/Echo/Pusher dependencies are active foundation. |
| Mobile navigation | Shopper route graph is inherited and extended; Vendor/Driver shells are duplicate implementations. |
| Package identifiers | Shopper Android is Urban Goodz; Shopper iOS remains 6amTech; Shopper Dart package name is an internal compatibility namespace. |
| Firebase | Shopper iOS is unsafe legacy binding; Android project ownership/isolation is unknown; Vendor/Driver apps have no committed Android Firebase file. |
| API endpoints | Core API is required; live credentials/provider behavior remain separate evidence gates. |
| Queues/cron | Current scheduler/jobs are customized; actual production cron/workers are unknown. Legacy MAMP script is unsafe. |
| Settings/branding | Translation overrides are customized; DB branding records are unknown without a narrow read-only export. |
| Dashboard queries | Legacy section is active and internally inconsistent; it should be separated or retired after reconciliation. |
| Duplicate server deployments | Historical wrong-copy incident is proven; current duplicate/interception state is unknown. |
| Caches | Local audit worktree has no generated cache; production cache identity is unknown and was not cleared. |

## Safe disposition order

1. Block production execution of non-reference seeders and separate test fixtures.
2. Remove fixed seeded credentials and plaintext mobile password persistence.
3. Provision an owned Shopper iOS bundle/Firebase app and validate Android Firebase ownership.
4. Replace customer-visible mail fallbacks and quarantine legacy SQL imports.
5. Reconcile and separate the Legacy Marketplace dashboard.
6. Inventory live branding records, document root, deployed SHA, cron/workers, and caches using read-only evidence.
7. Decide which external 6amTech licensing/support dependencies are contractually required.
8. Defer internal namespace and tax-project renames until dedicated compatibility migrations exist.

## What is not proven

- No claim is made that production contains rows from any committed seeder or partial SQL file.
- No claim is made that every 6amTech link is malicious or removable.
- No claim is made that current production still serves a duplicate Laravel copy.
- No claim is made that current production caches contain legacy views/routes.
- No claim is made that the Android Firebase project is unowned; ownership and isolation are simply unproven.
- No claim is made that visible imported items are orphaned; only their provenance and ranking eligibility are unproven.
