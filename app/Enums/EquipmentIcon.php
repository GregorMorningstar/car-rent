<?php

namespace App\Enums;

/**
 * Wbudowane ikony wyposażenia - nazwy ikon Font Awesome Free (solid).
 * Lista musi być zgodna z resources/js/components/ui/equipment-icon.tsx.
 */
enum EquipmentIcon: string
{
    case Snowflake = 'snowflake';
    case LocationDot = 'location-dot';
    case TowerBroadcast = 'tower-broadcast';
    case Music = 'music';
    case ShieldHalved = 'shield-halved';
    case CarBurst = 'car-burst';
    case Video = 'video';
    case SquareParking = 'square-parking';
    case Fire = 'fire';
    case GaugeHigh = 'gauge-high';
    case Trailer = 'trailer';
    case Chair = 'chair';
    case Baby = 'baby';
    case Key = 'key';
    case ChargingStation = 'charging-station';
    case Sun = 'sun';
    case SuitcaseRolling = 'suitcase-rolling';
    case Wifi = 'wifi';
    case Phone = 'phone';
    case Lightbulb = 'lightbulb';
    case Lock = 'lock';
    case Road = 'road';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
