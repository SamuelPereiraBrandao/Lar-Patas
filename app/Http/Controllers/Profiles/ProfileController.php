<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Models\FriendRequest;
use App\Models\ProfilePost;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $posts = $user->profilePosts()->with(['pet:id,name,image_path', 'comments.user:id,name,avatar_path'])->latest()->paginate(3);
        $adoptedPets = $user->adoptions()->where('status', 'approved')->with('pet:id,name,image_path')->get()->pluck('pet')->filter()->values();

        return response()->json(['profile' => $user->fresh()->load('roles'), 'stats' => ['interests' => $user->adoptions()->count(), 'adoptions' => $adoptedPets->count(), 'posts' => $user->profilePosts()->count()], 'adopted_pets' => $adoptedPets, 'posts' => $posts->items(), 'pagination' => ['current_page' => $posts->currentPage(), 'has_more' => $posts->hasMorePages()]]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        return $this->uploadProfileImage($request, 'avatar', 'profiles/avatars', 'avatar_path');
    }

    public function uploadBanner(Request $request): JsonResponse
    {
        return $this->uploadProfileImage($request, 'banner', 'profiles/banners', 'banner_path');
    }

    public function removeAvatar(Request $request): JsonResponse
    {
        return $this->removeProfileImage($request, 'avatar_path');
    }

    public function removeBanner(Request $request): JsonResponse
    {
        return $this->removeProfileImage($request, 'banner_path');
    }

    public function storePost(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => 'nullable|string|max:2000', 'pet_id' => 'nullable|integer|exists:pets,id', 'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120']);
        if (blank($data['body'] ?? null) && ! $request->hasFile('image')) {
            return response()->json(['message' => 'Escreva algo ou selecione uma foto para publicar.'], 422);
        }
        $post = $request->user()->profilePosts()->create(['body' => $data['body'] ?? null, 'pet_id' => $data['pet_id'] ?? null, 'image_path' => $request->hasFile('image') ? $request->file('image')->store('profiles/posts', 'public') : null]);

        return response()->json(['post' => $post->load(['pet:id,name,image_path', 'comments.user:id,name,avatar_path'])], 201);
    }

    public function storeComment(Request $request, ProfilePost $post): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:800']);
        $comment = $post->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);

        return response()->json(['comment' => $comment->load('user:id,name,avatar_path')], 201);
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
            ->with(['pet:id,name,image_path', 'comments.user:id,name,avatar_path'])
            ->latest()
            ->paginate(3);
        $adoptedPets = $user->adoptions()->where('status', 'approved')->with('pet:id,name,image_path')->get()->pluck('pet')->filter()->values();

        return response()->json([
            'profile' => $user->only(['id', 'name', 'city', 'state', 'avatar_url', 'banner_url', 'household_description']),
            'is_owner' => $request->user()->is($user),
            'stats' => [
                'interests' => $user->adoptions()->count(),
                'adoptions' => $user->adoptions()->where('status', 'approved')->count(),
                'posts' => $user->profilePosts()->count(),
            ],
            'friendship_status' => $friendship
                ? ($friendship->status === 'pending'
                    ? ($friendship->sender_id === $viewerId ? 'sent' : 'received')
                    : $friendship->status)
                : null,
            'posts' => $posts->items(),
            'adopted_pets' => $adoptedPets,
            'pagination' => ['current_page' => $posts->currentPage(), 'has_more' => $posts->hasMorePages()],
        ]);
    }

    private function uploadProfileImage(Request $request, string $field, string $folder, string $attribute): JsonResponse
    {
        $request->validate([$field => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $user = $request->user();
        if ($user->{$attribute}) {
            Storage::disk('public')->delete($user->{$attribute});
        }
        $path = $request->file($field)->store($folder, 'public');
        $user->update([$attribute => $path]);
        $post = $user->profilePosts()->create([
            'body' => $attribute === 'avatar_path' ? 'Atualizou a foto de perfil.' : 'Atualizou a imagem de capa.',
            'image_path' => $path,
        ]);

        return response()->json(['user' => $user->fresh()->load('roles'), 'post' => $post->load(['user:id,name,avatar_path', 'comments.user:id,name,avatar_path'])]);
    }

    private function removeProfileImage(Request $request, string $attribute): JsonResponse
    {
        $user = $request->user();

        if ($user->{$attribute}) {
            Storage::disk('public')->delete($user->{$attribute});
            $user->update([$attribute => null]);
        }

        return response()->json(['user' => $user->fresh()->load('roles')]);
    }
}
