<?php

namespace App\Http\Controllers\Pets;

use App\Http\Controllers\Controller;
use App\Models\Pet;
use App\Models\PetHealthRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetHealthController extends Controller
{
    private function canEdit(Request $r, Pet $pet): bool
    {
        return $r->user()->hasRole('admin') || Pet::ownedBy($r->user()->id)->whereKey($pet->id)->exists();
    }

    public function index(Request $r, Pet $pet): JsonResponse
    {
        abort_unless($this->canEdit($r, $pet) || $pet->adoptions()->where('user_id', $r->user()->id)->where('status', 'approved')->exists(), 403);

        return response()->json(['data' => PetHealthRecord::where('pet_id', $pet->id)->orderByDesc('performed_at')->get(), 'can_edit' => $this->canEdit($r, $pet)]);
    }

    public function store(Request $r, Pet $pet): JsonResponse
    {
        abort_unless($this->canEdit($r, $pet), 403);
        $data = $r->validate(['kind' => 'required|in:vaccine,neutering,care,consultation', 'title' => 'required|string|max:120', 'performed_at' => 'nullable|date|before_or_equal:today', 'due_at' => 'nullable|date|after_or_equal:today', 'notes' => 'nullable|string|max:2000'], [
            'kind.required' => 'Selecione o tipo de cuidado.',
            'kind.in' => 'Selecione um tipo de cuidado válido.',
            'title.required' => 'Informe o nome da vacina ou cuidado.',
            'title.string' => 'O nome da vacina ou cuidado deve ser um texto.',
            'title.max' => 'O nome da vacina ou cuidado deve ter no máximo 120 caracteres.',
            'performed_at.date' => 'Informe uma data de realização válida.',
            'performed_at.before_or_equal' => 'A data de realização deve ser hoje ou uma data anterior.',
            'due_at.date' => 'Informe uma data válida para a próxima dose ou retorno.',
            'due_at.after_or_equal' => 'A data da próxima dose ou retorno deve ser hoje ou uma data futura.',
            'notes.string' => 'As orientações e observações devem ser um texto.',
            'notes.max' => 'As orientações e observações devem ter no máximo 2.000 caracteres.',
        ]);

        return response()->json(['data' => PetHealthRecord::create([...$data, 'pet_id' => $pet->id, 'user_id' => $r->user()->id])], 201);
    }

    public function destroy(Request $r, Pet $pet, PetHealthRecord $record): JsonResponse
    {
        abort_unless($record->pet_id === $pet->id, 404);
        abort_unless($this->canEdit($r, $pet), 403);
        $record->delete();

        return response()->json(status: 204);
    }
}
