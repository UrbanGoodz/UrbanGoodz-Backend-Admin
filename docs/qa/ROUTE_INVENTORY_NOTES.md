# Route inventory (generated, 3,047 routes)

`docs/qa/ROUTE_INVENTORY.csv` lists every route with its resolved middleware, an `auth_class`
heuristic and whether a throttle is attached. **The auth_class column is a heuristic, not a verdict.**

Counts: admin 1595, vendor 549, customer 286, driver 164, business 120, authed 14, NONE 345.
113 of the NONE routes accept POST/PUT/PATCH/DELETE (80 without throttle).

Reviewed so far:
- `admin/product/analyze-image-auto-fill`, `admin/product/generate-title-suggestions`: flagged NONE but guarded;
  `ModulePermissionMiddleware` requires an authenticated admin/vendor. Not a defect.
- `payment/*` gateway callbacks: intentionally unauthenticated and CSRF-exempt; webhook signature
  verification per gateway is NOT yet reviewed.
- `_debugbar/*` appears only because the local `.env` has APP_DEBUG=true. Production must run APP_DEBUG=false.
- `api/v1/rental/user/*` cart/trip routes use `APIGuestMiddleware` (guest-id based); ownership checks NOT yet reviewed.
- `login_submit`: no route throttle, but the controller applies a RateLimiter (5 attempts / 2 min per IP).

Still to review: remaining unauthenticated mutating routes in the CSV (filter auth_class=NONE and a mutating method).
