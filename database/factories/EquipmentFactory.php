<?php

namespace Database\Factories;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\EquipmentType;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

class EquipmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'numero_inventaire' => strtoupper(fake()->unique()->bothify('INV-#####')),
            'equipment_type_id' => fn () => EquipmentType::firstOrCreate(['nom' => 'Ordinateur de bureau'])->id,
            'designation' => 'Ordinateur de bureau HP',
            'marque' => 'HP',
            'numero_serie' => strtoupper(fake()->unique()->bothify('SN########')),
            'statut' => EquipmentStatus::EnStock,
            'etat' => EquipmentCondition::Bon,
        ];
    }
}
