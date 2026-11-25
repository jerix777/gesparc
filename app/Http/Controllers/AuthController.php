<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            throw ValidationException::withMessages([
                'email' => 'Compte verrouillé après '.self::MAX_ATTEMPTS.' essais. Réessayez après '.$user->locked_until->format('H:i').'.',
            ]);
        }

        if (! $user || ! $user->active || ! Auth::attempt($data, $request->boolean('remember'))) {
            if ($user) {
                $user->failed_logins = $user->failed_logins + 1;
                if ($user->failed_logins >= self::MAX_ATTEMPTS) {
                    $user->locked_until = now()->addMinutes(self::LOCK_MINUTES);
                    $user->failed_logins = 0;
                }
                $user->save();
            }
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects ou compte désactivé.']);
        }

        $user->forceFill(['failed_logins' => 0, 'locked_until' => null])->save();
        $request->session()->regenerate();
        AuditLog::record('connexion');

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
