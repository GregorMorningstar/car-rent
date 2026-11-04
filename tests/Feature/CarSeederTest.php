<?php

use App\Enums\VehicleType;
use App\Models\Car;
use Database\Seeders\CarSeeder;
use Database\Seeders\EquipmentSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('public'));

test('seeder creates cars of every vehicle type', function (VehicleType $type) {
    $this->seed(CarSeeder::class);

    expect(Car::query()->where('vehicle_type', $type)->count())->toBe(2);
})->with(VehicleType::cases());

test('seeded cars have valid vin length', function () {
    $this->seed(CarSeeder::class);

    Car::query()->each(fn (Car $car) => expect(strlen($car->vin))->toBe(17));
});

test('seeded cars have images stored on disk', function () {
    $this->seed(CarSeeder::class);

    Car::query()->with('images')->get()->each(function (Car $car) {
        expect($car->images)->toHaveCount(3);

        foreach ($car->images as $image) {
            Storage::disk('public')->assertExists($image->path);

            expect(simplexml_load_string(Storage::disk('public')->get($image->path)))->not->toBeFalse()
                ->and(Storage::disk('public')->get($image->path))->not->toContain('{{');
        }
    });
});

test('seeded cars have equipment', function () {
    $this->seed([EquipmentSeeder::class, CarSeeder::class]);

    Car::query()->each(fn (Car $car) => expect($car->equipment)->not->toBeEmpty());
});

test('running seeder twice does not create duplicates', function () {
    $this->seed(CarSeeder::class);
    $this->seed(CarSeeder::class);

    expect(Car::query()->count())->toBe(8);
});

test('seeded cars have mileage limits or no limit', function () {
    $this->seed(CarSeeder::class);

    expect(Car::query()->where('vehicle_type', VehicleType::Passenger)->whereNull('km_limit_per_day')->count())->toBe(2)
        ->and(Car::query()->where('vehicle_type', VehicleType::Van)->value('km_limit_per_day'))->toBe(300);
});

test('seeded cars have price tiers starting from one day', function () {
    $this->seed(CarSeeder::class);

    Car::query()->with('prices')->get()->each(function (Car $car) {
        expect($car->prices)->toHaveCount(3)
            ->and($car->prices[0]->min_days)->toBe(1)
            ->and($car->prices[2]->max_days)->toBeNull()
            ->and($car->daily_rate)->toBe($car->prices[2]->daily_rate);
    });
});
