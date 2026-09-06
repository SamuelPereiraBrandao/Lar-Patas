<?php

namespace App\Http;

use App\Jobs\PublishAblyNotification;
use App\Models\Pet;
use App\Models\User;
use App\Models\UserNotification;

class PetCaretakerNotifier
{
    public function send(User $sender, Pet $pet, int $recipientId): void
    {
        UserNotification::create([
            'user_id' => $recipientId,
            'type' => 'pet_caretaker_request',
            'title' => 'Convite para ser dono de um pet',
            'body' => $sender->name.' convidou você para ser dono de '.$pet->name.'.',
            'data' => ['pet_id' => $pet->id, 'sender_id' => $sender->id],
        ]);
        PublishAblyNotification::dispatch($recipientId)->afterCommit();
    }
}
