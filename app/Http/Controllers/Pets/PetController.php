<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Http\PetPhotoUpdater;
use App\Http\Requests\StorePetRequest;
use App\Models\Pet;
use App\Models\Shelter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetController extends Controller
{
    public function __construct(private PetPhotoUpdater $photos) {}

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user('sanctum')?->id ?? $request->user()?->id;

        $pets = Pet::query()->where('ownership_kind', '!=', 'guardian')->with('shelter:id,name,district,city,state')->withCount('adoptions')->withMax('adoptions as latest_interest_at', 'created_at')->when($userId, fn ($query) => $query->withExists([
            'adoptions as is_interested' => fn ($adoptions) => $adoptions->where('user_id', $userId),
        ]))->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'like', "%{$s}%")->orWhere('city', 'like', "%{$s}%")))->when($request->species, fn ($q, $s) => $q->where('species', match (strtolower($s)) {
            'cachorro' => 'dog','gato' => 'cat',default => strtolower($s)
        }))->when($request->size, fn ($q, $s) => $q->where('size', match (strtolower($s)) {
            'pequeno' => 'small', 'médio', 'medio' => 'medium', 'grande' => 'large', default => strtolower($s),
        }))->when($request->shelter, fn ($q, $s) => $q->where('shelter_id', $s))->latest()->paginate(12);

        return response()->json($pets);
    }

    public function show(Request $request, Pet $pet): JsonResponse
    {
        $userId = $request->user('sanctum')?->id ?? $request->user()?->id;

        $pet = Pet::query()->with(['shelter:id,name,district,city,state', 'owner:id,name,avatar_path,city,state', 'caretakers' => fn ($query) => $query->select('users.id', 'users.name', 'users.avatar_path', 'users.city', 'users.state')->wherePivot('status', 'accepted')])->withCount(['adoptions', 'likes'])->withMax('adoptions as latest_interest_at', 'created_at')
            ->when($userId, fn ($query) => $query->withExists([
                'adoptions as is_interested' => fn ($adoptions) => $adoptions->where('user_id', $userId),
                'likes as is_liked' => fn ($likes) => $likes->where('user_id', $userId),
            ]))
            ->findOrFail($pet->id);
        $pet->setAttribute('is_owner', $userId && $pet->owner_id === $userId);
        $pet->setAttribute('has_pending_owner_request', $userId && $pet->caretakers()->where('users.id', $userId)->wherePivot('status', 'pending')->exists());

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
        abort_if($pet->ownership_kind === 'guardian' && ! Pet::ownedBy($request->user()->id)->whereKey($pet->id)->exists(), 403);

        return response()->json(['data' => $this->save($request, $pet)]);
    }

    public function destroy(Request $request, Pet $pet): JsonResponse
    {
        abort_if($pet->ownership_kind === 'guardian' && $pet->owner_id !== $request->user()->id, 403);
        $pet->delete();

        return response()->json(status: 204);
    }

    private function save(StorePetRequest $request, Pet $pet): Pet
    {
        $data = $request->validated();
        if ($shelter = Shelter::find($data['shelter_id'] ?? null)) {
            $data['city'] = $shelter->city;
            $data['state'] = $shelter->state;
        }

        return $this->photos->save($request, $pet, $data);
    }
}
