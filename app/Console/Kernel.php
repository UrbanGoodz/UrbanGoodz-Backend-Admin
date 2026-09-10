<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        \App\Console\Commands\CreateTestDriver::class,
        \App\Console\Commands\CreateBusinessOwner::class,
        \App\Console\Commands\UrbanGoodzEcosystemTest::class,
        \App\Console\Commands\ToggleRecaptcha::class,
        \App\Console\Commands\AiCopilotGenerateRecommendations::class,
        \App\Console\Commands\SyncLoadBoard::class,
        \App\Console\Commands\RunScheduledSourcing::class,
        \App\Console\Commands\RecoverOrderAnywhereCardIssuance::class,
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('ai-copilot:generate', ['--notify'])
            ->everyFifteenMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('sync-load-board')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(fn() => config('urban_goodz_load_board.sync.enabled', true));

        $schedule->command('run-scheduled-sourcing')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground()
            ->when(fn() => config('urban_goodz_load_board.sourcing.enabled', true));

        // Stranded is the one schedule here that somebody is waiting on in
        // real time. The responder answer window is measured in seconds, so
        // this runs every minute rather than on the usual cadence.
        $schedule->command('stranded:dispatch-tick')
            ->everyMinute()
            ->withoutOverlapping()
            ->runInBackground();

        // Driver breadcrumbs are unbounded by nature. Prune nightly, keeping
        // each driver's most recent point so the live map never goes blank.
        $schedule->command('delivery-history:prune')
            ->dailyAt('03:20')
            ->withoutOverlapping()
            ->runInBackground();

        $schedule->command('order-anywhere:recover-card-issuance')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Responders whose payout could not be sent the moment they earned it
        // -- onboarding not finished, bank details not cleared, Stripe briefly
        // unreachable. Without this the money sits in the platform balance
        // until somebody reconciles it by hand, which is exactly how responder
        // payouts came to be described as manual.
        //
        // Ten minutes, not every minute: nobody is waiting on this in real
        // time the way they wait on dispatch, and the common blocker resolves
        // on Stripe's schedule (hours), not ours.
        $schedule->command('urbangoodz:stranded-payouts-retry')
            ->everyTenMinutes()
            ->withoutOverlapping()
            ->runInBackground();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
