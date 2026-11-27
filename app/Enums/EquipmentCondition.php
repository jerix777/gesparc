<?php

namespace App\Enums;

enum EquipmentCondition: string
{
    case Neuf = 'neuf';
    case Bon = 'bon';
    case Moyen = 'moyen';
    case Mauvais = 'mauvais';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
