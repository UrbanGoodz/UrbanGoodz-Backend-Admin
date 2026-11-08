# Customer wallet, tracking and refunds certification

## Defects fixed
1. **Partner wallet credit (`customer/wallet/transfer-mart-from-drivemond`, no user session by design).**
   Shared secrets were compared with loose `==` (numeric-looking strings like `0e111`/`0e222` are "equal" in PHP);
   `amount` was only `required` (negative/non-numeric reached the balance); the credit was a non-atomic `+=`
   (concurrent credits lost one); a replayed call credited twice; an unsuccessful partner lookup hit an undefined variable.
   Now: `hash_equals` on all secrets, `numeric|gt:0|max:1000000`, row-locked transaction, 120-second replay guard that is
   released on failure, safe error responses. Tests: `PartnerWalletTransferTest`.
2. **Refund request** (`customer/order/refund-request`): a guest got a 500 (null user dereference); two taps could create two
   Refund rows for one order. Now guests get a clean 403 and the order is re-checked under a row lock. Tests: `CustomerRefundRequestTest`.
3. **Environment/schema:** `orders.refund_requested` existed in the baseline dump but in no migration, so any
   migrations-built environment failed every refund request. Guarded migration added.
4. Public order tracking (`customer/order/track`, guest = order id + phone number) had no rate limit; now 30/min.

## Verified (no change needed)
- Authenticated tracking is scoped to the caller (`user_id` + `is_guest = 0`); guest tracking requires both id and the order's phone.
- Customer notification, wallet-transaction and order endpoints are owner-scoped (see Customer app certification).

## Open
- Partner transfer has no partner-supplied transfer id, so replay protection is a time window; ask the partner (DriveMond)
  to send a unique transfer id and dedupe on it.
- `refund_request` reads `BusinessSetting::where('refund_active_status')->first()->value` and will 500 if that setting row is missing.
- Refund approval/payout flow (admin side) not re-audited here.
