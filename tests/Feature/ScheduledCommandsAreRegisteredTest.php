<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * The only live schedule is bootstrap/app.php's withSchedule(). A command defined anywhere else (the old
 * app/Console/Kernel.php) never runs, which silently disabled Stranded escalation, card-issuance recovery
 * and driver-history pruning. If a required command stops being scheduled, this fails.
 */
class ScheduledCommandsAreRegisteredTest extends TestCase
{
    public function test_every_required_command_is_on_the_live_schedule(): void
    {
        Artisan::call('schedule:list');
        $list = Artisan::output();

        foreach ([
            'queue:work',                              // drives every queued job, notifications included
            'stranded:dispatch-tick',
            'order-anywhere:dispatch-pending',
            'order-anywhere:recover-card-issuance',
            'routes:generate-recurring',
            'ai-concierge:prune-guests',
            'delivery-history:prune',
            'run-scheduled-sourcing',
            'sync-load-board',
        ] as $command) {
            $this->assertStringContainsString($command, $list, "{$command} is not on the live schedule");
        }
    }

    public function test_every_scheduled_command_exists(): void
    {
        $registered = array_keys(Artisan::all());
        foreach (['stranded:dispatch-tick', 'order-anywhere:dispatch-pending', 'order-anywhere:recover-card-issuance', 'routes:generate-recurring', 'ai-concierge:prune-guests', 'delivery-history:prune', 'run-scheduled-sourcing'] as $command) {
            $this->assertContains($command, $registered, "{$command} is scheduled but is not a registered command");
        }
    }
}
