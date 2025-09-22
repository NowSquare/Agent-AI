<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Run memory pruning daily at 03:10 AM
        $schedule->command('memories:prune')
            ->dailyAt('03:10')
            ->withoutOverlapping()
            ->onOneServer()
            ->appendOutputTo(storage_path('logs/memory-pruning.log'));
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
