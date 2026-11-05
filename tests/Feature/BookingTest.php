<?php

use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BookingOptionsService;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

// Środa w południe - bez weekendu w dobie roboczej.
beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-06-17 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
});

function bookedCar(): Car
{
    return Car::query()->where('vin', 'SB1K93BE10E100001')->sole();
}

function bookingData(array $overrides = []): array
{
    $location = Location::query()->orderBy('id')->value('id');

    return [
        'from' => '2026-06-20',
        'to' => '2026-06-24',
        'from_time' => '10:00',
        'to_time' => '16:30',
        'pickup' => $location,
        'dropoff' => $location,
        'driver_name' => 'Jan Kowalski',
        'driver_license_category' => 'b',
        ...$overrides,
    ];
}

function bookFor(User $client, ?Car $car = null, array $overrides = [])
{
    return test()->actingAs($client)->post(route('cars.book.store', ($car ?? bookedCar())->id), bookingData($overrides));
}

// Rezerwacja

test('client books a car', function () {
    $client = User::factory()->create();

    bookFor($client)->assertRedirect(route('user.rentals'))->assertSessionHas('status');

    $reservation = Reservation::query()->sole();

    expect($reservation->user_id)->toBe($client->id)
        ->and($reservation->car_id)->toBe(bookedCar()->id)
        ->and($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->daily_rate)->toBe('151.00')
        ->and($reservation->total_price)->toBe('604.00')
        ->and($reservation->deposit_amount)->toBe('1000.00');
});

test('price comes from the tier for the rental length', function (string $to, string $rate, string $total) {
    bookFor(User::factory()->create(), overrides: ['to' => $to]);

    expect(Reservation::query()->sole()->daily_rate)->toBe($rate)
        ->and(Reservation::query()->sole()->total_price)->toBe($total);
})->with([
    '1 dzień' => ['2026-06-21', '189.00', '189.00'],
    '2 dni' => ['2026-06-22', '170.00', '340.00'],
    '4 dni' => ['2026-06-24', '151.00', '604.00'],
]);

test('client cannot set price, status or owner', function () {
    $client = User::factory()->create();
    $other = User::factory()->create();

    bookFor($client, overrides: [
        'daily_rate' => 1,
        'total_price' => 1,
        'status' => 'confirmed',
        'user_id' => $other->id,
        'confirmation_deadline' => '2030-01-01',
    ]);

    $reservation = Reservation::query()->sole();

    expect($reservation->user_id)->toBe($client->id)
        ->and($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->total_price)->toBe('604.00');
});

test('booked car disappears from search for that term', function () {
    $count = fn () => $this->get(route('cars', ['from' => '2026-06-21', 'to' => '2026-06-23']));

    $count()->assertInertia(fn (Assert $page) => $page->has('cars', 8));

    bookFor(User::factory()->create());
    auth()->logout();

    $count()->assertInertia(fn (Assert $page) => $page->has('cars', 7));
});

test('booking data is validated', function (array $overrides, string $field) {
    bookFor(User::factory()->create(), overrides: $overrides)->assertSessionHasErrors($field);

    expect(Reservation::query()->count())->toBe(0);
})->with([
    'brak daty' => [['from' => ''], 'from'],
    'data z przeszłości' => [['from' => '2026-06-01'], 'from'],
    'zwrot przed odbiorem' => [['to' => '2026-06-19'], 'to'],
    'za długo' => [['to' => '2028-01-01'], 'to'],
    'nieznane miejsce' => [['pickup' => 999], 'pickup'],
    'brak miejsca zwrotu' => [['dropoff' => ''], 'dropoff'],
    'brak godziny odbioru' => [['from_time' => ''], 'from_time'],
    'brak godziny zwrotu' => [['to_time' => ''], 'to_time'],
    'zła godzina' => [['from_time' => '25:00'], 'from_time'],
    'godzina z tekstem' => [['to_time' => 'rano'], 'to_time'],
    'zwrot przed odbiorem tego dnia' => [['to' => '2026-06-20', 'to_time' => '09:00'], 'to_time'],
    'zwrot o godzinie odbioru' => [['to' => '2026-06-20', 'to_time' => '10:00'], 'to_time'],
    'za długie uwagi' => [['notes' => str_repeat('a', 501)], 'notes'],
]);

test('car must be at the chosen pickup location', function () {
    $bus = Car::query()->where('vehicle_type', 'bus')->firstOrFail();
    $second = Location::query()->orderByDesc('id')->value('id');

    bookFor(User::factory()->create(), $bus, ['pickup' => $second])->assertSessionHasErrors('pickup');
});

test('taken term cannot be booked twice', function () {
    bookFor(User::factory()->create());

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-23', 'to' => '2026-06-25'])
        ->assertSessionHasErrors('from');

    expect(Reservation::query()->count())->toBe(1);
});

test('client can hold only three cars at once', function () {
    $client = User::factory()->create();
    $cars = Car::query()->orderBy('id')->take(4)->get();

    foreach ($cars->take(3) as $car) {
        bookFor($client, $car)->assertSessionHasNoErrors();
    }

    bookFor($client, $cars[3])->assertSessionHasErrors('from');

    expect(Reservation::query()->count())->toBe(3);
});

test('inactive car cannot be booked', function () {
    $car = bookedCar();
    $car->update(['operational_status' => 'maintenance']);

    bookFor(User::factory()->create(), $car)->assertNotFound();
});

// Godziny

test('pickup and return are saved in UTC', function (string $date, string $utcStart) {
    bookFor(User::factory()->create(), overrides: ['from' => $date, 'to' => $date, 'from_time' => '10:00', 'to_time' => '16:30']);

    expect(Reservation::query()->sole()->start_date->format('Y-m-d H:i'))->toBe($utcStart);
})->with([
    'lato' => ['2026-06-20', '2026-06-20 08:00'],
    'zima' => ['2026-12-20', '2026-12-20 09:00'],
]);

test('return time after midnight UTC stays on the right day', function () {
    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-20', 'to' => '2026-06-21', 'from_time' => '00:30', 'to_time' => '00:15']);

    expect(DB::table('reservations')->value('start_date'))->toStartWith('2026-06-19 22:30')
        ->and(DB::table('reservations')->value('end_date'))->toStartWith('2026-06-20 22:15');
});

test('price does not depend on the hours', function (string $fromTime, string $toTime) {
    bookFor(User::factory()->create(), overrides: ['from_time' => $fromTime, 'to_time' => $toTime]);

    expect(Reservation::query()->sole()->total_price)->toBe('604.00');
})->with([['08:00', '20:00'], ['20:00', '08:00']]);

test('pickup time today must be in the future', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-17 12:00', 'Europe/Warsaw'));

    $today = ['from' => '2026-06-17', 'to' => '2026-06-18'];

    bookFor(User::factory()->create(), overrides: [...$today, 'from_time' => '11:45'])->assertSessionHasErrors('from_time');
    bookFor(User::factory()->create(), overrides: [...$today, 'from_time' => '12:15'])->assertSessionHasNoErrors();
});

test('car can go out again after it is returned the same day', function () {
    $first = User::factory()->create();
    bookFor($first, overrides: ['from' => '2026-06-20', 'to' => '2026-06-22', 'from_time' => '10:00', 'to_time' => '10:00']);

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-22', 'to' => '2026-06-23', 'from_time' => '12:00', 'to_time' => '12:00'])
        ->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(2);
});

test('overlapping hours on the same day are rejected', function () {
    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-20', 'to' => '2026-06-22', 'from_time' => '10:00', 'to_time' => '14:00']);

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-22', 'to' => '2026-06-23', 'from_time' => '13:00', 'to_time' => '12:00'])
        ->assertSessionHasErrors('from');
});

test('search hides a car reserved for part of a day', function () {
    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-20', 'to' => '2026-06-22', 'from_time' => '10:00', 'to_time' => '09:00']);
    auth()->logout();

    $this->get(route('cars', ['from' => '2026-06-22', 'to' => '2026-06-23']))
        ->assertInertia(fn (Assert $page) => $page->has('cars', 7));
});

test('rental dates are sent to the browser in UTC', function () {
    $client = User::factory()->create();
    bookFor($client, overrides: ['from' => '2026-06-20', 'to' => '2026-06-20', 'from_time' => '10:00', 'to_time' => '16:30']);

    $this->actingAs($client)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rentals.0.from', '2026-06-20T08:00:00.000000Z')
            ->where('rentals.0.to', '2026-06-20T14:30:00.000000Z')
            ->where('rentals.0.days', 1));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->where('reservations.0.from', '2026-06-20T08:00:00.000000Z'));
});

// Przerwa między wynajmami

test('reservation stores who drives', function () {
    bookFor(User::factory()->create(), null, ['driver_name' => 'Anna Nowak', 'driver_license_number' => 'ABC 123']);

    $reservation = Reservation::query()->sole();

    expect($reservation->driver_name)->toBe('Anna Nowak')
        ->and($reservation->driver_license_number)->toBe('ABC 123')
        ->and($reservation->driver_license_category->value)->toBe('b');
});

test('licence number is optional at booking, driver and category are not', function (array $overrides, ?string $field) {
    $response = bookFor(User::factory()->create(), null, $overrides);

    $field === null ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors($field);
})->with([
    'bez numeru' => [['driver_license_number' => ''], null],
    'bez kierowcy' => [['driver_name' => ''], 'driver_name'],
    'bez kategorii' => [['driver_license_category' => ''], 'driver_license_category'],
    'zła kategoria' => [['driver_license_category' => 'x'], 'driver_license_category'],
]);

test('category must allow driving the car', function () {
    $bus = Car::query()->where('vehicle_type', 'bus')->firstOrFail();

    bookFor(User::factory()->create(), $bus, ['driver_license_category' => 'b'])
        ->assertSessionHasErrors('driver_license_category');

    expect(Reservation::query()->count())->toBe(0);
});

function setBuffer(int $minutes, string $mode = 'after_time'): void
{
    app(BookingOptionsService::class)->save([
        'availability_mode' => $mode,
        'pickup_min_days' => 0,
        'pickup_max_days' => 365,
        'rental_min_days' => 1,
        'rental_max_days' => 365,
        'pickup_time_from' => '00:00',
        'pickup_time_to' => '23:59',
        'return_time_from' => '00:00',
        'return_time_to' => '23:59',
        'saturday_enabled' => true,
        'saturday_time_from' => '00:00',
        'saturday_time_to' => '23:59',
        'buffer_minutes' => $minutes,
    ]);
}

function bookReturnAtTen(): void
{
    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-22', 'to' => '2026-06-24', 'from_time' => '10:00', 'to_time' => '10:00'])
        ->assertSessionHasNoErrors();
}

test('default break between rentals is 60 minutes', function () {
    expect(app(BookingOptionsService::class)->all()['buffer_minutes'])->toBe(60);
});

test('next pickup must wait for the break', function (int $buffer, string $time, bool $allowed) {
    setBuffer($buffer);
    bookReturnAtTen();

    $response = bookFor(User::factory()->create(), overrides: ['from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => $time, 'to_time' => '10:00']);

    $allowed ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('from');
})->with([
    'minutę za wcześnie' => [60, '10:59', false],
    'równo po przerwie' => [60, '11:00', true],
    'o godzinie zwrotu' => [60, '10:00', false],
    'dłuższa przerwa' => [120, '11:30', false],
    'dłuższa przerwa dotrzymana' => [120, '12:00', true],
    'bez przerwy' => [0, '10:00', true],
]);

test('return must also leave time before the next pickup', function (string $time, bool $allowed) {
    setBuffer(60);
    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => '12:00', 'to_time' => '12:00'])
        ->assertSessionHasNoErrors();

    $response = bookFor(User::factory()->create(), overrides: ['from' => '2026-06-22', 'to' => '2026-06-24', 'from_time' => '10:00', 'to_time' => $time]);

    $allowed ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('from');
})->with([
    'za późno' => ['11:30', false],
    'równo przed przerwą' => ['11:00', true],
]);

test('editing a reservation respects the break', function () {
    setBuffer(60);
    bookReturnAtTen();

    $client = User::factory()->create();
    bookFor($client, overrides: ['from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => '12:00', 'to_time' => '10:00'])->assertSessionHasNoErrors();
    $reservation = Reservation::query()->latest('id')->first();
    $location = Location::query()->orderBy('id')->value('id');
    $changes = ['from' => '2026-06-24', 'to' => '2026-06-26', 'to_time' => '10:00', 'pickup' => $location, 'dropoff' => $location];

    $this->actingAs($client)
        ->put(route('user.reservations.update', $reservation->id), ['driver_name' => 'Jan Kowalski', 'driver_license_category' => 'b', ...$changes, 'from_time' => '10:30'])
        ->assertSessionHasErrors('from');

    $this->actingAs($client)
        ->put(route('user.reservations.update', $reservation->id), ['driver_name' => 'Jan Kowalski', 'driver_license_category' => 'b', ...$changes, 'from_time' => '11:00'])
        ->assertSessionHasNoErrors();
});

test('break does not block a reservation from itself when moved a little', function () {
    setBuffer(60);
    $client = User::factory()->create();
    bookFor($client);
    $reservation = Reservation::query()->sole();
    $location = Location::query()->orderBy('id')->value('id');

    $this->actingAs($client)
        ->put(route('user.reservations.update', $reservation->id), ['driver_name' => 'Jan Kowalski', 'driver_license_category' => 'b',
            'from' => '2026-06-20', 'to' => '2026-06-24', 'from_time' => '10:15', 'to_time' => '16:30',
            'pickup' => $location, 'dropoff' => $location,
        ])
        ->assertSessionHasNoErrors();
});

test('break applies only to the same car', function () {
    setBuffer(240);
    bookReturnAtTen();

    bookFor(User::factory()->create(), Car::query()->where('vin', '!=', bookedCar()->vin)->firstOrFail(), [
        'from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => '10:00', 'to_time' => '10:00',
    ])->assertSessionHasNoErrors();
});

test('error explains the break', function () {
    setBuffer(90);
    bookReturnAtTen();

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => '10:30', 'to_time' => '10:00'])
        ->assertSessionHasErrors(['from' => 'Ten samochód jest zajęty w wybranym terminie. Między wynajmami obowiązuje przerwa 90 min.']);
});

test('after next day mode the car waits for the next calendar day', function (string $returnTime, string $from, string $fromTime, bool $allowed) {
    setBuffer(0, 'next_day');

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-22', 'to' => '2026-06-24', 'from_time' => '10:00', 'to_time' => $returnTime])
        ->assertSessionHasNoErrors();

    $response = bookFor(User::factory()->create(), overrides: ['from' => $from, 'to' => '2026-06-26', 'from_time' => $fromTime, 'to_time' => '10:00']);

    $allowed ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('from');
})->with([
    'zwrot rano, odbiór wieczorem tego dnia' => ['08:00', '2026-06-24', '20:00', false],
    'zwrot rano, odbiór następnego dnia' => ['08:00', '2026-06-25', '00:00', true],
    'zwrot wieczorem, odbiór o północy' => ['23:30', '2026-06-25', '00:00', true],
]);

test('next day mode also protects the following reservation', function (string $to, bool $allowed) {
    setBuffer(0, 'next_day');

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => '20:00', 'to_time' => '10:00'])
        ->assertSessionHasNoErrors();

    $response = bookFor(User::factory()->create(), overrides: ['from' => '2026-06-22', 'to' => $to, 'from_time' => '10:00', 'to_time' => '08:00']);

    $allowed ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors('from');
})->with([
    'zwrot w dniu cudzego odbioru' => ['2026-06-24', false],
    'zwrot dzień wcześniej' => ['2026-06-23', true],
]);

test('next day follows the local day, not UTC', function () {
    setBuffer(0, 'next_day');

    // Zwrot o 00:30 czasu polskiego 24 czerwca to w UTC jeszcze 23 czerwca.
    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-22', 'to' => '2026-06-24', 'from_time' => '10:00', 'to_time' => '00:30'])
        ->assertSessionHasNoErrors();

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => '23:00', 'to_time' => '10:00'])
        ->assertSessionHasErrors('from');
});

test('next day mode ignores the minutes setting', function () {
    setBuffer(0, 'next_day');

    bookReturnAtTen();

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-24', 'to' => '2026-06-26', 'from_time' => '18:00', 'to_time' => '10:00'])
        ->assertSessionHasErrors(['from' => 'Ten samochód jest zajęty w wybranym terminie. Po zwrocie auto jest dostępne dopiero następnego dnia.']);
});

test('editing respects next day mode', function () {
    setBuffer(0, 'next_day');
    bookReturnAtTen();

    $client = User::factory()->create();
    bookFor($client, overrides: ['from' => '2026-06-25', 'to' => '2026-06-27', 'from_time' => '10:00', 'to_time' => '10:00'])->assertSessionHasNoErrors();
    $reservation = Reservation::query()->latest('id')->first();
    $location = Location::query()->orderBy('id')->value('id');

    $this->actingAs($client)
        ->put(route('user.reservations.update', $reservation->id), ['driver_name' => 'Jan Kowalski', 'driver_license_category' => 'b',
            'from' => '2026-06-24', 'to' => '2026-06-27', 'from_time' => '20:00', 'to_time' => '10:00',
            'pickup' => $location, 'dropoff' => $location,
        ])
        ->assertSessionHasErrors('from');
});

test('booking page gets the availability mode', function () {
    setBuffer(0, 'next_day');

    $this->actingAs(User::factory()->create())
        ->get(route('cars.book', bookedCar()->id))
        ->assertInertia(fn (Assert $page) => $page->where('limits.availability_mode', 'next_day'));
});

test('booking page shows the break', function () {
    setBuffer(45);

    $this->actingAs(User::factory()->create())
        ->get(route('cars.book', bookedCar()->id))
        ->assertInertia(fn (Assert $page) => $page->where('limits.buffer_minutes', 45));
});

// Dostęp

test('guest is sent to login', function () {
    $car = bookedCar();

    $this->get(route('cars.book', $car->id))->assertRedirect(route('login'));
    $this->post(route('cars.book.store', $car->id), bookingData())->assertRedirect(route('login'));

    expect(Reservation::query()->count())->toBe(0);
});

test('administrator cannot book', function () {
    $car = bookedCar();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get(route('cars.book', $car->id))->assertForbidden();
    $this->actingAs($admin)->post(route('cars.book.store', $car->id), bookingData())->assertForbidden();

    $this->withoutMiddleware(CheckRole::class)
        ->post(route('cars.book.store', $car->id), bookingData())
        ->assertForbidden();

    expect(Reservation::query()->count())->toBe(0);
});

test('unverified client cannot book', function () {
    $client = User::factory()->unverified()->create();

    bookFor($client)->assertRedirect(route('verification.notice'));

    expect(Reservation::query()->count())->toBe(0);
});

test('booking page shows the car and takes dates from the address', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cars.book', ['id' => bookedCar()->id, 'from' => '2026-06-20', 'to' => '2026-06-22']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('booking')
            ->where('car.brand', 'Toyota')
            ->missing('car.vin')
            ->where('defaults.from', '2026-06-20')
            ->has('pickupLocations', 2));
});

test('booking limit stops flooding', function () {
    $client = User::factory()->create();

    foreach (range(1, 10) as $attempt) {
        bookFor($client, overrides: ['from' => ''])->assertSessionHasErrors('from');
    }

    bookFor($client, overrides: ['from' => ''])->assertTooManyRequests();
});

// 24 godziny bez weekendu

test('deadline skips the weekend', function (string $local, string $expected) {
    $from = CarbonImmutable::parse($local, 'Europe/Warsaw');

    expect(LocalDate::businessDayAfter($from)->setTimezone('Europe/Warsaw')->format('Y-m-d H:i'))->toBe($expected)
        ->and(LocalDate::businessDayAfter($from)->timezoneName)->toBe('UTC');
})->with([
    'poniedziałek' => ['2026-11-02 10:00', '2026-11-03 10:00'],
    'czwartek' => ['2026-11-05 15:00', '2026-11-06 15:00'],
    'piątek' => ['2026-11-06 15:00', '2026-11-09 15:00'],
    'piątek o północy' => ['2026-11-06 00:00', '2026-11-09 00:00'],
    'sobota' => ['2026-11-07 10:00', '2026-11-10 00:00'],
    'niedziela' => ['2026-11-08 23:30', '2026-11-10 00:00'],
    'zmiana czasu' => ['2026-10-23 12:00', '2026-10-26 12:00'],
]);

test('booking stores the business deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-19 15:00', 'Europe/Warsaw'));

    bookFor(User::factory()->create(), overrides: ['from' => '2026-06-25', 'to' => '2026-06-27']);

    expect(Reservation::query()->sole()->confirmation_deadline->setTimezone('Europe/Warsaw')->format('Y-m-d H:i'))
        ->toBe('2026-06-22 15:00');
});

test('client sees until when the reservation holds', function () {
    $client = User::factory()->create();
    bookFor($client);

    $this->actingAs($client)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->where('rentals.0.confirm_by', Reservation::query()->sole()->confirmation_deadline->toJSON()));
});

// Powrót do puli

test('car returns to the pool the moment the deadline passes', function () {
    bookFor(User::factory()->create());
    auth()->logout();

    $search = fn () => $this->get(route('cars', ['from' => '2026-06-21', 'to' => '2026-06-23']));

    $this->travelTo('2026-06-18 11:59:00');
    $search()->assertInertia(fn (Assert $page) => $page->has('cars', 7));

    $this->travelTo('2026-06-18 12:01:00');
    $search()->assertInertia(fn (Assert $page) => $page->has('cars', 8));

    expect(Reservation::query()->sole()->status)->toBe(ReservationStatus::Pending);
});

test('overdue reservation can be booked by someone else', function () {
    bookFor(User::factory()->create());

    $this->travelTo('2026-06-18 12:01:00');

    bookFor(User::factory()->create())->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(2);
});

test('expire command marks only overdue pending reservations', function () {
    $client = User::factory()->create();
    $cars = Car::query()->orderBy('id')->take(3)->get();

    bookFor($client, $cars[0]);
    bookFor($client, $cars[1]);
    $confirmed = Reservation::query()->latest('id')->first();
    $confirmed->update(['status' => ReservationStatus::Confirmed]);

    $this->travelTo('2026-06-18 12:01:00');
    bookFor(User::factory()->create(), $cars[2]);

    $this->artisan('reservations:expire')->assertSuccessful();

    expect(Reservation::query()->orderBy('id')->pluck('status')->map->value->all())
        ->toBe(['expired', 'confirmed', 'pending']);
});

test('expire command is scheduled', function () {
    $this->artisan('schedule:list')->expectsOutputToContain('reservations:expire');
});

// Decyzja administratora

test('administrator list shows current reservations from database', function () {
    $client = User::factory()->create(['name' => 'Jan Testowy']);
    bookFor($client, overrides: ['notes' => 'Fotelik']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reservations/index')
            ->has('reservations', 1)
            ->where('reservations.0.client', 'Jan Testowy')
            ->where('reservations.0.car', 'Toyota Corolla')
            ->where('reservations.0.status', 'pending')
            ->where('reservations.0.notes', 'Fotelik')
            ->where('failed', false));
});

test('administrator confirms a reservation', function () {
    bookFor(User::factory()->create());
    $reservation = Reservation::query()->sole();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.confirm', $reservation->id))
        ->assertRedirect();

    $reservation->refresh();

    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->confirmed_at)->not->toBeNull();
});

test('confirmed reservation keeps the car after the deadline', function () {
    bookFor(User::factory()->create());
    $reservation = Reservation::query()->sole();

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.reservations.confirm', $reservation->id));
    auth()->logout();

    $this->travelTo('2026-06-18 12:01:00');

    $this->get(route('cars', ['from' => '2026-06-21', 'to' => '2026-06-23']))
        ->assertInertia(fn (Assert $page) => $page->has('cars', 7));
});

test('rejected reservation returns the car at once', function () {
    bookFor(User::factory()->create());
    $reservation = Reservation::query()->sole();

    $this->actingAs(User::factory()->admin()->create())->post(route('admin.reservations.reject', $reservation->id));
    auth()->logout();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled);

    $this->get(route('cars', ['from' => '2026-06-21', 'to' => '2026-06-23']))
        ->assertInertia(fn (Assert $page) => $page->has('cars', 8));
});

test('overdue reservation cannot be confirmed', function () {
    bookFor(User::factory()->create());
    $reservation = Reservation::query()->sole();

    $this->travelTo('2026-06-18 12:01:00');

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.confirm', $reservation->id))
        ->assertSessionHasErrors('reservation');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Pending);
});

test('overdue reservation leaves the administrator notifications', function () {
    bookFor(User::factory()->create());
    $this->travelTo('2026-06-18 12:01:00');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.reservations.count', 0));
});

test('client cannot decide about a reservation', function () {
    $client = User::factory()->create();
    bookFor($client);
    $reservation = Reservation::query()->sole();

    $this->post(route('admin.reservations.confirm', $reservation->id))->assertForbidden();
    $this->post(route('admin.reservations.reject', $reservation->id))->assertForbidden();

    $this->withoutMiddleware(CheckRole::class);
    $this->post(route('admin.reservations.confirm', $reservation->id))->assertForbidden();
    $this->post(route('admin.reservations.reject', $reservation->id))->assertForbidden();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Pending);
});

test('decision about unknown reservation returns 404', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.confirm', 999))
        ->assertNotFound();
});
