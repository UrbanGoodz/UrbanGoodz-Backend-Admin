<?php
/**
 * PATH C — seed the `migrations` ledger.
 *
 * Records every migration whose intended schema effect is already verified
 * present in the imported candidate baseline, WITHOUT executing its DDL.
 * Everything else is deliberately left unrecorded so that a subsequent
 * `php artisan migrate` executes exactly — and only — that set.
 *
 * Verdicts come from scripts/audit/pathc_ledger.php. The overrides below are
 * the migrations the static classifier could not decide; each one was resolved
 * by reading the migration source and querying the live schema, and each
 * carries its evidence inline. None was silently skipped.
 *
 * Usage: php scripts/audit/pathc_seed_ledger.php <db> [--apply]
 */

$db    = $argv[1] ?? 'urbangoodz_pathc_20260723';
$apply = in_array('--apply', $argv, true);

$root = dirname(__DIR__, 2);

/*
 * RESOLVED OVERRIDES — classifier could not decide these statically.
 *
 * 'represented' => effect verified present; recording without execution.
 *                  Executing would be a no-op at best, corrupting at worst.
 * 'execute'     => effect verified ABSENT or not schema-observable; must run.
 */
$overrides = [
    // --- resolved to REPRESENTED (record, never execute) ---
    '2022_04_21_145207_add_column_to_modules_table' => ['represented',
        'up() body is an explicit no-op: "Already created in create_modules_table".'],

    '2022_12_29_103803_add_order_id_column_to_expenses_table' => ['represented',
        'renameColumn(description -> order_id). expenses.order_id ALREADY EXISTS, and a '
        . 'separate expenses.description also exists. Re-running would rename the current '
        . 'description column onto the existing order_id and collide. Effect is present.'],

    '2024_04_18_171021_add_halal_extra_packaging_cols_to_store_configs_table' => ['represented',
        'Ternary table name defeats static parse. store_configs.halal_tag_status, '
        . '.extra_packaging_status, .extra_packaging_amount all verified present.'],

    '2024_10_22_133944_add_minimum_stock_for_warning_col_to_store_confg' => ['represented',
        'Ternary table name. store_configs.minimum_stock_for_warning verified present.'],

    '2025_09_21_171906_add_section_wise_ai_use_count_to_store_configs' => ['represented',
        'Ternary table name. store_configs.section_wise_ai_use_count and '
        . '.image_wise_ai_use_count verified present.'],

    '2026_04_06_000001_add_verified_seller_to_store_configs_table' => ['represented',
        'Ternary table name. store_configs.verified_seller and '
        . '.has_seen_verified_badge_popup verified present.'],

    '2026_04_13_120000_add_show_low_stock_count_to_store_configs_table' => ['represented',
        'Ternary table name. store_configs.show_low_stock_count verified present.'],

    '2025_07_24_123609_add_index_to_items_table' => ['represented',
        'Index-only migration. All 8 targets verified present: items_category_id_index, '
        . 'items_store_id_index, items_name_index, items_slug_index, items_price_index, '
        . 'items_created_at_index, items_order_count_index, items_avg_rating_index.'],

    '2025_07_24_131029_add_index_to_reviews_table' => ['represented',
        'Index-only. All 6 verified present: reviews_item_id_index, '
        . 'reviews_item_campaign_id_index, reviews_user_id_index, reviews_order_id_index, '
        . 'reviews_store_id_index, reviews_review_id_index.'],

    '2025_07_24_131340_add_index_to_wishlists_table' => ['represented',
        'Index-only. All 3 verified present: wishlists_user_id_index, '
        . 'wishlists_item_id_index, wishlists_store_id_index.'],

    '2025_07_27_152011_add_index_to_categories_table' => ['represented',
        'Index-only. Both verified present: categories_parent_id_index, categories_name_index.'],

    // --- resolved to EXECUTE ---
    '2026_07_12_130000_complete_service_booking_workflow' => ['execute',
        'Guarded/idempotent, so no parsable literal Schema:: target. Live check shows '
        . 'urban_goodz_service_booking_events, _service_provider_earnings and _service_reviews '
        . 'are ABSENT and urban_goodz_service_requests has only 13 columns. Genuinely applicable.'],

    '2026_07_16_160000_add_fulfillment_and_card_issuing_fields_to_order_anywhere_requests_table' => ['execute',
        'Classifier saw payment_captured_at as present, but that column is only ->change()d, '
        . 'not added. Every genuine ADD (fulfillment_type, sourcing_status, shopper_id, '
        . 'shopper_status, card_issued, …) is absent. All 7 external after() anchors verified '
        . 'present (status, vendor_id, payment_refunded_at, receipt_path, capture_reference, '
        . 'refund_reference, driver_payout_amount). Unguarded but safe to run.'],

    '2026_07_19_120000_add_intake_batch_id_and_route_label_to_dedicated_routes' => ['execute',
        'Fully guarded by hasColumn(). business_client_id present (will be skipped); '
        . 'intake_batch_id and route_label absent. FK target urban_goodz_intake_batches is '
        . 'created by 2026_07_19_061756, which sorts earlier. Idempotent.'],

    '2026_07_19_160400_create_merchant_prospects_table' => ['execute',
        'Both merchant_prospects and merchant_prospect_order_anywhere are ABSENT. The '
        . 'unconditional dropIfExists in up() is defensive cleanup, not forward intent.'],

    '2026_07_10_000002_fix_mail_config_smtp_host' => ['execute',
        'Data-only; effect is not schema-observable so it cannot be verified as represented. '
        . 'Returns early when the business_settings mail_config row is absent. Idempotent.'],

    '2026_07_12_000003_encrypt_mail_config_password' => ['execute',
        'Data-only; not schema-observable. Returns early when mail_config is absent, and '
        . 're-checks isEncrypted() before acting. Idempotent.'],

    '2026_07_16_140000_seed_urban_goodz_ai_intents' => ['execute',
        'Data seed via updateOrInsert (idempotent). Seeds AI intent configuration that '
        . 'staging needs. Not schema-observable.'],

    '2026_07_16_140001_seed_urban_goodz_test_records' => ['execute',
        'Data seed via updateOrInsert (idempotent). Seeds logistics/medical/creator sample '
        . 'rows appropriate for an isolated staging environment. Not schema-observable.'],
];

/* Recompute base verdicts by delegating to the classifier. */
$classifier = $root . '/scripts/audit/pathc_ledger.php';
exec('php ' . escapeshellarg($classifier) . ' ' . escapeshellarg($db) . ' --write-ledger', $o, $rc);
if ($rc !== 0) {
    fwrite(STDERR, "classifier failed\n");
    exit(1);
}

$ledgerCsv = $root . '/docs/audit/pathc_migration_ledger.csv';
$fh = fopen($ledgerCsv, 'r');
$hdr = fgetcsv($fh);
$rows = [];
while ($row = fgetcsv($fh)) {
    $rows[] = array_combine($hdr, $row);
}
fclose($fh);

$record  = [];   // migration => reason
$execute = [];   // migration => reason

foreach ($rows as $r) {
    $name = $r['migration'];

    if (isset($overrides[$name])) {
        [$action, $why] = $overrides[$name];
        if ($action === 'represented') {
            $record[$name] = 'RESOLVED: ' . $why;
        } else {
            $execute[$name] = 'RESOLVED: ' . $why;
        }
        continue;
    }

    switch ($r['verdict']) {
        case 'REPRESENTED':
            $record[$name] = $r['detail'];
            break;
        case 'APPLICABLE':
            $execute[$name] = $r['detail'];
            break;
        default:
            fwrite(STDERR, "UNRESOLVED WITH NO OVERRIDE: {$name} [{$r['verdict']}]\n");
            exit(2);
    }
}

printf("TOTAL MIGRATIONS      : %d\n", count($rows));
printf("RECORD AS REPRESENTED : %d\n", count($record));
printf("LEAVE PENDING (EXEC)  : %d\n", count($execute));

if (count($record) + count($execute) !== count($rows)) {
    fwrite(STDERR, "LEDGER DOES NOT ACCOUNT FOR EVERY MIGRATION\n");
    exit(3);
}

if (!$apply) {
    echo "\n(dry run — pass --apply to write the ledger)\n";
    echo "\nWOULD EXECUTE:\n";
    foreach ($execute as $n => $_) {
        echo "  {$n}\n";
    }
    exit(0);
}

require_once __DIR__ . '/pathc_ledger_lib.php';

$pdo = pathc_pdo($db);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id int unsigned NOT NULL AUTO_INCREMENT,
        migration varchar(255) NOT NULL,
        batch int NOT NULL,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$existing = $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
if ($existing > 0) {
    fwrite(STDERR, "REFUSING: migrations table already holds {$existing} rows.\n");
    exit(4);
}

$ins = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, 1)');
$pdo->beginTransaction();
foreach (array_keys($record) as $name) {
    $ins->execute([$name]);
}
$pdo->commit();

printf("\nLEDGER WRITTEN: %d migrations recorded as batch 1 (represented, not executed)\n", count($record));
printf("PENDING FOR artisan migrate: %d\n", count($execute));

/* Evidence file — why each recorded migration was not executed. */
$out = fopen($root . '/docs/audit/pathc_ledger_decisions.csv', 'w');
fputcsv($out, ['migration', 'action', 'evidence']);
foreach ($record as $n => $why) {
    fputcsv($out, [$n, 'RECORDED-NOT-EXECUTED', $why]);
}
foreach ($execute as $n => $why) {
    fputcsv($out, [$n, 'LEFT-PENDING-WILL-EXECUTE', $why]);
}
fclose($out);
