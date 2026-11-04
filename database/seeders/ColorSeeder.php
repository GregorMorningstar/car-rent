<?php

namespace Database\Seeders;

use App\Models\Color;
use Illuminate\Database\Seeder;

/**
 * Startowa lista kolorów. Ponowne uruchomienie nie tworzy duplikatów.
 */
class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Beżowy' => '#d6c6a5',
            'Biały' => '#f3f4f6',
            'Czarny' => '#111827',
            'Czerwony' => '#dc2626',
            'Grafitowy' => '#4b5563',
            'Niebieski' => '#2563eb',
            'Srebrny' => '#cbd5e1',
            'Szary' => '#9ca3af',
            'Zielony' => '#16a34a',
            'Żółty' => '#facc15',
        ];

        foreach ($names as $name => $hex) {
            Color::query()->firstOrCreate(['name' => $name], ['hex' => $hex]);
        }
    }
}
