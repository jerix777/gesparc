<?php

namespace App\Console\Commands;

use App\Enums\Position;
use App\Models\Institution;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\StaffPosition;
use App\Services\Personnel\PersonnelSetup;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reprend les fiches du personnel de l'application « Gestion du personnel – TPI Bouaké ».
 *
 * Fichiers acceptés : le fichier HTML de l'application (liste « initialPeople ») et/ou une sauvegarde
 * JSON contenant « tpi_people » (version à jour, avec services, bureaux et dates de prise de fonction).
 * Ces fichiers contiennent des données personnelles : ils ne doivent jamais être ajoutés au dépôt.
 * La commande peut être relancée : une fiche déjà importée est mise à jour, pas dupliquée.
 */
class ImportPersonnel extends Command
{
    protected $signature = 'gesparc:import-personnel {fichiers* : Fichier HTML de l\'application et/ou sauvegarde JSON}
        {--institution= : Code de l\'institution cible (ex. TPI-BKE)} {--dry-run : Afficher sans enregistrer}';

    protected $description = "Importe les fiches du personnel de l'application TPI (Gestion du personnel)";

    private const CATEGORIES = [
        'magistrats du siege' => 'magistrats_siege',
        'magistrats du parquet' => 'magistrats_parquet',
        'personnel des greffes' => 'greffes',
        'personnel interministeriel' => 'interministeriel',
        'contractuels du tribunal' => 'contractuels',
        'contractuels' => 'contractuels',
    ];

    public function handle(): int
    {
        $institution = Institution::where('code', strtoupper((string) $this->option('institution')))->first();
        if (! $institution) {
            $this->error('Institution introuvable. Indiquez --institution=CODE (ex. --institution=TPI-BKE).');

            return self::FAILURE;
        }

        $people = [];
        foreach ($this->argument('fichiers') as $path) {
            $rows = $this->read($path);
            if ($rows === null) {
                return self::FAILURE;
            }
            foreach ($rows as $row) { // la sauvegarde JSON, plus récente, complète la liste du HTML
                $key = (string) ($row['id'] ?? Str::uuid());
                $people[$key] = array_merge($people[$key] ?? [], array_filter($row, fn ($v) => $v !== null && $v !== ''));
            }
        }

        PersonnelSetup::applyJuridiction($institution);
        $categories = StaffCategory::withoutGlobalScopes()->where('institution_id', $institution->id)->pluck('id', 'code');
        $stats = ['créées' => 0, 'mises à jour' => 0, 'fonctions' => 0];
        $warnings = [];

        DB::beginTransaction();
        foreach ($people as $ref => $p) {
            $nom = trim((string) ($p['nom'] ?? ''));
            if ($nom === '') {
                $warnings[] = "fiche {$ref} ignorée : nom vide";

                continue;
            }
            $catCode = self::CATEGORIES[Str::of($p['categorie'] ?? '')->ascii()->lower()->trim()->value()] ?? null;
            if (! $catCode) {
                $warnings[] = "{$nom} : catégorie « ".($p['categorie'] ?? '').' » inconnue, à renseigner';
            }
            $serviceName = $this->serviceName($p);
            $service = $serviceName ? Service::withoutGlobalScopes()->firstOrCreate(['institution_id' => $institution->id, 'nom' => $serviceName]) : null;
            $sorti = mb_strtolower((string) ($p['statut'] ?? 'Actif')) !== 'actif';

            $staff = Staff::withoutGlobalScopes()->firstOrNew(['institution_id' => $institution->id, 'source_ref' => $ref]);
            $stats[$staff->exists ? 'mises à jour' : 'créées']++;
            $staff->fill([
                'staff_category_id' => $catCode ? $categories[$catCode] : null,
                'service_id' => $service?->id,
                'matricule' => trim((string) ($p['matricule'] ?? '')) ?: null,
                'nom' => $nom,
                'prenoms' => trim((string) ($p['prenoms'] ?? '')) ?: null,
                'fonction' => trim((string) ($p['fonction'] ?? '')) ?: null,
                'telephone' => trim((string) ($p['telephone'] ?? '')) ?: null,
                'email' => filter_var(trim((string) ($p['email'] ?? '')), FILTER_VALIDATE_EMAIL) ?: null,
                'bureau' => trim((string) ($p['numeroPorte'] ?? '')) ?: null,
                'date_prise_service' => $this->date($p['datePriseFonction'] ?? null),
                'statut' => $sorti ? 'sorti' : 'actif',
                'date_sortie' => $sorti ? $this->date($p['dateSortie'] ?? null) : null,
                'motif_sortie' => $sorti ? (trim((string) ($p['motifSortie'] ?? '')) ?: null) : null,
            ])->save();

            foreach ($this->positionsFor($p) as $position) {
                if ($position->isUnique() && StaffPosition::withoutGlobalScopes()->where('institution_id', $institution->id)->where('position', $position)->where('staff_id', '!=', $staff->id)->exists()) {
                    $warnings[] = "{$nom} : la fonction « {$position->label()} » a déjà un titulaire, à vérifier";

                    continue;
                }
                $pos = StaffPosition::withoutGlobalScopes()->firstOrCreate(['staff_id' => $staff->id, 'position' => $position], ['institution_id' => $institution->id]);
                $stats['fonctions'] += $pos->wasRecentlyCreated ? 1 : 0;
            }
        }

        if ($this->option('dry-run')) {
            DB::rollBack();
            $this->info('Simulation, rien n\'est enregistré.');
        } else {
            DB::commit();
        }
        $this->info(sprintf('%d fiche(s) créée(s), %d mise(s) à jour, %d fonction(s) d\'autorité attribuée(s) dans %s.',
            $stats['créées'], $stats['mises à jour'], $stats['fonctions'], $institution->nom));
        $this->line('Vérifiez les fonctions d\'autorité dans Personnel > Paramétrage.');
        foreach ($warnings as $w) {
            $this->warn('Attention — '.$w);
        }

        return self::SUCCESS;
    }

    /** Fonctions d'autorité déduites une seule fois, à l'import ; elles se règlent ensuite à la main. */
    private function positionsFor(array $p): array
    {
        $f = trim((string) ($p['fonction'] ?? ''));
        $out = [];
        if (preg_match('/^président du tribunal$/iu', $f)) {
            $out[] = Position::Chef;
        }
        if (preg_match('/^procureur de la république$/iu', $f)) {
            $out[] = Position::ChefParquet;
        }
        if (preg_match('/^greffier en chef$/iu', $f)) {
            $out[] = Position::ChefAdministratif;
        }
        if (preg_match('/^greffier en chef adjoint$/iu', $f)) {
            $out[] = Position::AdjointAdministratif;
        }
        // « Secrétaire des greffes et parquets » est un grade des greffes, pas un poste de secrétariat.
        if (preg_match('/secr[eé]taire/iu', $f) && ! preg_match('/greffes/iu', $f)) {
            $out[] = Position::Secretaire;
        }
        if (preg_match('/archiv/iu', $f) || ($p['serviceAffectation'] ?? '') === 'Archives') {
            $out[] = Position::Archiviste;
        }

        return $out;
    }

    private function serviceName(array $p): ?string
    {
        $s = trim((string) ($p['serviceAffectation'] ?? ''));
        if ($s === 'Autres services') {
            $s = trim((string) ($p['serviceAutre'] ?? ''));
        }

        return $s !== '' ? Str::limit($s, 120, '') : null;
    }

    private function read(string $path): ?array
    {
        if (! is_readable($path)) {
            $this->error("Fichier illisible : {$path}");

            return null;
        }
        $content = file_get_contents($path);

        if (preg_match('/const initialPeople\s*=\s*(\[.*?\]);/s', $content, $m)) {
            $rows = json_decode($m[1], true);
        } else {
            $json = json_decode($content, true);
            $data = is_array($json) ? ($json['donnees'] ?? $json) : null;
            $rows = is_array($data) ? (array_is_list($data) ? $data : ($data['tpi_people'] ?? null)) : null;
            if (is_string($rows)) {
                $rows = json_decode($rows, true); // valeur brute du localStorage
            }
        }
        if (! is_array($rows)) {
            $this->error("Aucune liste du personnel trouvée dans {$path} (attendu : fichier HTML de l'application ou sauvegarde avec « tpi_people »).");

            return null;
        }

        return array_values(array_filter($rows, 'is_array'));
    }

    private function date(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat('!'.$format, substr((string) $value, 0, 10))->toDateString();
            } catch (\Throwable) {
            }
        }

        return null;
    }
}
