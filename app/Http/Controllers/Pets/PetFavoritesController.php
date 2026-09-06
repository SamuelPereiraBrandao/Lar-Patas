<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\PetFavorite;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetFavoritesController extends Controller
{
    public function index(Request $r): JsonResponse
    {
        return response()->json(['data' => Pet::whereIn('id', PetFavorite::where('user_id', $r->user()->id)->select('pet_id'))->latest()->get()->each(fn ($p) => $p->setAttribute('is_favorited', true)), 'searches' => SavedSearch::where('user_id', $r->user()->id)->latest()->get()]);
    }

    public function store(Request $r, Pet $pet): JsonResponse
    {
        PetFavorite::firstOrCreate(['user_id' => $r->user()->id, 'pet_id' => $pet->id]);

        return response()->json(['is_favorited' => true]);
    }

    public function destroy(Request $r, Pet $pet): JsonResponse
    {
        PetFavorite::where('user_id', $r->user()->id)->where('pet_id', $pet->id)->delete();

        return response()->json(['is_favorited' => false]);
    }

    public function saveSearch(Request $r): JsonResponse
    {
        $data = $r->validate(['name' => 'required|string|max:80', 'filters' => 'required|array:search,species,size,shelter,favorites', 'filters.favorites' => 'sometimes|boolean', 'filters.search' => 'nullable|string|max:100', 'filters.species' => 'nullable|in:dog,cat,Cachorro,Gato', 'filters.size' => 'nullable|in:small,medium,large,Pequeno,Médio,Grande', 'filters.shelter' => 'nullable|integer|exists:shelters,id']);
        abort_if(SavedSearch::where('user_id', $r->user()->id)->count() >= 30, 422, 'Você pode salvar até 30 buscas.');

        return response()->json(['data' => SavedSearch::create([...$data, 'user_id' => $r->user()->id])], 201);
    }

    public function deleteSearch(Request $r, SavedSearch $search): JsonResponse
    {
        abort_unless($search->user_id === $r->user()->id, 403);
        $search->delete();

        return response()->json(status: 204);
    }
}
