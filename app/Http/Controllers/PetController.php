<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePetRequest;
use App\Models\Pet;
use App\Models\Shelter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        $pet = Pet::query()->with('shelter:id,name,district,city,state')->withCount('adoptions')->withMax('adoptions as latest_interest_at', 'created_at')
            ->when($userId, fn ($query) => $query->withExists([
                'adoptions as is_interested' => fn ($adoptions) => $adoptions->where('user_id', $userId),
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
        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('pets', 'public');
        }$pet->fill($data)->save();

        return $pet;
    }
}
