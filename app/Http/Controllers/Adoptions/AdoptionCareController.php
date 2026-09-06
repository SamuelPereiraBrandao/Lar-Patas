<?php

namespace App\Http\Controllers\Adoptions;

use App\Http\Controllers\Controller;
use App\Jobs\PublishAblyNotification;
use App\Models\Adoption;
use App\Models\Pet;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AdoptionCareController extends Controller
{
    public function update(Request $r, Adoption $adoption): JsonResponse
    {
        abort_unless($adoption->user_id === $r->user()->id || $r->user()->hasRole('admin'), 403);
        $data = $r->validate(['action' => 'required|in:cancel,reschedule,followup,support', 'reason' => 'required_if:action,cancel,reschedule|nullable|string|min:10|max:2000', 'requested_pickup_at' => 'required_if:action,reschedule|nullable|date|after:now', 'adaptation_status' => 'required_if:action,followup|nullable|in:well,adjusting,needs_help', 'adaptation_notes' => 'nullable|string|max:2000', 'support_message' => 'required_if:action,support|nullable|string|min:10|max:2000', 'resolved' => 'sometimes|boolean']);
        $result = DB::transaction(function () use ($r, $adoption, $data): Adoption {
            $pet = Pet::lockForUpdate()->findOrFail($adoption->pet_id);
            $adoption = Adoption::lockForUpdate()->findOrFail($adoption->id);
            if ($data['action'] === 'support') {
                abort_unless($r->user()->hasRole('admin') && $adoption->released_at, 403);
                $adoption->update(['support_message' => $data['support_message'], 'support_replied_at' => now(), 'followup_resolved_at' => ($data['resolved'] ?? false) ? now() : null]);
            } elseif ($data['action'] === 'followup') {
                abort_unless($adoption->released_at && $adoption->user_id === $r->user()->id, 409, 'O acompanhamento começa após a adoção.');
                $adoption->update(['adaptation_status' => $data['adaptation_status'], 'adaptation_notes' => $data['adaptation_notes'] ?? null, 'followup_completed_at' => now(), 'followup_resolved_at' => null]);
            } else {
                abort_if($adoption->released_at || $adoption->cancelled_at || $adoption->status === 'rejected', 409, 'Esta solicitação já foi encerrada.');
                if ($data['action'] === 'cancel') {
                    $reserved = $adoption->status === 'approved';
                    $adoption->update(['status' => 'rejected', 'cancelled_at' => now(), 'cancellation_reason' => $data['reason'], 'pickup_code' => null, 'pickup_at' => null, 'pickup_location' => null, 'pickup_message' => null, 'reschedule_requested_at' => null, 'requested_pickup_at' => null]);
                    if ($reserved && $pet->status === 'in_process') {
                        $pet->update(['status' => 'available']);
                    }
                } else {
                    abort_unless($adoption->status === 'approved' && $adoption->pickup_at, 409, 'A retirada ainda não foi agendada.');
                    $adoption->update(['reschedule_requested_at' => now(), 'requested_pickup_at' => Carbon::parse($data['requested_pickup_at'])->utc(), 'reschedule_reason' => $data['reason']]);
                }
            }
            $title = match ($data['action']) {
                'cancel' => 'Retirada cancelada','reschedule' => 'Pedido de reagendamento','support' => 'A equipe respondeu seu acompanhamento',default => 'Atualização da adaptação'
            };
            $recipients = User::whereHas('roles', fn ($q) => $q->where('name', 'admin'))->pluck('id')->push($adoption->user_id)->unique();
            foreach ($recipients as $id) {
                UserNotification::create(['user_id' => $id, 'type' => 'adoption_care', 'title' => $title, 'body' => $pet->name.': '.($data['support_message'] ?? $data['reason'] ?? $data['adaptation_notes'] ?? 'Acompanhamento atualizado.'), 'data' => ['adoption_id' => $adoption->id, 'pet_id' => $pet->id, 'url' => $id === $adoption->user_id ? '/painel' : '/admin/pendencias']]);
                PublishAblyNotification::dispatch($id)->afterCommit();
            }

            return $adoption;
        });

        return response()->json(['data' => $result]);
    }
}
