<?php

namespace App\Console\Commands;

use App\Services\UrbanGoodz\RecurringRouteScheduler;
use Illuminate\Console\Command;

class GenerateRecurringRoutes extends Command
{
    protected $signature = 'routes:generate-recurring {--ahead=7 : How many days ahead to create runs}';
    protected $description = 'Create the upcoming runs of recurring routes (safe to run repeatedly)';

    public function handle(RecurringRouteScheduler $scheduler): int
    {
        $r = $scheduler->generate((int) $this->option('ahead'));
        $this->info("created={$r['created']} already_existed={$r['skipped']} unsupported_rules={$r['unsupported']}");

        return self::SUCCESS;
    }
}
