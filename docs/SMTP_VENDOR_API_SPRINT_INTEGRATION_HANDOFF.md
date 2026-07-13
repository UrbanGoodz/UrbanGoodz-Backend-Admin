# SMTP and Vendor API Sprint Integration Handoff

Date: 2026-07-12  
Source branch: `smtp-vendor-api-sprint`  
Locked primary source: `0576608b4aec286fe362596db84b3c80f872251e`  
Integration target: `adminpanel-v39-backend-sprint`  
Production domain: `https://admin.urbangoodzdelivery.com`  
Deployment: not performed

## Release position

The source implementation and automated verification are complete for the assigned SMTP hardening, Vendor commerce, Fashion Fit AI workflow, Creator Commerce/Reels, and service-booking domains. The feature branch must be reviewed and integrated into `adminpanel-v39-backend-sprint`; it must not be deployed directly.

This is not a full ecosystem readiness declaration. Live SMTP, the configured external Fashion Fit AI call, configured sandbox payment calls, cPanel smoke tests, device installation, and cross-application lifecycle tests still require the deployed environment.

## Implemented backend domains

- SMTP: encrypted database credential storage, Admin-setting-to-runtime mapping, config precedence, timeout/auth-mode/EHLO support, redacted diagnostics, classified safe failures, Admin-only POST test action, throttling, password reset/verification mail-path coverage, and queue-safe runtime application.
- Vendor: login/logout and revocation, account/store-state enforcement, profile/store data, owned products and multipart media, inventory and availability, owned order listing/details/transitions, wallet/transactions/earnings, validated withdrawals, FCM token registration, notifications, and support conversations.
- Fashion Fit AI: versioned consent, private guided-view upload sessions, customer ownership, separate measurement/photo grants, asynchronous provider interface, structured result validation, confidence and retake data, correction/approval, provider requests/estimates/status, staged test-controlled payment state, audit records, notifications, history, earnings, revocation, and deletion controls.
- Creator Commerce/Reels: creator profiles and approval, owned video/thumbnail upload, drafts and publish state, owned product/service tagging, moderation/reports, real published feed, customer attribution, server-derived commission/revenue, fulfillment linkage, history, and notifications.
- Service bookings: the twelve required categories, provider profiles/services/availability, discovery, quote/estimate, booking/payment confirmation, legal status transitions, reschedule/cancel/review, sandbox gateway interface, idempotent transactions, platform fee/provider earnings, authorization, audit history, and notifications.
- Platform repair: primary Admin permission checks no longer depend on an optional role relationship, and medical-courier soft deletes now have the required guarded schema column.

The authoritative request/response and authorization details are in:

- `docs/URBAN_GOODZ_VENDOR_API_CONTRACT.md`
- `docs/URBAN_GOODZ_FASHION_FIT_AI_MEASUREMENT_CONTRACT.md`
- `docs/URBAN_GOODZ_CREATOR_REELS_API_CONTRACT.md`
- `docs/URBAN_GOODZ_SERVICE_BOOKING_API_CONTRACT.md`

## Database migrations

Inspect in order before applying:

1. `2026_07_12_000003_encrypt_mail_config_password.php`
2. `2026_07_12_100000_create_fashion_fit_ai_workflow_tables.php`
3. `2026_07_12_120000_complete_creator_reel_commerce.php`
4. `2026_07_12_130000_complete_service_booking_workflow.php`
5. `2026_07_12_150000_add_soft_deletes_to_urban_goodz_medical_courier_jobs.php`

All are guarded. Back up the production database before applying them. The mail migration encrypts an existing stored password in place and must be reviewed against a backup before deployment.

## Runtime configuration

Preserve the cPanel `.env`; never replace it with a repository file. Update only values confirmed by the owner or an established provider configuration.

SMTP values may originate from encrypted active Admin database settings, then are applied with `Config::set`; absent Admin values fall back to environment/config values. Clear stale configuration cache after deployment and restart queue workers so workers load the same active settings.

Relevant non-secret keys:

- SMTP: `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_TIMEOUT`, `MAIL_AUTH_MODE`, `MAIL_EHLO_DOMAIN`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`, `QUEUE_CONNECTION`.
- Fashion Fit: `FASHION_FIT_AI_ENABLED`, `FASHION_FIT_AI_PROVIDER`, `FASHION_FIT_AI_ENDPOINT`, `FASHION_FIT_AI_API_KEY`, `FASHION_FIT_AI_MODEL`, `FASHION_FIT_AI_MODEL_VERSION`, `FASHION_FIT_AI_TIMEOUT`, `FASHION_FIT_AI_MAX_ATTEMPTS`, `FASHION_FIT_STAGED_PAYMENTS_ENABLED`, `FASHION_FIT_CONSENT_VERSION`.
- Service bookings: `SERVICE_BOOKING_PLATFORM_FEE_PERCENT`, `SERVICE_BOOKING_PAYMENT_SANDBOX`, `SERVICE_BOOKING_PAYMENT_ENDPOINT`, `SERVICE_BOOKING_PAYMENT_SECRET`, `SERVICE_BOOKING_PAYMENT_TIMEOUT`.

No provider, credential, recipient, or live payment value is supplied by this branch.

## Verification evidence

- Changed PHP files from the locked source: syntax clean.
- Route compilation: 2,121 routes.
- Targeted P0 backend tests: 25 tests / 66 assertions passed before aggregate verification.
- Corrected formerly failing runtime/security subset: 26 tests / 114 assertions passed.
- Complete backend suite: 135 tests / 490 assertions passed; zero failures or errors; one PHPUnit deprecation notice.
- Secret scan: no credential literals, cookies, bearer tokens, production database values, real recipients, or absolute user paths in the branch diff. Reserved `example` addresses exist only in tests/config defaults.

## cPanel integration and deployment procedure

1. Review this feature branch and integrate it into `adminpanel-v39-backend-sprint` without a direct feature-branch deployment.
2. Run the complete backend suite on the integrated branch and record a new immutable backend SHA.
3. Back up the current cPanel application files and export the production database.
4. Deploy only the locked integrated SHA through the established cPanel process.
5. Preserve the production `.env`; compare and add only proven keys listed above.
6. Run `composer install --no-dev --optimize-autoloader` using the deployed PHP version.
7. Inspect `php artisan migrate:status`, review the five migrations above, then run `php artisan migrate --force` only after backup confirmation.
8. Run `php artisan optimize:clear`, then deliberately rebuild with `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache` if those commands are part of the established deployment process.
9. Run `php artisan queue:restart`; verify the cPanel/Supervisor worker is running and uses the configured queue connection.
10. Verify the cron invokes `php artisan schedule:run` once per minute and inspect failed jobs without printing payload secrets.
11. Verify private Fashion Fit storage is not publicly browsable and that signed/time-limited access is enforced.
12. Smoke-test login/logout, Vendor profile/products/inventory, owned order transitions, wallet/withdrawal, FCM registration/notifications, Fashion Fit upload/status/approval/grants/estimate, Creator upload/moderation/feed/attribution, and booking quote/payment/status/history.
13. Confirm payments remain sandbox/test-controlled. Do not enable live processing.
14. In the authenticated Admin mail page, inspect redacted diagnostics first. If all required fields are present, initiate exactly one POST `third-party/send-mail` request to the owner-approved recipient.
15. Record the independent SMTP gates: configuration load, DNS/connection/TLS, authentication, provider acceptance/message ID, and recipient/spam-folder confirmation. Do not repeat the send if the first attempt is pending or accepted.
16. Build tester applications against the locked deployed SHA and execute all cross-system lifecycle tests before any readiness verdict.

## External gates and exact next actions

- SMTP: inspect the deployed redacted diagnostic response, then perform exactly one owner-initiated test send. No live SMTP attempt has been made locally.
- Fashion Fit AI: configure an approved server-side endpoint/key/model/version, start the queue worker, upload a consented front/side set, and verify a non-fixture structured result through approval and provider access/revocation.
- Service payment: configure an approved sandbox endpoint/secret, keep sandbox mode true, and verify idempotent deposit/payment/refund responses and ledger totals.
- Creator media: verify the configured private/public media disks and URLs in cPanel, then complete upload, moderation, playback, tagged commerce, attribution, and commission history.
- Firebase: verify deployed service credentials and send one controlled incoming-order/test event to each applicable tester role.
- Device: install the Vendor RC2 APK on an attached Android target and capture launch/login/lifecycle evidence. No ADB target was attached locally.

## SMTP gate status

- Local configuration mapping: PASS
- Credential encryption: PASS
- Diagnostics redaction: PASS
- Test endpoint security: PASS
- Live configuration load: EXTERNALLY BLOCKED
- Live network connection: EXTERNALLY BLOCKED
- Live authentication: EXTERNALLY BLOCKED
- Provider message acceptance: EXTERNALLY BLOCKED
- Recipient delivery: EXTERNALLY BLOCKED

## Verdict

`PARTIALLY READY — BLOCKED BY DEPLOYED SMTP/AI/SANDBOX-PAYMENT/FIREBASE CONFIGURATION, CPANEL INTEGRATION, DEVICE INSTALLATION, AND FULL CROSS-APPLICATION END-TO-END EVIDENCE`

