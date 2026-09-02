<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = URL::temporarySignedRoute('verification.verify', Carbon::now()->addMinutes(60), ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]);

        return (new MailMessage)->subject('Confirme seu e-mail — Lar & Patas')->view('emails.verify-email', ['name' => $notifiable->name, 'url' => $url]);
    }
}
