<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\FriendRequest;
use App\Models\ProfilePost;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommunitySafetyController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(['data' => User::whereIn('id', UserBlock::where('user_id', $r->user()->id)->select('blocked_user_id'))->get(['id', 'name', 'avatar_path'])]);
    }

    public function block(Request $r, User $user): JsonResponse
    {
        abort_if($r->user()->is($user), 422, 'Você não pode bloquear sua própria conta.');
        DB::transaction(function () use ($r, $user): void {
            UserBlock::firstOrCreate(['user_id' => $r->user()->id, 'blocked_user_id' => $user->id]);
            FriendRequest::where(fn ($q) => $q->where('sender_id', $r->user()->id)->where('recipient_id', $user->id))->orWhere(fn ($q) => $q->where('sender_id', $user->id)->where('recipient_id', $r->user()->id))->delete();
        });

        return response()->json(['message' => 'Usuário bloqueado. Novas interações foram impedidas.']);
    }

    public function unblock(Request $r, User $user): JsonResponse
    {
        UserBlock::where('user_id', $r->user()->id)->where('blocked_user_id', $user->id)->delete();

        return response()->json(status: 204);
    }

    public function report(Request $r, ProfilePost $post): JsonResponse
    {
        $data = $r->validate(['reason' => 'required|string|min:10|max:1000']);

        return response()->json(['data' => ContentReport::firstOrCreate(['user_id' => $r->user()->id, 'profile_post_id' => $post->id], $data)], 201);
    }
}
