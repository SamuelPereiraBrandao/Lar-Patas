<?php

namespace App\Jobs;

class PublishAblyDirectMessage extends PublishAblyMessage
{
    /** @param array<string, mixed> $message */
    public function __construct(int $conversationId, array $message)
    {
        parent::__construct("direct:{$conversationId}:messages", $message);

        $this->onQueue('ably-messages');
    }
}
