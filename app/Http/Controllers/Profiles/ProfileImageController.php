<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAvatarRequest;
use App\Http\Requests\UploadBannerRequest;
use App\Http\SafeImageStorage;
use App\Models\ProfilePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProfileImageController extends Controller
{
    public function __construct(private SafeImageStorage $images) {}

    public function uploadAvatar(UploadAvatarRequest $request): JsonResponse
    {
        return $this->uploadProfileImage($request, 'avatar', 'profiles/avatars', 'avatar_path');
    }

    public function uploadBanner(UploadBannerRequest $request): JsonResponse
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

    private function uploadProfileImage(Request $request, string $field, string $folder, string $attribute): JsonResponse
    {
        $user = $request->user();
        $path = $this->images->store($request->file($field), $folder);
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
