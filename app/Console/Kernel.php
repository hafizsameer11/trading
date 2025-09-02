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
        // Settle expired trades every minute
        $schedule->command('trades:settle-expired')->everyMinute();
        
        // Generate market data every 30 seconds for 1-minute timeframe
        $schedule->command('market:generate-data --timeframe=60')->everyThirtySeconds();
        
        // Generate market data every 5 minutes for 5-minute timeframe
        $schedule->command('market:generate-data --timeframe=300')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

