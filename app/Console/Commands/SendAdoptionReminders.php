<?php

namespace App\Console\Commands;

use App\Jobs\PublishAblyNotification;
use App\Models\Adoption;
use App\Models\UserNotification;
use App\Notifications\AdoptionCareReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAdoptionReminders extends Command
{
    protected $signature = 'adoptions:send-reminders';

    protected $description = 'Envia lembretes de retirada e acompanhamento após adoção.';

    public function handle(): int
    {
        Adoption::where('status', 'approved')->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('released_at')->whereNull('reminded_at')->whereBetween('pickup_at', [now(), now()->addDay()]))->orWhere(fn ($q) => $q->where('released_at', '<=', now()->subDays(7))->whereNull('followup_sent_at')->whereNull('followup_completed_at')))->chunkById(100, function ($adoptions): void {
            foreach ($adoptions as $item) {
                DB::transaction(function () use ($item): void {
                    $adoption = Adoption::lockForUpdate()->find($item->id);
                    if (! $adoption || ! $adoption->user?->is_active || $adoption->cancelled_at) {
                        return;
                    }
                    $followup = (bool) $adoption->released_at;
                    if ($adoption->status !== 'approved' || (! $followup && (! $adoption->pickup_at || $adoption->pickup_at->isPast() || $adoption->pickup_at->gt(now()->addDay()))) || ($followup && $adoption->released_at->gt(now()->subDays(7)))) {
                        return;
                    }
                    $column = $followup ? 'followup_sent_at' : 'reminded_at';
                    if ($adoption->$column || ($followup && $adoption->followup_completed_at)) {
                        return;
                    }
                    $title = $followup ? 'Como está a adaptação?' : 'Sua retirada está chegando';
                    $body = $followup ? 'Conte como está a adaptação de '.$adoption->pet->name.'. A equipe está disponível para ajudar.' : 'Confira a data, o endereço e o código de retirada de '.$adoption->pet->name.' no seu painel.';
                    UserNotification::create(['user_id' => $adoption->user_id, 'type' => 'adoption_care', 'title' => $title, 'body' => $body, 'data' => ['adoption_id' => $adoption->id, 'url' => '/painel']]);
                    $adoption->update([$column => now()]);
                    $adoption->user->notify((new AdoptionCareReminder($adoption, $followup, $adoption->pickup_at?->toISOString()))->afterCommit());
                    PublishAblyNotification::dispatch($adoption->user_id)->afterCommit();
                });
            }
        });

        return self::SUCCESS;
    }
}
