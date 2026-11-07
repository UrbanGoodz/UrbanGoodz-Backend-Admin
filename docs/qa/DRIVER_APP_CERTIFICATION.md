# Driver app certification — status: PARTIAL (no device or emulator run)

Repo: `UrbanGoodz/UrbanGoodz_Driver_App` @ 83ffae3. The Flutter project is the `driver_app/` subfolder
(package `urban_goodz_driver`). The repo root is an unrelated copy of the customer app (`sixam_mart`) plus
a 100 MB+ source zip and `node_modules`; it should be cleaned up so the root is not mistaken for the driver app.

## Verified
| Check | Result |
|---|---|
| `flutter analyze` (3.47.9) | No issues |
| `flutter test` | 192 passed, 0 failed |
| `flutter build web --release` | builds |
| Endpoints vs backend routes | 78 endpoint strings (prefix expanded), 0 unmatched. Path match only, not HTTP method |
| Driver order endpoints (backend) | read/update lookups are scoped to the assigned driver (`delivery_man_id`); "accept order" is limited to unassigned orders (intended) |

## Backend driver authorization (added tests: `DriverAuthorizationTest`, 21 tests)
Cross-driver isolation held for every endpoint exercised: dedicated routes (read, started, completed, scans, package events,
age verification, sequencing, finish), active jobs for all four job sources, load acceptance, business courier jobs
(10 actions), AI dispatches (6 actions), bids, certifications, dispatch notifications, Order Anywhere purchase card (403 for any
other driver), and the legacy order endpoints. Each test also proves the owner can reach the record and that the record is
unchanged after the attack. Those 14 isolation tests pass on the old and new code: no cross-driver hole was found.

Defects found and fixed:
| ID | Sev | Defect | Fix |
|---|---|---|---|
| D1 | **P1** | `dm.api` accepted any valid token without checking the driver's state. A suspended driver, or one whose application was denied/reset to pending, kept full API access (jobs, purchase cards) until the token happened to be replaced | middleware rejects drivers with `status = 0` or `application_status != approved` (401, same codes as login) |
| D2 | **P1** | Admin suspend set `auth_token = null` on the model but never saved it, so suspension never revoked anything; denying an application did not touch the token | suspend now persists revocation and sets the driver offline; denying/un-approving revokes the token |
| D3 | **P1** | 37 legacy `delivery-man` endpoints re-read the driver with `where(['auth_token' => $request['token']])`. A Bearer-header caller has no `token` parameter, so Eloquent queried `auth_token IS NULL` and the request ran as an arbitrary logged-out driver (cross-driver read/write). The shipped app always sends `?token=`, so it was not hit | middleware pins `token` to the authenticated driver |
Fixture changes: three existing test fixtures created token-holding drivers who were never approved (a state production cannot reach);
they now set `application_status = approved`.

## Not verified
Everything that needs a device: install, login, go online/offline, background location, assignment push/accept/reject,
navigation, pickup/delivery scans, proof of delivery (camera, signature, GPS), connectivity loss, payout/earnings
screens, and the release APK/AAB. There is no Android SDK in the sandbox and no device is reachable from it.

## Findings
- No functional defects found by analysis, unit tests or endpoint matching.
- Repo hygiene (P3): driver repo root holds a customer-app copy, `UrbanGoodz2026-Revised-FULL-SOURCE.zip`,
  `node_modules/`, `playwright-report/`, `bash.exe.stackdump`.
- `ApiConfig.baseUrl` defaults to production; builds for testing must pass `--dart-define=UG_API_BASE_URL=...`.

## To finish
1. Run the app on a device against staging (`adb reverse tcp:8000 tcp:8000` + `UG_API_BASE_URL`), walk the delivery lifecycle,
   POD and offline cases.
2. Cross-driver tests for the remaining legacy endpoints (earnings, withdrawals, messages `message/details`, `reviews/submit`).
3. Admin suspend / deny / approve are plain `GET` links (`admin/users/delivery-man/status/{id}/{status}`,
   `.../update-application/{id}/{status}`): state changes with no CSRF protection. Move them to POST.
4. Release build + signing check.
