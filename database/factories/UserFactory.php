<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => Role::Agent,
            'fonction' => 'Greffier',
            'bureau' => (string) fake()->numberBetween(1, 40),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role] + ($role === Role::AdminNational || $role === Role::Auditeur ? ['institution_id' => null] : []));
    }
}
