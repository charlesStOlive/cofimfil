<?php

// app/Jobs/ProcessEmailNotification.php
namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Facades\MsgConnect;

class ProcessEmailNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $notificationData;

    public function __construct($notificationData)
    {
        $this->notificationData = $notificationData;
    }

    public function handle()
    {
        \Log::info('Processing email notification in background...');
        MsgConnect::processEmailNotification($this->notificationData);
        \Log::info('Background email processing completed.');
        // Marquer la notification comme traitée
        // ...
    }
}
