<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function sessionLogin(Request $request): JsonResponse
    {
        $credentials = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return response()->json(['message' => 'E-mail ou senha inválidos.'], 422);
        }

        /** @var User $user */
        $user = Auth::user();
        if (! $user->is_active) {
            Auth::logout();

            return response()->json(['message' => 'Esta conta está desativada. Procure a administração.'], 403);
        }
        if (! $user->hasVerifiedEmail()) {
            Auth::logout();

            return response()->json(['message' => 'Confirme seu e-mail antes de entrar.'], 403);
        }

        Auth::logout();
        $request->session()->put('two_factor_user_id', $user->id);
        $this->sendTwoFactorCode($user);

        return response()->json(['message' => 'Enviamos um código de acesso para o seu e-mail.', 'requires_two_factor' => true]);
    }

    public function verifyTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => 'required|digits:6']);
        $user = User::find($request->session()->get('two_factor_user_id'));
        if (! $user || ! $user->is_active || ! $user->hasVerifiedEmail() || ! $user->two_factor_expires_at?->isFuture() || ! Hash::check($data['code'], $user->two_factor_code)) {
            return response()->json(['message' => 'Código inválido ou expirado.'], 422);
        }

        $user->forceFill(['two_factor_code' => null, 'two_factor_expires_at' => null])->save();
        $request->session()->forget('two_factor_user_id');
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user->load('roles')]);
    }

    public function resendTwoFactor(Request $request): JsonResponse
    {
        $user = User::find($request->session()->get('two_factor_user_id'));
        abort_unless($user && $user->is_active && $user->hasVerifiedEmail(), 403);
        $this->sendTwoFactorCode($user);

        return response()->json(['message' => 'Enviamos um novo código para o seu e-mail.']);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json(['user' => $request->user()->load('roles')]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'nullable|string|max:30',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'birth_date' => 'nullable|date|before:today',
            'housing_type' => 'nullable|in:Casa com quintal,Casa sem quintal,Apartamento',
            'has_other_pets' => 'boolean',
            'household_description' => 'nullable|string|max:1000',
        ], ['housing_type.in' => 'Escolha um tipo de moradia válido na aba Informações.']);
        $request->user()->update($data);

        return response()->json(['user' => $request->user()->fresh()->load('roles')]);
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|unique:users,email', 'password' => ['required', 'confirmed', Password::min(8)], 'city' => 'required|string|max:100', 'state' => 'required|string|size:2', 'housing_type' => 'required|in:Casa com quintal,Casa sem quintal,Apartamento', 'has_other_pets' => 'boolean', 'household_description' => 'nullable|string|max:1000']);
        $user = User::create([...collect($data)->except('password')->all(), 'password' => Hash::make($data['password'])]);
        $adopterRole = Role::firstOrCreate(['name' => 'adopter'], ['label' => 'Adotante']);
        $user->roles()->syncWithoutDetaching([$adopterRole->id]);
        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Cadastro criado. Confirme o link enviado ao seu e-mail para entrar.'], 201);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(status: 204);
    }

    public function sessionLogout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(status: 204);
    }

    private function sendTwoFactorCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);
        $user->forceFill(['two_factor_code' => Hash::make($code), 'two_factor_expires_at' => now()->addMinutes(10)])->save();
        Mail::send('emails.two-factor-code', ['name' => $user->name, 'code' => $code], function ($message) use ($user): void {
            $message->to($user->email)->subject('Lar & Patas - código de acesso');
        });
    }
}
