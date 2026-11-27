<?php

namespace Tests\Feature;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Enums\Position;
use App\Models\Consumable;
use App\Models\Equipment;
use App\Models\Institution;
use App\Models\Staff;

class RepriseOrigineTest extends SigaciTestCase
{
    /** Fichier HTML fictif reproduisant la structure de l'application d'origine (aucune donnée réelle). */
    private function originalHtml(): string
    {
        $people = [
            ['id' => '0-1', 'nom' => 'KONAN', 'prenoms' => 'Ama', 'categorie' => 'Magistrats du siège', 'fonction' => 'Président du tribunal', 'matricule' => '100 001-A', 'telephone' => '0102030405', 'email' => 'NEANT', 'statut' => 'Actif'],
            ['id' => '0-2', 'nom' => 'KOUAME', 'prenoms' => 'Yao', 'categorie' => 'Personnel des greffes', 'fonction' => 'Greffier', 'matricule' => '100 002-B', 'email' => 'yao@example.org', 'statut' => 'Actif'],
            ['id' => '0-3', 'nom' => 'TANO', 'prenoms' => 'Luc', 'categorie' => 'Contractuels du tribunal', 'fonction' => 'Opérateur de saisie', 'matricule' => '', 'statut' => 'Actif'],
        ];
        $updates = [['id' => '0-1', 'service' => 'CABINET DU PRESIDENT', 'porte' => '1'], ['id' => '0-2', 'service' => 'GREFFE CIVIL', 'porte' => '12']];
        $pc = fn (array $v) => $v + array_fill_keys(['numero', 'utilisateur', 'service', 'fonction', 'porte', 'modeleUniteCentrale', 'nomPC', 'numeroSerieUC', 'ramQuantite', 'ramType', 'ramTailleGo', 'ramFrequenceMHz', 'disqueType', 'disqueTailleGo', 'processeurType', 'processeurFrequenceGHz', 'systemeExploitation', 'systemeProduit', 'systemeActivation', 'officeProduit', 'officeActivation', 'dateMiseEnService', 'adresseIP', 'adresseMAC', 'etatUC', 'observationsUC', 'ecranMarque', 'ecranTaille', 'personnelId'], '');
        $seed = [
            'ben' => [
                ['id' => 'b1', 'nom' => 'KONAN Ama', 'svc' => 'CABINET DU PRESIDENT', 'fn' => 'PRESIDENT', 'porte' => '1', 'personnelId' => '0-1'],
                ['id' => 'b2', 'nom' => 'NO USER', 'svc' => 'INDORMATIQUE', 'fn' => '', 'porte' => '23'],
                ['id' => 'b3', 'nom' => 'VIDE', 'svc' => 'GREFFE', 'fn' => '', 'porte' => ''],
            ],
            'pc' => [
                $pc(['numero' => '1', 'utilisateur' => 'KONAN Ama', 'service' => 'CABINET DU PRESIDENT', 'porte' => '1', 'modeleUniteCentrale' => 'HP PRODESK 600 G3 MT', 'nomPC' => 'DESKTOP-1', 'numeroSerieUC' => 'UC001', 'ramQuantite' => '1', 'ramTailleGo' => '8', 'ramType' => 'DDR4', 'processeurType' => 'Core i5', 'systemeExploitation' => 'WIN 11 PRO', 'officeActivation' => '2025', 'dateMiseEnService' => 'DHCP', 'etatUC' => 'LENT', 'observationsUC' => 'HP P22v MONITOR', 'ecranMarque' => '22"', 'ecranTaille' => 'EC001', 'personnelId' => '0-1']),
                $pc(['numero' => '2', 'utilisateur' => 'NO USER', 'service' => 'INDORMATIQUE', 'porte' => '23', 'modeleUniteCentrale' => 'HP 290 G1 MT', 'officeActivation' => '2025-11-11 00:00:00']),
                $pc(['numero' => '3', 'utilisateur' => 'VIDE', 'service' => 'GREFFE']),
            ],
            'imp' => [
                ['id' => 'p1', 'marque' => 'HP', 'modele' => 'HP LASERJET PRO MFP M426dw', 'serie' => 'IMP001', 'ref' => '26A', 'ags' => ['b1'], 'etat' => 'ANCIEN', 'observations' => 'BON ETAT', 'dateDeploiement' => '45988', 'adresseIP' => '0'],
                ['id' => 'p2', 'marque' => 'HP', 'modele' => 'HP LASERJET P1102', 'serie' => 'AUCUN', 'ref' => '85A', 'ags' => ['b2'], 'etat' => 'BON', 'observations' => 'A QUITTE LE BUREAU', 'dateDeploiement' => '2018', 'adresseIP' => '0'],
            ],
            'scanners' => [
                ['numero' => '1', 'utilisateur' => 'X', 'service' => 'RCCM', 'porte' => '39', 'modele' => 'HP LASERJET PRO MFP M426dw', 'numeroSerie' => 'IMP001', 'cartouche' => '26A'],
                ['numero' => '2', 'utilisateur' => 'Y', 'service' => 'INFORMATIQUE', 'porte' => '23', 'modele' => 'SCANER CANOSCAN LID 300', 'numeroSerie' => 'SC001', 'cartouche' => ''],
            ],
        ];
        $pack = base64_encode('<script>const SEED='.json_encode($seed, JSON_UNESCAPED_UNICODE).';</script>');
        $html = '<html><body><script>const initialPeople='.json_encode($people, JSON_UNESCAPED_UNICODE).';'
            .'const TPI_SERVICE_UPDATE_NOV2026='.json_encode($updates).";function f(){}</script>\n"
            .'<script type="text/plain" id="pcSrc">'.$pack.'</script></body></html>';
        $path = tempnam(sys_get_temp_dir(), 'origine').'.html';
        file_put_contents($path, $html);

        return $path;
    }

    public function test_imports_personnel_and_equipment_from_original_html_and_is_idempotent(): void
    {
        $html = $this->originalHtml();
        $this->artisan('sigaci:reprise-origine', ['sources' => [$html], '--institution' => 'tpi-a'])
            ->expectsOutputToContain('Personnel : 3 fiche(s) créée(s)')
            ->expectsOutputToContain('Équipements : 6 créé(s)')
            ->expectsOutputToContain('Non repris — inventaire des postes ligne 3')
            ->assertSuccessful();
        $this->artisan('sigaci:reprise-origine', ['sources' => [$html], '--institution' => 'tpi-a'])
            ->expectsOutputToContain('Équipements : 0 créé(s), 6 mis à jour')
            ->assertSuccessful();

        $staff = Staff::withoutGlobalScopes()->where('institution_id', $this->a->id)->with('service', 'category')->get()->keyBy('source_ref');
        $this->assertCount(3, $staff);
        $this->assertNull($staff['0-1']->email);
        $this->assertSame('CABINET DU PRESIDENT', $staff['0-1']->service->nom);
        $this->assertSame('12', $staff['0-2']->bureau);
        $this->assertSame('contractuels', $staff['0-3']->category->code);
        $this->assertTrue($staff['0-1']->holds(Position::Chef));

        $eq = Equipment::withoutGlobalScopes()->where('institution_id', $this->a->id)->with('service', 'type')->orderBy('id')->get()->keyBy('source_ref');
        $this->assertSame(['pc:1', 'ecran:1', 'pc:2', 'imp:p1', 'imp:p2', 'scan:2'], $eq->keys()->all());
        $uc = $eq['pc:1'];
        $this->assertSame('Ordinateur de bureau', $uc->type->nom);
        $this->assertSame(EquipmentStatus::Affecte, $uc->statut);
        $this->assertSame(EquipmentCondition::Moyen, $uc->etat);
        $this->assertSame('CABINET DU PRESIDENT', $uc->service->nom);
        $this->assertSame('1', $uc->bureau);
        $this->assertSame('2025-01-01', $uc->date_acquisition->toDateString());
        $this->assertStringContainsString('Adresse IP : DHCP', $uc->caracteristiques);
        $this->assertStringContainsString('fiche du personnel 0-1', $uc->observations);
        $this->assertSame(['import', 'affectation'], $uc->movements()->reorder('id')->pluck('type')->all());
        $this->assertSame('EC001', $eq['ecran:1']->numero_serie);
        $this->assertSame('INFORMATIQUE', $eq['pc:2']->service->nom); // libellé corrigé
        $this->assertSame('2025-11-11', $eq['pc:2']->date_acquisition->toDateString());
        $this->assertSame('2025-11-27', $eq['imp:p1']->date_acquisition->toDateString()); // date Excel
        $this->assertNull($eq['imp:p2']->numero_serie);
        $this->assertSame(EquipmentStatus::EnStock, $eq['imp:p2']->statut); // « a quitté le bureau »
        $this->assertSame('Scanner', $eq['scan:2']->type->nom);
        $this->assertSame(6, $eq->pluck('numero_inventaire')->unique()->count());

        $cons = Consumable::withoutGlobalScopes()->where('institution_id', $this->a->id)->pluck('stock', 'reference');
        $this->assertSame(['HP 26A' => 0, 'HP 85A' => 0], $cons->sortKeys()->all());
    }

    public function test_exports_json_then_imports_it_and_dry_run_saves_nothing(): void
    {
        $json = tempnam(sys_get_temp_dir(), 'reprise').'.json';
        $this->artisan('sigaci:reprise-origine', ['sources' => [$this->originalHtml()], '--exporter' => $json])->assertSuccessful();
        $data = json_decode(file_get_contents($json), true);
        $this->assertSame('sigaci-reprise', $data['format']);
        $this->assertCount(3, $data['personnel']);
        $this->assertSame(Equipment::count(), 0);

        $this->artisan('sigaci:reprise-origine', ['sources' => [$json], '--institution' => 'TPI-NEW'])->assertFailed();
        $this->artisan('sigaci:reprise-origine', ['sources' => [$json], '--institution' => 'TPI-NEW', '--creer-institution' => true, '--dry-run' => true])
            ->expectsOutputToContain('Simulation')->assertSuccessful();
        $this->assertSame(0, Equipment::withoutGlobalScopes()->count());
        $this->assertSame(0, Staff::withoutGlobalScopes()->count());
        $this->assertNull(Institution::where('code', 'TPI-NEW')->first());

        $this->artisan('sigaci:reprise-origine', ['sources' => [$json], '--institution' => 'TPI-NEW', '--creer-institution' => true])->assertSuccessful();
        $new = Institution::where('code', 'TPI-NEW')->firstOrFail();
        $this->assertSame('Bouaké', $new->ville);
        $this->assertSame(6, Equipment::withoutGlobalScopes()->where('institution_id', $new->id)->count());
    }
}
