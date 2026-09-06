<?php

namespace App\Http;

use App\Models\Pet;
use App\Models\ProfilePost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PetPhotoUpdater
{
    /** @param array<string, mixed> $data */
    public function save(Request $request, Pet $pet, array $data): Pet
    {
        $current = collect([$pet->image_path, ...($pet->gallery_paths ?? [])])->filter()->values();
        $removed = collect($request->input('removed_photo_paths', []))->unique();
        if ($removed->diff($current)->isNotEmpty()) {
            throw ValidationException::withMessages(['removed_photo_paths' => 'Selecione apenas fotos deste pet.']);
        }
        $remaining = $current->diff($removed)->values();
        $uploads = $request->file('photos', []);
        $image = isset($data['image']) ? $request->file('image') : null;
        if (! $pet->exists && count($uploads) === 0 && ! $image) {
            throw ValidationException::withMessages(['photos' => 'Adicione pelo menos uma foto para criar o pet.']);
        }
        if ($removed->isNotEmpty() && $remaining->isEmpty() && count($uploads) === 0 && ! $image) {
            throw ValidationException::withMessages(['removed_photo_paths' => 'O pet precisa manter pelo menos uma foto.']);
        }
        if ($remaining->count() + count($uploads) > 10) {
            throw ValidationException::withMessages(['photos' => 'O limite da galeria é de 10 fotos.']);
        }
        $cover = $request->input('cover_photo_path');
        if ($cover && ! $remaining->contains($cover)) {
            throw ValidationException::withMessages(['cover_photo_path' => 'Escolha uma foto que permanece na galeria deste pet.']);
        }
        $coverIndex = $request->input('cover_photo_index');
        if ($coverIndex !== null && ! array_key_exists((int) $coverIndex, $uploads)) {
            throw ValidationException::withMessages(['cover_photo_index' => 'Escolha uma das novas fotos para a capa.']);
        }
        $newPaths = collect($uploads)->map(fn ($photo) => app(SafeImageStorage::class)->store($photo, 'pets/gallery'));
        $paths = $remaining->concat($newPaths)->values();
        if ($image) {
            $cover = app(SafeImageStorage::class)->store($image, 'pets');
            $paths = $paths->reject(fn ($path) => $path === $pet->image_path)->prepend($cover)->values();
        }
        if ($coverIndex !== null) {
            $cover = $newPaths->get((int) $coverIndex);
        }
        if ($cover) {
            $paths = $paths->reject(fn ($path) => $path === $cover)->prepend($cover)->values();
        }
        $data['image_path'] = $paths->first();
        $data['gallery_paths'] = $paths->slice(1)->values()->all();
        unset($data['image'], $data['photos'], $data['removed_photo_paths'], $data['cover_photo_path'], $data['cover_photo_index']);
        $pet->fill($data)->save();
        foreach ($removed as $path) {
            if (! ProfilePost::withoutGlobalScopes()->where('image_path', $path)->orWhereJsonContains('gallery_paths', $path)->exists()) {
                Storage::disk('public')->delete($path);
            }
        }

        return $pet;
    }
}
