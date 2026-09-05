<?php

namespace App\Http\Controllers\Social;

use Ably\AblyRest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $key = (string) config('services.ably.key');
        abort_if(blank($key) || ! str_contains($key, ':'), 503, 'O chat em tempo real ainda não está configurado.');

        $ably = new AblyRest($key);
        $tokenRequest = $ably->auth->createTokenRequest([
            'clientId' => (string) $request->user()->id,
            'ttl' => 3_600_000,
        ]);

        return response()->json($tokenRequest->toArray());
    }
}
