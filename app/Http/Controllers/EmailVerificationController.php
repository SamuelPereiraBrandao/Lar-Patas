<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;

class EmailVerificationController extends Controller
{
    public function verify(int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);
        abort_unless(hash_equals(sha1($user->getEmailForVerification()), $hash), 403);
        $user->markEmailAsVerified();

        return redirect('/entrar?verified=1');
    }
}
