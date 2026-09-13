<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

/**
 * What the scheduler will actually run.
 *
 * This exists because four commands were declared in app/Console/Kernel.php
 * and nowhere else. Nothing binds that Kernel in this application - the
 * schedule the framework reads is the ->withSchedule() block in
 * bootstrap/app.php - so run-scheduled-sourcing, stranded:dispatch-tick,
 * delivery-history:prune and order-anywhere:recover-card-issuance had never
 * executed a single time, while looking perfectly scheduled to anyone reading
 * the Kernel.
 *
 * Asking `schedule:list` is the point: it is the scheduler's own account of
 * what it will run, so a command that drifts back out of it fails here rather
 * than silently never running again.
 */
class ScheduledCommandsAreRegisteredTest extends TestCase
{
    private ?string $listing = null;

    /**
     * The scheduler's own view of what it will run.
     *
     * Asking `schedule:list` rather than resolving Schedule from the container
     * is deliberate: the ->withSchedule() callback is applied when the console
     * kernel builds the schedule, so a container lookup inside a test comes
     * back empty and would make every assertion here pass vacuously.
     */
    private function listing(): string
    {
        if ($this->listing === null) {
            \Illuminate\Support\Facades\Artisan::call('schedule:list');
            // Strip ANSI colour so the cron expressions can be matched.
            $this->listing = preg_replace("/\e\[[0-9;]*m/", '', \Illuminate\Support\Facades\Artisan::output());
        }

        return $this->listing;
    }

    private function assertScheduled(string $command, string $expression): void
    {
        $listing = $this->listing();

        self::assertStringContainsString(
            $command,
            $listing,
            "[$command] is not scheduled at all, so it will never run. Declaring it in "
            . 'app/Console/Kernel.php does nothing - it belongs in the ->withSchedule() '
            . "block in bootstrap/app.php.
schedule:list said:
" . $listing
        );

        $found = false;
        foreach (preg_split('/\R/', $listing) as $line) {
            if (str_contains($line, $command)) {
                $found = true;
                self::assertStringContainsString(
                    $expression,
                    $line,
                    "[$command] is scheduled, but not on '{$expression}'. Line: " . trim($line)
                );
            }
        }

        self::assertTrue($found, "[$command] produced no line in schedule:list.");
    }

    public function test_the_four_commands_that_were_never_running_are_scheduled(): void
    {
        // A responder is waiting on this one in real time.
        $this->assertScheduled('stranded:dispatch-tick', '*    * * * *');
        $this->assertScheduled('order-anywhere:recover-card-issuance', '*/5  * * * *');
        $this->assertScheduled('run-scheduled-sourcing', '*/30 * * * *');
        $this->assertScheduled('delivery-history:prune', '20   3 * * *');
    }

    public function test_the_pre_existing_schedule_is_still_intact(): void
    {
        $this->assertScheduled('ai-copilot:generate', '*/15 * * * *');
        $this->assertScheduled('sync-load-board', '*/30 * * * *');
        $this->assertScheduled('queue:work', '*    * * * *');
    }
}
