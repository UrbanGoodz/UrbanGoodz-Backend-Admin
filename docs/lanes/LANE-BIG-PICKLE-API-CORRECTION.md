# LANE: BIG PICKLE — correction on the production API probe

You concluded:

> "Direct API probing hits the admin-login SPA fallback (StackFood 302-on-auth);
> the UI drive will be authoritative."

That is not what is happening, and acting on it will cost you a lot of time for
no reason. The API is fine. Your URL simply did not match any route, so Laravel
fell through to the catch-all, which redirects to the admin login. Every
unmatched path on that host returns the same 302 and the same login HTML — so
that response tells you nothing about auth.

## The actual shape of the endpoint

`zone_id` is not a query parameter. The route is:

```
GET /api/v1/stores/get-stores/{filter_data}
```

`{filter_data}` is a **path segment** (`all`, `popular`, `latest`, `new`), and
the zone and module travel as **headers**, not query params.

What you sent, and what it returns:

```
GET /api/v1/stores/get-stores?zone_id=2&offset=1&limit=5     → 302, admin login HTML
```

What actually works, verified against production just now:

```bash
curl -H 'moduleId: 4' -H 'zoneId: [2]' -H 'Accept: application/json' \
  'https://admin.urbangoodzdelivery.com/api/v1/stores/get-stores/all?offset=1&limit=3'
```

```
200, 17454 bytes
{"total_size":20,"limit":"3","offset":"1","stores":[{"id":6,
 "name":"Frenchy's Chicken - Scott St","address":"3602 Scott St, Houston, TX 77004", ...
```

20 real stores in zone 2. No auth needed for this one.

## The two headers matter everywhere

`moduleId` and `zoneId` are how this codebase scopes nearly every customer
endpoint. `zoneId` is a JSON array in a header: `zoneId: [2]`. Without them you
get empty results or a redirect, which reads like a broken API and is not.

Endpoints under `/api/v1/customer/**` additionally need
`Authorization: Bearer <token>` from `POST /api/v1/auth/login`, and that login
takes `email_or_phone` + `field_type` — **not** `phone`:

```json
{"email_or_phone": "+1...", "field_type": "phone", "password": "...", "login_type": "manual"}
```

## Two more things that will bite you on the order flow

1. **The cart is server-side.** `POST /api/v1/customer/order/place` reads the
   `carts` table, not a `cart` array in your payload (unless `is_buy_now=1`).
   Post to `/api/v1/customer/cart/add` first or you get
   "You can not place empty orders".

2. **The delivery point must be inside the zone POLYGON**, not just near it —
   the zone is resolved with `ST_Contains`. A plausible-looking downtown
   lat/long lands outside and you get "Out of coverage area".

## You do not need to rediscover the order flow

I have the whole lifecycle passing at the API level against a local copy of this
backend — customer login, browse, add to cart, checkout, vendor prepare and
handover, driver accept, pick up, deliver: `scripts/order-e2e-test.php` in
AdminPanel_Update_V39, 21/21. Read it for the exact payloads rather than
reverse-engineering them.

Your job is still the **app** — that the customer UI drives this correctly on a
real device. The API contract is settled; do not spend the build slot proving it
again.
