# P0: Admin HTTP 419 (Page Expired)

Status: **fix implemented and unit-verified; NOT yet verified against the environment that produced the 419.**

## What was checked in the code
| Check | Finding |
|---|---|
| Framework | Laravel 12; `web` group runs `StartSession` then `VerifyCsrfToken` (`bootstrap/app.php`). |
| Login form | `resources/views/auth/login.blade.php` includes `@csrf`. A locally served login page issues both `laravel_session` and `XSRF-TOKEN` cookies. |
| CSRF exclusions | Only payment-gateway callbacks and two vendor variation helpers; nothing under `/login_submit` or `/admin`. |
| **Trusted proxies** | **Not configured.** `app/Http/Middleware/TrustProxies.php` extends `Fideloper\Proxy\TrustProxies` (not installed on Laravel 12) and is never registered. `bootstrap/app.php` uses `$middleware->use([...])`, which replaces the framework's default global stack, so the framework `TrustProxies` was not running either. |
| Session cookie config | `driver=file` (default), `same_site=null`, `secure`/`domain` from env. |
| HTTPS forcing | `AppServiceProvider::boot()` forces https only when `APP_ENV=local`. |

## Most likely mechanism
Behind a TLS-terminating load balancer/CDN the app sees plain HTTP from an untrusted proxy. Generated URLs, redirects and the Secure-cookie decision then disagree with the browser's https origin, the session cookie is not returned on the next request, and the next POST fails CSRF with 419. With several app instances and `SESSION_DRIVER=file`, a request landing on a different instance also loses the session.

## Fix
`bootstrap/app.php` now registers the framework `TrustProxies` and configures it from `TRUSTED_PROXIES` (comma-separated IPs/CIDRs, or `*`). Unset = previous behaviour. CSRF protection is untouched.

## Required deployment settings (to be confirmed against the real environment)
- `TRUSTED_PROXIES=<load balancer CIDRs or *>`
- `APP_URL=https://<admin host>`; `SESSION_SECURE_COOKIE=true`
- `SESSION_DRIVER=redis` or `database` if more than one instance serves the admin
- `SESSION_DOMAIN` empty unless admin/API must share a session across subdomains
- `php artisan config:clear` after changing env

## Tests
`tests/Feature/AdminSessionCsrfRegressionTest.php`: CSRF still rejects missing/forged tokens with 419; no proxy is trusted by default; a trusted proxy makes https/client IP visible; untrusted sources cannot spoof forwarded headers. The two proxy tests fail on the pre-fix code.

## Related findings (not changed, need an owner decision)
- `LoginController::submit` accepts captcha code `9999` for one hard-coded email, bypassing captcha.
- "Remember me" stores the admin's password (encrypted) in a cookie.
