<?php

namespace App\Console\Commands;

use App\Services\AiCopilotService;
use Illuminate\Console\Command;

class AiCopilotGenerateRecommendations extends Command
{
    protected $signature = 'ai-copilot:generate {--notify : Send notifications for high-confidence recommendations}';

    protected $description = 'Generate AI Ops Copilot recommendations for pending orders, stuck deliveries, and triage items';

    public function handle(AiCopilotService $copilotService): int
    {
        $mode = $copilotService->getMode();

        if ($mode === 'off') {
            $this->info('AI Ops is disabled. Enable it in settings to generate recommendations.');
            return self::SUCCESS;
        }

        $this->info("Running AI Copilot recommendation generation (mode: {$mode})...");

        $results = $copilotService->generateRecommendations();

        if (empty($results)) {
            $this->info('No recommendations generated.');
            return self::SUCCESS;
        }

        $total = collect($results)->sum('count');

        foreach ($results as $category => $data) {
            if (is_array($data) && isset($data['count'])) {
                $this->line("  {$data['label']}: {$data['count']} recommendations");
            }
        }

        $this->info("Total: {$total} recommendations generated.");

        if ($this->option('notify')) {
            $copilotService->notifyHighConfidenceRecommendations($results);
            $this->info('High-confidence notifications sent.');
        }

        return self::SUCCESS;
    }
}
