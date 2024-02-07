<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Laravel\Sanctum\Sanctum;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('sanctum:prune-expired --hours=24')->daily();
        $schedule->command('telescope:prune')->daily();
        // $schedule->command('whatsapp:send --hours=24')->daily();
        // $schedule->command('trade:auto-forward --hours=24')->daily();
        $schedule->command('liveDashboard:update')->everySixHours();         #_php artisan schedule:work
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
