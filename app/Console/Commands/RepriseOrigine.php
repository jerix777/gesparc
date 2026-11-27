<?php

namespace App\Console\Commands;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\Consumable;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\EquipmentType;
use App\Models\Institution;
use App\Models\Service;
use App\Models\Staff;
use App\Services\EquipmentLifecycle;
use App\Services\Numbering;
use App\Services\Personnel\StaffImport;
use App\Services\Reprise\TpiHtmlExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reprise complète des données intégrées aux fichiers HTML de l'application d'origine du TPI de Bouaké :
 * personnel (avec services et bureaux), unités centrales, écrans, imprimantes, scanners et références de toner.
 *
 * Source : un ou plusieurs fichiers HTML de l'application (la version la plus complète de chaque liste est retenue),
 * ou le fichier JSON « sigaci-reprise » produit par --exporter. Ces fichiers contiennent des données personnelles :
 * ne les ajoutez jamais au dépôt.
 *
 * La commande peut être relancée : fiches du personnel et équipements sont retrouvés par leur identifiant
 * d'origine (source_ref) et mis à jour ; le statut et l'affectation d'un équipement déjà repris ne sont plus touchés.
 */
class RepriseOrigine extends Command
{
    protected $signature = 'sigaci:reprise-origine {sources* : Fichier(s) HTML de l\'application d\'origine, ou fichier JSON sigaci-reprise}
        {--institution= : Code de l\'institution cible (ex. TPI-BKE)}
        {--creer-institution : Créer l\'institution décrite dans le fichier si elle n\'existe pas}
        {--exporter= : Écrire les données mises en correspondance dans ce fichier JSON, sans rien importer}
        {--dry-run : Simuler l\'import sans rien enregistrer}';

    protected $description = "Reprend personnel, matériel et consommables des fichiers HTML de l'application d'origine (TPI de Bouaké)";

    public function handle(EquipmentLifecycle $lifecycle): int
    {
        $data = $this->load($this->argument('sources'));
        if ($data === null) {
            return self::FAILURE;
        }

        if ($path = $this->option('exporter')) {
            file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
            $this->info(sprintf('%d agent(s), %d équipement(s), %d consommable(s) écrits dans %s.',
                count($data['personnel']), count($data['equipements']), count($data['consommables']), $path));
            $this->report($data);

            return self::SUCCESS;
        }

        DB::beginTransaction(); // la création éventuelle de l'institution est annulée elle aussi en simulation
        $code = strtoupper((string) ($this->option('institution') ?: ''));
        $institution = $code ? Institution::where('code', $code)->first() : null;
        if (! $institution && $code && $this->option('creer-institution')) {
            $institution = Institution::create(['code' => $code] + array_diff_key($data['institution'] ?? [], ['code' => 1]) + ['nom' => $code]);
            $this->line("Institution {$code} créée.");
        }
        if (! $institution) {
            DB::rollBack();
            $this->error('Institution introuvable. Indiquez --institution=CODE (ex. --institution=TPI-BKE), avec --creer-institution pour la créer.');

            return self::FAILURE;
        }

        try {
            $staffImport = new StaffImport($institution);
            foreach ($data['personnel'] as $row) {
                $staffImport->upsert($row);
            }
            foreach ($data['services'] as $nom) {
                Service::withoutGlobalScopes()->firstOrCreate(['institution_id' => $institution->id, 'nom' => $nom]);
            }
            $eq = $this->equipments($institution, $data['equipements'], $lifecycle);
            $cons = 0;
            foreach ($data['consommables'] as $c) {
                $created = Consumable::withoutGlobalScopes()->firstOrCreate(
                    ['institution_id' => $institution->id, 'reference' => $c['reference']],
                    ['designation' => $c['designation'], 'categorie' => $c['categorie'], 'imprimantes_compatibles' => $c['imprimantes_compatibles'] ?? null, 'stock' => 0],
                );
                $cons += $created->wasRecentlyCreated ? 1 : 0;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->info('Simulation : rien n\'est enregistré.');
        } else {
            DB::commit();
        }
        $s = $staffImport->stats;
        $this->info("Reprise dans {$institution->nom} :");
        $this->line(sprintf('  Personnel : %d fiche(s) créée(s), %d mise(s) à jour, %d fonction(s) d\'autorité attribuée(s).', $s['créées'], $s['mises à jour'], $s['fonctions']));
        $this->line(sprintf('  Équipements : %d créé(s), %d mis à jour.', $eq['créés'], $eq['mis à jour']));
        $this->line(sprintf('  Consommables : %d référence(s) créée(s) (stock à 0, à saisir).', $cons));
        foreach ($staffImport->warnings as $w) {
            $this->warn('Attention — '.$w);
        }
        $this->report($data);

        return self::SUCCESS;
    }

    /** @return array{créés: int, 'mis à jour': int} */
    private function equipments(Institution $institution, array $rows, EquipmentLifecycle $lifecycle): array
    {
        $stats = ['créés' => 0, 'mis à jour' => 0];
        $types = [];
        $services = Service::withoutGlobalScopes()->where('institution_id', $institution->id)->pluck('id', 'nom');
        $staff = Staff::withoutGlobalScopes()->where('institution_id', $institution->id)->whereNotNull('source_ref')->with('user')->get()->keyBy('source_ref');

        foreach ($rows as $row) {
            $types[$row['type']] ??= EquipmentType::firstOrCreate(['nom' => $row['type']])->id;
            $descriptive = [
                'equipment_type_id' => $types[$row['type']],
                'designation' => $row['designation'],
                'marque' => $row['marque'] ?? null,
                'modele' => $row['modele'] ?? null,
                'numero_serie' => $row['numero_serie'] ?? null,
                'caracteristiques' => $row['caracteristiques'] ?? null,
                'date_acquisition' => $row['date_acquisition'] ?? null,
                'observations' => $row['observations'] ?? null,
            ];
            $equipment = Equipment::withoutGlobalScopes()->where('institution_id', $institution->id)->where('source_ref', $row['source_ref'])->first();
            if ($equipment) {
                // Déjà repris : on actualise la description, pas le statut ni l'affectation (gérés dans SIGACI).
                $equipment->fill($descriptive)->save();
                $stats['mis à jour']++;

                continue;
            }

            $equipment = Equipment::create($descriptive + [
                'institution_id' => $institution->id,
                'source_ref' => $row['source_ref'],
                'numero_inventaire' => Numbering::nextInventory($institution->id, $institution->code),
                'statut' => EquipmentStatus::EnStock,
                'etat' => EquipmentCondition::tryFrom($row['etat'] ?? '') ?? EquipmentCondition::Bon,
            ]);
            EquipmentMovement::create([
                'institution_id' => $institution->id,
                'equipment_id' => $equipment->id,
                'type' => 'import',
                'statut_apres' => EquipmentStatus::EnStock,
                'motif' => "Reprise de l'inventaire de l'application d'origine",
            ]);
            $serviceId = ($row['service'] ?? null) ? ($services[$row['service']] ?? null) : null;
            $bureau = $row['bureau'] ?? null;
            $user = ($row['staff_ref'] ?? null) ? $staff->get($row['staff_ref'])?->user : null;
            if (($row['statut'] ?? null) === EquipmentStatus::Affecte->value && ($user || $serviceId || $bureau)) {
                $lifecycle->assign($equipment, $user, $serviceId, $bureau, "Affectation d'origine (inventaire de l'application TPI)");
            }
            $stats['créés']++;
        }

        return $stats;
    }

    private function load(array $paths): ?array
    {
        $html = [];
        foreach ($paths as $path) {
            if (! is_readable($path)) {
                $this->error("Fichier illisible : {$path}");

                return null;
            }
            $head = ltrim((string) file_get_contents($path, false, null, 0, 512));
            if (str_starts_with($head, '{')) {
                $json = json_decode((string) file_get_contents($path), true);
                if (($json['format'] ?? null) !== TpiHtmlExtractor::FORMAT) {
                    $this->error("{$path} n'est pas un fichier « ".TpiHtmlExtractor::FORMAT.' » (produit par --exporter).');

                    return null;
                }

                return $json;
            }
            $html[] = $path;
        }
        $data = (new TpiHtmlExtractor)->extract($html);
        if (! $data['personnel'] && ! $data['equipements']) {
            $this->error('Aucune donnée de l\'application d\'origine trouvée dans ces fichiers (listes initialPeople et Pack informatique).');

            return null;
        }

        return $data;
    }

    private function report(array $data): void
    {
        foreach ($data['non_repris'] ?? [] as $n) {
            $this->warn("Non repris — {$n['source']} {$n['ref']} : {$n['motif']}");
        }
        foreach ($data['alertes'] ?? [] as $a) {
            $this->warn('À vérifier — '.$a);
        }
    }
}
