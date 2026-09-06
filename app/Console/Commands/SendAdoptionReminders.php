<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:send-adoption-reminders')]
#[Description('Command description')]
class SendAdoptionReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
    }
}
