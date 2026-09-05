<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Jobs\PublishAblyNotification;
use App\Models\FriendRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FriendshipController extends Controller
{
    public function cancel(Request $r, User $user)
    {
        FriendRequest::query()
            ->where(function ($query) use ($r, $user) {
                $query->where('sender_id', $r->user()->id)
                    ->where('recipient_id', $user->id)
                    ->orWhere(function ($reverseQuery) use ($r, $user) {
                        $reverseQuery->where('sender_id', $user->id)
                            ->where('recipient_id', $r->user()->id);
                    });
            })
            ->whereIn('status', ['pending', 'accepted'])
            ->delete();

        return response()->json(['ok' => true]);
    }

    public function send(Request $r, User $user)
    {
        abort_if($r->user()->is($user), 422);
        $request = FriendRequest::firstOrCreate(['sender_id' => $r->user()->id, 'recipient_id' => $user->id], ['status' => 'pending']);
        UserNotification::firstOrCreate(['user_id' => $user->id, 'type' => 'friend_request', 'data' => ['request_id' => $request->id]], ['title' => 'Nova solicitação de amizade', 'body' => $r->user()->name.' quer adicionar você como amigo.']);

        PublishAblyNotification::dispatch($user->id);

        return response()->json(['data' => $request], 201);
    }

    public function respond(Request $r, FriendRequest $friendRequest)
    {
        abort_unless($friendRequest->recipient_id === $r->user()->id, 403);
        $data = $r->validate(['status' => 'required|in:accepted,rejected']);
        $friendRequest->update($data);
        UserNotification::create(['user_id' => $friendRequest->sender_id, 'type' => 'friend_response', 'title' => $data['status'] === 'accepted' ? 'Solicitação aceita' : 'Solicitação recusada', 'body' => $r->user()->name.($data['status'] === 'accepted' ? ' aceitou sua solicitação.' : ' recusou sua solicitação.'), 'data' => ['request_id' => $friendRequest->id]]);

        PublishAblyNotification::dispatch($friendRequest->sender_id);

        return response()->json(['data' => $friendRequest]);
    }

    public function notifications(Request $r)
    {
        $items = UserNotification::where('user_id', $r->user()->id)->latest()->take(30)->get();
        $friendRequests = FriendRequest::with('sender:id,name,avatar_path,city,state')
            ->whereIn('id', $items->pluck('data.request_id')->filter())
            ->get()
            ->keyBy('id');
        $actors = User::whereIn('id', $items->pluck('data.sender_id')->filter())
            ->get(['id', 'name', 'avatar_path', 'city', 'state'])
            ->keyBy('id');

        $petRequests = DB::table('pet_caretakers')->where('user_id', $r->user()->id)->whereIn('pet_id', $items->pluck('data.pet_id')->filter())->pluck('status', 'pet_id');

        $data = $items->map(function (UserNotification $notification) use ($friendRequests, $actors, $petRequests) {
            $request = $friendRequests->get($notification->data['request_id'] ?? null);

            return [
                ...$notification->toArray(),
                'friend_request' => $request ? [
                    'id' => $request->id,
                    'status' => $request->status,
                    'sender' => $request->sender,
                ] : null,
                'pet_request_status' => $petRequests->get($notification->data['pet_id'] ?? null),
                'actor' => $actors->get($notification->data['sender_id'] ?? null),
            ];
        });

        return response()->json([
            'data' => $data,
            'unread' => $items->whereNull('read_at')->count(),
            'realtime_channel' => 'user:'.$r->user()->id.':notifications',
        ]);
    }

    public function read(Request $r)
    {
        $data = $r->validate([
            'conversation_id' => 'nullable|integer',
            'except_types' => 'nullable|array',
            'except_types.*' => 'string',
        ]);
        $query = UserNotification::where('user_id', $r->user()->id)->whereNull('read_at');

        if (isset($data['conversation_id'])) {
            $query->where('type', 'direct_message')
                ->where('data->conversation_id', $data['conversation_id']);
        } elseif (! empty($data['except_types'])) {
            $query->whereNotIn('type', $data['except_types']);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
