<?php

namespace App\Jobs;

class PublishAblyMessageNotification extends PublishAblyMessage
{
    public function __construct(int $userId)
    {
        parent::__construct("user:{$userId}:notifications", ['kind' => 'direct_message']);

        $this->onQueue('ably-notifications-messages');
    }
}
