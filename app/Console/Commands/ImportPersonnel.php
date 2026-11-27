<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Services\Personnel\StaffImport;
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
    protected $signature = 'sigaci:import-personnel {fichiers* : Fichier HTML de l\'application et/ou sauvegarde JSON}
        {--institution= : Code de l\'institution cible (ex. TPI-BKE)} {--dry-run : Afficher sans enregistrer}';

    protected $description = "Importe les fiches du personnel de l'application TPI (Gestion du personnel)";

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

        DB::beginTransaction();
        $import = new StaffImport($institution);
        foreach ($people as $ref => $p) {
            $sorti = mb_strtolower((string) ($p['statut'] ?? 'Actif')) !== 'actif';
            $import->upsert([
                'source_ref' => (string) $ref,
                'nom' => (string) ($p['nom'] ?? ''),
                'prenoms' => $p['prenoms'] ?? null,
                'categorie' => StaffImport::categoryCode($p['categorie'] ?? null) ?? ($p['categorie'] ?? null),
                'service' => $this->serviceName($p),
                'matricule' => $p['matricule'] ?? null,
                'fonction' => $p['fonction'] ?? null,
                'telephone' => $p['telephone'] ?? null,
                'email' => $p['email'] ?? null,
                'bureau' => $p['numeroPorte'] ?? null,
                'date_prise_service' => $this->date($p['datePriseFonction'] ?? null),
                'statut' => $sorti ? 'sorti' : 'actif',
                'date_sortie' => $sorti ? $this->date($p['dateSortie'] ?? null) : null,
                'motif_sortie' => $p['motifSortie'] ?? null,
            ]);
        }
        $stats = $import->stats;
        $warnings = $import->warnings;

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
