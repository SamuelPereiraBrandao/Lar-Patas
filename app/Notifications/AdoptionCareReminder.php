<?php

namespace App\Notifications;

use App\Models\Adoption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdoptionCareReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public Adoption $adoption, public bool $followup, public ?string $scheduledAt) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        $current = $this->adoption->fresh();

        return $notifiable->fresh()?->is_active && $current && ! $current->cancelled_at && ($this->followup ? $current->released_at && ! $current->followup_completed_at : ! $current->released_at && $current->pickup_at?->isFuture() && $current->pickup_at?->toISOString() === $this->scheduledAt);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(($this->followup ? 'Como está a adaptação?' : 'Sua retirada está chegando').' — Lar & Patas')->view(['html' => 'emails.adoption-care', 'text' => 'emails.adoption-care-text'], ['name' => $notifiable->name, 'title' => $this->followup ? 'Como está a adaptação?' : 'Sua retirada está chegando', 'body' => $this->followup ? 'Conte como estão os primeiros dias com '.$this->adoption->pet->name.'. Você pode pedir ajuda à equipe pelo painel.' : 'Estamos esperando você! Confira a data, o endereço e o código da retirada de '.$this->adoption->pet->name.' no painel.', 'url' => route('adoptions.pickup-page')]);
    }
}
