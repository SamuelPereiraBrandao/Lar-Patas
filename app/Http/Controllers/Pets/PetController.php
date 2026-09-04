<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePetRequest;
use App\Models\Pet;
use App\Models\Shelter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user('sanctum')?->id ?? $request->user()?->id;

        $pets = Pet::query()->with('shelter:id,name,district,city,state')->withCount('adoptions')->withMax('adoptions as latest_interest_at', 'created_at')->when($userId, fn ($query) => $query->withExists([
            'adoptions as is_interested' => fn ($adoptions) => $adoptions->where('user_id', $userId),
        ]))->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('city', 'like', "%{$s}%")))->when($request->species, fn ($q, $s) => $q->where('species', match (strtolower($s)) {
            'cachorro' => 'dog','gato' => 'cat',default => strtolower($s)
        }))->when($request->size, fn ($q, $s) => $q->where('size', strtolower($s)))->when($request->shelter, fn ($q, $s) => $q->where('shelter_id', $s))->latest()->paginate(12);

        return response()->json($pets);
    }

    public function show(Request $request, Pet $pet): JsonResponse
    {
        $userId = $request->user('sanctum')?->id ?? $request->user()?->id;

        $pet = Pet::query()->with('shelter:id,name,district,city,state')->withCount(['adoptions', 'likes'])->withMax('adoptions as latest_interest_at', 'created_at')
            ->when($userId, fn ($query) => $query->withExists([
                'adoptions as is_interested' => fn ($adoptions) => $adoptions->where('user_id', $userId),
                'likes as is_liked' => fn ($likes) => $likes->where('user_id', $userId),
            ]))
            ->findOrFail($pet->id);

        return response()->json(['data' => $pet]);
    }

    public function shelters(): JsonResponse
    {
        return response()->json(['data' => Shelter::query()->where('active', true)->orderBy('name')->get(['id', 'name', 'district', 'city', 'state'])]);
    }

    public function store(StorePetRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->save($request, new Pet)], 201);
    }

    public function update(StorePetRequest $request, Pet $pet): JsonResponse
    {
        return response()->json(['data' => $this->save($request, $pet)]);
    }

    public function destroy(Pet $pet): JsonResponse
    {
        $pet->delete();

        return response()->json(status: 204);
    }

    private function save(StorePetRequest $request, Pet $pet): Pet
    {
        $data = $request->validated();
        $currentPaths = collect([$pet->image_path, ...($pet->gallery_paths ?? [])])->filter()->values();
        $removedPaths = collect($request->input('removed_photo_paths', []))->intersect($currentPaths);
        if ($removedPaths->isNotEmpty() && $currentPaths->count() - $removedPaths->count() < 1) {
            throw ValidationException::withMessages(['removed_photo_paths' => 'O pet precisa manter pelo menos uma foto.']);
        }
        if ($removedPaths->isNotEmpty()) {
            Storage::disk('public')->delete($removedPaths->all());
            $remainingPaths = $currentPaths->diff($removedPaths)->values();
            $data['image_path'] = $remainingPaths->first();
            $data['gallery_paths'] = $remainingPaths->slice(1)->all();
        }
        $coverPath = $request->input('cover_photo_path');
        $availablePaths = collect([$data['image_path'] ?? $pet->image_path, ...($data['gallery_paths'] ?? $pet->gallery_paths ?? [])])->filter()->values();
        if ($coverPath && $availablePaths->contains($coverPath)) {
            $data['image_path'] = $coverPath;
            $data['gallery_paths'] = $availablePaths->reject(fn ($path) => $path === $coverPath)->values()->all();
        }
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('pets', 'public');
        }
        if ($request->hasFile('photos')) {
            $newPaths = collect($request->file('photos'))->map(fn ($photo) => $photo->store('pets/gallery', 'public'));
            if (! $pet->image_path && ! isset($data['image_path'])) {
                $data['image_path'] = $newPaths->shift();
            }
            $data['gallery_paths'] = [...($data['gallery_paths'] ?? $pet->gallery_paths ?? []), ...$newPaths->all()];
        }
        unset($data['image'], $data['photos'], $data['removed_photo_paths'], $data['cover_photo_path']);
        $pet->fill($data)->save();

        return $pet;
    }
}
