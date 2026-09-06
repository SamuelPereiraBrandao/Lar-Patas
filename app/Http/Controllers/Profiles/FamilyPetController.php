<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Http\PetCaretakerNotifier;
use App\Http\PetPhotoUpdater;
use App\Http\Requests\SaveFamilyPetRequest;
use App\Models\Pet;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FamilyPetController extends Controller
{
    public function __construct(private PetCaretakerNotifier $notifier) {}

    public function store(SaveFamilyPetRequest $request, PetPhotoUpdater $photos): JsonResponse
    {
        $data = $request->validated();
        $data['owner_id'] = $request->user()->id;
        $data['ownership_kind'] = 'guardian';
        $data['status'] = 'adopted';

        return response()->json(['data' => $this->saveFamilyPet($request, new Pet, $data, $photos)], 201);
    }

    public function update(SaveFamilyPetRequest $request, Pet $pet, PetPhotoUpdater $photos): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['data' => $this->saveFamilyPet($request, $pet, $data, $photos)]);
    }

    public function destroy(Request $request, Pet $pet): JsonResponse
    {
        abort_unless($pet->owner_id === $request->user()->id, 403);
        DB::transaction(function () use ($pet): void {
            $pet = Pet::lockForUpdate()->findOrFail($pet->id);
            abort_if($pet->adoptions()->where('status', 'approved')->whereNull('released_at')->whereNotNull('pickup_at')->exists(), 409, 'Cancele a retirada agendada antes de excluir este pet.');
            $pet->delete();
        });

        return response()->json(status: 204);
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
                $existingCaretakers = $pet->caretakers()->get()->keyBy('id');
                $selectedCaretakerIds = collect($ownerIds)
                    ->map(fn ($id) => (int) $id)
                    ->reject(fn ($id) => $id === $pet->owner_id);
                if ($request->user()->id !== $pet->owner_id) {
                    $selectedCaretakerIds->push($request->user()->id);
                }
                $caretakerStatuses = $selectedCaretakerIds->unique()->mapWithKeys(fn ($id) => [
                    $id => ['status' => $existingCaretakers->get($id)?->pivot->status ?? 'pending'],
                ]);
                $pet->caretakers()->sync($caretakerStatuses->all());
                foreach ($selectedCaretakerIds->unique()->diff($existingCaretakers->keys()) as $id) {
                    $this->notifier->send($request->user(), $pet, $id);
                }
            }

            return $pet->load(['owner:id,name', 'caretakers' => fn ($query) => $query->select('users.id', 'users.name')]);
        });
    }
}
