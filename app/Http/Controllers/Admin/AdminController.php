<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreShelterRequest;
use App\Models\Pet;
use App\Models\Role;
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

        return response()->json(['data' => User::with('roles:id,name,label')->select('id', 'name', 'email', 'city', 'state', 'is_active', 'created_at')->latest()->get()]);
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

    public function updateShelterStatus(Request $request, Shelter $shelter): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['active' => 'required|boolean']);
        $shelter->update($data);

        return response()->json(['data' => $shelter->fresh()]);
    }

    public function updateUserRoles(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate(['roles' => 'required|array|min:1', 'roles.*' => 'in:admin,adopter,donor']);
        $user->roles()->sync(Role::whereIn('name', $data['roles'])->pluck('id'));

        return response()->json(['data' => $user->fresh()->load('roles:id,name,label')]);
    }

    public function updateUserStatus(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate(['is_active' => 'required|boolean']);
        $user->update($data);

        return response()->json(['data' => $user->fresh()->load('roles:id,name,label')]);
    }

    public function userInterests(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(['data' => $user->adoptions()->with('pet:id,name,species,city,status')->latest()->get()]);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->hasRole('admin'), 403);
    }
}
