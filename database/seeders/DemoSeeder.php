<?php

namespace Database\Seeders;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Enums\Role;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\EquipmentType;
use App\Models\Incident;
use App\Models\Institution;
use App\Models\MaterialRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration. Ne pas lancer en production.
 * Tous les comptes ont le mot de passe « demo2026 ».
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $pwd = 'demo2026';

        User::create(['name' => 'Administrateur national', 'email' => 'national@gesparc.test', 'password' => $pwd, 'role' => Role::AdminNational]);
        User::create(['name' => 'Inspection générale', 'email' => 'audit@gesparc.test', 'password' => $pwd, 'role' => Role::Auditeur]);

        $bke = Institution::create(['code' => 'TPI-BKE', 'nom' => 'Tribunal de première instance de Bouaké', 'categorie' => 'Juridiction', 'ville' => 'Bouaké']);
        $abj = Institution::create(['code' => 'TPI-ABJ', 'nom' => "Tribunal de première instance d'Abidjan-Plateau", 'categorie' => 'Juridiction', 'ville' => 'Abidjan']);

        foreach ([$bke, $abj] as $inst) {
            $slug = strtolower(str_replace('TPI-', '', $inst->code));
            $services = collect(['Présidence', 'Parquet', 'Greffe civil', 'Greffe correctionnel', 'Service informatique'])
                ->mapWithKeys(fn ($n) => [$n => Service::create(['institution_id' => $inst->id, 'nom' => $n])]);

            $mk = fn ($name, $email, Role $role, $service, $fonction, $bureau) => User::create([
                'institution_id' => $inst->id, 'service_id' => $services[$service]->id, 'name' => $name,
                'email' => "{$email}.{$slug}@gesparc.test", 'password' => $pwd, 'role' => $role,
                'fonction' => $fonction, 'bureau' => $bureau,
            ]);

            $mk('Admin '.$inst->ville, 'admin', Role::AdminInstitution, 'Présidence', 'Secrétaire général', '1');
            $info = $mk('Koné Ibrahim', 'info', Role::Informaticien, 'Service informatique', 'Informaticien', '12');
            $mk('N’Guessan Aya', 'stock', Role::GestionnaireStock, 'Service informatique', 'Secrétaire', '13');
            $mk('Yao Kouassi', 'president', Role::Responsable, 'Présidence', 'Président du tribunal', '2');
            $agents = collect([
                $mk('Traoré Mariam', 'agent', Role::Agent, 'Greffe civil', 'Greffière', '21'),
                $mk('Bamba Sékou', 'bamba', Role::Agent, 'Parquet', 'Substitut du procureur', '8'),
                $mk('Kouadio Ange', 'kouadio', Role::Agent, 'Greffe correctionnel', 'Greffier', '24'),
            ]);

            $types = EquipmentType::pluck('id', 'nom');
            $catalogue = [
                ['Ordinateur de bureau', 'HP', 'ProDesk 400 G7', 2019, 450000],
                ['Ordinateur de bureau', 'Dell', 'OptiPlex 3080', 2021, 520000],
                ['Ordinateur portable', 'Lenovo', 'ThinkPad E14', 2023, 610000],
                ['Imprimante', 'HP', 'LaserJet Pro M404dn', 2020, 280000],
                ['Imprimante', 'Canon', 'i-SENSYS MF445dw', 2018, 310000],
                ['Onduleur', 'APC', 'Back-UPS 1200', 2022, 95000],
                ['Écran', 'Samsung', 'S24R350', 2021, 85000],
                ['Scanner', 'Epson', 'WorkForce DS-530', 2017, 260000],
            ];
            $i = 0;
            foreach ($catalogue as [$type, $marque, $modele, $year, $prix]) {
                foreach ([0, 1] as $k) {
                    $i++;
                    $agent = $k === 0 ? $agents[$i % 3] : null;
                    $statut = $agent ? EquipmentStatus::Affecte : EquipmentStatus::EnStock;
                    if ($i === 5) {
                        $statut = EquipmentStatus::EnPanne;
                    }
                    if ($i === 15) {
                        $statut = EquipmentStatus::Reforme;
                        $agent = null;
                    }
                    $e = Equipment::create([
                        'institution_id' => $inst->id,
                        'numero_inventaire' => sprintf('%s-%05d', $inst->code, $i),
                        'equipment_type_id' => $types[$type],
                        'designation' => "$type $marque",
                        'marque' => $marque, 'modele' => $modele,
                        'numero_serie' => strtoupper(substr($marque, 0, 2)).$year.str_pad((string) ($i * 37 + $inst->id), 6, '0', STR_PAD_LEFT),
                        'date_acquisition' => "$year-03-15",
                        'prix_achat' => $prix,
                        'fin_garantie' => ($year + 3).'-03-15',
                        'fournisseur' => 'Fournisseur agréé',
                        'statut' => $statut,
                        'etat' => $statut === EquipmentStatus::Reforme ? EquipmentCondition::Mauvais : EquipmentCondition::Bon,
                        'assigned_user_id' => $agent?->id,
                        'service_id' => $agent?->service_id ?? $services['Service informatique']->id,
                        'bureau' => $agent?->bureau ?? '12',
                    ]);
                    EquipmentMovement::create(['institution_id' => $inst->id, 'equipment_id' => $e->id, 'type' => 'reception', 'statut_apres' => EquipmentStatus::EnStock, 'author_id' => $info->id]);
                    if ($statut === EquipmentStatus::EnPanne) {
                        Incident::create(['institution_id' => $inst->id, 'numero' => 'PAN-'.now()->year.'-001', 'equipment_id' => $e->id, 'reported_by' => $agent?->id, 'description' => "L'appareil ne s'allume plus."]);
                    }
                }
            }

            MaterialRequest::create(['institution_id' => $inst->id, 'numero' => 'DEM-'.now()->year.'-001', 'requester_id' => $agents[0]->id, 'materiel' => 'Souris', 'quantite' => 1, 'bureau' => $agents[0]->bureau]);
            MaterialRequest::create(['institution_id' => $inst->id, 'numero' => 'DEM-'.now()->year.'-002', 'requester_id' => $agents[1]->id, 'categorie' => 'cartouche', 'materiel' => 'Cartouche d’encre', 'quantite' => 2, 'caracteristiques' => 'HP 59A', 'bureau' => $agents[1]->bureau]);
        }
    }
}
