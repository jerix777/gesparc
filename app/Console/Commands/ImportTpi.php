<?php

namespace App\Console\Commands;

use App\Enums\EquipmentCondition;
use App\Enums\EquipmentStatus;
use App\Models\Equipment;
use App\Models\EquipmentMovement;
use App\Models\EquipmentType;
use App\Models\Institution;
use App\Models\User;
use App\Services\Numbering;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reprend le module « Parc informatique et accessoires » de l'application TPI Bouaké.
 *
 * Fichier accepté : une sauvegarde JSON de l'application ({"donnees": {"tpi_it": "[…]"}}),
 * un objet {"tpi_it": [...]}, ou directement le tableau des équipements.
 */
class ImportTpi extends Command
{
    protected $signature = 'gesparc:import-tpi {fichier : Chemin du fichier JSON} {--institution= : Code de l\'institution cible} {--dry-run : Afficher sans enregistrer}';

    protected $description = "Importe l'inventaire du module parc informatique de l'application TPI (localStorage)";

    private const STATUTS = [
        'en service' => EquipmentStatus::Affecte,
        'en panne' => EquipmentStatus::EnPanne,
        'en réparation' => EquipmentStatus::EnMaintenance,
        'réformé' => EquipmentStatus::Reforme,
    ];

    public function handle(): int
    {
        $institution = Institution::where('code', strtoupper((string) $this->option('institution')))->first();
        if (! $institution) {
            $this->error('Institution introuvable. Indiquez --institution=CODE (ex. --institution=TPI-BKE).');

            return self::FAILURE;
        }

        $items = $this->readItems($this->argument('fichier'));
        if ($items === null) {
            return self::FAILURE;
        }

        $users = User::where('institution_id', $institution->id)->get();
        $created = 0;
        $skipped = [];

        DB::beginTransaction();
        foreach ($items as $n => $row) {
            $designation = trim((string) ($row['designation'] ?? ''));
            if ($designation === '') {
                $skipped[] = 'ligne '.($n + 1).' : désignation vide';

                continue;
            }
            $serial = trim((string) ($row['numeroSerie'] ?? '')) ?: null;
            if ($serial && Equipment::withoutGlobalScopes()->where('institution_id', $institution->id)->where('numero_serie', $serial)->exists()) {
                $skipped[] = "{$designation} : n° de série {$serial} déjà présent";

                continue;
            }

            $type = EquipmentType::firstOrCreate(['nom' => trim((string) ($row['type'] ?? '')) ?: 'Autre']);
            $affectation = trim((string) ($row['affectation'] ?? ''));
            $statut = self::STATUTS[mb_strtolower(trim((string) ($row['etat'] ?? '')))] ?? EquipmentStatus::EnStock;
            $user = $affectation ? $users->first(fn ($u) => mb_stripos($affectation, $u->name) !== false || mb_stripos($u->name, $affectation) !== false) : null;
            if ($statut === EquipmentStatus::Affecte && ! $affectation) {
                $statut = EquipmentStatus::EnStock;
            }

            $equipment = new Equipment([
                'institution_id' => $institution->id,
                'numero_inventaire' => Numbering::nextInventory($institution->id, $institution->code),
                'equipment_type_id' => $type->id,
                'designation' => $designation,
                'marque' => trim((string) ($row['marque'] ?? '')) ?: null,
                'numero_serie' => $serial,
                'date_acquisition' => $this->date($row['dateAcquisition'] ?? null),
                'statut' => $statut,
                'etat' => $statut === EquipmentStatus::Reforme ? EquipmentCondition::Mauvais : EquipmentCondition::Bon,
                'assigned_user_id' => $user?->id,
                'service_id' => $user?->service_id,
                'bureau' => $user?->bureau,
                'observations' => $affectation ? "Affectation d'origine (TPI) : {$affectation}" : null,
            ]);
            $equipment->save();
            EquipmentMovement::create([
                'institution_id' => $institution->id,
                'equipment_id' => $equipment->id,
                'type' => 'import',
                'statut_apres' => $statut,
                'to_user_id' => $user?->id,
                'motif' => "Reprise depuis l'application TPI".($row['etat'] ?? null ? ' (état : '.$row['etat'].')' : ''),
            ]);
            $created++;
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->info("Simulation : {$created} équipement(s) seraient importés.");
        } else {
            DB::commit();
            $this->info("{$created} équipement(s) importé(s) dans {$institution->nom}.");
        }
        foreach ($skipped as $s) {
            $this->warn('Ignoré — '.$s);
        }

        return self::SUCCESS;
    }

    private function readItems(string $path): ?array
    {
        if (! is_readable($path)) {
            $this->error("Fichier illisible : {$path}");

            return null;
        }
        $json = json_decode(file_get_contents($path), true);
        if (! is_array($json)) {
            $this->error('Le fichier n\'est pas un JSON valide.');

            return null;
        }
        $data = $json['donnees'] ?? $json;
        $items = array_is_list($data) ? $data : ($data['tpi_it'] ?? null);
        if (is_string($items)) {
            $items = json_decode($items, true); // valeur brute du localStorage
        }
        if (! is_array($items)) {
            $this->error('Aucune liste « tpi_it » trouvée dans le fichier.');

            return null;
        }

        return array_values(array_filter($items, 'is_array'));
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, substr((string) $value, 0, 10))->toDateString();
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
