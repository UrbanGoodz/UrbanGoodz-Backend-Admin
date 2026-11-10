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
        // Intentionally empty. Laravel 12 does not read this file for scheduling; the live schedule is the
        // ->withSchedule() closure in bootstrap/app.php. Commands defined here never ran.
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
