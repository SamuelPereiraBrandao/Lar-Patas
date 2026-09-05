<?php

namespace App\Notifications;

use App\Models\Adoption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdoptionPickupScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Adoption $adoption, public string $petName, public string $code) {}

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Venha buscar '.$this->petName.' — Lar & Patas')
            ->view(['html' => 'emails.adoption-pickup', 'text' => 'emails.adoption-pickup-text'], [
                'name' => $notifiable->name,
                'petName' => $this->petName,
                'pickupDate' => $this->adoption->pickup_at->copy()->timezone($this->adoption->pickup_timezone)->format('d/m/Y'),
                'pickupTime' => $this->adoption->pickup_at->copy()->timezone($this->adoption->pickup_timezone)->format('H:i'),
                'timezoneOffset' => $this->adoption->pickup_at->copy()->timezone($this->adoption->pickup_timezone)->format('P'),
                'location' => $this->adoption->pickup_location,
                'pickupMessage' => $this->adoption->pickup_message,
                'code' => $this->code,
                'url' => route('adoptions.pickup-page'),
            ]);
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $this->adoption->fresh()?->pickup_code === $this->code;
    }
}
