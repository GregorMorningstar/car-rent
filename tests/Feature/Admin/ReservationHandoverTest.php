<?php

use App\Enums\DrivingLicense;
use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-06-17 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
    $this->staff = User::factory()->admin()->create();
    $this->car = Car::query()->where('vehicle_type', 'passenger')->orderBy('id')->firstOrFail();
    $this->car->update(['mileage_km' => 50000]);
});

function handoverReservation(array $overrides = [], ?User $owner = null, ?Car $car = null): Reservation
{
    return Reservation::query()->create([
        'user_id' => ($owner ?? User::factory()->create())->id,
        'car_id' => ($car ?? test()->car)->id,
        'start_date' => now()->addDays(2),
        'end_date' => now()->addDays(4),
        'status' => ReservationStatus::Confirmed,
        'daily_rate' => '170.00',
        'deposit_amount' => '1000.00',
        'total_price' => '500.00',
        'confirmation_deadline' => now()->addDay(),
        ...$overrides,
    ]);
}

function pickedUpReservation(array $overrides = [], ?User $owner = null): Reservation
{
    return handoverReservation(['picked_up_at' => now(), 'driver_name' => 'Jan Kowalski', ...$overrides], $owner);
}

function driverData(array $overrides = []): array
{
    return ['driver_name' => 'Anna Nowak', 'driver_license_number' => '01234/26/0001', 'driver_license_category' => 'b', ...$overrides];
}

// Wymagane prawo jazdy

test('seeded cars have the licence of their type', function (string $type, DrivingLicense $license) {
    expect(Car::query()->where('vehicle_type', $type)->get()->every(fn (Car $car) => $car->required_license === $license))->toBeTrue();
})->with([
    'osobowe' => ['passenger', DrivingLicense::B],
    'dostawcze' => ['van', DrivingLicense::B],
    'autobusy' => ['bus', DrivingLicense::D],
    'lawety' => ['tow_truck', DrivingLicense::C],
]);

test('licence covers only what the law allows', function (DrivingLicense $held, DrivingLicense $required, bool $allowed) {
    expect($held->covers($required))->toBe($allowed);
})->with([
    [DrivingLicense::B, DrivingLicense::B, true],
    [DrivingLicense::C, DrivingLicense::B, true],
    [DrivingLicense::D, DrivingLicense::B, true],
    [DrivingLicense::B, DrivingLicense::C, false],
    [DrivingLicense::D, DrivingLicense::C, false],
    [DrivingLicense::C, DrivingLicense::D, false],
    [DrivingLicense::D, DrivingLicense::D, true],
]);

test('car page shows the required licence to guests', function () {
    $this->get(route('cars.show', $this->car->id))
        ->assertInertia(fn (Assert $page) => $page->where('car.required_license', 'b'));
});

// Wydanie

test('administrator hands over the car with driver data', function () {
    $reservation = handoverReservation(['driver_name' => 'Jan Kowalski']);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.handover', $reservation->id), driverData())
        ->assertSessionHasNoErrors();

    $reservation->refresh();

    expect($reservation->picked_up_at)->not->toBeNull()
        ->and($reservation->driver_name)->toBe('Anna Nowak')
        ->and($reservation->driver_license_number)->toBe('01234/26/0001')
        ->and($reservation->driver_license_category)->toBe(DrivingLicense::B);
});

test('hand over needs complete driver data', function (array $overrides, string $field) {
    $reservation = handoverReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.handover', $reservation->id), driverData($overrides))
        ->assertSessionHasErrors($field);

    expect($reservation->refresh()->picked_up_at)->toBeNull();
})->with([
    'brak kierowcy' => [['driver_name' => ''], 'driver_name'],
    'brak numeru' => [['driver_license_number' => ''], 'driver_license_number'],
    'brak kategorii' => [['driver_license_category' => ''], 'driver_license_category'],
    'zła kategoria' => [['driver_license_category' => 'z'], 'driver_license_category'],
]);

test('driver without the right category cannot get the car', function () {
    $bus = Car::query()->where('vehicle_type', 'bus')->firstOrFail();
    $reservation = handoverReservation(car: $bus);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.handover', $reservation->id), driverData(['driver_license_category' => 'c']))
        ->assertSessionHasErrors('driver_license_category');

    $this->post(route('admin.reservations.handover', $reservation->id), driverData(['driver_license_category' => 'd']))
        ->assertSessionHasNoErrors();
});

test('only a confirmed reservation can be handed over', function (ReservationStatus $status) {
    $reservation = handoverReservation(['status' => $status]);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.handover', $reservation->id), driverData())
        ->assertSessionHasErrors('reservation');
})->with([ReservationStatus::Pending, ReservationStatus::Cancelled, ReservationStatus::Expired]);

test('car cannot be handed over twice', function () {
    $reservation = pickedUpReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.handover', $reservation->id), driverData())
        ->assertSessionHasErrors('reservation');

    expect($reservation->refresh()->driver_name)->toBe('Jan Kowalski');
});

// Zwrot i przebieg

test('return sets the new mileage as current', function () {
    $reservation = pickedUpReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.takeback', $reservation->id), ['mileage_km' => 50420])
        ->assertSessionHasNoErrors();

    expect($this->car->refresh()->mileage_km)->toBe(50420)
        ->and($reservation->refresh()->returned_mileage_km)->toBe(50420)
        ->and($reservation->returned_at)->not->toBeNull();
});

test('mileage equal to the current one is allowed', function () {
    $this->actingAs($this->staff)
        ->post(route('admin.reservations.takeback', pickedUpReservation()->id), ['mileage_km' => 50000])
        ->assertSessionHasNoErrors();
});

test('smaller or invalid mileage is refused', function (mixed $mileage) {
    $reservation = pickedUpReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.takeback', $reservation->id), ['mileage_km' => $mileage])
        ->assertSessionHasErrors('mileage_km');

    expect($this->car->refresh()->mileage_km)->toBe(50000)
        ->and($reservation->refresh()->returned_at)->toBeNull();
})->with([
    'mniejszy o 1' => [49999],
    'zero' => [0],
    'ujemny' => [-1],
    'pusty' => [''],
    'tekst' => ['dużo'],
    'ułamek' => [50100.5],
    'absurdalny' => [100000000],
]);

test('repository refuses a smaller mileage even if the validator was passed', function () {
    $reservation = pickedUpReservation();
    $this->car->update(['mileage_km' => 60000]);

    expect(app(ReservationRepositoryInterface::class)->recordReturn($reservation, 55000))->toBeFalse()
        ->and($this->car->refresh()->mileage_km)->toBe(60000);
});

test('return needs a car that was handed over and is taken only once', function () {
    $waiting = handoverReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.takeback', $waiting->id), ['mileage_km' => 51000])
        ->assertSessionHasErrors('reservation');

    $returned = pickedUpReservation(['returned_at' => now(), 'returned_mileage_km' => 50000, 'start_date' => now()->addDays(10), 'end_date' => now()->addDays(12)]);

    $this->post(route('admin.reservations.takeback', $returned->id), ['mileage_km' => 51000])
        ->assertSessionHasErrors('reservation');

    expect($this->car->refresh()->mileage_km)->toBe(50000);
});

// Samochód u klienta

test('rental cannot be cancelled or changed while the car is out', function () {
    $reservation = pickedUpReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.reject', $reservation->id))
        ->assertSessionHasErrors('reservation');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('client cannot edit or remove a rental after pickup', function () {
    $owner = User::factory()->create();
    $reservation = pickedUpReservation(owner: $owner);

    $this->actingAs($owner)
        ->delete(route('user.reservations.destroy', $reservation->id))
        ->assertSessionHasErrors('reservation');

    expect(Reservation::query()->count())->toBe(1);

    $this->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rentals.0.can_edit', false)
            ->where('rentals.0.can_delete', false));
});

test('details show the driver, licence and mileage', function () {
    $reservation = pickedUpReservation(['driver_license_number' => 'ABC', 'driver_license_category' => 'b']);

    $this->actingAs($this->staff)
        ->get(route('admin.reservations.edit', $reservation->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reservation.driver.name', 'Jan Kowalski')
            ->where('reservation.driver.license_number', 'ABC')
            ->where('reservation.required_license', 'b')
            ->where('reservation.car_mileage_km', 50000));
});

// Uprawnienia

test('only administrator hands over and takes back', function () {
    $owner = User::factory()->create();
    $waiting = handoverReservation(owner: $owner);
    $out = pickedUpReservation(['start_date' => now()->addDays(10), 'end_date' => now()->addDays(12)], $owner);

    $this->actingAs($owner);

    $this->post(route('admin.reservations.handover', $waiting->id), driverData())->assertForbidden();
    $this->post(route('admin.reservations.takeback', $out->id), ['mileage_km' => 60000])->assertForbidden();

    $this->withoutMiddleware(CheckRole::class);

    $this->post(route('admin.reservations.handover', $waiting->id), driverData())->assertForbidden();
    $this->post(route('admin.reservations.takeback', $out->id), ['mileage_km' => 60000])->assertForbidden();

    expect($waiting->refresh()->picked_up_at)->toBeNull()
        ->and($this->car->refresh()->mileage_km)->toBe(50000);
});

test('removed reservation cannot be handed over', function () {
    $reservation = handoverReservation();
    $reservation->delete();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.handover', $reservation->id), driverData())
        ->assertNotFound();
});
