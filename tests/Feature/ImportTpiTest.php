<?php

namespace Tests\Feature;

use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Models\Equipment;

class ImportTpiTest extends SigaciTestCase
{
    public function test_imports_tpi_backup_file(): void
    {
        $agent = $this->user(Role::Agent);
        $agent->update(['name' => 'Traoré Mariam', 'bureau' => '21']);

        $items = [
            ['designation' => 'PC greffe', 'type' => 'Ordinateur', 'marque' => 'HP', 'numeroSerie' => 'SN1', 'etat' => 'En service', 'affectation' => 'Traoré Mariam', 'dateAcquisition' => '2021-05-03'],
            ['designation' => 'Imprimante parquet', 'type' => 'Imprimante', 'numeroSerie' => 'SN2', 'etat' => 'En panne', 'affectation' => ''],
            ['designation' => 'Vieux scanner', 'type' => 'Scanner', 'etat' => 'Réformé'],
            ['designation' => 'Doublon', 'type' => 'Ordinateur', 'numeroSerie' => 'SN1', 'etat' => 'En service'],
            ['designation' => '', 'type' => 'Autre'],
        ];
        // Format de la sauvegarde de l'application TPI : valeurs brutes du localStorage.
        $file = tempnam(sys_get_temp_dir(), 'tpi');
        file_put_contents($file, json_encode(['application' => 'TPI', 'donnees' => ['tpi_it' => json_encode($items)]]));

        $this->artisan('sigaci:import-tpi', ['fichier' => $file, '--institution' => 'tpi-a'])->assertSuccessful();

        $all = Equipment::withoutGlobalScopes()->where('institution_id', $this->a->id)->orderBy('id')->get();
        $this->assertCount(3, $all);
        $this->assertSame(['TPI-A-00001', 'TPI-A-00002', 'TPI-A-00003'], $all->pluck('numero_inventaire')->all());
        $this->assertSame(EquipmentStatus::Affecte, $all[0]->statut);
        $this->assertSame($agent->id, $all[0]->assigned_user_id);
        $this->assertSame('2021-05-03', $all[0]->date_acquisition->toDateString());
        $this->assertSame(EquipmentStatus::EnPanne, $all[1]->statut);
        $this->assertSame(EquipmentStatus::Reforme, $all[2]->statut);
        $this->assertSame('import', $all[0]->movements()->value('type'));
    }
}
