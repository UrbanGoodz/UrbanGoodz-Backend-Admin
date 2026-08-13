# Orders Baseline Reconciliation

**Date:** 2026-07-23
**Status: PROPOSAL — NOT APPROVED FOR EXECUTION**
**Source of evidence:** CANDIDATE SCHEMA BASELINE (production provenance UNVERIFIED)

No orders schema was invented. Every statement below is derived from the candidate
baseline as imported, from the repository migrations, or from application code.

---

## 1. The gap

The repository contains **337** migrations. **Zero** of them create the `orders` table:

- No `Schema::create('orders')` exists anywhere in `database/migrations/`.
- All 22 orders-related migration files are `add_*_column_to_orders_table` ALTERs.
- Path A proves the consequence: migrating from empty fails at the 18th migration with
  `Table 'orders' doesn't exist`, having produced 16 tables and no `orders`.

The candidate baseline is currently the **only** artifact in the project that can produce
a working `orders` table.

---

## 2. Reconciliation result

Candidate `orders` definition: **79 columns**.

| Check | Result |
|---|---|
| Columns the 22 orders ALTER migrations would add | 29 |
| Of those, **absent** from the candidate baseline | **0** |
| Columns referenced in `Order` model (`$casts` + `$fillable`) | 33 |
| Of those, absent from the candidate baseline | 1 — `details_count` |
| Baseline columns not traceable to any migration or the model | 32 |

### `details_count` is not a schema gap

`details_count` is an Eloquent aggregate alias, not a column. It is produced by
`withCount('details')` and by explicit assignment in `app/CentralLogics/Helpers.php`:

```php
$item['details_count'] = (int) $item->details->count();
```

The `'details_count' => 'integer'` cast in `app/Models/Order.php` is defensive casting of
that computed value. The same pattern appears in `Message` and `Conversation`. **No column
is required.**

### Conclusion of the reconciliation

**The candidate `orders` definition has zero unexplained gaps against the repository
migrations and the application model.** Every column the migration history would add is
present, and every real column the model references is present.

This is strong corroborating evidence that the candidate `orders` table descends from the
genuine schema lineage. It is **not** proof that it matches current production — a
schema can be authentic to its lineage and still be out of date.

---

## 3. The 32 columns that define the missing create-orders migration

These baseline columns are not attributable to any surviving ALTER migration. They are
precisely what the absent original `create_orders` migration must have supplied:

```
id                       coupon_discount_title    payment_status
order_status             payment_method           transaction_reference
coupon_code              order_note               order_type
checked                  schedule_at              callback
otp                      pending                  accepted
confirmed                processing               handover
picked_up                delivered                canceled
refund_requested         refunded                 delivery_address
failed                   adjusment                edited
delivery_time            parcel_category_id       charge_payer
age_verification_status  tax_type
```

Notes carried forward without correction, as evidence:

- `adjusment` is misspelled in the live schema. Any reconstructed migration must
  reproduce the misspelling exactly or existing code will break.
- The status-timestamp columns (`pending` … `refunded`, `failed`) form the order
  lifecycle audit trail and are `timestamp NULL DEFAULT NULL`.

Verbatim `SHOW CREATE TABLE orders` evidence is preserved at
`docs/audit/orders_table_baseline.txt`.

---

## 4. Proposed baseline — and why it is not committed as a migration

**No executable migration is proposed at this time.** Per the recovery mandate, an
executable production migration may not be committed until all four gates pass:

| Gate | Status |
|---|---|
| Production schema comparison complete | **NOT DONE** — no read-only production export obtained |
| All code references reconciled | **DONE for `orders`** (§2); wallet / ledger / payout paths not yet traced |
| Focused tests pass | **NOT DONE** — never executed (disk halt) |
| Independent review approves | **NOT DONE** |

Only gate 2 is satisfied, and only for the `orders` table itself.

### Recommended path once unblocked

1. Obtain a fresh read-only `mysqldump --no-data` from production.
2. Diff production `orders` against the candidate `orders` (79 columns) column by column.
3. If they match, promote the candidate definition to a `create_orders` migration guarded
   by `Schema::hasTable('orders')` so it is a no-op on existing environments.
4. Sequence it before `2022_05_14_122133_add_dm_tips_column_to_orders_table`, the first
   migration that fails without it.
5. Re-run Path A end to end and require it to reach the final migration.

Step 3 must reproduce `adjusment` as spelled.

---

## 5. Unresolved

- **Production provenance.** The dump header reads `Host: localhost`; the source is a
  local database named `urbakkej_urbangoodzdelivery`. Schema-shape analysis dates the
  snapshot to between **2026-07-09 and 2026-07-12** (see
  `DATABASE_RECOVERY_PATH_RESULTS.md`). Whether production has diverged since is unknown.
- **Other tables with the same defect.** Only `orders` was reconciled in depth. Path A
  failed too early to reveal whether other tables are also missing their create
  migrations. Path C classification found 0 conflicts, which is encouraging but not
  conclusive.
- **Wallet, ledger, payout and refund reconciliation** against the baseline has not been
  performed.
