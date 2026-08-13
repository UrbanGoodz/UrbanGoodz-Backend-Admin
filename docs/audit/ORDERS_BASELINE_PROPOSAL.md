# Orders Baseline Proposal — field classification

**This document contains NO executable migration.** It classifies the existing
`orders` table so an owner can decide what a baseline should look like. Every
statement below was read from the reconciled staging schema
(`urbangoodz_isolated_staging_20260723`), not from migration files.

`orders` currently has **79 columns**.

---

## 1. Money (20 columns) — the material finding

The table mixes two incompatible numeric representations for currency:

**Exact (`decimal`) — correct:**

| Column | Type |
|--------|------|
| `order_amount` | `decimal(24,2)` |
| `coupon_discount_amount` | `decimal(24,2)` |
| `total_tax_amount` | `decimal(24,2)` |
| `delivery_charge` | `decimal(24,2)` |
| `original_delivery_charge` | `decimal(24,2)` |
| `store_discount_amount` | `decimal(24,2)` |
| `extra_discount_amount` | `decimal(10,2)` |
| `adjusment` | `decimal(24,2)` *(sic — misspelled in the schema)* |

**Binary floating point (`double`) — cannot represent currency exactly:**

| Column | Type |
|--------|------|
| `additional_charge` | `double(23,3)` |
| `partially_paid_amount` | `double(23,3)` |
| `flash_admin_discount_amount` | `double(24,3)` |
| `flash_store_discount_amount` | `double(24,3)` |
| `extra_packaging_amount` | `double(23,3)` |
| `ref_bonus_amount` | `double(23,3)` |
| `dm_tips` | `double(24,2)` |
| `tax_percentage` | `double(24,3)` *(a rate, not an amount — acceptable)* |

Two further money columns outside `orders` share the defect and are asserted
by the P0 suite: `partial_payments.amount` and
`subscription_billing_and_refund_histories.amount`, both `double`.

**Classification:** an order total is assembled by summing `decimal` and
`double` fields. The `double` terms introduce binary rounding error that the
`decimal` terms do not, so the same order can reconcile differently depending
on which fields are non-zero. This is the single highest-value correction a
baseline could make.

**Also note:** `bring_change_amount` is `int` — it cannot hold a fractional
amount at all.

**Proposed (NOT executed):** normalise every currency *amount* to
`decimal(24,2)`, keep rates (`tax_percentage`) as-is, and decide explicitly
whether `bring_change_amount` is minor units or a bug. This is a data-bearing
change and must not be run against production without a backfill and
reconciliation plan.

---

## 2. Lifecycle timestamps (13 columns) — denormalised state machine

`pending`, `accepted`, `confirmed`, `processing`, `handover`, `picked_up`,
`delivered`, `canceled`, `refund_requested`, `refunded`, `failed`,
`refund_request_canceled` are each a nullable `timestamp` column, alongside a
separate `order_status varchar(255)` string.

**Classification:** the order state machine is stored twice — once as a
string, once as 12 sparse columns — with no database-level constraint keeping
them consistent. `order_status` is an unconstrained `varchar`, not an `enum`,
so any string can be written.

**Proposed (NOT executed):** keep the timestamps as an audit trail, but
constrain `order_status` to a known vocabulary. Extracting a separate
`order_status_transitions` table is the cleaner end state but is a larger
change than a baseline should absorb.

---

## 3. PII (5 columns)

`delivery_address`, `delivery_address_id`, `order_note`,
`unavailable_item_note`, `cancellation_note`, plus `receiver_details`
(`longtext`, classified under "other" but PII-bearing) and `order_proof` /
`order_attachment` (delivery photos).

**Classification:** `delivery_address` is stored inline as `text` *and*
referenced by `delivery_address_id`. Inline storage means a customer's
address cannot be redacted by deleting the referenced address row — a
data-deletion request would leave the address behind on every historical
order.

**Proposed (NOT executed):** decide whether inline `delivery_address` is a
deliberate immutable snapshot (defensible for dispute handling) or an
accident. If deliberate, document it as a retention obligation.

---

## 4. Foreign keys (9 columns)

`user_id`, `delivery_man_id`, `store_id`, `zone_id`, `module_id`,
`parcel_category_id`, `dm_vehicle_id`, `cash_back_id` — all
`bigint unsigned`.

The reconciled schema carries **84 foreign key constraints across 242
tables**, which is sparse. Whether `orders` FKs are enforced at the database
level or only in application code should be confirmed per column before a
baseline claims referential integrity.

---

## 5. Sensitive / operational (selected)

- `otp varchar(255)` — an OTP stored on the order row in plaintext-capable
  form. Retention and hashing need an owner decision.
- `transaction_reference varchar(30)` — payment correlation id.
- `callback varchar(255)` — payment gateway callback target.

---

## 6. Remaining categories

- **Status/type (5):** `payment_status`, `order_status`, `payment_method`,
  `order_type`, `age_verification_status` — all unconstrained `varchar`.
- **Flags (7):** `checked`, `scheduled`, `edited`, `prescription_order`,
  `age_restricted_order`, `cutlery`, `is_guest` — `tinyint(1)`.
- **Other (27):** coupon, distance, cancellation and attachment fields.

---

## Recommended baseline scope

1. Currency type normalisation (section 1) — highest value, highest risk.
2. `order_status` vocabulary constraint (section 2).
3. An explicit decision on inline `delivery_address` retention (section 3).

Items 1 and 3 are data-bearing. Neither should be executed without a
production backfill plan, and neither is executed here.
