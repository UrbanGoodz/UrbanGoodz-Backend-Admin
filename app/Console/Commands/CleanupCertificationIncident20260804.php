<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CleanupCertificationIncident20260804 extends Command
{
    protected $signature = 'urban-goodz:cleanup-certification-incident-20260804 {--dry-run : Perform a dry run without deleting data} {--force : Force production execution}';

    protected $description = 'Clean up synthetic certification incident records created on August 4 2026';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("=================================================");
        $this->info(" URBAN GOODZ INCIDENT CLEANUP COMMAND (20260804) ");
        $this->info(" Mode: " . ($isDryRun ? "DRY RUN (No Data Deleted)" : "LIVE EXECUTION"));
        $this->info("=================================================");

        if (!$isDryRun && !$force) {
            $this->error("Live execution requires --force option.");
            return 1;
        }

        $allowlist = [
            'orders' => range(100013, 100024),
            'order_details' => range(14, 25),
            'order_transactions' => range(8, 18),
            'order_anywhere_requests' => range(6, 16),
            'urban_goodz_service_requests' => range(1, 10),
            'fashion_fit_consents' => range(2, 11),
            'fashion_fit_audit_events' => range(3, 12),
            'urban_goodz_package_scans' => [1, 2, 3, 5, 6],
            'urban_goodz_batch_packages' => [3, 4, 5, 6],
            'urban_goodz_route_packages' => [57, 58],
            'urban_goodz_intake_batches' => range(2, 8),
            'urban_goodz_route_optimization_histories' => [1, 3, 4],
            'withdraw_requests' => [1, 2, 3],
            'user_notifications' => [36, 37, 38],
        ];

        // Process order: child tables first, then parent tables
        $tablesOrder = [
            'order_details',
            'order_transactions',
            'orders',
            'order_anywhere_requests',
            'urban_goodz_service_requests',
            'fashion_fit_audit_events',
            'fashion_fit_consents',
            'urban_goodz_package_scans',
            'urban_goodz_batch_packages',
            'urban_goodz_route_packages',
            'urban_goodz_intake_batches',
            'urban_goodz_route_optimization_histories',
            'withdraw_requests',
            'user_notifications',
        ];

        $summary = [];

        DB::beginTransaction();

        try {
            foreach ($tablesOrder as $table) {
                if (!Schema::hasTable($table)) {
                    $summary[$table] = 0;
                    continue;
                }

                $ids = $allowlist[$table] ?? [];
                $cnt = DB::table($table)->whereIn('id', $ids)->count();
                $summary[$table] = $cnt;

                if (!$isDryRun && $cnt > 0) {
                    DB::table($table)->whereIn('id', $ids)->delete();
                }
            }

            if ($isDryRun) {
                DB::rollBack();
                $this->info("Dry run completed. Transaction rolled back.");
            } else {
                DB::commit();
                $this->info("Live cleanup execution SUCCESS. Transaction committed.");
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Cleanup failed and rolled back: " . $e->getMessage());
            return 1;
        }

        foreach ($summary as $tbl => $c) {
            $this->line("  Table {$tbl}: {$c} rows " . ($isDryRun ? "found for deletion" : "deleted"));
        }

        $jsonEvidence = json_encode(['timestamp' => now()->toIso8601String(), 'dry_run' => $isDryRun, 'summary' => $summary], JSON_PRETTY_PRINT);
        $this->line("\nEVIDENCE_JSON:\n" . $jsonEvidence);

        return 0;
    }
}
