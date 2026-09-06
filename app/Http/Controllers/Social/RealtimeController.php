<?php

namespace App\Http\Controllers\Social;

use Ably\AblyRest;
use App\Http\Controllers\Controller;
use App\Models\DirectConversation;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function token(Request $request): JsonResponse
    {
        $key = (string) config('services.ably.key');
        abort_if(blank($key) || ! str_contains($key, ':'), 503, 'O chat em tempo real ainda não está configurado.');

        $ably = new AblyRest($key);
        $userId = $request->user()->id;
        $capability = ['user:'.$userId.':notifications' => ['subscribe'], 'pet:*' => ['subscribe']];
        $excluded = UserBlock::excludedIds($userId);
        $conversations = DirectConversation::query()
            ->where(fn ($query) => $query->where('user_one_id', $userId)->orWhere('user_two_id', $userId))
            ->whereNotIn('user_one_id', $excluded)->whereNotIn('user_two_id', $excluded)->pluck('id');
        foreach ($conversations as $id) {
            $capability['direct:'.$id.':messages'] = ['subscribe'];
        }
        $tokenRequest = $ably->auth->createTokenRequest([
            'clientId' => (string) $request->user()->id,
            'ttl' => 60_000,
            'capability' => json_encode($capability, JSON_THROW_ON_ERROR),
        ]);

        return response()->json($tokenRequest->toArray());
    }
}
