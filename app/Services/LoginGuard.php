<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Vérification des identifiants avec verrouillage après 5 échecs (web et API). */
class LoginGuard
{
    public const MAX_ATTEMPTS = 5;

    public const LOCK_MINUTES = 15;

    public function check(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if ($user && $user->locked_until && $user->locked_until->isFuture()) {
            throw ValidationException::withMessages([
                'email' => 'Compte verrouillé après '.self::MAX_ATTEMPTS.' essais. Réessayez après '.$user->locked_until->format('H:i').'.',
            ]);
        }

        if (! $user || ! $user->active || ! Hash::check($password, $user->password)) {
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

        return $user;
    }
}
