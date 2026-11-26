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

    /** $login : adresse e-mail ou matricule (espaces ignorés pour le matricule). */
    public function check(string $login, string $password): User
    {
        $login = trim($login);
        $user = str_contains($login, '@')
            ? User::where('email', $login)->first()
            : User::whereRaw("upper(replace(replace(matricule, ' ', ''), '.', '')) = ?", [self::normalize($login)])->first();

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

    public static function normalize(?string $matricule): string
    {
        return mb_strtoupper(preg_replace('/[\s.]/u', '', (string) $matricule));
    }
}
