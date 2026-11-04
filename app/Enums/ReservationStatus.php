<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

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
            self::Pending => 'Oczekuje na potwierdzenie',
            self::Confirmed => 'Potwierdzona',
            self::Cancelled => 'Anulowana',
            self::Expired => 'Wygasła',
        };
    }
}
