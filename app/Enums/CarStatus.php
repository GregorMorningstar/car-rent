<?php

namespace App\Enums;

enum CarStatus: string
{
    case Active = 'active';
    case Maintenance = 'maintenance';
    case Retired = 'retired';

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
            self::Active => 'Aktywny',
            self::Maintenance => 'W serwisie',
            self::Retired => 'Wycofany',
        };
    }
}
