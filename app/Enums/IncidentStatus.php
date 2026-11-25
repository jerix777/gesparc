<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Ouvert = 'ouvert';
    case EnCours = 'en_cours';
    case Resolu = 'resolu';
    case Irreparable = 'irreparable';

    public function label(): string
    {
        return match ($this) {
            self::Ouvert => 'Ouvert',
            self::EnCours => 'En maintenance',
            self::Resolu => 'Résolu',
            self::Irreparable => 'Irréparable',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Ouvert => 'red',
            self::EnCours => 'amber',
            self::Resolu => 'green',
            self::Irreparable => 'grey',
        };
    }

    public function isClosed(): bool
    {
        return in_array($this, [self::Resolu, self::Irreparable], true);
    }
}
