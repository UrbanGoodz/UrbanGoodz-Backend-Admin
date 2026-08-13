# Urban Goodz Database Schema Gap Report

Audit basis: static migration/model/controller inspection only. No database was opened or modified.

The generated table-to-source map is [URBAN_GOODZ_DATABASE_TABLE_USAGE_MATRIX.csv](URBAN_GOODZ_DATABASE_TABLE_USAGE_MATRIX.csv), containing 285 inferred table rows. It is a lexical usage map, not an authoritative schema.

## Foundational blocker: `orders`

The repository has no committed `Schema::create('orders', ...)` migration and no committed full installation schema/dump. At this SHA, 24 migrations alter `orders`:

1. `2022_05_14_122133_add_dm_tips_column_to_orders_table.php`
2. `2022_07_31_103626_add_free_delivery_by_column_to_orders_table.php`
3. `2022_10_18_093323_add_refund_request_cancel_column_to_orders_table.php`
4. `2022_12_29_114005_add_prescription_order_column_to_orders_table.php`
5. `2023_01_23_144828_add_tax_status_column_to_orders_table.php`
6. `2023_02_25_133409_add_vehicle_id_column_to_orders_table.php`
7. `2023_02_27_111937_add_cancellation_reason_col_to_orders_table.php`
8. `2023_02_27_162357_add_coupon_created_by_columns_to_orders_table.php`
9. `2023_03_02_103114_add_discount_on_product_by_column_to_orders_table.php`
10. `2023_05_16_104129_add_cutlery_processing_time_unavailable_product_note_col_to_orders_table.php`
11. `2023_05_18_143530_add_delivery_instruction_col_to_orders_table.php`
12. `2023_05_28_153920_add_tax_percentage_col_to_orders_table.php`
13. `2023_07_05_135741_add_service_charge_col_to_orders_table.php`
14. `2023_07_05_155429_add_order_proof_col_to_orders_table.php`
15. `2023_07_06_124530_add_partially_paid_amount_col_to_orders_table.php`
16. `2023_08_22_102914_add_is_guest_col_to_orders_table.php`
17. `2023_09_23_184806_add_flash_sale_cols_to_orders_table.php`
18. `2024_04_18_171851_add_cashback_ref_amount_cols_to_temp_orders_table.php`
19. `2024_05_28_112559_add_change_order_attachment_column_to_orders_table.php`
20. `2025_07_05_070056_add_tax_type_col_to_order_table.php`
21. `2025_09_20_141407_add_bring_change_amount_col_to_orders_table.php`
22. `2026_03_09_151700_add_extra_discount_amount_col_to_orders_table.php`
23. `2026_07_09_000300_add_age_restricted_compliance_fields.php`
24. `2026_07_09_000500_add_customer_age_confirmation_to_orders.php`

The filename in item 18 says `temp_orders`, but its source also alters `orders`; it is included intentionally.

This prevents a clean database build from committed migrations. It also means source models/tests may reference columns supplied only by an uncommitted vendor installation schema.

## Financial tables located

| Table | Source | Current concern |
|---|---|---|
| `urban_goodz_driver_pricing_policies` | 2026-07-17 migration | safety/approval flags persist but are not enforced |
| `urban_goodz_driver_earnings` | 2026-07-08 migration | approved status credits wallet immediately |
| `urban_goodz_driver_payout_requests` | 2026-07-08 migration | “paid” can be recorded without processor settlement |
| `urban_goodz_dispatch_commissions` | 2026-07-12 migration | no approval/pay/ledger endpoints located |
| `urban_goodz_payment_ledgers` | 2026-07-03 migration | useful idempotency fields; not universal settlement proof |
| `urban_goodz_payment_splits` | payment migrations/models | partial split trace; cross-feature consistency unproven |
| `delivery_man_wallets` | legacy schema/model | credited by approved earnings; origin schema completeness unproven |

## AI Chief of Staff tables located

The service expects `ai_tasks`, `ai_approvals`, `business_needs`, `human_action_items`, `merchant_prospects`, `orders`, and vendor data. The service mutates business needs and human action items from a GET page request. No transaction, approval gate, or Chief-of-Staff-specific audit row surrounds those writes.

## Safe schema recovery plan

1. Obtain a read-only schema-only export from the authoritative production-compatible installation database. Exclude all row data, secrets, routines with credentials, and user grants.
2. Record database engine/version and export command in a restricted evidence file.
3. Compare the export to all committed migrations and models; do not treat the export as automatically correct.
4. Build a clean baseline schema migration or sanitized framework schema dump in a dedicated schema-recovery branch.
5. Add automated empty-database migration coverage before changing application behavior.
6. Run the migration chain against a disposable, known database only.
7. Add foreign-key ordering tests and fix the two known test-code FK teardown defects separately from schema drift.
8. Prove rollback behavior for newly introduced migrations.
9. Re-run focused domain tests, then the full suite, then staging browser workflows.
10. Do not run production migrations until a reviewed backup, migration plan, exact SHA, rollback plan, and maintenance window exist.

## What remains unknown

- Authoritative production schema and current migration ledger.
- Whether all 285 inferred tables exist in production.
- Foreign keys, indexes, collation, generated columns, triggers, and defaults not represented in committed source.
- Whether legacy vendor installation files outside this repository created foundational tables.
- Data quality and reconciliation state for wallets, payouts, commissions, and ledgers.
