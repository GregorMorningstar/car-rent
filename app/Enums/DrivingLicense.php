<?php

namespace App\Enums;

/**
 * Kategoria prawa jazdy wymagana do prowadzenia samochodu.
 */
enum DrivingLicense: string
{
    case B = 'b';
    case C = 'c';
    case D = 'd';

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
            self::B => 'B (do 3,5 t)',
            self::C => 'C (powyżej 3,5 t)',
            self::D => 'D (autobus)',
        };
    }

    /** Kategorię B ma każdy kierowca C i D, ale C nie uprawnia do D ani odwrotnie. */
    public function covers(self $required): bool
    {
        return $this === $required || $required === self::B;
    }
}
