<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Http\PetPhotoUpdater;
use App\Http\SafeImageStorage;
use App\Jobs\PublishAblyNotification;
use App\Models\City;
use App\Models\FriendRequest;
use App\Models\Pet;
use App\Models\ProfilePost;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function communityFeed(Request $request): JsonResponse
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

    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $posts = $user->profilePosts()->when($user->hasRole('admin'), fn ($query) => $query->withoutGlobalScope('visible'))->with(['pet:id,name,image_path', 'comments.user:id,name,avatar_path'])->withCount('likes')->withExists(['likes as is_liked' => fn ($query) => $query->where('user_id', $user->id)])->latest()->paginate(3);
        $myPets = Pet::ownedBy($user->id)->with(['owner:id,name', 'caretakers' => fn ($query) => $query->select('users.id', 'users.name')])->get();

        return response()->json(['profile' => $user->fresh()->load('roles'), 'stats' => ['pets' => $myPets->where('status', 'adopted')->count(), 'interests' => $user->adoptions()->count(), 'adoptions' => $myPets->where('ownership_kind', 'adoption')->where('status', 'adopted')->count(), 'posts' => $user->profilePosts()->count()], 'my_pets' => $myPets, 'adopted_pets' => $myPets->where('status', 'adopted')->values(), 'posts' => $posts->items(), 'pagination' => ['current_page' => $posts->currentPage(), 'has_more' => $posts->hasMorePages()]]);
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

    public function updatePost(Request $request, ProfilePost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        $data = $request->validate(['body' => 'required|string|max:2000'], ['body.required' => 'Escreva o texto da publicação.', 'body.max' => 'O texto deve ter no máximo 2.000 caracteres.']);
        $post->fill($data);
        if ($post->isDirty('body')) {
            $post->forceFill(['edited_at' => now()])->save();
        }

        return response()->json(['data' => $post]);
    }

    public function destroyPost(Request $request, ProfilePost $post): JsonResponse
    {
        abort_unless($post->user_id === $request->user()->id, 403);
        $post->forceFill(['hidden_at' => now()])->save();

        return response()->json(status: 204);
    }

    public function storePost(Request $request): JsonResponse
    {
        $data = $request->validate(['body' => 'nullable|string|max:2000', 'pet_id' => 'nullable|integer|exists:pets,id', 'images' => 'nullable|array|max:5', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120']);
        if (blank($data['body'] ?? null) && ! $request->hasFile('images')) {
            return response()->json(['message' => 'Escreva algo ou selecione uma foto para publicar.'], 422);
        }
        $paths = collect($request->file('images', []))
            ->map(fn ($image) => app(SafeImageStorage::class)->store($image, 'profiles/posts'))
            ->values();
        $post = $request->user()->profilePosts()->create(['body' => $data['body'] ?? null, 'pet_id' => $data['pet_id'] ?? null, 'image_path' => $paths->first(), 'gallery_paths' => $paths->slice(1)->all()]);

        return response()->json(['post' => $post->load(['user:id,name,avatar_path,city,state', 'pet:id,name,image_path', 'comments.user:id,name,avatar_path'])->loadCount('likes')], 201);
    }

    public function showPost(Request $request, ProfilePost $post): JsonResponse
    {
        return response()->json(['data' => $post->load(['user:id,name,avatar_path', 'pet:id,name,image_path', 'comments.user:id,name,avatar_path'])
            ->loadCount('likes')->loadExists(['likes as is_liked' => fn ($query) => $query->where('user_id', $request->user()->id)])]);
    }

    public function storeOwnedPet(Request $request, PetPhotoUpdater $photos): JsonResponse
    {
        $this->preparePetLocation($request);
        $data = $request->validate([
            'name' => 'required|string|max:80', 'species' => 'required|in:dog,cat', 'breed' => 'nullable|string|max:100',
            'birth_date' => 'nullable|date|before:today', 'size' => 'required|in:small,medium,large', 'sex' => 'required|in:male,female',
            'city' => 'required|string|max:100', 'state' => 'nullable|string|size:2', 'lives_with_owner' => 'sometimes|boolean', 'temperament' => 'required|string|max:150', 'description' => 'required|string|max:2000',
            'photos' => 'nullable|array|max:10', 'photos.*' => 'image|max:5120', 'removed_photo_paths' => 'nullable|array|max:10', 'removed_photo_paths.*' => 'string|max:255', 'cover_photo_path' => 'nullable|string|max:255', 'cover_photo_index' => 'nullable|integer|min:0|max:9', 'owner_ids' => 'sometimes|array|max:20', 'owner_ids.*' => 'required|integer|distinct|exists:users,id',
        ]);
        $data['owner_id'] = $request->user()->id;
        $data['ownership_kind'] = 'guardian';
        $data['status'] = 'adopted';

        return response()->json(['data' => $this->saveFamilyPet($request, new Pet, $data, $photos)], 201);
    }

    /** @param array<string, mixed> $data */
    private function saveFamilyPet(Request $request, Pet $pet, array $data, PetPhotoUpdater $photos): Pet
    {
        $ownerIds = $data['owner_ids'] ?? null;
        foreach ($ownerIds ?? [] as $id) {
            abort_if(UserBlock::between($request->user()->id, (int) $id), 403, 'Não é possível convidar uma conta bloqueada.');
        }
        unset($data['owner_ids']);

        return DB::transaction(function () use ($request, $pet, $data, $photos, $ownerIds): Pet {
            $photos->save($request, $pet, $data);
            if ($ownerIds !== null) {
                $existing = $pet->caretakers()->get()->keyBy('id');
                $selected = collect($ownerIds)->map(fn ($id) => (int) $id)->reject(fn ($id) => $id === $pet->owner_id);
                if ($request->user()->id !== $pet->owner_id) {
                    $selected->push($request->user()->id);
                }
                $pet->caretakers()->sync($selected->unique()->mapWithKeys(fn ($id) => [$id => ['status' => $existing->get($id)?->pivot->status ?? 'pending']])->all());
                foreach ($selected->unique()->diff($existing->keys()) as $id) {
                    $this->notifyPetInvitation($request, $pet, $id);
                }
            }

            return $pet->load(['owner:id,name', 'caretakers' => fn ($query) => $query->select('users.id', 'users.name')]);
        });
    }

    public function petOwnerOptions(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:80']);
        $users = User::query()->whereKeyNot($request->user()->id)
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'city', 'state']);

        return response()->json(['data' => $users]);
    }

    private function preparePetLocation(Request $request): void
    {
        if (is_string($request->input('owner_ids'))) {
            $request->merge(['owner_ids' => json_decode($request->input('owner_ids'), true)]);
        }
        $request->validate(['lives_with_owner' => 'sometimes|boolean']);
        if ($request->boolean('lives_with_owner')) {
            if (! $request->user()->city || ! $request->user()->state) {
                throw ValidationException::withMessages(['lives_with_owner' => 'Preencha a cidade e a UF do seu perfil ou escolha outra localização.']);
            }
            $request->merge(['city' => $request->user()->city, 'state' => $request->user()->state]);
        }
        $request->validate(['state' => 'required_if:lives_with_owner,false|nullable|string|size:2|exists:states,code']);
        if ($request->filled('state') && ! City::where('name', $request->input('city'))->whereHas('state', fn ($query) => $query->where('code', $request->input('state')))->exists()) {
            throw ValidationException::withMessages(['city' => 'Escolha uma cidade da UF selecionada.']);
        }
    }

    private function notifyPetInvitation(Request $request, Pet $pet, int $userId): void
    {
        UserNotification::create(['user_id' => $userId, 'type' => 'pet_caretaker_request', 'title' => 'Convite para ser dono de um pet', 'body' => $request->user()->name.' convidou você para ser dono de '.$pet->name.'.', 'data' => ['pet_id' => $pet->id, 'sender_id' => $request->user()->id]]);
        PublishAblyNotification::dispatch($userId)->afterCommit();
    }

    public function inviteCaretaker(Request $request, Pet $pet): JsonResponse
    {
        abort_unless($pet->owner_id === $request->user()->id, 403);
        $data = $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', $data['email'])->firstOrFail();
        abort_if(UserBlock::between($request->user()->id, $user->id), 403, 'Não é possível convidar uma conta bloqueada.');
        abort_if($user->id === $pet->owner_id, 422, 'Esta pessoa já é a criadora do pet.');
        if (! $pet->caretakers()->where('users.id', $user->id)->exists()) {
            $pet->caretakers()->attach($user->id, ['status' => 'pending']);
            $this->notifyPetInvitation($request, $pet, $user->id);
        }

        return response()->json(['message' => 'Solicitação enviada.']);
    }

    public function updateOwnedPet(Request $request, Pet $pet, PetPhotoUpdater $photos): JsonResponse
    {
        abort_unless(($pet->ownership_kind === 'guardian' || $pet->status === 'adopted') && Pet::ownedBy($request->user()->id)->whereKey($pet->id)->exists(), 403);
        $this->preparePetLocation($request);
        $data = $request->validate(['name' => 'required|string|max:80', 'species' => 'required|in:dog,cat', 'breed' => 'nullable|string|max:100', 'birth_date' => 'nullable|date|before:today', 'size' => 'required|in:small,medium,large', 'sex' => 'required|in:male,female', 'city' => 'required|string|max:100', 'state' => 'nullable|string|size:2', 'lives_with_owner' => 'sometimes|boolean', 'temperament' => 'required|string|max:150', 'description' => 'required|string|max:2000', 'photos' => 'nullable|array|max:10', 'photos.*' => 'image|max:5120', 'removed_photo_paths' => 'nullable|array|max:10', 'removed_photo_paths.*' => 'string|max:255', 'cover_photo_path' => 'nullable|string|max:255', 'cover_photo_index' => 'nullable|integer|min:0|max:9', 'owner_ids' => 'sometimes|array|max:20', 'owner_ids.*' => 'required|integer|distinct|exists:users,id']);

        return response()->json(['data' => $this->saveFamilyPet($request, $pet, $data, $photos)]);
    }

    public function respondCaretaker(Request $request, Pet $pet): JsonResponse
    {
        $data = $request->validate(['accept' => 'required|boolean']);
        $relation = $pet->caretakers()->where('user_id', $request->user()->id)->wherePivot('status', 'pending')->firstOrFail();
        if ($data['accept']) {
            $pet->caretakers()->updateExistingPivot($relation->id, ['status' => 'accepted']);
        } else {
            $pet->caretakers()->detach($relation->id);
        }

        return response()->json(['message' => $data['accept'] ? 'Você agora é dono deste pet.' : 'Solicitação recusada.']);
    }

    public function leaveCaretaker(Request $request, Pet $pet): JsonResponse
    {
        abort_if($pet->owner_id === $request->user()->id, 422, 'Apenas outro dono pode sair da tutela.');
        $pet->caretakers()->detach($request->user()->id);

        return response()->json(status: 204);
    }

    public function destroyOwnedPet(Request $request, Pet $pet): JsonResponse
    {
        abort_unless($pet->owner_id === $request->user()->id, 403);
        DB::transaction(function () use ($pet): void {
            $pet = Pet::lockForUpdate()->findOrFail($pet->id);
            abort_if($pet->adoptions()->where('status', 'approved')->whereNull('released_at')->whereNotNull('pickup_at')->exists(), 409, 'Cancele a retirada agendada antes de excluir este pet.');
            $pet->delete();
        });

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

    public function togglePostLike(Request $request, ProfilePost $post): JsonResponse
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

    private function uploadProfileImage(Request $request, string $field, string $folder, string $attribute): JsonResponse
    {
        $request->validate([$field => 'required|image|mimes:jpg,jpeg,png,webp|max:5120']);
        $user = $request->user();
        $path = app(SafeImageStorage::class)->store($request->file($field), $folder);
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
            if (! ProfilePost::withoutGlobalScopes()->where('image_path', $user->{$attribute})->exists()) {
                Storage::disk('public')->delete($user->{$attribute});
            }
            $user->update([$attribute => null]);
        }

        return response()->json(['user' => $user->fresh()->load('roles')]);
    }
}
