<?php

namespace App\Http\Controllers\Locations;

use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\JsonResponse;

class LocationController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => State::query()->with('cities:id,state_id,name')->orderBy('name')->get(['id', 'name', 'code'])]);
    }
}
