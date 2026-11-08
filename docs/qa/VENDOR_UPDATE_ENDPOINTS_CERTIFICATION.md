# Vendor update endpoints: payload-complete tenant isolation

## Why this pass existed
`VendorTenantIsolationTest` attacks with thin payloads. For the item, coupon and add-on **update** endpoints those attacks
returned **HTTP 500** (`count(null)` on a missing `translations`) before the handler ever looked up the record, so
"vendor B changed nothing" proved nothing about ownership for them. The owner test also never exercised the update endpoints.

## What was added (`VendorUpdatePayloadCompleteTest`)
For item, coupon, banner, advertisement, add-on, driver and order-status updates, each with a COMPLETE valid payload:
1. the owner applies it (so the payload is provably good, and a "404 for everyone" bug cannot pass);
2. the identical payload from another vendor is refused with 403/404 and changes nothing.
Result: **all seven are correctly tenant-scoped.** No cross-tenant hole was found in the update endpoints.

## Defects fixed
- A request with no (or malformed) `translations` crashed with a 500 in item, coupon, add-on and banner store/update
  (13 `count($data)` sites plus banner indexing). They now return a clean validation error.
- Coupon store/update added the "title is required" error to the validator's message bag and then called `->fails()`, which
  re-runs validation and discards manually added errors, so the check never fired. The error is now returned explicitly.

## Not covered / notes
- Endpoints with no id parameter (`update-profile`, `update-basic-info`, `update-business-setup`) act only on the caller's own
  store and cannot cross tenants.
- `update-order-amount` reaches its ownership check with the existing thin payload (covered by the original test).
- Image-upload variants of banner/advertisement update were not exercised.
