<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\LoginGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request, LoginGuard $guard)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = $guard->check($data['email'], $data['password']);
        Auth::login($user, $request->boolean('remember'));
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
