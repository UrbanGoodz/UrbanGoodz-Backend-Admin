# FCM: v1 is the authority, the legacy server key is not a send path

## 1. What was found

Three separate copies of the push sender exist in the codebase:

| Copy | File |
| --- | --- |
| A | `app/CentralLogics/Helpers.php` — `Helpers::sendNotificationToHttp()` |
| B | `app/Traits/NotificationTrait.php` — `NotificationTrait::sendNotificationToHttp()` |
| C | `app/Library/Notification.php` — global `sendNotificationToHttp()` |

All three already posted to the **FCM v1** endpoint
`https://fcm.googleapis.com/v1/projects/<project_id>/messages:send` using an
OAuth2 bearer token minted from the service-account JSON stored in the
`push_notification_service_file_content` business setting.

**No live legacy server-key send path was found.** Before this hardening patch,
a repository-wide search for `https://fcm.googleapis.com/fcm/send` and for the
`push_notification_key` business setting found the setting only in
`resources/views/admin-views/business-settings/fcm-config.blade.php:63-68`,
where the server-key form field is **commented out**. The new resolver and tests
now also name that setting solely to detect, report, and ignore stale legacy
credentials. Legacy FCM was decommissioned by Google on 2024-06-20.

Two real weaknesses did exist:

1. The precedence was **implicit**. Each copy only checked
   `if ($key['project_id'])`. Copies B and C indexed `$key['project_id']`
   directly, so a missing/corrupt service account raised an *undefined array
   key* warning instead of failing cleanly. Nothing stated that a stored legacy
   key must be ignored, so re-enabling the commented-out admin field would have
   silently re-introduced a second credential with no defined precedence.
2. Failure was silent — a suppressed push returned `false` with no log line, so
   "push isn't arriving" had no server-side evidence.

## 2. What was implemented

`app/Services/Notifications/FirebaseCredentialResolver.php` is now the single
authority.

`FirebaseCredentialResolver::decide(?array $serviceAccount, ?string $legacyServerKey)`
is a **pure** function (no database, no network) returning:

```
mode                       v1 | legacy_only | unconfigured
can_send                   bool
project_id                 string|null
endpoint                   the v1 URL, or null
legacy_server_key_present  bool
legacy_server_key_ignored  bool
reason                     null | legacy_server_key_is_not_a_send_path
                                | fcm_v1_service_account_missing
```

Precedence rules encoded:

- A service account is only valid when **`project_id`, `client_email` and
  `private_key`** are all present and non-empty.
- When a valid service account exists → `mode = v1`, `can_send = true`. If a
  legacy server key is *also* stored it is reported as
  `legacy_server_key_ignored = true` — **v1 wins outright; the legacy key is
  never a fallback.**
- Legacy key alone → `mode = legacy_only`, `can_send = false`. The legacy
  endpoint is a constant on the class only so tests can assert it is never
  called.
- Neither → `mode = unconfigured`, `can_send = false`.

All three sender copies (A, B, C) now call `decide()` first, refuse to send when
`can_send` is false, log
`FCM push suppressed: v1 credentials are not authoritative.` with
`mode`/`reason`/`legacy_server_key_present` (no credential values), and post to
`$decision['endpoint']` rather than rebuilding the URL by hand.

Tests: `tests/Unit/FirebaseCredentialResolverTest.php` (precedence table) and
`tests/Unit/FcmLegacyServerKeySourceTest.php` (no source file anywhere reaches
the legacy endpoint or reads the legacy setting as a credential).

## 3. OWNER ACTION REQUIRED — not doable from an engineering session

These need the owner's Google Cloud / Firebase console and were **NOT DONE**:

1. **Delete the legacy Cloud Messaging API.**
   Firebase console → ⚙ **Project settings → Cloud Messaging** → the
   **Cloud Messaging API (Legacy)** row → **⋮ → Disable**. The backend no longer
   uses it; disabling removes an unused credential surface.
2. **Restrict "API key 7"** (the unrestricted browser/API key seen in the
   project's key list).
   Google Cloud console → **APIs & Services → Credentials** → the key →
   - **Application restrictions:** *Websites* (HTTP referrers) for a web key, or
     *IP addresses* limited to the production server for a server key.
   - **API restrictions:** *Restrict key* → tick only the APIs it must reach
     (e.g. Firebase Cloud Messaging API, Firebase Installations API).
   - **Save**, then confirm the key list shows no key with
     "Unrestricted key" beside it.
3. **Rotate any legacy server key that is still stored** in the
   `business_settings` row `push_notification_key`, then delete that row. It is
   dead weight, and the code now ignores it, but a stored credential is still a
   credential.

## 4. What could NOT be certified here

Real push arrival on a physical Android/iOS device cannot be verified from this
session — it needs the production service account and enrolled devices. The
tests prove precedence and endpoint discipline, not delivery.
