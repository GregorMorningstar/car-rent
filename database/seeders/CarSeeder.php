<?php

namespace Database\Seeders;

use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use App\Models\Car;
use App\Models\Equipment;
use App\Models\Location;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Przykładowe samochody: po dwa każdego rodzaju (VehicleType), każdy z wyposażeniem, trzema zdjęciami
 * i progami cen. Pole daily_rate w danych poniżej to cena za 1 dzień, w bazie zostaje najniższa z progów.
 * Zdjęcia to ilustracje SVG z katalogu database/seeders/images - w kolorze samochodu.
 * Ponowne uruchomienie aktualizuje istniejące auta (po numerze VIN) zamiast tworzyć duplikaty.
 */
class CarSeeder extends Seeder
{
    /** Tła kolejnych zdjęć: niebo, horyzont, droga. */
    private const SCENES = [
        ['#bfdbfe', '#eff6ff', '#9ca3af'],
        ['#fdba74', '#fef3c7', '#78716c'],
        ['#1e293b', '#475569', '#334155'],
    ];

    /** Wyposażenie według rodzaju pojazdu (z EquipmentSeeder). */
    private const EQUIPMENT = [
        'passenger' => ['ABS', 'Klimatyzacja', 'GPS', 'Poduszki powietrzne', 'Tempomat'],
        'van' => ['ABS', 'Klimatyzacja', 'Czujniki parkowania', 'Hak holowniczy'],
        'bus' => ['ABS', 'Klimatyzacja', 'System audio', 'Kamera cofania'],
        'tow_truck' => ['ABS', 'Bluetooth', 'Hak holowniczy'],
    ];

    /** Limit kilometrów na dobę według rodzaju pojazdu, null to bez limitu. */
    private const KM_LIMITS = [
        'passenger' => null,
        'van' => 300,
        'bus' => 500,
        'tow_truck' => 250,
    ];

    private const BODY_COLORS = [
        'Biały' => '#f3f4f6',
        'Grafitowy' => '#4b5563',
        'Srebrny' => '#cbd5e1',
        'Czerwony' => '#dc2626',
        'Żółty' => '#facc15',
    ];

    public function run(): void
    {
        $cars = [
            [
                'brand' => 'Toyota',
                'model' => 'Corolla',
                'vehicle_type' => VehicleType::Passenger,
                'registration_number' => 'KR 1001A',
                'vin' => 'SB1K93BE10E100001',
                'production_year' => 2023,
                'fuel_type' => FuelType::Hybrid,
                'transmission' => Transmission::Automatic,
                'seats' => 5,
                'doors' => 5,
                'mileage_km' => 28400,
                'color' => 'Biały',
                'daily_rate' => '189.00',
                'deposit_amount' => '1000.00',
                'description' => 'Oszczędny samochód na co dzień i w dłuższą trasę.',
            ],
            [
                'brand' => 'Škoda',
                'model' => 'Octavia Combi',
                'vehicle_type' => VehicleType::Passenger,
                'registration_number' => 'KR 1002A',
                'vin' => 'TMBJJ7NX5PY100002',
                'production_year' => 2022,
                'fuel_type' => FuelType::Diesel,
                'transmission' => Transmission::Manual,
                'seats' => 5,
                'doors' => 5,
                'mileage_km' => 61200,
                'color' => 'Grafitowy',
                'daily_rate' => '169.00',
                'deposit_amount' => '1000.00',
                'description' => 'Pojemne kombi dla rodziny i na wakacje.',
            ],
            [
                'brand' => 'Ford',
                'model' => 'Transit',
                'vehicle_type' => VehicleType::Van,
                'registration_number' => 'KR 2001A',
                'vin' => 'WF0XXXTTGXNA20001',
                'production_year' => 2022,
                'fuel_type' => FuelType::Diesel,
                'transmission' => Transmission::Manual,
                'seats' => 3,
                'doors' => 4,
                'mileage_km' => 74200,
                'color' => 'Biały',
                'daily_rate' => '259.00',
                'deposit_amount' => '2000.00',
                'description' => 'Furgon do przeprowadzek i przewozu towarów.',
            ],
            [
                'brand' => 'Renault',
                'model' => 'Master',
                'vehicle_type' => VehicleType::Van,
                'registration_number' => 'KR 2002A',
                'vin' => 'VF1MA000X68200002',
                'production_year' => 2023,
                'fuel_type' => FuelType::Diesel,
                'transmission' => Transmission::Manual,
                'seats' => 3,
                'doors' => 4,
                'mileage_km' => 39800,
                'color' => 'Srebrny',
                'daily_rate' => '279.00',
                'deposit_amount' => '2000.00',
                'description' => 'Duży furgon z wysokim dachem.',
            ],
            [
                'brand' => 'Mercedes-Benz',
                'model' => 'Sprinter Travel',
                'vehicle_type' => VehicleType::Bus,
                'registration_number' => 'KR 3001A',
                'vin' => 'WDB9076331P300001',
                'production_year' => 2021,
                'fuel_type' => FuelType::Diesel,
                'transmission' => Transmission::Automatic,
                'seats' => 20,
                'doors' => 3,
                'mileage_km' => 132500,
                'color' => 'Srebrny',
                'daily_rate' => '649.00',
                'deposit_amount' => '3000.00',
                'description' => 'Autobus na wycieczki i przewozy grupowe.',
            ],
            [
                'brand' => 'Volkswagen',
                'model' => 'Crafter Bus',
                'vehicle_type' => VehicleType::Bus,
                'registration_number' => 'KR 3002A',
                'vin' => 'WV1ZZZSYZL9300002',
                'production_year' => 2022,
                'fuel_type' => FuelType::Diesel,
                'transmission' => Transmission::Automatic,
                'seats' => 16,
                'doors' => 3,
                'mileage_km' => 97400,
                'color' => 'Biały',
                'daily_rate' => '549.00',
                'deposit_amount' => '3000.00',
                'description' => 'Wygodny autobus dla mniejszych grup.',
            ],
            [
                'brand' => 'Iveco',
                'model' => 'Daily Laweta',
                'vehicle_type' => VehicleType::TowTruck,
                'registration_number' => 'KR 4001A',
                'vin' => 'ZCFC35A0005400001',
                'production_year' => 2020,
                'fuel_type' => FuelType::Diesel,
                'transmission' => Transmission::Manual,
                'seats' => 3,
                'doors' => 2,
                'mileage_km' => 158700,
                'color' => 'Czerwony',
                'daily_rate' => '399.00',
                'deposit_amount' => '3000.00',
                'description' => 'Laweta do przewozu samochodów osobowych.',
            ],
            [
                'brand' => 'Mercedes-Benz',
                'model' => 'Atego Laweta',
                'vehicle_type' => VehicleType::TowTruck,
                'registration_number' => 'KR 4002A',
                'vin' => 'WDB9700761L400002',
                'production_year' => 2019,
                'fuel_type' => FuelType::Diesel,
                'transmission' => Transmission::Automatic,
                'seats' => 3,
                'doors' => 2,
                'mileage_km' => 214300,
                'color' => 'Żółty',
                'daily_rate' => '499.00',
                'deposit_amount' => '4000.00',
                'description' => 'Ciężka laweta do większych pojazdów.',
            ],
        ];

        foreach ($cars as $car) {
            $prices = $this->priceTiers((float) $car['daily_rate']);

            $model = Car::query()->updateOrCreate(
                ['vin' => $car['vin']],
                [
                    ...$car,
                    'daily_rate' => $prices[2]['daily_rate'],
                    'km_limit_per_day' => self::KM_LIMITS[$car['vehicle_type']->value],
                    'operational_status' => CarStatus::Active,
                ],
            );

            $model->prices()->reorder()->delete();
            $model->prices()->createMany($prices);

            $this->seedImages($model);
            $this->seedLocations($model);
            $model->equipment()->sync(
                Equipment::query()->whereIn('name', self::EQUIPMENT[$model->vehicle_type->value])->pluck('id'),
            );
        }
    }

    /**
     * Cena za 1 dzień oraz taniej przy dłuższym wynajmie: 2-3 dni o 10%, 4+ dni o 20%.
     *
     * @return array<int, array{min_days: int, max_days: int|null, daily_rate: float}>
     */
    private function priceTiers(float $oneDay): array
    {
        return [
            ['min_days' => 1, 'max_days' => 1, 'daily_rate' => $oneDay],
            ['min_days' => 2, 'max_days' => 3, 'daily_rate' => round($oneDay * 0.9)],
            ['min_days' => 4, 'max_days' => null, 'daily_rate' => round($oneDay * 0.8)],
        ];
    }

    /** Osobowe i dostawcze są w obu miejscach, autobusy i lawety tylko w pierwszym. */
    private function seedLocations(Car $car): void
    {
        $locations = Location::query()->orderBy('id')->pluck('id');

        $car->locations()->sync(
            in_array($car->vehicle_type, [VehicleType::Passenger, VehicleType::Van], true)
                ? $locations
                : $locations->take(1),
        );
    }

    private function seedImages(Car $car): void
    {
        $template = file_get_contents(database_path("seeders/images/{$car->vehicle_type->value}.svg"));

        $car->images()->delete();

        foreach (self::SCENES as $position => [$sky, $horizon, $ground]) {
            $path = 'cars/seed/'.strtolower($car->vin)."-{$position}.svg";

            Storage::disk('public')->put($path, strtr($template, [
                '{{sky}}' => $sky,
                '{{horizon}}' => $horizon,
                '{{ground}}' => $ground,
                '{{body}}' => self::BODY_COLORS[$car->color],
            ]));

            $car->images()->create(['path' => $path, 'position' => $position]);
        }
    }
}
