<?php

namespace App\Enums;

enum EquipmentStatus: string
{
    case EnStock = 'en_stock';
    case Affecte = 'affecte';
    case EnPanne = 'en_panne';
    case EnMaintenance = 'en_maintenance';
    case Reforme = 'reforme';

    public function label(): string
    {
        return match ($this) {
            self::EnStock => 'En stock',
            self::Affecte => 'Affecté',
            self::EnPanne => 'En panne',
            self::EnMaintenance => 'En maintenance',
            self::Reforme => 'Réformé',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::EnStock => 'blue',
            self::Affecte => 'green',
            self::EnPanne => 'red',
            self::EnMaintenance => 'amber',
            self::Reforme => 'grey',
        };
    }
}
