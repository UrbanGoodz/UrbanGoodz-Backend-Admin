<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Vendor;
use App\Services\UrbanGoodz\Agent\MoniqueEntitlementService;
use App\Services\UrbanGoodz\Agent\MoniqueProactiveAttentionService;
use Illuminate\Console\Command;

class MoniqueEmployeeWorkerCommand extends Command
{
    protected $signature = 'monique:work {--account-type=all : The account type to monitor (admin, vendor, all)}';

    protected $description = 'Run the Monique AI Employee loop: observe, prioritize, act, verify, and notify';

    public function handle(
        MoniqueProactiveAttentionService $attention,
        MoniqueEntitlementService $entitlement
    ): int {
        $this->info('Starting Monique AI Employee operating loop...');

        $type = $this->option('account-type');

        if ($type === 'admin' || $type === 'all') {
            $admins = Admin::all();
            foreach ($admins as $admin) {
                $sub = $entitlement->checkEntitlement('admin', $admin->id);
                if ($sub['allowed']) {
                    $res = $attention->observeAndAct('admin', $admin->id);
                    $this->line("Admin #{$admin->id}: {$res['observations_total']} observed, {$res['auto_resolved_count']} auto-resolved, {$res['notifications_created']} alerts.");
                }
            }
        }

        if ($type === 'vendor' || $type === 'all') {
            $vendors = Vendor::where('status', 1)->get();
            foreach ($vendors as $vendor) {
                $sub = $entitlement->checkEntitlement('vendor', $vendor->id);
                if ($sub['allowed']) {
                    $res = $attention->observeAndAct('vendor', $vendor->id);
                    $this->line("Vendor #{$vendor->id} ({$vendor->f_name}): {$res['observations_total']} observed, {$res['auto_resolved_count']} auto-resolved, {$res['notifications_created']} alerts.");
                }
            }
        }

        $this->info('Monique AI Employee loop complete.');
        return Command::SUCCESS;
    }
}
