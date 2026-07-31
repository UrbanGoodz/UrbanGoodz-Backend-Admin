# APNs auth key (.p8) setup — replacing the expired push certificates

**Status of the code:** DONE. `config/apns.php` and
`App\Services\Notifications\ApnsAuthKeyProvider` implement token-based APNs auth
end to end (ES256 provider token, `apns-topic` handling, sandbox/production
endpoint selection, credential-free readiness reporting).

**Status of the credentials:** NOT DONE — **owner action required.** No Apple
Developer console access exists in the engineering session that wrote this. No
key id, team id, or .p8 was fabricated. Every value below is a blank the owner
fills in.

---

## 1. Why the change

Both existing Urban Goodz APNs push **certificates expired on 2021-12-18**.
Apple push certificates:

- expire after one year and must be regenerated and re-uploaded annually,
- are bound to a single app bundle id,
- need separate sandbox and production handling.

A **token-based auth key (.p8)**:

- does not expire,
- covers **every** app bundle id under the same Apple Developer team (customer,
  driver, vendor apps share one key),
- works against both `api.push.apple.com` and `api.sandbox.push.apple.com`,
- is downloadable exactly once — Apple never shows it again.

Certificate auth is deliberately **not** supported by the new code:
`ApnsAuthKeyProvider` rejects any file containing `BEGIN CERTIFICATE`.

---

## 2. Owner steps — Apple Developer console

> Requires the **Account Holder** or an **Admin** role in the Apple Developer
> account. Estimated time: 5 minutes.

1. Sign in at <https://developer.apple.com/account>.
2. Go to **Certificates, Identifiers & Profiles → Keys**.
3. Click **+** (Create a key).
4. **Key Name:** `Urban Goodz APNs` (any name; it is only a label).
5. Tick **Apple Push Notifications service (APNs)**.
   - If a **Environment** selector is offered, choose **Sandbox & Production**.
6. Click **Continue → Register**.
7. Click **Download**. You get `AuthKey_XXXXXXXXXX.p8`.
   **This download happens once. If it is lost the key must be revoked and a new
   one created.**
8. On the same confirmation screen, copy the **Key ID** (10 characters,
   e.g. the `XXXXXXXXXX` in the filename).
9. Go to **Membership details** (left sidebar) and copy the **Team ID**
   (10 characters).
10. Collect the **Bundle ID** of each iOS app
    (**Identifiers → App IDs**, e.g. `com.urbangoodz.customer`).

**Do not** paste the .p8 contents, key id, or team id into a chat, an issue, a
commit, or a screenshot.

---

## 3. Owner steps — Firebase console (this is the one that actually turns iOS push back on)

Urban Goodz iOS push **currently travels through FCM**, not directly to Apple.
The backend posts to
`https://fcm.googleapis.com/v1/projects/<project>/messages:send`; Firebase then
performs the APNs leg using the credential stored in the Firebase project.
Therefore the .p8 must be uploaded to Firebase:

1. <https://console.firebase.google.com> → select the Urban Goodz project.
2. **⚙ Project settings → Cloud Messaging** tab.
3. Scroll to **Apple app configuration**.
4. For each iOS app: under **APNs Authentication Key**, click **Upload**.
5. Upload `AuthKey_XXXXXXXXXX.p8` and enter the **Key ID** and **Team ID**.
6. If expired **APNs Certificates** are still listed for that app, delete them —
   Firebase prefers the auth key, but leaving dead certificates in place makes
   the console misleading during triage.

After this step iOS push works again **without any backend deploy**, because the
credential lives in Firebase, not in this repository.

---

## 4. Owner steps — server (only for direct-to-Apple sending)

Needed **only** if the platform is later switched to talking to APNs directly
instead of through FCM. The code path exists and is off by default.

1. Copy the key to the server, outside the web root:

   ```
   mkdir -p storage/app/apns
   # upload AuthKey_XXXXXXXXXX.p8 into storage/app/apns/
   chmod 600 storage/app/apns/AuthKey_XXXXXXXXXX.p8
   ```

2. Add to `.env` (names only shown here — values come from §2):

   ```
   APNS_KEY_ID=
   APNS_TEAM_ID=
   APNS_BUNDLE_ID=
   APNS_ADDITIONAL_BUNDLE_IDS=
   APNS_AUTH_KEY_PATH=/absolute/path/to/storage/app/apns/AuthKey_XXXXXXXXXX.p8
   APNS_ENVIRONMENT=production
   APNS_DIRECT_SEND_ENABLED=true
   ```

3. `php artisan config:clear`

`.gitignore` already blocks `*.p8`, `*.p12`, `AuthKey_*.p8` and
`storage/app/apns/`.

> Note: `app/Library/Constant.php` currently lists `.p8` in its allowed upload
> extensions (`FILE_EXTENSION`). Uploading a .p8 through the admin file manager
> would place it in public storage. Use the filesystem path above, never the
> file manager.

---

## 5. What the code does with it

| Piece | Location |
| --- | --- |
| Config (names only, no secrets) | `config/apns.php` |
| Token minting + readiness | `app/Services/Notifications/ApnsAuthKeyProvider.php` |
| Tests | `tests/Unit/ApnsAuthKeyProviderTest.php` |

`ApnsAuthKeyProvider::status()` returns a credential-free readiness report
(`configured`, `key_id_present`, `team_id_present`, `bundle_id`,
`auth_key_readable`, `environment`, `endpoint`, `problems[]`). It is safe to log
and safe to surface in an admin diagnostics screen.

`providerToken()` builds the JWT Apple requires:

- header `{"alg":"ES256","kid":"<key id>"}`
- claims `{"iss":"<team id>","iat":<unix time>}`
- signature converted from OpenSSL's DER output to the raw `R||S` form JWS
  mandates
- cached for `APNS_TOKEN_TTL_SECONDS` (default 3000s = 50 min), which sits inside
  Apple's 60-minute maximum age and above Apple's 20-minute regeneration limit.

`requestHeaders()` refuses any `apns-topic` that is not `APNS_BUNDLE_ID` or one
of `APNS_ADDITIONAL_BUNDLE_IDS`.

---

## 6. Verification the owner can do (engineering cannot)

Real APNs delivery cannot be certified from a development session — it needs the
Apple credentials and a physical iOS device.

1. Backend readiness (no Apple call, safe anywhere):
   `php artisan tinker` → `app(\App\Services\Notifications\ApnsAuthKeyProvider::class)->status()`
   Expect `configured => true` and `problems => []`.
2. Real delivery: send a test push from the Firebase console
   (**Engage → Messaging → New campaign → Notifications**) to a physical iOS
   device that has granted notification permission, and confirm it arrives.
