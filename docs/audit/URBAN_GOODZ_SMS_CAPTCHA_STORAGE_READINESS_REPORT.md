# Urban Goodz SMS, CAPTCHA, and Media Storage Readiness Report

Audit date: 2026-07-23

Audit basis: `6937e5d5c8d7bbf0c6405eda8a6c1d0e922033c9`

Evidence boundary: source and previously reviewed test artifacts only. No production setting, secret value, database, provider console, filesystem, symlink, queue, browser, or mobile device was accessed.

## SMS and OTP

### Provider configuration

- The Admin SMS settings surface supports Twilio, Nexmo, 2Factor, MSG91, and Alphanet through `SMSModuleController` and `addon_settings`.
- Only one provider is intended to be active at a time.
- The current production active provider and credential completeness are **unproven**. Twilio and 2Factor being inactive/unconfigured are user-reported live observations, not independently verified database facts.
- Two overlapping dispatch implementations exist: `App\CentralLogics\SMS_module` and `App\Traits\SmsGateway`, while some controllers import a module-provided gateway trait. This makes provider behavior and fixes liable to drift.
- A transport-level success from the 2Factor cURL call is treated as delivery success without parsing the provider response for an application-level rejection. This can produce false-positive OTP delivery.
- If no provider is active, the gateway returns `not_found`; no production alerting or delivery-health evidence was located.

### Delivery channel by actor

| Actor/workflow | Source path | Result |
|---|---|---|
| Shopper registration/phone verification | Customer authentication controllers choose Firebase OTP when enabled, otherwise SMS gateway | Source path exists; live channel and device delivery unproven |
| Shopper password reset | `PasswordResetController` supports email and Firebase-or-SMS paths | Source path exists; live delivery unproven |
| Driver password reset | `DMPasswordResetController` uses Firebase-or-SMS selection | Source path exists; live delivery unproven |
| Admin recovery | `LoginController` contains SMS gateway calls while the login UI also describes email recovery | Mixed recovery behavior; live delivery unproven |
| Vendor web recovery | Vendor login presents an email-oriented forgot-password flow | SMS/OTP recovery not established |
| Vendor standalone app | No OTP/forgot-password implementation found in the inspected Flutter source | Absent/unproven |
| Driver standalone app | No OTP/forgot-password implementation found in the inspected Flutter source | Absent/unproven |
| Business Portal recovery | `BusinessForgotPasswordController` uses the Laravel password broker email link | Email, not SMS |

### SMS/OTP decision

- **TWILIO CONFIGURED/ACTIVE:** UNKNOWN; user reports inactive/apparently unconfigured.
- **2FACTOR CONFIGURED/ACTIVE:** UNKNOWN; user reports inactive/apparently unconfigured.
- **SHOPPER OTP CHANNEL:** Firebase-or-SMS in source; live selection unproven.
- **VENDOR OTP CHANNEL:** Not established.
- **DRIVER OTP CHANNEL:** Firebase-or-SMS for server password reset; standalone-app flow not established.
- **BUSINESS OTP CHANNEL:** Not used for the inspected password-reset flow; email broker is used.
- **PASSWORD RESET DELIVERY:** Partially implemented across actors, not end-to-end certified.
- **ACCOUNT VERIFICATION DELIVERY:** Shopper source path exists, but live provider/device evidence is absent.
- **SECURITY STATUS:** Not ready. Provider response validation, delivery-health monitoring, duplicate implementation removal, actor-by-actor channel policy, rate-limit proof, and non-production delivery tests are required.

## Google reCAPTCHA path

- Admin configuration source exists for reCAPTCHA v3: status, site key, secret key, and Admin setup instructions.
- The login server path validates the secret and token, rejects non-2xx responses and exceptions, requires `success=true`, numeric score at least `0.5`, and action `submit`.
- The reviewed focused PHP artifact covers missing token/secret/score/action, non-numeric and low score, wrong action, non-2xx response, timeout, generic exception, `success=false`, and the exact `0.5` boundary.
- Live production enablement, key validity, secret presence, hostname/domain restrictions, Google console ownership, quota, and real browser behavior remain unproven. The user reports that Google reCAPTCHA appears disabled in production.

**GOOGLE RECAPTCHA PATH:** Configurable and locally source/test evidenced; current live configuration and operation are not certified.

## Custom CAPTCHA path

- A Gregwar image CAPTCHA is generated on login GET and stored in session key `six_captcha`.
- The POST path rejects a missing answer, missing session phrase, and case-insensitive mismatch.
- The page renders the custom CAPTCHA even while Google reCAPTCHA is enabled.
- JavaScript sets a hidden `set_default_captcha=1` field when Google initialization/token acquisition fails.
- The server trusts that client-controlled field to choose the custom path. Any client can therefore request the weaker fallback even when Google is healthy; the server does not prove an outage or enforce a server-side fallback policy.
- A successful or failed submission does not consume or rotate the custom phrase. The same phrase can be replayed until login GET/reload changes the session value.
- Invalid-CAPTCHA attempts fail before the login-attempt limiter is hit, so repeated custom-CAPTCHA guesses are not covered by that limiter.
- Focused tests cover basic missing/mismatch behavior, but no behavioral test proves server-controlled fallback, one-time consumption, rotation, or CAPTCHA-specific rate limiting.

**CUSTOM CAPTCHA PATH:** Active and partially tested, but not security-certified.

**PRODUCTION FALLBACK DECISION:** Production must not silently or client-selectably downgrade from Google reCAPTCHA. Either enforce a server-controlled, monitored fallback policy with one-time/rate-limited custom challenges, or fail closed with an attributable service-unavailable response.

## Media storage and disaster recovery

### Default storage and S3

- `config/filesystems.php` defaults to `local`; local files use `storage/app`, public files use `storage/app/public`, and the configured link is `public/storage -> storage/app/public`.
- `ConfigServiceProvider` casts `local_storage` to boolean and then uses:
  `Config::set('filesystems.default', $config ? ($config == 0 ? 's3' : 'local') : 'local')`.
- Because a boolean false enters the final `: 'local'` branch and true selects `local`, this expression always selects local storage. The Admin third-party/S3 toggle cannot make S3 the default through this path.
- S3 credentials are stored as JSON in `business_settings` and the Admin form renders the secret in a text input. No encryption-at-rest control, connection test, rotation workflow, least-privilege policy, or migration verification was found.

**PRODUCTION MEDIA STORAGE:** Source defaults and the selection defect make local storage the effective result. Direct live filesystem/settings proof was not obtained.

### Symlink and URL integrity

- The repository declares the standard `public/storage` link target, but production link existence, target, ownership, permissions, persistence across deployments, and backup coverage are unproven.
- `UrbanGoodzFileStorageService` stores Fashion Fit and generic files explicitly on the private `local` disk.
- Its `temporaryUrl()` calls `Storage::disk('local')->url(...)`, while Admin Fashion Fit and file-library views construct `asset('storage/' . stored_path)`.
- Those public-style URLs do not match the configured private local root/symlink. This is a contradictory availability path, not a proven secure-download design.

### Private document access

- The newer customer Fashion Fit API verifies profile ownership, active consent, image dimensions, and serves downloads through an authenticated controller with `private, no-store` and `nosniff` headers.
- A separate legacy Fashion Fit upload endpoint accepts any existing measurement request ID without proving that it belongs to the authenticated customer.
- The generic Urban Goodz upload endpoint accepts any existing Order Anywhere request ID without proving ownership.
- The Admin file library links `customer_private` records through public-style asset URLs rather than an authorized download controller.
- Business Portal and Admin business-client documents are stored on the `public` disk. Business Portal download queries enforce the current client ID, but the underlying files remain reachable through the public storage link if their paths are known.
- Driver certification documents are stored on the `public` disk, and the API returns the stored path in the certification record.
- Driver/delivery identity images also use the ordinary configured media disk and are displayed by direct full URLs.
- No distinct medical-document storage/download policy was located in the inspected medical courier source.

**PRIVATE DOCUMENT STATUS:** Not certified. Fashion Fit contains a stronger owned-download flow, but legacy upload/link paths, public business/driver storage, and missing medical policy prevent a platform-wide privacy claim.

### Reels/video

- Admin and Vendor Reels upload source validates allowed extensions and maximum size. Reels are stored through `Helpers::getDisk()`.
- Streaming supports byte ranges, conditional requests, local streaming, and remote-disk redirection.
- Because the S3 selection path always resolves to local, Reels remain dependent on the application server unless a different path overrides the disk.
- No server-side video transcoding, malware/content scanning, codec validation, adaptive bitrate packaging, thumbnail job, moderation-before-publication guarantee, CDN configuration, lifecycle/retention policy, or tested object-storage failover was established.

**REELS/VIDEO STATUS:** Upload and streaming code exists; scalable processing, delivery, safety, backup, and live operation are unproven.

### Backup and disaster recovery

- No application backup package, database/media backup command, scheduled backup job, restore command, retention policy, encryption policy, off-site replication, checksum manifest, or restore-drill evidence was located outside documentation/backup-named source copies.
- cPanel or hosting-provider backups may exist externally, but they were not evidenced in this audit.

**UPLOAD BACKUP/DR:** Unproven and deployment-blocking for irreplaceable/private media.

## Required controls

1. Correct and behaviorally test the local/S3 selector without exposing credentials.
2. Move S3 secrets to protected configuration/secret storage; mask them in Admin and establish rotation.
3. Inventory every upload category and assign `public`, `account-private`, `role-private`, or `regulated` classification.
4. Store private Fashion Fit, business, driver, and medical documents on a non-public disk and serve them only through authorization-checked downloads or short-lived signed URLs.
5. Enforce record ownership on all customer/driver upload associations.
6. Verify the production symlink and every generated URL with read-only evidence.
7. Back up database metadata and media together, off-site and encrypted; document RPO/RTO and prove a restore into an isolated environment.
8. Migrate scalable media to versioned object storage with least privilege, encryption, lifecycle rules, checksums, observability, and reversible cutover.
9. Add Reels virus/content scanning, codec probing, queued transcoding, CDN delivery, moderation gates, retention, and failed-job handling.

## Readiness decision

- SMS/OTP ready for production certification: **NO**
- Google reCAPTCHA live-certified: **NO**
- Custom CAPTCHA security-certified: **NO**
- Private media access-certified: **NO**
- Upload backup/restore-certified: **NO**
- S3/object-storage ready: **NO**
- Reels/video production-certified: **NO**
