<?php

namespace App\Enums;

enum VehicleType: string
{
    case Passenger = 'passenger';
    case Van = 'van';
    case Bus = 'bus';
    case TowTruck = 'tow_truck';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Passenger => 'Osobowy',
            self::Van => 'Dostawczy',
            self::Bus => 'Autobus',
            self::TowTruck => 'Laweta',
        };
    }
}
