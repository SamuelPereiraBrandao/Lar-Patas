<?php

namespace App\Http\Controllers\Adoptions;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        DB::transaction(function () use ($adoption): void {
            Pet::lockForUpdate()->findOrFail($adoption->pet_id);
            $adoption = Adoption::lockForUpdate()->findOrFail($adoption->id);
            abort_if($adoption->status === 'approved', 409, 'Uma adoção aprovada não pode ser removida. Entre em contato com a equipe.');
            $adoption->delete();
        });

        return response()->json(status: 204);
    }

    public function update(Request $request, Adoption $adoption): JsonResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['status' => 'required|in:pending,rejected']);
        $adoption = DB::transaction(function () use ($adoption, $data): Adoption {
            Pet::lockForUpdate()->findOrFail($adoption->pet_id);
            $adoption = Adoption::lockForUpdate()->findOrFail($adoption->id);
            abort_if($adoption->status === 'approved', 409, 'Esta adoção já foi aprovada. Use a confirmação de retirada.');
            $adoption->update($data);

            return $adoption;
        });

        return response()->json(['data' => $adoption]);
    }
}
