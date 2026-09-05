<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class PublishAblyMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 120, 300];

    /** @param array<string, mixed> $message */
    public function __construct(
        public string $channel,
        public array $message,
    ) {
        $this->onQueue('ably');
    }

    public function handle(): void
    {
        $key = (string) config('services.ably.key');
        if (blank($key) || ! str_contains($key, ':')) {
            return;
        }

        [$keyName, $secret] = explode(':', $key, 2);
        $request = Http::withBasicAuth($keyName, $secret)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->timeout(10);

        if (! config('services.ably.verify_ssl')) {
            $request = $request->withoutVerifying();
        }

        $request
            ->post('https://rest.ably.io/channels/'.$this->channel.'/messages', [
                'name' => 'message:created',
                'data' => $this->message,
            ])
            ->throw();
    }
}
