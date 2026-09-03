<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Adoptions\AdoptionController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Dashboards\RoleDashboardController;
use App\Http\Controllers\Pets\PetController;
use Illuminate\Support\Facades\Route;

Route::get('pets', [PetController::class, 'index']);
Route::get('pets/{pet}', [PetController::class, 'show']);
Route::get('shelters', [PetController::class, 'shelters']);
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::middleware('auth:sanctum')->group(function () {
    Route::post('pets/{pet}/adoptions', [AdoptionController::class, 'store']);
    Route::delete('adoptions/{adoption}', [AdoptionController::class, 'destroy']);
    Route::get('profile', [AuthController::class, 'user']);
    Route::put('profile', [AuthController::class, 'updateProfile']);
    Route::get('dashboards/receiver', [RoleDashboardController::class, 'receiver']);
    Route::get('dashboards/donor', [RoleDashboardController::class, 'donor']);
    Route::get('dashboards/admin', [RoleDashboardController::class, 'admin']);
    Route::get('admin/pets', [AdminController::class, 'pets']);
    Route::get('admin/users', [AdminController::class, 'users']);
    Route::put('admin/users/{user}/roles', [AdminController::class, 'updateUserRoles']);
    Route::put('admin/users/{user}/status', [AdminController::class, 'updateUserStatus']);
    Route::get('admin/users/{user}/interests', [AdminController::class, 'userInterests']);
    Route::get('admin/shelters', [AdminController::class, 'shelters']);
    Route::post('admin/shelters', [AdminController::class, 'storeShelter']);
    Route::put('admin/shelters/{shelter}/status', [AdminController::class, 'updateShelterStatus']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::apiResource('pets', PetController::class)->except(['index', 'show']);
    Route::get('adoptions', [AdoptionController::class, 'index']);
    Route::patch('adoptions/{adoption}', [AdoptionController::class, 'update']);
});
