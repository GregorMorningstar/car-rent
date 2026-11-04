<?php

namespace Database\Seeders;

use App\Enums\EquipmentIcon;
use App\Models\Equipment;
use Illuminate\Database\Seeder;

/**
 * Startowa lista wyposażenia. Ponowne uruchomienie nie tworzy duplikatów.
 */
class EquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'ABS' => EquipmentIcon::ShieldHalved,
            'Bluetooth' => EquipmentIcon::TowerBroadcast,
            'Czujniki parkowania' => EquipmentIcon::SquareParking,
            'GPS' => EquipmentIcon::LocationDot,
            'Hak holowniczy' => EquipmentIcon::Trailer,
            'Kamera cofania' => EquipmentIcon::Video,
            'Klimatyzacja' => EquipmentIcon::Snowflake,
            'Poduszki powietrzne' => EquipmentIcon::CarBurst,
            'Podgrzewane fotele' => EquipmentIcon::Fire,
            'System audio' => EquipmentIcon::Music,
            'Tempomat' => EquipmentIcon::GaugeHigh,
        ];

        foreach ($names as $name => $icon) {
            Equipment::query()->firstOrCreate(['name' => $name], ['icon' => $icon->value]);
        }
    }
}
