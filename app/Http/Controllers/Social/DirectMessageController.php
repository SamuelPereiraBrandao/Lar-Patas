<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Jobs\PublishAblyDirectMessage;
use App\Jobs\PublishAblyMessageLike;
use App\Jobs\PublishAblyMessageNotification;
use App\Jobs\PublishAblyMessageRead;
use App\Models\DirectConversation;
use App\Models\DirectMessage;
use App\Models\FriendRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DirectMessageController extends Controller
{
    public function contacts(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $requests = FriendRequest::with(['sender:id,name,avatar_path,city,state', 'recipient:id,name,avatar_path,city,state'])
            ->where('status', 'accepted')
            ->where(fn ($query) => $query->where('sender_id', $userId)->orWhere('recipient_id', $userId))
            ->latest()
            ->get();

        return response()->json(['data' => $requests->map(fn (FriendRequest $friendship) => $friendship->sender_id === $userId ? $friendship->recipient : $friendship->sender)->values()]);
    }

    public function open(Request $request, User $user): JsonResponse
    {
        abort_unless($this->areFriends($request->user()->id, $user->id), 403, 'Você só pode conversar com amigos.');
        $conversation = $this->conversationFor($request->user()->id, $user->id);

        return response()->json(['data' => $conversation]);
    }

    public function messages(Request $request, DirectConversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $this->markMessagesAsRead($conversation, $request->user()->id);

        $messages = $conversation->messages()->with('user:id,name,avatar_path')->latest()->paginate(10);

        return response()->json([
            'data' => $messages->getCollection()
                ->reverse()
                ->map(fn (DirectMessage $message) => $this->messageData($message, $request->user()->id))
                ->values(),
            'pagination' => [
                'current_page' => $messages->currentPage(),
                'has_more' => $messages->hasMorePages(),
            ],
        ]);
    }

    public function read(Request $request, DirectConversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);

        return response()->json([
            'data' => $this->markMessagesAsRead($conversation, $request->user()->id),
        ]);
    }

    public function store(Request $request, DirectConversation $conversation): JsonResponse
    {
        $this->authorizeParticipant($request, $conversation);
        $data = $request->validate(['body' => 'required|string|max:1500']);
        $message = $conversation->messages()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        $messageData = $this->messageData($message, $request->user()->id);

        $recipientId = $conversation->user_one_id === $request->user()->id
            ? $conversation->user_two_id
            : $conversation->user_one_id;
        UserNotification::create([
            'user_id' => $recipientId,
            'type' => 'direct_message',
            'title' => 'Nova mensagem de '.$request->user()->name,
            'body' => (string) str($data['body'])->limit(120),
            'data' => ['sender_id' => $request->user()->id, 'conversation_id' => $conversation->id],
        ]);

        PublishAblyDirectMessage::dispatch($conversation->id, $messageData);
        PublishAblyMessageNotification::dispatch($recipientId);

        return response()->json(['data' => $messageData], 201);
    }

    public function toggleLike(Request $request, DirectMessage $message): JsonResponse
    {
        $message->load('conversation');
        $this->authorizeParticipant($request, $message->conversation);
        $like = $message->likes()->where('user_id', $request->user()->id)->first();

        if ($like) {
            $like->delete();
        } else {
            $message->likes()->create(['user_id' => $request->user()->id]);
        }

        $likesCount = $message->likes()->count();
        PublishAblyMessageLike::dispatch($message->direct_conversation_id, $message->id, $likesCount);

        return response()->json([
            'liked' => ! $like,
            'likes_count' => $likesCount,
        ]);
    }

    private function conversationFor(int $firstUserId, int $secondUserId): DirectConversation
    {
        [$userOneId, $userTwoId] = collect([$firstUserId, $secondUserId])->sort()->values()->all();

        return DirectConversation::firstOrCreate(['user_one_id' => $userOneId, 'user_two_id' => $userTwoId]);
    }

    private function areFriends(int $firstUserId, int $secondUserId): bool
    {
        return FriendRequest::where('status', 'accepted')
            ->where(fn ($query) => $query->where('sender_id', $firstUserId)->where('recipient_id', $secondUserId)->orWhere(fn ($reverseQuery) => $reverseQuery->where('sender_id', $secondUserId)->where('recipient_id', $firstUserId)))
            ->exists();
    }

    private function authorizeParticipant(Request $request, DirectConversation $conversation): void
    {
        abort_unless(in_array($request->user()->id, [$conversation->user_one_id, $conversation->user_two_id], true), 403);
    }

    /** @return array<int, int> */
    private function markMessagesAsRead(DirectConversation $conversation, int $userId): array
    {
        $readMessageIds = $conversation->messages()
            ->where('user_id', '!=', $userId)
            ->whereNull('read_at')
            ->pluck('id')
            ->all();

        if ($readMessageIds) {
            $conversation->messages()->whereIn('id', $readMessageIds)->update(['read_at' => now()]);
            PublishAblyMessageRead::dispatch($conversation->id, $readMessageIds);
        }

        return $readMessageIds;
    }

    /** @return array<string, mixed> */
    private function messageData(DirectMessage $message, int $userId): array
    {
        $message->load('user:id,name,avatar_path')->loadCount('likes');
        $message->setAttribute('is_liked', $message->likes()->where('user_id', $userId)->exists());

        return $message->toArray();
    }
}
