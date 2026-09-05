<?php

namespace App\Http\Controllers\Adoptions;

use App\Http\Controllers\Controller;
use App\Jobs\PublishAblyNotification;
use App\Models\Adoption;
use App\Models\Pet;
use App\Models\ProfilePost;
use App\Models\UserNotification;
use App\Notifications\AdoptionPickupScheduled;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdoptionPickupController extends Controller
{
    public function store(Request $request, Adoption $adoption): JsonResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate([
            'pickup_at' => 'required|date|after:now',
            'pickup_timezone' => 'required|timezone',
            'pickup_location' => 'required|string|max:500',
            'pickup_message' => 'required|string|max:2000',
        ]);

        $adoption = DB::transaction(function () use ($request, $adoption, $data): Adoption {
            $pet = Pet::lockForUpdate()->findOrFail($adoption->pet_id);
            $adoption = Adoption::lockForUpdate()->findOrFail($adoption->id);
            abort_if($pet->status === 'adopted' || $pet->ownership_kind === 'guardian', 409, 'Este pet não está disponível para adoção.');
            abort_if($adoption->status !== 'pending' || ! $adoption->user, 409, 'Esta solicitação não pode ser agendada.');
            abort_if($pet->adoptions()->where('status', 'approved')->whereNotNull('pickup_at')->whereNull('released_at')->exists(), 409, 'Já existe uma retirada agendada para este pet.');

            $code = (string) random_int(100000, 999999);
            $adoption->update([
                ...$data,
                'pickup_at' => Carbon::parse($data['pickup_at'])->utc(),
                'status' => 'approved',
                'pickup_code' => $code,
                'scheduled_by' => $request->user()->id,
            ]);
            $pet->update(['status' => 'in_process']);
            $date = $adoption->pickup_at->copy()->timezone($adoption->pickup_timezone)->format('d/m/Y H:i');
            UserNotification::create([
                'user_id' => $adoption->user_id,
                'type' => 'adoption_pickup',
                'title' => 'Venha buscar '.$pet->name.'!',
                'body' => 'Retirada em '.$date.' ('.$adoption->pickup_timezone.') em '.$adoption->pickup_location.'. Código: '.$code.'. '.$adoption->pickup_message,
                'data' => ['pet_id' => $pet->id, 'adoption_id' => $adoption->id],
            ]);
            PublishAblyNotification::dispatch($adoption->user_id)->afterCommit();
            $adoption->user->notify((new AdoptionPickupScheduled($adoption, $pet->name, $code))->afterCommit());

            return $adoption;
        });

        return response()->json(['data' => $adoption]);
    }

    public function update(Request $request, Adoption $adoption): JsonResponse
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        $data = $request->validate(['code' => 'required|string|regex:/^[0-9]{6}$/']);

        $adoption = DB::transaction(function () use ($request, $adoption, $data): Adoption {
            $pet = Pet::lockForUpdate()->findOrFail($adoption->pet_id);
            $adoption = Adoption::lockForUpdate()->findOrFail($adoption->id);
            abort_if($adoption->status !== 'approved' || ! $adoption->pickup_at || $adoption->released_at || $pet->status === 'adopted' || $pet->ownership_kind === 'guardian' || ! $adoption->user, 409, 'Esta retirada não está aguardando liberação.');
            if (! $adoption->pickup_code || ! hash_equals($adoption->pickup_code, $data['code'])) {
                throw ValidationException::withMessages(['code' => 'Código inválido. Confira o código apresentado pelo adotante.']);
            }

            $adoption->update(['released_at' => now(), 'released_by' => $request->user()->id, 'pickup_code' => null]);
            $hasLocation = (bool) ($adoption->user->city && $adoption->user->state);
            $pet->update(['status' => 'adopted', 'owner_id' => $adoption->user_id, 'ownership_kind' => 'adoption', 'queue_position' => null, 'shelter_id' => null, 'city' => $hasLocation ? $adoption->user->city : $pet->city, 'state' => $hasLocation ? $adoption->user->state : $pet->state, 'lives_with_owner' => $hasLocation]);
            $pet->caretakers()->detach();
            $pet->adoptions()->whereKeyNot($adoption->id)->where('status', 'pending')->update(['status' => 'rejected']);
            $post = ProfilePost::create(['user_id' => $adoption->user_id, 'pet_id' => $pet->id, 'body' => 'Uma nova história começa: '.$pet->name.' foi adotado e agora faz parte da minha família! 🐾', 'image_path' => $pet->image_path, 'gallery_paths' => $pet->gallery_paths]);
            UserNotification::create(['user_id' => $adoption->user_id, 'type' => 'adoption_completed', 'title' => 'Adoção concluída!', 'body' => $pet->name.' agora faz parte da sua família. A adoção foi publicada no seu perfil.', 'data' => ['pet_id' => $pet->id, 'post_id' => $post->id]]);
            PublishAblyNotification::dispatch($adoption->user_id)->afterCommit();

            return $adoption;
        });

        return response()->json(['data' => $adoption]);
    }
}
