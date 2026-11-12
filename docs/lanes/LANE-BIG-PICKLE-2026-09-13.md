# LANE: BIG PICKLE (customer app) — directive 2026-09-13

You own the **customer app** (`UrbanGoodz2026-Revised`, main @ `2ed891b`) and
nothing else. Do not edit, commit in, or run builds out of
`AdminPanel_Update_V39` — another lane is live in that tree right now and your
writes will collide with theirs.

## Context you need

The backend you are testing against is **production**, and it moved today.
`app_constants.dart:61` already points at `https://admin.urbangoodzdelivery.com`,
which is now at commit `340c725` (verified serving, login 200). You do not need
to change the base URL, and you should not point it at localhost.

## 1. Finish the release APK (you hold the build slot)

This is a 7.6 GB machine and two Gradle/Flutter builds at once crash both. You
have the slot until your build exits — the other lane is holding off. When it
finishes, say so, because that is the hand-back signal.

Install it on the attached device and record the exact byte size and the
`versionName+versionCode` you installed, not "it built".

## 2. Run one complete order, end to end, on production

This is the actual deliverable and it has not been done yet. A parity sweep and
`flutter analyze` do not cover it. Drive it as a real user would, through the
UI, and capture what the screen showed at each step:

1. Sign in as a customer (existing account — do not create one).
2. Browse to a store, add an item, reach checkout.
3. Place the order. **Payment is sandbox** — `URBAN_GOODZ_PAYMENT_MODE=sandbox`
   and there is no `STRIPE_LIVE_SECRET_KEY` on prod yet. Use the sandbox test
   card. Do **not** try to switch the backend to live mode; that needs a key
   only the owner can supply, and flipping it is out of scope for this lane.
4. Confirm the order appears with a real order ID and a status.
5. Follow it forward: vendor acceptance, driver assignment, status transitions,
   and the tracking view. Note precisely where it stops if it stops.

## 3. Report

Give a short written result per step: what you did, what the app showed, and
the order ID. For anything that failed, give the screen, the request, and the
response — not a guess at the cause.

## Explicitly out of scope

- The admin panel, vendor portal, and driver app.
- Anything requiring the Stripe live secret key.
- Creating accounts or entering payment credentials that are not the documented
  sandbox test values.
