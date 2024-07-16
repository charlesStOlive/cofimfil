<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\MsgUser;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->call(function () {
            //\Log::info('refresh susriptions');
            $msgUsers = MsgUser::where('suscription_id', '<>', null)->get();
            foreach ($msgUsers as $msgUser) {
                // Call your renew function here
                $msgUser->refreshSuscription();
            }
        })->dailyAt('18:00')->timezone('Europe/Paris');
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
