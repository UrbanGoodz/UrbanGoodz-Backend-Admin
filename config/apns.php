<?php

/*
|--------------------------------------------------------------------------
| Apple Push Notification service (APNs) - auth key (.p8) configuration
|--------------------------------------------------------------------------
|
| The platform's two APNs push CERTIFICATES expired on 2021-12-18. Apple push
| certificates expire annually and must be regenerated and re-uploaded every
| year. A token-based APNs AUTH KEY (.p8) does not expire, works for every app
| bundle under the same Apple Developer team, and works for both the sandbox
| and production APNs environments. Certificate auth is therefore replaced.
|
| NOTHING HERE IS A CREDENTIAL. Only names and file paths live in this file.
| The .p8 itself must be placed outside the web root and never committed.
|
| See docs/urban-goodz/notifications/APNS_AUTH_KEY_SETUP.md for the exact
| Apple Developer console path the owner must follow.
|
*/

return [
    /*
    | Key ID: the 10-character identifier Apple shows next to the key
    | (Apple Developer -> Certificates, Identifiers & Profiles -> Keys).
    */
    'key_id' => env('APNS_KEY_ID'),

    /*
    | Team ID: the 10-character Apple Developer Team ID
    | (Apple Developer -> Membership details).
    */
    'team_id' => env('APNS_TEAM_ID'),

    /*
    | Bundle ID of the iOS app the token is being sent to. This is the APNs
    | "apns-topic" header. Separate customer/driver/vendor apps have separate
    | bundle IDs; the default below is the topic used when none is passed.
    */
    'bundle_id' => env('APNS_BUNDLE_ID'),

    /*
    | Additional bundle IDs allowed as apns-topic values. One auth key covers
    | every app in the team, so all Urban Goodz iOS apps can share this key.
    | Comma separated, e.g. "com.x.customer,com.x.driver".
    */
    'additional_bundle_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('APNS_ADDITIONAL_BUNDLE_IDS', ''))
    ))),

    /*
    | Absolute path to the downloaded AuthKey_<KEYID>.p8 file. Store it outside
    | the public docroot (for example storage/app/apns/AuthKey_XXXXXXXXXX.p8)
    | with 0600 permissions. Apple lets you download a key exactly once.
    */
    'auth_key_path' => env('APNS_AUTH_KEY_PATH'),

    /*
    | Optional alternative to the file: the PEM contents of the .p8 supplied
    | through the environment instead of the filesystem. Leave empty when
    | auth_key_path is used.
    */
    'auth_key_content' => env('APNS_AUTH_KEY_CONTENT'),

    /*
    | "production" -> api.push.apple.com, "sandbox" -> api.sandbox.push.apple.com
    */
    'environment' => env('APNS_ENVIRONMENT', 'production'),

    'endpoints' => [
        'production' => 'https://api.push.apple.com/3/device/',
        'sandbox' => 'https://api.sandbox.push.apple.com/3/device/',
    ],

    /*
    | Apple rejects provider tokens older than 1 hour and rate-limits token
    | regeneration to once per 20 minutes. 50 minutes sits inside both bounds.
    */
    'token_ttl_seconds' => (int) env('APNS_TOKEN_TTL_SECONDS', 3000),

    /*
    | Direct APNs sending is OFF by default. Urban Goodz iOS push currently
    | travels through FCM, which performs the APNs leg itself using the .p8 the
    | owner uploads to the Firebase console. Turn this on only if the platform
    | is switched to talking to APNs directly.
    */
    'direct_send_enabled' => filter_var(env('APNS_DIRECT_SEND_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
