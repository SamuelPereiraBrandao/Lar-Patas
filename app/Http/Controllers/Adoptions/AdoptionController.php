<?php

namespace App\Http\Controllers\Adoptions;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdoptionController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Adoption::with('pet:id,name')->latest()->get()]);
    }

    public function store(Request $request, Pet $pet): JsonResponse
    {
        abort_if($pet->status === 'adopted', 422, 'Este pet já foi adotado.');
        $user = $request->user();
        $user->has_other_pets ??= false;
        $adoption = $pet->adoptions()->firstOrCreate(['user_id' => $user->id], ['applicant_name' => $user->name, 'email' => $user->email, 'phone' => $user->phone ?: 'Não informado', 'housing_type' => $user->housing_type ?: 'Não informado', 'has_other_pets' => $user->has_other_pets, 'message' => $user->household_description ?: 'Perfil de adotante preenchido.', 'status' => 'pending']);

        return response()->json(['data' => $adoption], 201);
    }

    public function destroy(Request $request, Adoption $adoption): JsonResponse
    {
        abort_unless($adoption->user_id === $request->user()->id, 403);
        $adoption->delete();

        return response()->json(status: 204);
    }

    public function update(Request $request, Adoption $adoption): JsonResponse
    {
        $data = $request->validate(['status' => 'required|in:pending,approved,rejected']);
        $adoption->update($data);
        if ($data['status'] === 'approved') {
            $adoption->pet->update(['status' => 'adopted']);
        }

        return response()->json(['data' => $adoption]);
    }
}
