<?php

namespace Database\Seeders;

use App\Models\EquipmentType;
use Illuminate\Database\Seeder;

/** Référentiels nécessaires en production. Les données de démonstration sont dans DemoSeeder. */
class DatabaseSeeder extends Seeder
{
    public const TYPES = [
        'Ordinateur de bureau', 'Ordinateur portable', 'Écran', 'Imprimante', 'Scanner', 'Photocopieur',
        'Onduleur', 'Serveur', 'Réseau', 'Vidéoprojecteur', 'Téléphone', 'Tablette', 'Autre',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $nom) {
            EquipmentType::firstOrCreate(['nom' => $nom]);
        }
    }
}
