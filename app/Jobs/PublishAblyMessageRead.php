<?php

namespace App\Jobs;

class PublishAblyMessageRead extends PublishAblyMessage
{
    /** @param array<int, int> $messageIds */
    public function __construct(int $conversationId, array $messageIds)
    {
        parent::__construct(
            "direct:{$conversationId}:messages",
            ['message_ids' => $messageIds, 'read_at' => now()->toIso8601String()],
            'messages:read',
        );

        $this->onQueue('ably-messages');
    }
}
