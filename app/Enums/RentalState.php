<?php

namespace App\Enums;

/**
 * Stan wynajmu widoczny dla klienta - wyliczany ze statusu rezerwacji i dat.
 */
enum RentalState: string
{
    case Pending = 'pending';
    case Upcoming = 'upcoming';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
