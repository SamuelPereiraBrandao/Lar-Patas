<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\PasswordResetController;
use Illuminate\Support\Facades\Route;

Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware('signed')->name('verification.verify');
Route::post('/login', [AuthController::class, 'sessionLogin'])->middleware('guest');
Route::post('/two-factor/verify', [AuthController::class, 'verifyTwoFactor'])->middleware('guest');
Route::post('/two-factor/resend', [AuthController::class, 'resendTwoFactor'])->middleware('guest');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('guest')->name('password.email');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('guest')->name('password.update');
Route::view('/redefinir-senha/{token}', 'app')->middleware('guest')->name('password.reset');
Route::get('/auth/user', [AuthController::class, 'user'])->middleware('auth');
Route::post('/logout', [AuthController::class, 'sessionLogout'])->middleware('auth');
Route::view('/painel', 'app')->name('adoptions.pickup-page');
Route::view('/{any?}', 'app')->where('any', '.*');
