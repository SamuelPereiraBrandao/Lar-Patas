<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreShelterRequest;
use App\Models\Pet;
use App\Models\Shelter;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function pets(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => Pet::with(['owner:id,name,email', 'shelter:id,name,city,state,district', 'adoptions.user:id,name,email'])->withCount('adoptions')->latest()->get()]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => User::with('roles:id,name,label')->select('id', 'name', 'email', 'city', 'state', 'created_at')->latest()->get()]);
    }

    public function shelters(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => Shelter::withCount('pets')->latest()->get()]);
    }

    public function storeShelter(StoreShelterRequest $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => Shelter::create($request->validated())], 201);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403);
    }
}
