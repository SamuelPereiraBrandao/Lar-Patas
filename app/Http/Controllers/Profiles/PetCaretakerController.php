<?php

namespace App\Http\Controllers\Profiles;

use App\Http\Controllers\Controller;
use App\Http\PetCaretakerNotifier;
use App\Models\Pet;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PetCaretakerController extends Controller
{
    public function __construct(private PetCaretakerNotifier $notifier) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => 'nullable|string|max:80']);
        $users = User::query()->whereKeyNot($request->user()->id)
            ->when($data['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->limit(30)->get(['id', 'name', 'city', 'state']);

        return response()->json(['data' => $users]);
    }

    public function store(Request $request, Pet $pet): JsonResponse
    {
        abort_unless($pet->owner_id === $request->user()->id, 403);
        $data = $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', $data['email'])->firstOrFail();
        abort_if(UserBlock::between($request->user()->id, $user->id), 403, 'Não é possível convidar uma conta bloqueada.');
        abort_if($user->id === $pet->owner_id, 422, 'Esta pessoa já é a criadora do pet.');
        if (! $pet->caretakers()->where('users.id', $user->id)->exists()) {
            $pet->caretakers()->attach($user->id, ['status' => 'pending']);
            $this->notifier->send($request->user(), $pet, $user->id);
        }

        return response()->json(['message' => 'Solicitação enviada.']);
    }

    public function update(Request $request, Pet $pet): JsonResponse
    {
        $data = $request->validate(['accept' => 'required|boolean']);
        $relation = $pet->caretakers()->where('user_id', $request->user()->id)->wherePivot('status', 'pending')->firstOrFail();
        if ($data['accept']) {
            $pet->caretakers()->updateExistingPivot($relation->id, ['status' => 'accepted']);
        } else {
            $pet->caretakers()->detach($relation->id);
        }

        return response()->json(['message' => $data['accept'] ? 'Você agora é dono deste pet.' : 'Solicitação recusada.']);
    }

    public function destroy(Request $request, Pet $pet): JsonResponse
    {
        abort_if($pet->owner_id === $request->user()->id, 422, 'Apenas outro dono pode sair da tutela.');
        $pet->caretakers()->detach($request->user()->id);

        return response()->json(status: 204);
    }
}
