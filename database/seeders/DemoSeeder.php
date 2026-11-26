<?php

namespace Database\Seeders;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Enums\Position;
use App\Enums\Role;
use App\Models\Consumable;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\EquipmentType;
use App\Models\Incident;
use App\Models\Institution;
use App\Models\MaterialRequest;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\VacationCampaign;
use App\Services\Personnel\LeaveService;
use App\Services\Personnel\PersonnelSetup;
use App\Services\Personnel\VacationService;
use App\Services\Personnel\WorkingDays;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

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

        WorkingDays::seed(now()->year);
        WorkingDays::seed(now()->year + 1);

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

            foreach ([['HP 59A', 'Toner noir HP 59A', 'toner', 'HP LaserJet Pro M404dn', 6, 2], ['Canon 057', 'Toner noir Canon 057', 'toner', 'Canon i-SENSYS MF445dw', 1, 2], ['HP 305', 'Cartouche couleur HP 305', 'cartouche', 'HP DeskJet 2710', 4, 2]] as [$ref, $des, $cat, $compat, $qte, $seuil]) {
                Consumable::create(['institution_id' => $inst->id, 'reference' => $ref, 'designation' => $des, 'categorie' => $cat, 'imprimantes_compatibles' => $compat, 'stock' => $qte, 'seuil_alerte' => $seuil]);
            }

            // Module Personnel : catégories et règles d'une juridiction, fiches fictives, fonctions d'autorité.
            PersonnelSetup::applyJuridiction($inst);
            $cat = StaffCategory::withoutGlobalScopes()->where('institution_id', $inst->id)->pluck('id', 'code');
            $gc = $mk('Ouattara Fanta', 'greffier', Role::Agent, 'Greffe civil', 'Greffier en Chef', '5');
            $proc = $mk('Diabaté Moussa', 'procureur', Role::Agent, 'Parquet', 'Procureur de la République', '7');
            $n = 0;
            $fiche = function (?User $u, string $nom, string $cat_, string $service, string $fonction, array $positions = []) use ($inst, $cat, $services, $slug, &$n) {
                $n++;
                $staff = Staff::withoutGlobalScopes()->create([
                    'institution_id' => $inst->id, 'user_id' => $u?->id, 'staff_category_id' => $cat[$cat_],
                    'service_id' => $services[$service]->id, 'matricule' => strtoupper($slug).'-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT),
                    'nom' => strtok($nom, ' '), 'prenoms' => trim(strstr($nom, ' ')), 'fonction' => $fonction, 'bureau' => $u?->bureau,
                    'date_prise_service' => now()->subYears(1 + $n % 6)->startOfMonth(),
                ]);
                $u?->update(['matricule' => $staff->matricule]);
                foreach ($positions as $p) {
                    StaffPosition::withoutGlobalScopes()->create(['institution_id' => $inst->id, 'staff_id' => $staff->id, 'position' => $p]);
                }
            };
            $users = User::where('institution_id', $inst->id)->get()->keyBy('fonction');
            $fiche($users['Président du tribunal'], 'Yao Kouassi', 'magistrats_siege', 'Présidence', 'Président du tribunal', [Position::Chef]);
            $fiche($proc, 'Diabaté Moussa', 'magistrats_parquet', 'Parquet', 'Procureur de la République', [Position::ChefParquet]);
            $fiche($gc, 'Ouattara Fanta', 'greffes', 'Greffe civil', 'Greffier en Chef', [Position::ChefAdministratif]);
            $fiche($agents[0], 'Traoré Mariam', 'greffes', 'Greffe civil', 'Greffière');
            $fiche($agents[1], 'Bamba Sékou', 'magistrats_parquet', 'Parquet', 'Substitut du procureur');
            $fiche($agents[2], 'Kouadio Ange', 'greffes', 'Greffe correctionnel', 'Greffier');
            $fiche($info, 'Koné Ibrahim', 'interministeriel', 'Service informatique', 'Informaticien');
            $fiche($users['Secrétaire'], 'N’Guessan Aya', 'interministeriel', 'Service informatique', 'Secrétaire', [Position::Secretaire]);
            $fiche(null, 'Kacou Brou Serge', 'greffes', 'Greffe correctionnel', 'Greffier en Chef adjoint', [Position::AdjointAdministratif]);
            $fiche(null, 'Coulibaly Awa', 'contractuels', 'Greffe civil', 'Agent de saisie');
            $fiche(null, 'Gnagne Adjoua', 'magistrats_siege', 'Présidence', 'Juge');

            // Absences et vacances judiciaires de démonstration (passent par les services, comme dans l'application).
            $byName = fn ($nom) => Staff::withoutGlobalScopes()->with('positions', 'category.rules', 'service', 'user')->where('institution_id', $inst->id)->where('nom', $nom)->first();
            $monday = now()->next('Monday');
            Auth::login($agents[0]);
            app(LeaveService::class)->submit($byName('Traoré'), ['motif' => 'Consultation ou rendez-vous médical', 'debut' => $monday, 'fin' => $monday->copy()->addDay()], $agents[0]);
            Auth::login($agents[2]);
            $l = app(LeaveService::class)->submit($byName('Kouadio'), ['motif' => 'Formation ou stage', 'debut' => $monday->copy()->addWeek(), 'fin' => $monday->copy()->addWeek()->addDays(2)], $agents[2]);
            Auth::login($gc);
            app(LeaveService::class)->decide($l, $gc, true);
            $campaign = VacationCampaign::withoutGlobalScopes()->create(['institution_id' => $inst->id, 'annee' => now()->year + 1, 'mois' => VacationCampaign::MOIS, 'statut' => 'ouverte']);
            foreach ([[$agents[0], 'Traoré', 'Août'], [$agents[2], 'Kouadio', 'Septembre'], [$info, 'Koné', 'Août']] as [$u, $nom, $mois]) {
                Auth::login($u);
                $req = app(VacationService::class)->request($byName($nom), $campaign, $mois, $u);
                if ($nom === 'Kouadio') {
                    Auth::login($gc);
                    app(VacationService::class)->decide($req, $gc->fresh(), true, $mois, null);
                }
            }
            Auth::logout();

            MaterialRequest::create(['institution_id' => $inst->id, 'numero' => 'DEM-'.now()->year.'-001', 'requester_id' => $agents[0]->id, 'materiel' => 'Souris', 'quantite' => 1, 'bureau' => $agents[0]->bureau]);
            MaterialRequest::create(['institution_id' => $inst->id, 'numero' => 'DEM-'.now()->year.'-002', 'requester_id' => $agents[1]->id, 'categorie' => 'cartouche', 'materiel' => 'Cartouche d’encre', 'quantite' => 2, 'caracteristiques' => 'HP 59A', 'bureau' => $agents[1]->bureau]);
        }
    }
}
