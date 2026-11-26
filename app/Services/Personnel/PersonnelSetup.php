<?php

namespace App\Services\Personnel;

use App\Enums\Position as P;
use App\Models\ApprovalRule;
use App\Models\Institution;
use App\Models\StaffCategory;
use Illuminate\Support\Facades\DB;

/**
 * Paramétrage du module Personnel. Le modèle « juridiction » reprend les règles du TPI de Bouaké ;
 * une autre institution peut ensuite modifier catégories et règles depuis l'administration.
 */
class PersonnelSetup
{
    /** code => [nom, magistrat, [kind => [1er niveau, niveau final, copie]]] */
    public const JURIDICTION = [
        'magistrats_siege' => ['Magistrats du siège', true, [
            'absence' => [[P::Chef], null, []],
            'vacances' => [[], P::Chef, []],
            'attestation' => [[P::Chef], null, []],
        ]],
        'magistrats_parquet' => ['Magistrats du parquet', true, [
            'absence' => [[P::ChefParquet], null, [P::Chef]],
            'vacances' => [[P::ChefParquet], P::Chef, []],
            'attestation' => [[P::ChefParquet], null, []],
        ]],
        'greffes' => ['Personnel des greffes', false, [
            'absence' => [[P::ChefAdministratif], null, []],
            'vacances' => [[P::ChefAdministratif], P::Chef, []],
            'attestation' => [[P::ChefAdministratif], null, []],
        ]],
        'interministeriel' => ['Personnel interministériel', false, [
            'absence' => [[P::ChefAdministratif, P::AdjointAdministratif], null, []],
            'vacances' => [[P::ChefAdministratif, P::AdjointAdministratif], P::Chef, []],
            'attestation' => [[P::Chef], null, []],
        ]],
        'contractuels' => ['Contractuels', false, [
            'absence' => [[P::ChefAdministratif, P::AdjointAdministratif], null, []],
            'vacances' => [[P::ChefAdministratif, P::AdjointAdministratif], P::Chef, []],
            'attestation' => [[P::Chef], null, []],
        ]],
    ];

    /** Crée (ou complète) catégories et règles ; ne modifie pas une règle déjà réglée. */
    public static function applyJuridiction(Institution $institution): void
    {
        DB::transaction(function () use ($institution) {
            $ordre = 0;
            foreach (self::JURIDICTION as $code => [$nom, $magistrat, $rules]) {
                $category = StaffCategory::withoutGlobalScopes()->firstOrCreate(
                    ['institution_id' => $institution->id, 'code' => $code],
                    ['nom' => $nom, 'magistrat' => $magistrat, 'ordre' => ++$ordre],
                );
                foreach ($rules as $kind => [$first, $final, $copy]) {
                    ApprovalRule::withoutGlobalScopes()->firstOrCreate(
                        ['staff_category_id' => $category->id, 'kind' => $kind],
                        [
                            'institution_id' => $institution->id,
                            'first_level' => array_map(fn (P $p) => $p->value, $first),
                            'final_level' => $final,
                            'copy_to' => array_map(fn (P $p) => $p->value, $copy),
                        ],
                    );
                }
            }
        });
    }
}
