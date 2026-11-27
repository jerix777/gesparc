<?php

namespace App\Services\Personnel;

use App\Enums\Position;
use App\Models\Institution;
use App\Models\Service;
use App\Models\Staff;
use App\Models\StaffCategory;
use App\Models\StaffPosition;
use Illuminate\Support\Str;

/**
 * Création ou mise à jour d'une fiche du personnel reprise d'une application d'origine.
 * Utilisé par « sigaci:import-personnel » et « sigaci:reprise-origine ».
 *
 * La fiche est retrouvée par son identifiant d'origine (source_ref) : relancer une reprise
 * met la fiche à jour au lieu de la dupliquer.
 */
class StaffImport
{
    /** Libellés de catégorie de l'application TPI => code de catégorie SIGACI. */
    public const CATEGORIES = [
        'magistrats du siege' => 'magistrats_siege',
        'magistrats du parquet' => 'magistrats_parquet',
        'personnel des greffes' => 'greffes',
        'personnel interministeriel' => 'interministeriel',
        'contractuels du tribunal' => 'contractuels',
        'contractuels' => 'contractuels',
    ];

    /** @var array<string, int> code de catégorie => id */
    private array $categories;

    /** @var list<string> */
    public array $warnings = [];

    /** @var array{créées: int, 'mises à jour': int, fonctions: int} */
    public array $stats = ['créées' => 0, 'mises à jour' => 0, 'fonctions' => 0];

    public function __construct(private Institution $institution)
    {
        PersonnelSetup::applyJuridiction($institution);
        $this->categories = StaffCategory::withoutGlobalScopes()->where('institution_id', $institution->id)->pluck('id', 'code')->all();
    }

    public static function categoryCode(?string $label): ?string
    {
        $key = Str::of((string) $label)->ascii()->lower()->trim()->value();

        return self::CATEGORIES[$key] ?? (in_array($key, self::CATEGORIES, true) ? $key : null);
    }

    /**
     * @param  array{source_ref: string, nom: string, prenoms?: ?string, categorie?: ?string, service?: ?string,
     *     matricule?: ?string, fonction?: ?string, telephone?: ?string, email?: ?string, bureau?: ?string,
     *     date_prise_service?: ?string, statut?: ?string, date_sortie?: ?string, motif_sortie?: ?string}  $row
     *     « categorie » est un code SIGACI (ou un libellé de l'application TPI), « service » un nom de service.
     */
    public function upsert(array $row): ?Staff
    {
        $nom = trim((string) ($row['nom'] ?? ''));
        $ref = (string) ($row['source_ref'] ?? '');
        if ($nom === '' || $ref === '') {
            $this->warnings[] = 'fiche '.($ref ?: '?').' ignorée : nom ou identifiant d\'origine vide';

            return null;
        }
        $catCode = self::categoryCode($row['categorie'] ?? null);
        if (! $catCode) {
            $this->warnings[] = "{$nom} : catégorie « ".($row['categorie'] ?? '').' » inconnue, à renseigner';
        }
        $serviceName = Str::limit(trim((string) ($row['service'] ?? '')), 120, '');
        $service = $serviceName !== '' ? Service::withoutGlobalScopes()->firstOrCreate(['institution_id' => $this->institution->id, 'nom' => $serviceName]) : null;
        $sorti = ($row['statut'] ?? 'actif') === 'sorti';
        $text = fn (string $k) => trim((string) ($row[$k] ?? '')) ?: null;

        $staff = Staff::withoutGlobalScopes()->firstOrNew(['institution_id' => $this->institution->id, 'source_ref' => $ref]);
        $this->stats[$staff->exists ? 'mises à jour' : 'créées']++;
        $staff->fill([
            'staff_category_id' => $catCode ? $this->categories[$catCode] : null,
            'service_id' => $service?->id,
            'matricule' => $text('matricule'),
            'nom' => $nom,
            'prenoms' => $text('prenoms'),
            'fonction' => $text('fonction'),
            'telephone' => $text('telephone'),
            'email' => filter_var((string) $text('email'), FILTER_VALIDATE_EMAIL) ?: null,
            'bureau' => $text('bureau'),
            'date_prise_service' => $row['date_prise_service'] ?? null,
            'statut' => $sorti ? 'sorti' : 'actif',
            'date_sortie' => $sorti ? ($row['date_sortie'] ?? null) : null,
            'motif_sortie' => $sorti ? $text('motif_sortie') : null,
        ])->save();

        foreach (self::positionsFor((string) $staff->fonction, $serviceName) as $position) {
            $taken = $position->isUnique() && StaffPosition::withoutGlobalScopes()->where('institution_id', $this->institution->id)
                ->where('position', $position)->where('staff_id', '!=', $staff->id)->exists();
            if ($taken) {
                $this->warnings[] = "{$nom} : la fonction « {$position->label()} » a déjà un titulaire, à vérifier";

                continue;
            }
            $pos = StaffPosition::withoutGlobalScopes()->firstOrCreate(['staff_id' => $staff->id, 'position' => $position], ['institution_id' => $this->institution->id]);
            $this->stats['fonctions'] += $pos->wasRecentlyCreated ? 1 : 0;
        }

        return $staff;
    }

    /** Fonctions d'autorité déduites une seule fois, à l'import ; elles se règlent ensuite à la main. */
    public static function positionsFor(string $fonction, ?string $service = null): array
    {
        $f = trim($fonction);
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
        if (preg_match('/archiv/iu', $f) || $service === 'Archives') {
            $out[] = Position::Archiviste;
        }

        return $out;
    }
}
