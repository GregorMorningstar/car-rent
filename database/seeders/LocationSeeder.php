<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

/**
 * Startowe miejsca odbioru i dostawy. Współrzędne są przybliżone - dokładne ustawia się w panelu.
 * Ponowne uruchomienie nie tworzy duplikatów.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $locations = [
            [
                'name' => 'Miś Krosno Popiełuszki',
                'street' => 'ks. Jerzego Popiełuszki',
                'postal_code' => '38-400',
                'city' => 'Krosno',
                'latitude' => 49.6950000,
                'longitude' => 21.7600000,
            ],
            [
                'name' => 'Myjnia Czysta Polska Podkarpacka Krosno',
                'street' => 'Podkarpacka',
                'postal_code' => '38-400',
                'city' => 'Krosno',
                'latitude' => 49.6790000,
                'longitude' => 21.7700000,
            ],
        ];

        foreach ($locations as $location) {
            Location::query()->firstOrCreate(['name' => $location['name']], $location);
        }
    }
}
