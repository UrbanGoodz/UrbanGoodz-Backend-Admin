<?php

namespace App\Console\Commands;

use App\Models\UrbanGoodzStrandedNearbyPresence;
use Illuminate\Console\Command;

class PruneStrandedNearbyPresence extends Command
{
    protected $signature = 'stranded:prune-presence';

    protected $description = 'Delete expired Nearby help alert positions';

    public function handle(): int
    {
        $n = UrbanGoodzStrandedNearbyPresence::where('expires_at', '<', now())->delete();
        $this->info("Expired positions removed: {$n}");

        return self::SUCCESS;
    }
}
