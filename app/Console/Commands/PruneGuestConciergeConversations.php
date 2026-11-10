<?php

namespace App\Console\Commands;

use App\Models\UrbanGoodzAIConversation;
use Illuminate\Console\Command;

/**
 * Guest concierge chats have no account to prune against (signed-in chats are trimmed per customer),
 * and the endpoint is open, so without this the table only ever grows.
 */
class PruneGuestConciergeConversations extends Command
{
    protected $signature = 'ai-concierge:prune-guests {--days=30}';
    protected $description = 'Delete guest AI concierge conversations older than the retention window';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $deleted = 0;

        // In chunks, so one run never holds a long lock on a large table.
        do {
            $batch = UrbanGoodzAIConversation::whereNull('customer_id')
                ->where('created_at', '<', now()->subDays($days))
                ->limit(1000)
                ->delete();
            $deleted += $batch;
        } while ($batch === 1000);

        $this->info("deleted={$deleted}");

        return self::SUCCESS;
    }
}
