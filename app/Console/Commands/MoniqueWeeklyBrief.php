<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\AiMoniqueNotification;
use App\Services\UrbanGoodz\Agent\MoniqueBusinessAnalyst;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Once a week Monique emails each admin her latest business read (the same real-number insights she
 * posts in the dashboard), so the owner sees it without opening the panel. Never invents anything:
 * it only mails what the analyst already wrote, and says so plainly when there is nothing to send.
 */
class MoniqueWeeklyBrief extends Command
{
    protected $signature = 'monique:weekly-brief {--dry-run : Show who would get the brief and send nothing}';

    protected $description = 'Email each admin Monique\'s latest weekly business brief';

    public function handle(MoniqueBusinessAnalyst $analyst): int
    {
        if (! config('urban_goodz_ai.monique_proactive.enabled', true)
            || ! (bool) config('urban_goodz_ai.monique_proactive.weekly_brief_email', true)) {
            return self::SUCCESS;
        }

        $sent = 0;
        foreach (Admin::query()->whereNotNull('email')->get(['id', 'email', 'f_name']) as $admin) {
            $latest = $this->latestFor($admin->id);
            if (! $latest && ! $this->option('dry-run')) {
                $analyst->run(true);
                $latest = $this->latestFor($admin->id);
            }
            if (! $latest) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("Would send to admin #{$admin->id}: " . $latest->title);
                continue;
            }
            try {
                Mail::raw(
                    $latest->message . "\n\nOpen the admin dashboard for the full picture. - Monique",
                    fn ($m) => $m->to($admin->email)->subject('Monique: ' . $latest->title)
                );
                $sent++;
            } catch (\Throwable $e) {
                Log::warning('Monique weekly brief email failed', ['admin_id' => $admin->id, 'error' => $e->getMessage()]);
            }
        }
        $this->info("Weekly brief sent to {$sent} admin(s).");

        return self::SUCCESS;
    }

    private function latestFor(int $adminId): ?AiMoniqueNotification
    {
        return AiMoniqueNotification::forAccount('admin', $adminId)
            ->where('category', MoniqueBusinessAnalyst::CATEGORY)
            ->where('created_at', '>=', now()->subDays(2))
            ->latest('id')
            ->first();
    }
}
