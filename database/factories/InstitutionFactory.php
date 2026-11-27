<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class InstitutionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('INST-###')),
            'nom' => 'Tribunal de première instance de '.fake()->city(),
            'categorie' => 'Juridiction',
            'ville' => fake()->city(),
        ];
    }
}
