<?php

namespace App\Jobs;

class PublishAblyNotification extends PublishAblyMessage
{
    public function __construct(int $userId)
    {
        parent::__construct("user:{$userId}:notifications", ['kind' => 'notification']);

        $this->onQueue('ably-notifications');
    }
}
