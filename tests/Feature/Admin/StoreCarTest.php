<?php

use App\Enums\FuelType;
use App\Enums\VehicleType;
use App\Models\Car;
use App\Models\Color;
use App\Models\Equipment;
use App\Models\User;
use App\Policies\CarPolicy;
use App\Repositories\Contracts\CarRepositoryInterface;
use Illuminate\Support\Arr;

function carPayload(array $overrides = []): array
{
    return [
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'vehicle_type' => 'passenger',
        'registration_number' => 'rkr 12345',
        'vin' => 'jtdbr32e720123456',
        'production_year' => 2022,
        'color' => null,
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 15000,
        'daily_rate' => '189.99',
        'deposit_amount' => '1000',
        'prices' => [['min_days' => 1, 'max_days' => null, 'daily_rate' => '189.99']],
        'operational_status' => 'active',
        'description' => null,
        ...$overrides,
    ];
}

test('administrator can add a car', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), carPayload())
        ->assertRedirect(route('admin.cars.index'));

    $car = Car::query()->sole();

    expect($car->registration_number)->toBe('RKR 12345')
        ->and($car->vin)->toBe('JTDBR32E720123456')
        ->and($car->fuel_type)->toBe(FuelType::Hybrid);
});

test('car is saved with chosen color and equipment', function () {
    Color::query()->create(['name' => 'Biały']);
    $gps = Equipment::query()->create(['name' => 'GPS']);
    $abs = Equipment::query()->create(['name' => 'ABS']);
    Equipment::query()->create(['name' => 'Tempomat']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), carPayload(['color' => 'Biały', 'equipment' => [$gps->id, $abs->id]]))
        ->assertRedirect(route('admin.cars.index'));

    $car = Car::query()->sole();

    expect($car->color)->toBe('Biały')
        ->and($car->equipment->pluck('name')->sort()->values()->all())->toBe(['ABS', 'GPS']);
});

test('car cannot use hidden or unknown options', function (Closure $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), carPayload($overrides()))
        ->assertSessionHasErrors($field);

    expect(Car::query()->count())->toBe(0);
})->with([
    'nieznany kolor' => [fn () => ['color' => 'Tęczowy'], 'color'],
    'ukryty kolor' => [function () {
        Color::query()->create(['name' => 'Beżowy', 'is_active' => false]);

        return ['color' => 'Beżowy'];
    }, 'color'],
    'nieznane wyposażenie' => [fn () => ['equipment' => [999]], 'equipment.0'],
    'ukryte wyposażenie' => [fn () => [
        'equipment' => [Equipment::query()->create(['name' => 'GPS', 'is_active' => false])->id],
    ], 'equipment.0'],
]);

test('bus can have more seats than other vehicles', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), carPayload(['vehicle_type' => 'bus', 'seats' => 20]))
        ->assertRedirect(route('admin.cars.index'));

    expect(Car::query()->sole()->seats)->toBe(20);
});

test('saving failure returns to the form without hiding validation', function () {
    $this->mock(CarRepositoryInterface::class)
        ->shouldReceive('createCar')
        ->once()
        ->andThrow(new RuntimeException('Baza niedostępna'));

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.cars.create'))
        ->post(route('admin.cars.store'), carPayload())
        ->assertRedirect(route('admin.cars.create'))
        ->assertSessionHasErrors('save');
});

test('car requires valid data', function () {
    Car::query()->create(carPayload(['registration_number' => 'RKR 12345', 'vin' => 'JTDBR32E720123456']));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), carPayload(['fuel_type' => 'steam', 'brand' => '']))
        ->assertSessionHasErrors(['brand', 'fuel_type', 'registration_number', 'vin']);

    expect(Car::query()->count())->toBe(1);
});

test('user cannot add a car', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.cars.store'), carPayload())
        ->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->post(route('admin.cars.store'), carPayload())
        ->assertRedirect(route('login'));

    expect(Car::query()->count())->toBe(0);
});

test('only administrator passes car policy', function () {
    $policy = new CarPolicy;

    expect($policy->create(User::factory()->admin()->make()))->toBeTrue()
        ->and($policy->create(User::factory()->make()))->toBeFalse();
});

test('car fields are validated', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->from(route('admin.cars.create'))
        ->post(route('admin.cars.store'), carPayload($overrides))
        ->assertRedirect(route('admin.cars.create'))
        ->assertSessionHasErrors($field);

    expect(Car::query()->count())->toBe(0);
})->with([
    'brak modelu' => [['model' => ''], 'model'],
    'zły rodzaj pojazdu' => [['vehicle_type' => 'tank'], 'vehicle_type'],
    'za dużo miejsc w autobusie' => [['vehicle_type' => 'bus', 'seats' => 61], 'seats'],
    'za krótki VIN' => [['vin' => 'ABC123'], 'vin'],
    'rok z przyszłości' => [fn () => ['production_year' => now()->year + 5], 'production_year'],
    'rok za stary' => [['production_year' => 1980], 'production_year'],
    'zła skrzynia' => [['transmission' => 'cvt'], 'transmission'],
    'zły status' => [['operational_status' => 'sold'], 'operational_status'],
    'ujemny przebieg' => [['mileage_km' => -1], 'mileage_km'],
    'cena nie liczbą' => [['prices' => [['min_days' => 1, 'max_days' => null, 'daily_rate' => 'abc']]], 'prices.0.daily_rate'],
    'ujemna kaucja' => [['deposit_amount' => -10], 'deposit_amount'],
    'za dużo drzwi' => [['doors' => 7], 'doors'],
    'za dużo miejsc' => [['seats' => 12], 'seats'],
]);

test('car is passenger by default and casts vehicle type', function () {
    $car = Car::query()->create(Arr::except(carPayload(), 'vehicle_type'));

    expect($car->refresh()->vehicle_type)->toBe(VehicleType::Passenger);

    $car->update(['vehicle_type' => 'tow_truck']);

    expect($car->refresh()->vehicle_type)->toBe(VehicleType::TowTruck)
        ->and($car->vehicle_type->label())->toBe('Laweta');
});
