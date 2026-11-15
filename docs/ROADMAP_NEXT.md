# Urban Goodz: what makes it the best it can be

Ranked by (impact on launch and growth) over (effort), grounded in what the code and the live system actually do today.

## 0. Launch blockers (fix before spending on growth)
1. Stripe test webhook signing secret installed (user), then an end-to-end QA payment: hold -> cancel -> hold released.
2. Cron every minute (stranded tick, Order Anywhere dispatch and offer windows assume it). Host currently runs it every 5.
3. Customer, driver and vendor app builds with signing keys; phone checks of Skylar (2D), driver reimbursement screen,
   notification preferences, Stranded flows.
4. 11 pending driver applications (Monique now flags them); QA order 100013 cleanup, then drop MONIQUE_DELAYED_ORDERS_ACTION=ask.
5. Settings only the owner can supply: MAIL_FROM_ADDRESS, server map key, ORDER_ANYWHERE_MARKUP_PERCENT, SMS provider.
6. A STAGING environment. Everything so far was deployed straight to production by build-beside-and-swap; it worked, but one
   more person-made mistake is one outage. Staging + a nightly off-host backup copy + error tracking (Sentry-style) is the
   cheapest insurance in this list.

## 1. Stranded: better than a roadside club
- Free to ask, optional reward (done). Add: one-tap SOS that shares live location with chosen contacts; check-in timers.
- A membership ("Stranded Plus") using the existing member-fee settings: priority dispatch, pre-priced tows, covered fees.
- A real professional network: tow/mechanic onboarding, insurance docs, transparent quotes and ETAs, bidding when more than one answers.
- Coverage map for ops: where requests have no responder, to recruit Samaritans and providers where they are needed.
- Light requester path (ask first, verify while help is on the way) so verification never delays an emergency.
- Helper recognition: badges, streaks, "Samaritan of the month", points redeemable in the marketplace.
- Low-signal fallback (SMS), in-app chat, photo of the vehicle/plate, a live ETA that is honest.
- B2B: apartment complexes, rideshare/delivery fleets, employers, credit unions and insurers as channel partners.

## 2. Order Anywhere
- Paste a product link or snap a photo -> instant quote (the AI can read it).
- Transparent price breakdown, substitutions the customer approves in-app, scheduled and recurring orders, tips, ratings.
- Instant driver payouts, stacked/batched orders, surge-free "busy zone" incentives for drivers.

## 3. Creator Commerce (TikTok-style)
- A real For You feed: personalised ranking (watch time, saves, purchases), autoplay, infinite scroll.
- Shoppable tags that go straight to checkout; live shopping; creator storefronts.
- Video pipeline: transcoding, thumbnails, AI moderation before publish; creator tiers and fast payouts; affiliate attribution.

## 4. Skylar and Monique
- Skylar: memory (reorders, favourites, birthdays), proactive nudges, voice ordering, Spanish. Monique: weekly board-style
  brief, anomaly and fraud alerts, cash-flow forecast; widen her autonomy ladder as her actions prove themselves.
- Rigged 3D body of the real Skylar (today the only rig is the executive's).

## 5. Platform
- Admin 2FA, audit log, data export/delete requests (CCPA), accessibility pass, image CDN, feature flags, staged rollouts.
- Unified wallet/loyalty/gift cards, referral engine, in-app support with response-time targets.
