<?php

namespace App\Enums;

/**
 * Fonctions d'autorité ou de gestion attribuées nommément à un agent (plus de déduction
 * à partir du texte de la fonction, comme dans l'application d'origine).
 */
enum Position: string
{
    case Chef = 'chef';                         // Président du Tribunal, chef de l'institution
    case ChefParquet = 'chef_parquet';          // Procureur de la République
    case ChefAdministratif = 'chef_administratif'; // Greffier en Chef titulaire
    case AdjointAdministratif = 'adjoint_administratif'; // Greffier en Chef adjoint (signe « P.O. »)
    case Secretaire = 'secretaire';
    case Archiviste = 'archiviste';
    case GestionnaireFournitures = 'gestionnaire_fournitures';

    public function label(): string
    {
        return match ($this) {
            self::Chef => 'Président du Tribunal',
            self::ChefParquet => 'Procureur de la République',
            self::ChefAdministratif => 'Greffier en Chef',
            self::AdjointAdministratif => 'Greffier en Chef adjoint',
            self::Secretaire => 'Secrétaire',
            self::Archiviste => 'Archiviste',
            self::GestionnaireFournitures => 'Gestionnaire des fournitures',
        };
    }

    /** Fonctions qui ne peuvent avoir qu'un seul titulaire par institution. */
    public function isUnique(): bool
    {
        return in_array($this, [self::Chef, self::ChefParquet, self::ChefAdministratif], true);
    }

    /** Dispensé de demande d'autorisation d'absence. */
    public function exemptFromAbsence(): bool
    {
        return in_array($this, [self::Chef, self::ChefAdministratif], true);
    }

    /** Dispensé de la programmation des vacances judiciaires. */
    public function exemptFromVacation(): bool
    {
        return $this === self::Chef;
    }
}
