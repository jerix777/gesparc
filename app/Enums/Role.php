<?php

namespace App\Enums;

enum Role: string
{
    case AdminNational = 'admin_national';
    case AdminInstitution = 'admin_institution';
    case Informaticien = 'informaticien';
    case GestionnaireStock = 'gestionnaire_stock';
    case Responsable = 'responsable';
    case Agent = 'agent';
    case Auditeur = 'auditeur';

    public function label(): string
    {
        return match ($this) {
            self::AdminNational => 'Administrateur national',
            self::AdminInstitution => "Administrateur d'institution",
            self::Informaticien => 'Informaticien',
            self::GestionnaireStock => 'Gestionnaire de stock',
            self::Responsable => 'Responsable',
            self::Agent => 'Agent',
            self::Auditeur => 'Auditeur',
        };
    }

    /** Rôles qui voient toutes les institutions. */
    public function isCrossInstitution(): bool
    {
        return in_array($this, [self::AdminNational, self::Auditeur], true);
    }

    /** Rôles qu'un administrateur d'institution peut attribuer. */
    public static function assignableByInstitutionAdmin(): array
    {
        return [self::AdminInstitution, self::Informaticien, self::GestionnaireStock, self::Responsable, self::Agent];
    }
}
