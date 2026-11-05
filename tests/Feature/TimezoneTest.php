<?php

use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\Equipment;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

function timezoneCar(): Car
{
    return Car::query()->create([
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'registration_number' => 'KR 12345',
        'vin' => 'JTDBR32E720123456',
        'production_year' => 2022,
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 1000,
        'daily_rate' => '100.00',
        'deposit_amount' => '500.00',
    ]);
}

function timezoneRental(User $user, Car $car, string $from, string $to): Reservation
{
    return Reservation::query()->create([
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => $from,
        'end_date' => $to,
        'status' => ReservationStatus::Confirmed,
        'daily_rate' => '100.00',
        'deposit_amount' => '500.00',
        'total_price' => '100.00',
        'confirmation_deadline' => now()->addDay(),
    ]);
}

test('application and database connections work in UTC', function () {
    expect(config('app.timezone'))->toBe('UTC')
        ->and(date_default_timezone_get())->toBe('UTC')
        ->and(config('database.connections.mysql.timezone'))->toBe('+00:00');
});

test('dates with time are saved in UTC', function () {
    $this->travelTo(Carbon::parse('2026-07-01 23:30:00', 'Europe/Warsaw'));

    Equipment::query()->create(['name' => 'GPS']);

    expect(DB::table('equipment')->value('created_at'))->toStartWith('2026-07-01 21:30:00');
});

test('dates with time are sent to the browser in UTC with zone mark', function () {
    $this->travelTo(Carbon::parse('2026-07-01 23:30:00', 'Europe/Warsaw'));

    $equipment = Equipment::query()->create(['name' => 'GPS']);

    expect($equipment->toArray()['created_at'])->toBe('2026-07-01T21:30:00.000000Z');
});

test('rental dates are stored in UTC without shifting', function () {
    $this->travelTo('2026-11-05 23:30:00');

    timezoneRental(User::factory()->create(), timezoneCar(), '2026-11-10 09:00:00', '2026-11-12 15:00:00');

    expect(DB::table('reservations')->value('start_date'))->toStartWith('2026-11-10 09:00');
});

test('today follows the rental office, not UTC', function (string $utc, string $today) {
    $this->travelTo($utc);

    expect(LocalDate::today())->toBe($today);
})->with([
    'po północy zimą' => ['2026-11-05 23:30:00', '2026-11-06'],
    'po północy latem' => ['2026-07-01 22:30:00', '2026-07-02'],
    'przed północą' => ['2026-11-05 22:30:00', '2026-11-05'],
]);

test('rental days follow the local calendar', function () {
    // 00:30 i 23:30 czasu polskiego to w UTC dwa różne dni, a lokalnie ten sam.
    $from = CarbonImmutable::parse('2026-11-06 00:30', 'Europe/Warsaw');
    $to = CarbonImmutable::parse('2026-11-06 23:30', 'Europe/Warsaw');

    expect(LocalDate::rentalDays($from, $to))->toBe(1)
        ->and(LocalDate::rentalDays($from, $to->addDay()))->toBe(1)
        ->and(LocalDate::rentalDays($from, $to->addDays(2)))->toBe(2);
});

test('local day limits are converted to UTC', function () {
    expect(LocalDate::startOfDay('2026-11-06')->format('Y-m-d H:i'))->toBe('2026-11-05 23:00')
        ->and(LocalDate::endOfDay('2026-07-06')->format('Y-m-d H:i'))->toBe('2026-07-06 21:59')
        ->and(LocalDate::at('2026-07-06', '10:00')->format('Y-m-d H:i'))->toBe('2026-07-06 08:00');
});

test('search rejects a date that is already past locally', function () {
    $this->travelTo('2026-11-05 23:30:00');

    $this->get(route('cars', ['from' => '2026-11-05', 'to' => '2026-11-07']))->assertSessionHasErrors('from');
    $this->get(route('cars', ['from' => '2026-11-06', 'to' => '2026-11-07']))->assertOk();
});

test('location counts cars free on the local day', function () {
    $this->travelTo('2026-11-05 23:30:00');

    $location = Location::query()->create(['name' => 'Krosno', 'street' => 'Rynek', 'postal_code' => '38-400', 'city' => 'Krosno']);
    $car = timezoneCar();
    $location->cars()->attach($car);

    // Rezerwacja na 6 listopada zajmuje auto, bo lokalnie to już 6 listopada.
    timezoneRental(User::factory()->create(), $car, '2026-11-06', '2026-11-07');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.locations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('locations.0.available_cars_count', 0));
});

test('local timezone can be configured', function () {
    config(['app.local_timezone' => 'America/New_York']);
    $this->travelTo('2026-11-05 03:00:00');

    expect(LocalDate::today())->toBe('2026-11-04');
});
