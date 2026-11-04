<?php

use App\Enums\CarStatus;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\Equipment;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\CarSeeder;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->seed(LocationSeeder::class);
});

test('search works without filters and shows only active cars', function () {
    $this->seed(CarSeeder::class);
    Car::query()->where('vin', 'SB1K93BE10E100001')->update(['operational_status' => CarStatus::Retired]);

    $this->get(route('reservation'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('cars', 7));
});

test('search filters cars', function (array $filters, int $count) {
    $this->seed(CarSeeder::class);

    $this->get(route('reservation', $filters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('cars', $count));
})->with([
    'rodzaj pojazdu' => [['vehicle_type' => 'tow_truck'], 2],
    'paliwo' => [['fuel' => 'hybrid'], 1],
    'drzwi' => [['doors' => 2], 2],
    'cena od' => [['priceFrom' => 500], 1],
    'cena do' => [['priceTo' => 170], 2],
    'cena do zero' => [['priceTo' => 0], 0],
    'kilka filtrów' => [['vehicle_type' => 'bus', 'priceTo' => 450], 1],
]);

test('search filters cars by equipment', function (array $names, int $count) {
    $this->seed([EquipmentSeeder::class, CarSeeder::class]);

    $ids = Equipment::query()->whereIn('name', $names)->pluck('id')->all();

    $this->get(route('reservation', ['equipment' => $ids]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reservation')->has('cars', $count));
})->with([
    'jedna pozycja' => [['GPS'], 2],
    'na wszystkich autach' => [['ABS'], 8],
    'kilka pozycji naraz' => [['Klimatyzacja', 'Hak holowniczy'], 2],
    'bez auta z kompletem' => [['GPS', 'Hak holowniczy'], 0],
]);

test('search results include equipment name and icon', function () {
    $this->seed([EquipmentSeeder::class, CarSeeder::class]);

    $gps = Equipment::query()->where('name', 'GPS')->sole();

    $this->get(route('reservation', ['equipment' => [$gps->id]]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('cars.0.equipment', fn ($equipment) => collect($equipment)->contains(
                fn (array $item) => $item['name'] === 'GPS' && $item['icon'] === 'location-dot',
            ))
            ->has('cars.0.images', 3));
});

test('home search gets active equipment', function () {
    Equipment::query()->create(['name' => 'GPS', 'icon' => 'location-dot']);
    Equipment::query()->create(['name' => 'Ukryte', 'is_active' => false]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('equipment', 1)
            ->where('equipment.0.name', 'GPS')
            ->where('equipment.0.icon', 'location-dot'));
});

test('search does not expose vin and registration number', function () {
    $this->seed(CarSeeder::class);

    $this->get(route('reservation'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('cars.0.vin')
            ->missing('cars.0.registration_number'));
});

test('invalid search returns to home page with errors', function (array $filters, string $field) {
    $this->get(route('reservation', $filters))
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors($field);
})->with([
    'zły rodzaj pojazdu' => [['vehicle_type' => 'tank'], 'vehicle_type'],
    'złe paliwo' => [['fuel' => 'steam'], 'fuel'],
    'złe drzwi' => [['doors' => 9], 'doors'],
    'ujemna cena' => [['priceFrom' => -1], 'priceFrom'],
    'cena do mniejsza niż od' => [['priceFrom' => 200, 'priceTo' => 100], 'priceTo'],
    'zła data' => [['from' => 'wczoraj', 'to' => '2030-01-01'], 'from'],
    'data z przeszłości' => [['from' => '2020-01-01', 'to' => '2030-01-01'], 'from'],
    'zwrot przed odbiorem' => [['from' => '2030-01-10', 'to' => '2030-01-05'], 'to'],
    'brak daty zwrotu' => [['from' => '2030-01-10'], 'to'],
    'tablica zamiast tekstu' => [['pickup' => ['a']], 'pickup'],
    'nieznane wyposażenie' => [['equipment' => [999]], 'equipment.0'],
    'wyposażenie nie jako lista' => [['equipment' => 'GPS'], 'equipment'],
]);

test('search does not run a query with invalid filters', function () {
    DB::enableQueryLog();

    $this->get(route('reservation', ['fuel' => 'steam']))->assertRedirect(route('home'));

    expect(collect(DB::getQueryLog())->pluck('query')->filter(fn (string $sql) => str_contains($sql, 'cars')))
        ->toBeEmpty();
});

// Wyszukiwarka ze strony startowej

function rentalSearch(array $overrides = []): array
{
    return [
        'pickup' => Location::query()->orderBy('id')->value('id'),
        'dropoff' => Location::query()->orderByDesc('id')->value('id'),
        'from' => now()->addDays(10)->toDateString(),
        'to' => now()->addDays(14)->toDateString(),
        ...$overrides,
    ];
}

function reserveCar(Car $car, ReservationStatus $status, int $fromDays = 11, int $toDays = 12, int $deadlineDays = 1): Reservation
{
    return Reservation::query()->create([
        'user_id' => User::factory()->create()->id,
        'car_id' => $car->id,
        'start_date' => now()->addDays($fromDays)->toDateString(),
        'end_date' => now()->addDays($toDays)->toDateString(),
        'status' => $status,
        'daily_rate' => '100.00',
        'deposit_amount' => '100.00',
        'total_price' => '200.00',
        'confirmation_deadline' => now()->addDays($deadlineDays),
    ]);
}

test('home search returns matching cars as json', function () {
    $this->seed(CarSeeder::class);

    $this->getJson(route('cars.search', rentalSearch(['vehicle_type' => 'bus'])))
        ->assertOk()
        ->assertJsonCount(2, 'cars')
        ->assertJsonPath('cars.0.vehicle_type', 'bus')
        ->assertJsonMissingPath('cars.0.vin')
        ->assertJsonMissingPath('cars.0.registration_number');
});

test('home search requires places and dates', function (string $field) {
    $this->getJson(route('cars.search', rentalSearch([$field => ''])))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with(['pickup', 'dropoff', 'from', 'to']);

test('home search rejects invalid data', function (array $overrides, string $field) {
    $this->getJson(route('cars.search', rentalSearch($overrides)))
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'nieznane miejsce odbioru' => [['pickup' => 999], 'pickup'],
    'nieznane miejsce zwrotu' => [['dropoff' => "' OR 1=1 --"], 'dropoff'],
    'data z przeszłości' => [['from' => '2020-01-01'], 'from'],
    'zwrot przed odbiorem' => [['to' => '2020-01-01'], 'to'],
    'zły rodzaj' => [['vehicle_type' => 'tank'], 'vehicle_type'],
    'tablica zamiast miejsca' => [['pickup' => ['a']], 'pickup'],
]);

test('home search has a request limit', function () {
    foreach (range(1, 30) as $request) {
        $this->getJson(route('cars.search', rentalSearch()))->assertOk();
    }

    $this->getJson(route('cars.search', rentalSearch()))->assertTooManyRequests();
});

test('reserved car is hidden in the chosen dates only', function (ReservationStatus $status, int $deadlineDays, int $count) {
    $this->seed(CarSeeder::class);
    reserveCar(Car::query()->firstOrFail(), $status, deadlineDays: $deadlineDays);

    $this->getJson(route('cars.search', rentalSearch()))->assertJsonCount($count, 'cars');
})->with([
    'potwierdzona' => [ReservationStatus::Confirmed, 1, 7],
    'oczekująca' => [ReservationStatus::Pending, 1, 7],
    'oczekująca po terminie' => [ReservationStatus::Pending, -1, 8],
    'anulowana' => [ReservationStatus::Cancelled, 1, 8],
    'wygasła' => [ReservationStatus::Expired, 1, 8],
]);

test('availability checks overlapping dates', function (int $fromDays, int $toDays, int $count) {
    $this->seed(CarSeeder::class);
    reserveCar(Car::query()->firstOrFail(), ReservationStatus::Confirmed, $fromDays, $toDays);

    $this->getJson(route('cars.search', rentalSearch()))->assertJsonCount($count, 'cars');
})->with([
    'kończy się w dniu odbioru' => [8, 10, 7],
    'zaczyna się w dniu zwrotu' => [14, 16, 7],
    'obejmuje cały termin' => [5, 20, 7],
    'przed terminem' => [5, 9, 8],
    'po terminie' => [15, 20, 8],
]);

// Strona z samochodami

test('cars page lists all active cars with filters', function () {
    $this->seed([EquipmentSeeder::class, CarSeeder::class]);

    $this->get(route('cars'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cars')
            ->has('cars', 8)
            ->has('equipment', 11)
            ->missing('cars.0.vin'));
});

test('cars page applies filters from address', function (array $filters, int $count) {
    $this->seed(CarSeeder::class);

    $this->get(route('cars', $filters))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('cars', $count)->where('filters', $filters));
})->with([
    'rodzaj' => [['vehicle_type' => 'van'], 2],
    'skrzynia' => [['transmission' => 'automatic'], 4],
]);

test('cars page hides cars reserved in the chosen dates', function () {
    $this->seed(CarSeeder::class);
    reserveCar(Car::query()->firstOrFail(), ReservationStatus::Confirmed);

    $this->get(route('cars', ['from' => now()->addDays(10)->toDateString(), 'to' => now()->addDays(14)->toDateString()]))
        ->assertInertia(fn (Assert $page) => $page->has('cars', 7));
});

test('invalid filters return to cars page with errors', function () {
    $this->get(route('cars', ['transmission' => 'cvt']))
        ->assertRedirect(route('cars'))
        ->assertSessionHasErrors('transmission');
});

test('cars page has a request limit', function () {
    foreach (range(1, 60) as $request) {
        $this->get(route('cars'))->assertOk();
    }

    $this->get(route('cars'))->assertTooManyRequests();
});

test('home page gets pickup and dropoff locations separately', function () {
    Location::query()->create(['name' => 'Tylko dostawa', 'street' => 'Polna', 'postal_code' => '38-400', 'city' => 'Krosno', 'is_pickup' => false]);
    Location::query()->create(['name' => 'Ukryte', 'street' => 'Polna', 'postal_code' => '38-400', 'city' => 'Krosno', 'is_active' => false]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page->has('pickupLocations', 2)->has('dropoffLocations', 3));
});

test('search shows only cars from the chosen pickup location', function () {
    $this->seed(CarSeeder::class);
    $second = Location::query()->orderByDesc('id')->value('id');

    $this->getJson(route('cars.search', rentalSearch(['pickup' => $second])))->assertJsonCount(4, 'cars');
    $this->get(route('cars', ['pickup' => $second]))->assertInertia(fn (Assert $page) => $page->has('cars', 4));
});

test('search rejects hidden location and location of the wrong type', function () {
    $hidden = Location::query()->create(['name' => 'Ukryte', 'street' => 'Polna', 'postal_code' => '38-400', 'city' => 'Krosno', 'is_active' => false]);
    $dropoffOnly = Location::query()->create(['name' => 'Tylko dostawa', 'street' => 'Polna', 'postal_code' => '38-400', 'city' => 'Krosno', 'is_pickup' => false]);

    $this->getJson(route('cars.search', rentalSearch(['pickup' => $hidden->id])))->assertJsonValidationErrors('pickup');
    $this->getJson(route('cars.search', rentalSearch(['pickup' => $dropoffOnly->id])))->assertJsonValidationErrors('pickup');
});

// Samochody na stronie startowej

test('home page shows up to eight active cars from database', function () {
    $this->seed(CarSeeder::class);

    foreach (range(1, 2) as $number) {
        Car::query()->create([
            'brand' => 'Fiat',
            'model' => "Panda {$number}",
            'registration_number' => "KR 900{$number}A",
            'vin' => "ZFA3120000J00000{$number}",
            'production_year' => 2022,
            'fuel_type' => 'petrol',
            'transmission' => 'manual',
            'doors' => 5,
            'seats' => 5,
            'mileage_km' => 1000,
            'daily_rate' => '99.00',
            'deposit_amount' => '500.00',
        ]);
    }

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cars', 8)
            ->missing('cars.0.vin')
            ->missing('cars.0.registration_number')
            ->missing('cars.0.mileage_km'));
});

test('home page skips cars that are not active', function () {
    $this->seed(CarSeeder::class);
    Car::query()->where('vehicle_type', 'bus')->update(['operational_status' => CarStatus::Maintenance]);

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cars', 6)
            ->where('cars', fn ($cars) => collect($cars)->every(fn (array $car) => $car['vehicle_type'] !== 'bus')));
});

test('home page works without cars', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('cars', 0));
});

// Szczegóły samochodu

test('car details show public data with images and equipment', function () {
    $this->seed([EquipmentSeeder::class, CarSeeder::class]);
    $car = Car::query()->where('vin', 'SB1K93BE10E100001')->sole();

    $this->get(route('cars.show', $car->id))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('car')
            ->where('car.id', $car->id)
            ->where('car.brand', 'Toyota')
            ->where('car.description', $car->description)
            ->has('car.images', 3)
            ->has('car.equipment', 5)
            ->has('car.locations', 2));
});

test('car details do not expose private data', function () {
    $this->seed(CarSeeder::class);

    $this->get(route('cars.show', Car::query()->value('id')))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('car.vin')
            ->missing('car.registration_number')
            ->missing('car.mileage_km')
            ->missing('car.operational_status'));
});

test('car details are not available for hidden cars', function (Closure $hide) {
    $this->seed(CarSeeder::class);
    $car = Car::query()->firstOrFail();
    $hide($car);

    $this->get(route('cars.show', $car->id))->assertNotFound();
})->with([
    'w serwisie' => [fn (Car $car) => $car->update(['operational_status' => CarStatus::Maintenance])],
    'wycofany' => [fn (Car $car) => $car->update(['operational_status' => CarStatus::Retired])],
    'usunięty' => [fn (Car $car) => $car->delete()],
]);

test('unknown car returns 404', function () {
    $this->get(route('cars.show', 999))->assertNotFound();
    $this->get('/samochody/abc')->assertNotFound();
});

test('car details have a request limit', function () {
    $this->seed(CarSeeder::class);
    $id = Car::query()->value('id');

    foreach (range(1, 60) as $request) {
        $this->get(route('cars.show', $id))->assertOk();
    }

    $this->get(route('cars.show', $id))->assertTooManyRequests();
});

// Ceny zależne od długości wynajmu

test('price filter uses the tier for the rental length', function (array $filters, int $count) {
    $this->seed(CarSeeder::class);

    $this->get(route('cars', $filters))->assertInertia(fn (Assert $page) => $page->has('cars', $count));
})->with([
    '1 dzień: cena za dobę do 170' => [['days' => 1, 'priceTo' => 170], 1],
    '2 dni: do 170' => [['days' => 2, 'priceTo' => 170], 2],
    '4 dni: do 170' => [['days' => 4, 'priceTo' => 170], 2],
    '1 dzień: od 600' => [['days' => 1, 'priceFrom' => 600], 1],
    '4 dni: od 600' => [['days' => 4, 'priceFrom' => 600], 0],
    'bez dni: najniższa cena' => [['priceTo' => 170], 2],
    'sama liczba dni nie ukrywa aut' => [['days' => 3], 8],
]);

test('rental length comes from dates before typed days', function () {
    $this->seed(CarSeeder::class);

    $this->get(route('cars', [
        'from' => now()->addDays(10)->toDateString(),
        'to' => now()->addDays(11)->toDateString(),
        'days' => 30,
        'priceTo' => 170,
    ]))->assertInertia(fn (Assert $page) => $page->has('cars', 1));
});

test('home search uses the tier for chosen dates', function () {
    $this->seed(CarSeeder::class);

    $this->getJson(route('cars.search', rentalSearch(['priceTo' => 170])))->assertJsonCount(2, 'cars');
    $this->getJson(route('cars.search', rentalSearch([
        'to' => now()->addDays(11)->toDateString(),
        'priceTo' => 170,
    ])))->assertJsonCount(1, 'cars');
});

test('rental days are validated', function (mixed $days) {
    $this->get(route('cars', ['days' => $days]))->assertSessionHasErrors('days');
})->with([0, -1, 366, 'abc']);

test('public cars include price tiers', function () {
    $this->seed(CarSeeder::class);
    $car = Car::query()->where('vin', 'SB1K93BE10E100001')->sole();

    expect($car->daily_rate)->toBe('151.00');

    $this->get(route('cars.show', $car->id))
        ->assertInertia(fn (Assert $page) => $page
            ->has('car.prices', 3)
            ->where('car.prices.0.min_days', 1)
            ->where('car.prices.0.max_days', 1)
            ->where('car.prices.0.daily_rate', '189.00')
            ->where('car.prices.2.min_days', 4)
            ->where('car.prices.2.max_days', null)
            ->where('car.prices.2.daily_rate', '151.00'));
});
