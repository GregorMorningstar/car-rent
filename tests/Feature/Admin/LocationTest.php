<?php

use App\Enums\CarStatus;
use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function locationData(array $overrides = []): array
{
    return [
        'name' => 'Krosno Centrum',
        'street' => 'Rynek',
        'building_number' => '5A',
        'postal_code' => '38-400',
        'city' => 'Krosno',
        'details' => 'Wjazd od podwórza',
        'is_pickup' => true,
        'is_dropoff' => false,
        ...$overrides,
    ];
}

function locationCar(array $overrides = []): Car
{
    return Car::query()->create([
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'vehicle_type' => 'passenger',
        'registration_number' => fake()->unique()->bothify('RKR #####'),
        'vin' => fake()->unique()->bothify('JTDBR32E72#######'),
        'production_year' => 2022,
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 15000,
        'required_license' => 'b',
        'daily_rate' => '189.99',
        'deposit_amount' => '1000',
        ...$overrides,
    ]);
}

function locationCarData(array $overrides = []): array
{
    return [
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'vehicle_type' => 'passenger',
        'registration_number' => 'RKR 12345',
        'vin' => 'JTDBR32E720123456',
        'production_year' => 2022,
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 15000,
        'required_license' => 'b',
        'daily_rate' => '189.99',
        'deposit_amount' => '1000',
        'prices' => [['min_days' => 1, 'max_days' => null, 'daily_rate' => '189.99']],
        'operational_status' => 'active',
        ...$overrides,
    ];
}

test('administrator can add a location', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.locations.store'), locationData())
        ->assertRedirect(route('admin.locations.index'));

    $location = Location::query()->sole();

    expect($location->name)->toBe('Krosno Centrum')
        ->and($location->building_number)->toBe('5A')
        ->and($location->details)->toBe('Wjazd od podwórza')
        ->and($location->is_pickup)->toBeTrue()
        ->and($location->is_dropoff)->toBeFalse();
});

test('administrator can edit a location', function () {
    $location = Location::query()->create(locationData());

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.locations.update', $location->id), locationData(['street' => 'Lwowska', 'is_dropoff' => true]))
        ->assertRedirect(route('admin.locations.index'));

    expect($location->refresh()->street)->toBe('Lwowska')
        ->and($location->is_dropoff)->toBeTrue();
});

test('administrator can hide and restore a location', function () {
    $location = Location::query()->create(locationData());
    $this->actingAs(User::factory()->admin()->create());

    $this->patch(route('admin.locations.toggle', $location->id));
    expect($location->refresh()->is_active)->toBeFalse();

    $this->patch(route('admin.locations.toggle', $location->id));
    expect($location->refresh()->is_active)->toBeTrue();
});

test('location data is validated', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.locations.store'), locationData($overrides))
        ->assertSessionHasErrors($field);

    expect(Location::query()->count())->toBe(0);
})->with([
    'brak nazwy' => [['name' => ''], 'name'],
    'brak ulicy' => [['street' => ''], 'street'],
    'zły kod' => [['postal_code' => '38400'], 'postal_code'],
    'brak miejscowości' => [['city' => ''], 'city'],
    'ani odbiór, ani dostawa' => [['is_pickup' => false, 'is_dropoff' => false], 'is_dropoff'],
    'za długie szczegóły' => [['details' => str_repeat('a', 2001)], 'details'],
]);

test('location name cannot be duplicated', function (string $name) {
    Location::query()->create(locationData(['name' => 'Żółta baza']));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.locations.store'), locationData(['name' => $name]))
        ->assertSessionHasErrors('name');
})->with(['ŻÓŁTA BAZA', '  Żółta   baza ', 'Zolta baza']);

test('edited location keeps its own name', function () {
    $location = Location::query()->create(locationData());

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.locations.update', $location->id), locationData())
        ->assertSessionHasNoErrors();
});

test('list counts all cars and cars available today', function () {
    $location = Location::query()->create(locationData());

    $free = locationCar();
    $reserved = locationCar();
    $serviced = locationCar(['operational_status' => CarStatus::Maintenance]);
    $location->cars()->attach([$free->id, $reserved->id, $serviced->id]);

    Reservation::query()->create([
        'user_id' => User::factory()->create()->id,
        'car_id' => $reserved->id,
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'status' => ReservationStatus::Confirmed,
        'daily_rate' => '100.00',
        'deposit_amount' => '100.00',
        'total_price' => '200.00',
        'confirmation_deadline' => now()->addDay(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.locations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('locations.0.cars_count', 3)
            ->where('locations.0.available_cars_count', 1));
});

// Samochód

test('car can be in one or several locations', function () {
    $first = Location::query()->create(locationData());
    $second = Location::query()->create(locationData(['name' => 'Jasło']));
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.cars.store'), locationCarData(['locations' => [$first->id, $second->id]]))
        ->assertSessionHasNoErrors();

    $car = Car::query()->sole();
    expect($car->locations)->toHaveCount(2);

    $this->post(route('admin.cars.update', $car->id), locationCarData(['locations' => [$second->id]]))
        ->assertSessionHasNoErrors();

    expect($car->refresh()->locations->pluck('name')->all())->toBe(['Jasło']);
});

test('car cannot use unknown or hidden location', function () {
    $hidden = Location::query()->create(locationData(['is_active' => false]));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), locationCarData(['locations' => [$hidden->id, 999]]))
        ->assertSessionHasErrors(['locations.0', 'locations.1']);

    expect(Car::query()->count())->toBe(0);
});

test('car keeps its hidden location when edited', function () {
    $hidden = Location::query()->create(locationData(['is_active' => false]));
    $car = locationCar(['registration_number' => 'RKR 12345', 'vin' => 'JTDBR32E720123456']);
    $car->locations()->attach($hidden);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.update', $car->id), locationCarData(['locations' => [$hidden->id]]))
        ->assertSessionHasNoErrors();

    expect($car->refresh()->locations)->toHaveCount(1);
});

test('car forms get active locations', function () {
    Location::query()->create(locationData());
    Location::query()->create(locationData(['name' => 'Ukryte', 'is_active' => false]));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.cars.create'))
        ->assertInertia(fn (Assert $page) => $page->has('locations', 1));
});

// Tylko administrator

test('regular user cannot manage locations', function () {
    $location = Location::query()->create(locationData());
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.locations.index'))->assertForbidden();
    $this->post(route('admin.locations.store'), locationData(['name' => 'Nowe']))->assertForbidden();
    $this->put(route('admin.locations.update', $location->id), locationData(['name' => 'Nowe']))->assertForbidden();
    $this->patch(route('admin.locations.toggle', $location->id))->assertForbidden();

    expect($location->refresh()->name)->toBe('Krosno Centrum')
        ->and($location->is_active)->toBeTrue()
        ->and(Location::query()->count())->toBe(1);
});

test('guest cannot manage locations', function () {
    $this->post(route('admin.locations.store'), locationData())->assertRedirect(route('login'));

    expect(Location::query()->count())->toBe(0);
});

test('location policy blocks regular user even without role middleware', function () {
    $location = Location::query()->create(locationData());

    $this->withoutMiddleware(CheckRole::class)
        ->actingAs(User::factory()->create());

    $this->get(route('admin.locations.index'))->assertForbidden();
    $this->post(route('admin.locations.store'), locationData(['name' => 'Nowe']))->assertForbidden();
    $this->put(route('admin.locations.update', $location->id), locationData(['name' => 'Nowe']))->assertForbidden();
    $this->patch(route('admin.locations.toggle', $location->id))->assertForbidden();

    expect($location->refresh()->name)->toBe('Krosno Centrum');
});

test('editing unknown location returns 404', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.locations.update', 999), locationData())
        ->assertNotFound();
});

// Współrzędne i mapa

test('location is saved with coordinates', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.locations.store'), locationData(['latitude' => '49.6886', 'longitude' => '21.7706']))
        ->assertSessionHasNoErrors();

    expect(Location::query()->sole()->latitude)->toBe(49.6886)
        ->and(Location::query()->sole()->longitude)->toBe(21.7706);
});

test('coordinates are optional but must be given together and in range', function (array $overrides, ?string $field) {
    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.locations.store'), locationData($overrides));

    $field === null ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors($field);
})->with([
    'bez współrzędnych' => [['latitude' => '', 'longitude' => ''], null],
    'tylko szerokość' => [['latitude' => '49.6', 'longitude' => ''], 'longitude'],
    'tylko długość' => [['latitude' => '', 'longitude' => '21.7'], 'latitude'],
    'szerokość poza zakresem' => [['latitude' => '91', 'longitude' => '21.7'], 'latitude'],
    'długość poza zakresem' => [['latitude' => '49.6', 'longitude' => '181'], 'longitude'],
    'tekst' => [['latitude' => 'abc', 'longitude' => '21.7'], 'latitude'],
]);

test('home page gets map locations', function () {
    Location::query()->create(locationData(['latitude' => 49.6886, 'longitude' => 21.7706, 'is_dropoff' => true]));

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pickupLocations.0.latitude', 49.6886)
            ->where('pickupLocations.0.city', 'Krosno')
            ->missing('googleMaps'));
});

test('public location list has no administrative fields', function () {
    Location::query()->create(locationData());

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('pickupLocations.0.is_active')
            ->missing('pickupLocations.0.created_at'));
});
