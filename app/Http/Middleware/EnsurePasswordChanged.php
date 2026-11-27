<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Un mot de passe provisoire doit être remplacé avant toute autre action. */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->must_change_password && ! $request->routeIs('password.*', 'logout')) {
            return redirect()->route('password.edit');
        }

        return $next($request);
    }
}
