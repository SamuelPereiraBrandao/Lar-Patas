<?php

namespace App\Jobs;

class PublishAblyMessageLike extends PublishAblyMessage
{
    public function __construct(int $conversationId, int $messageId, int $likesCount)
    {
        parent::__construct(
            "direct:{$conversationId}:messages",
            ['message_id' => $messageId, 'likes_count' => $likesCount],
            'messages:liked',
        );

        $this->onQueue('ably-messages');
    }
}
