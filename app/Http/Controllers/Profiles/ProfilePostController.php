<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProfilePostRequest;
use App\Http\Requests\UpdateProfilePostRequest;
use App\Http\SafeImageStorage;
use App\Jobs\PublishAblyNotification;
use App\Models\ProfilePost;
use App\Models\UserBlock;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfilePostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $viewerId = $request->user('sanctum')?->id ?? $request->user()?->id;
        $posts = ProfilePost::query()
            ->where(fn ($query) => $query->whereNull('image_path')->orWhere(fn ($images) => $images->where('image_path', 'not like', 'profiles/avatars/%')->where('image_path', 'not like', 'profiles/banners/%')))
            ->whereNotIn('user_id', UserBlock::excludedIds($viewerId))
            ->with(['user:id,name,avatar_path,city,state', 'pet:id,name,image_path', 'comments.user:id,name,avatar_path'])
            ->withCount(['comments', 'likes'])
            ->when($viewerId, fn ($query) => $query->withExists([
                'likes as is_liked' => fn ($likes) => $likes->where('user_id', $viewerId),
            ]))
            ->latest()
            ->paginate(3);

        return response()->json([
            'data' => $posts->items(),
            'pagination' => [
                'current_page' => $posts->currentPage(),
                'has_more' => $posts->hasMorePages(),
            ],
        ]);
    }

    public function store(StoreProfilePostRequest $request, SafeImageStorage $images): JsonResponse
    {
        $data = $request->validated();
        if (blank($data['body'] ?? null) && ! $request->hasFile('images')) {
            return response()->json(['message' => 'Escreva algo ou selecione uma foto para publicar.'], 422);
        }
        $paths = collect($request->file('images', []))
            ->map(fn ($image) => $images->store($image, 'profiles/posts'))
            ->values();
        $post = $request->user()->profilePosts()->create([
            'body' => $data['body'] ?? null,
            'pet_id' => $data['pet_id'] ?? null,
            'image_path' => $paths->first(),
            'gallery_paths' => $paths->slice(1)->all(),
        ]);

        return response()->json(['post' => $post->load(['user:id,name,avatar_path,city,state', 'pet:id,name,image_path', 'comments.user:id,name,avatar_path'])->loadCount('likes')], 201);
    }

    public function show(Request $request, ProfilePost $post): JsonResponse
    {
        return response()->json(['data' => $post->load(['user:id,name,avatar_path', 'pet:id,name,image_path', 'comments.user:id,name,avatar_path'])
            ->loadCount('likes')->loadExists(['likes as is_liked' => fn ($query) => $query->where('user_id', $request->user()->id)])]);
    }

    public function update(UpdateProfilePostRequest $request, ProfilePost $post): JsonResponse
    {
        $data = $request->validated();
        $post->fill($data);
        if ($post->isDirty('body')) {
            $post->forceFill(['edited_at' => now()])->save();
        }

        return response()->json(['data' => $post]);
    }

    public function destroy(Request $request, ProfilePost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        $post->forceFill(['hidden_at' => now()])->save();

        return response()->json(status: 204);
    }

    public function storeComment(Request $request, ProfilePost $post): JsonResponse
    {
        $data = $request->validate(['body' => 'required|string|max:800']);
        $comment = $post->comments()->create(['user_id' => $request->user()->id, 'body' => $data['body']]);
        if ($post->user_id !== $request->user()->id) {
            UserNotification::create(['user_id' => $post->user_id, 'type' => 'post_comment', 'title' => 'Novo comentário na sua publicação', 'body' => $request->user()->name.' comentou: '.str($data['body'])->limit(100), 'data' => ['sender_id' => $request->user()->id, 'post_id' => $post->id]]);
            PublishAblyNotification::dispatch($post->user_id);
        }

        return response()->json(['comment' => $comment->load('user:id,name,avatar_path')], 201);
    }

    public function toggleLike(Request $request, ProfilePost $post): JsonResponse
    {
        $like = $post->likes()->where('user_id', $request->user()->id)->first();
        if ($like) {
            $like->delete();
        } else {
            $post->likes()->create(['user_id' => $request->user()->id]);
            if ($post->user_id !== $request->user()->id) {
                UserNotification::create(['user_id' => $post->user_id, 'type' => 'post_like', 'title' => 'Curtiram sua publicação', 'body' => $request->user()->name.' curtiu sua publicação.', 'data' => ['sender_id' => $request->user()->id, 'post_id' => $post->id]]);
                PublishAblyNotification::dispatch($post->user_id);
            }
        }

        return response()->json(['liked' => ! $like, 'likes_count' => $post->likes()->count()]);
    }
}
