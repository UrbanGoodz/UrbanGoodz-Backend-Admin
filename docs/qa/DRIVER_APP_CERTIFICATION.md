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
2. Backend-side driver authorization tests (driver A cannot read/modify driver B's jobs, routes, earnings) similar to
   `CustomerOrderOwnershipTest`; the urban-goodz driver API uses `assignedRouteOrFail` but is untested for cross-driver access.
3. Release build + signing check.
