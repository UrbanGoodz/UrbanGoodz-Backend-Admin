# Payout / withdrawal race certification

## Defects
1. **Over-withdrawal race (driver x2 paths, vendor).** Driver (`DeliverymanController::request_withdraw`,
   `UrbanGoodzDriverApiController::requestPayout`) and vendor (`UrbanGoodzVendorPayoutController::request_`)
   payout requests read the balance, compared, then inserted with no lock. Concurrent requests could all pass
   and together withdraw more than owed. The vendor path also reserved `pending_withdraw` non-atomically.
2. **Vendor payouts could never be created.** `urban_goodz_driver_payout_requests.delivery_man_id` was
   NOT NULL, but vendor payouts carry no driver. Every vendor payout request failed with integrity error 1048.
   Found by the race test. Fixed by migration `2026_11_07_000001_make_payout_request_delivery_man_nullable`.

## Fix
`App\Services\UrbanGoodzPayoutGuard` locks the payee row (`delivery_men` row / `store_wallets` row) in a
transaction, recomputes the balance inside the lock, creates the request and (vendor) reserves the amount in the
same transaction. The three controllers call it and return their existing error shapes if the balance was
lost to a concurrent request.

## Verification
`tests/Feature/PayoutRaceTest.php`: 8 real OS processes request 80 against a balance of 100.
- Without the locks: 8 of 8 requests succeed (both tests fail).
- With the locks: exactly 1 succeeds; one request row; vendor reserve taken once.
Full suite: 1,386 tests passing.

## Deploy note
The migration must run in production before vendor payouts can work.
