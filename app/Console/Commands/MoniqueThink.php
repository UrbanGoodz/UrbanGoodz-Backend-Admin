<?php

namespace App\Console\Commands;

use App\Services\UrbanGoodz\Agent\MoniqueBusinessAnalyst;
use Illuminate\Console\Command;

class MoniqueThink extends Command
{
    protected $signature = 'monique:think {--force : Ignore the once-a-day limit}';

    protected $description = 'Monique reviews the real business numbers and writes ranked recommendations for the admins';

    public function handle(MoniqueBusinessAnalyst $analyst): int
    {
        if (! config('urban_goodz_ai.monique_proactive.enabled', true)) {
            return self::SUCCESS;
        }
        $r = $analyst->run((bool) $this->option('force'));
        $this->info(json_encode($r));

        return self::SUCCESS;
    }
}
