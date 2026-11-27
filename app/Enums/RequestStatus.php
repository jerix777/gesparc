<?php

namespace App\Enums;

enum RequestStatus: string
{
    case EnAttente = 'en_attente';
    case Accordee = 'accordee';
    case Refusee = 'refusee';

    public function label(): string
    {
        return match ($this) {
            self::EnAttente => 'En attente de traitement',
            self::Accordee => 'Accordée',
            self::Refusee => 'Refusée',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnAttente => 'amber',
            self::Accordee => 'green',
            self::Refusee => 'red',
        };
    }
}
