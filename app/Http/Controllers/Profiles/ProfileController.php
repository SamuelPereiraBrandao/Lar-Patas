<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Models\FriendRequest;
use App\Models\Pet;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $posts = $user->profilePosts()
            ->when($user->hasRole('admin'), fn ($query) => $query->withoutGlobalScope('visible'))
            ->with(['pet:id,name,image_path', 'comments.user:id,name,avatar_path'])
            ->withCount('likes')
            ->withExists(['likes as is_liked' => fn ($query) => $query->where('user_id', $user->id)])
            ->latest()
            ->paginate(3);
        $myPets = Pet::ownedBy($user->id)
            ->with(['owner:id,name', 'caretakers' => fn ($query) => $query->select('users.id', 'users.name')])
            ->get();

        return response()->json([
            'profile' => $user->fresh()->load('roles'),
            'stats' => [
                'pets' => $myPets->where('status', 'adopted')->count(),
                'interests' => $user->adoptions()->count(),
                'adoptions' => $myPets->where('ownership_kind', 'adoption')->where('status', 'adopted')->count(),
                'posts' => $user->profilePosts()->count(),
            ],
            'my_pets' => $myPets,
            'adopted_pets' => $myPets->where('status', 'adopted')->values(),
            'posts' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'has_more' => $posts->hasMorePages(),
            ],
        ]);
    }

    public function publicProfile(Request $request, User $user): JsonResponse
    {
        $viewerId = $request->user()->id;
        $friendship = FriendRequest::query()
            ->where(function ($query) use ($viewerId, $user) {
                $query->where('sender_id', $viewerId)
                    ->where('recipient_id', $user->id)
                    ->orWhere(function ($reverseQuery) use ($viewerId, $user) {
                        $reverseQuery->where('sender_id', $user->id)
                            ->where('recipient_id', $viewerId);
                    });
            })
            ->first();
        $posts = $user->profilePosts()
            ->when($request->user()->hasRole('admin'), fn ($query) => $query->withoutGlobalScope('visible'))
            ->with(['pet:id,name,image_path', 'comments.user:id,name,avatar_path'])
            ->withCount('likes')
            ->withExists(['likes as is_liked' => fn ($query) => $query->where('user_id', $viewerId)])
            ->latest()
            ->paginate(3);
        $adoptedPets = Pet::ownedBy($user->id)->where('status', 'adopted')->with(['owner:id,name', 'caretakers' => fn ($query) => $query->select('users.id', 'users.name')])->get();

        return response()->json([
            'profile' => $user->only(['id', 'name', 'city', 'state', 'avatar_url', 'banner_url', 'household_description']),
            'is_owner' => $request->user()->is($user),
            'stats' => [
                'pets' => $adoptedPets->count(),
                'interests' => $user->adoptions()->count(),
                'adoptions' => $user->adoptions()->whereNotNull('released_at')->count(),
                'posts' => $user->profilePosts()->count(),
            ],
            'friendship_status' => $friendship
                ? ($friendship->status === 'pending'
                    ? ($friendship->sender_id === $viewerId ? 'sent' : 'received')
                    : $friendship->status)
                : null,
            'friendship_id' => $friendship?->id,
            'posts' => $posts->items(),
            'adopted_pets' => $adoptedPets,
            'pagination' => ['current_page' => $posts->currentPage(), 'has_more' => $posts->hasMorePages()],
        ]);
    }
}
