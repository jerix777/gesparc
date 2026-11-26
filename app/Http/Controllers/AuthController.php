<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\LoginGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, LoginGuard $guard)
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'max:190'], // adresse e-mail ou matricule
            'password' => ['required', 'string'],
        ]);

        $user = $guard->check($data['email'], $data['password']);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        AuditLog::record('connexion');

        return redirect()->intended(route('home'));
    }

    public function editPassword()
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8), 'different:current_password'],
        ], ['password.different' => 'Le nouveau mot de passe doit être différent du mot de passe actuel.']);
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        AuditLog::record('mot-de-passe.changement', $user);

        return redirect()->route('home')->with('ok', 'Mot de passe modifié.');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
