<?php

namespace App\Http\Controllers\Dashboards;

use App\Http\Controllers\Controller;
use App\Models\Adoption;
use App\Models\Pet;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleDashboardController extends Controller
{
    public function receiver(Request $request): JsonResponse
    {
        return response()->json(['data' => Adoption::with(['pet' => fn ($query) => $query->withCount(['adoptions', 'visits'])])->where('user_id', $request->user()->id)->latest()->get()]);
    }

    public function donor(Request $request): JsonResponse
    {
        return response()->json(['data' => Pet::with(['adoptions', 'adoptions.pet'])->where('owner_id', $request->user()->id)->orderBy('queue_position')->get()]);
    }

    public function admin(): JsonResponse
    {
        return response()->json(['data' => ['pets_in_queue' => Pet::whereNotNull('queue_position')->count(), 'pending_adoptions' => Adoption::where('status', 'pending')->count(), 'donors' => Role::where('name', 'donor')->first()?->users()->count() ?? 0, 'adopters' => Role::where('name', 'adopter')->first()?->users()->count() ?? 0]]);
    }
}
