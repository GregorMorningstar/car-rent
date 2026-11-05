<?php

use App\Enums\ReservationStatus;
use App\Events\ReservationPlaced;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Services\BookingOptionsService;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

// Środa w południe, bez świąt w pobliżu.
beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-06-17 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
});

function ownedCar(): Car
{
    return Car::query()->where('vin', 'SB1K93BE10E100001')->sole();
}

function ownedReservation(User $owner, array $overrides = [], ?Car $car = null): Reservation
{
    $location = Location::query()->orderBy('id')->value('id');

    return Reservation::query()->create([
        'user_id' => $owner->id,
        'car_id' => ($car ?? ownedCar())->id,
        'pickup_location_id' => $location,
        'dropoff_location_id' => $location,
        'start_date' => CarbonImmutable::parse('2026-06-23 10:00', 'Europe/Warsaw')->utc(),
        'end_date' => CarbonImmutable::parse('2026-06-25 10:00', 'Europe/Warsaw')->utc(),
        'status' => ReservationStatus::Pending,
        'daily_rate' => '170.00',
        'deposit_amount' => '1000.00',
        'total_price' => '340.00',
        'confirmation_deadline' => now()->addDay(),
        ...$overrides,
    ]);
}

function reservationChanges(array $overrides = []): array
{
    $location = Location::query()->orderBy('id')->value('id');

    return [
        'from' => '2026-06-23',
        'to' => '2026-06-27',
        'from_time' => '09:00',
        'to_time' => '15:00',
        'pickup' => $location,
        'dropoff' => $location,
        'notes' => 'Nowe uwagi',
        'driver_name' => 'Jan Kowalski',
        'driver_license_category' => 'b',
        ...$overrides,
    ];
}

// Edycja

test('owner edits a pending reservation and price is recalculated', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs($owner)
        ->put(route('user.reservations.update', $reservation->id), reservationChanges())
        ->assertRedirect(route('user.rentals'))
        ->assertSessionHas('status');

    $reservation->refresh();

    expect($reservation->start_date->format('Y-m-d H:i'))->toBe('2026-06-23 07:00')
        ->and($reservation->end_date->format('Y-m-d H:i'))->toBe('2026-06-27 13:00')
        ->and($reservation->daily_rate)->toBe('151.00')
        ->and($reservation->total_price)->toBe('604.00')
        ->and($reservation->notes)->toBe('Nowe uwagi')
        ->and($reservation->status)->toBe(ReservationStatus::Pending);
});

test('edit form gets reservation in local time', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner, ['notes' => 'Uwagi']);

    $this->actingAs($owner)
        ->get(route('user.reservations.edit', $reservation->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('booking')
            ->where('reservation.id', $reservation->id)
            ->where('reservation.from', '2026-06-23')
            ->where('reservation.from_time', '10:00')
            ->where('reservation.notes', 'Uwagi')
            ->where('reservation.confirmed', false)
            ->where('car.brand', 'Toyota'));
});

test('editing a confirmed reservation sends it back for approval', function () {
    Event::fake([ReservationPlaced::class]);

    $owner = User::factory()->create();
    $reservation = ownedReservation($owner, [
        'status' => ReservationStatus::Confirmed,
        'confirmed_at' => now(),
        'seen_by_admin_at' => now(),
    ]);

    $this->actingAs($owner)
        ->put(route('user.reservations.update', $reservation->id), reservationChanges())
        ->assertSessionHasNoErrors();

    $reservation->refresh();

    expect($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->confirmed_at)->toBeNull()
        ->and($reservation->seen_by_admin_at)->toBeNull()
        ->and($reservation->confirmation_deadline->toDateString())->toBe('2026-06-18');

    Event::assertDispatched(ReservationPlaced::class);
});

test('owner cannot change car, owner, price or status', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $reservation = ownedReservation($owner);
    $otherCar = Car::query()->where('vin', '!=', ownedCar()->vin)->firstOrFail();

    $this->actingAs($owner)->put(route('user.reservations.update', $reservation->id), reservationChanges([
        'car_id' => $otherCar->id,
        'user_id' => $other->id,
        'total_price' => 1,
        'daily_rate' => 1,
        'status' => 'confirmed',
        'seen_by_admin_at' => now(),
    ]));

    $reservation->refresh();

    expect($reservation->car_id)->toBe(ownedCar()->id)
        ->and($reservation->user_id)->toBe($owner->id)
        ->and($reservation->total_price)->toBe('604.00')
        ->and($reservation->status)->toBe(ReservationStatus::Pending);
});

test('reservation does not block itself when moved', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs($owner)
        ->put(route('user.reservations.update', $reservation->id), reservationChanges(['from' => '2026-06-24', 'to' => '2026-06-26']))
        ->assertSessionHasNoErrors();
});

test('edit cannot take a term of another reservation', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);
    ownedReservation(User::factory()->create(), [
        'start_date' => CarbonImmutable::parse('2026-06-26 10:00', 'Europe/Warsaw')->utc(),
        'end_date' => CarbonImmutable::parse('2026-06-28 10:00', 'Europe/Warsaw')->utc(),
    ]);

    $this->actingAs($owner)
        ->put(route('user.reservations.update', $reservation->id), reservationChanges())
        ->assertSessionHasErrors('from');

    expect($reservation->refresh()->total_price)->toBe('340.00');
});

test('edit follows booking rules', function (array $overrides, string $field) {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs($owner)
        ->put(route('user.reservations.update', $reservation->id), reservationChanges($overrides))
        ->assertSessionHasErrors($field);

    expect($reservation->refresh()->total_price)->toBe('340.00');
})->with([
    'data z przeszłości' => [['from' => '2026-06-01'], 'from'],
    'święto' => [['from' => '2026-08-15', 'to' => '2026-08-18'], 'from'],
    'zwrot przed odbiorem' => [['to' => '2026-06-20'], 'to'],
    'zła godzina' => [['from_time' => 'rano'], 'from_time'],
    'nieznane miejsce' => [['pickup' => 999], 'pickup'],
]);

test('edit respects options', function () {
    app(BookingOptionsService::class)->save([
        'pickup_min_days' => 0, 'pickup_max_days' => 365, 'rental_min_days' => 10, 'rental_max_days' => 30,
        'pickup_time_from' => '08:00', 'pickup_time_to' => '18:00', 'return_time_from' => '08:00', 'return_time_to' => '18:00',
        'saturday_enabled' => true, 'saturday_time_from' => '09:00', 'saturday_time_to' => '13:00', 'availability_mode' => 'after_time', 'buffer_minutes' => 60,
    ]);

    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs($owner)
        ->put(route('user.reservations.update', $reservation->id), reservationChanges())
        ->assertSessionHasErrors('to');
});

test('reservation cannot be edited when it can no longer change', function (array $overrides) {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner, $overrides);

    $this->actingAs($owner)
        ->put(route('user.reservations.update', $reservation->id), reservationChanges())
        ->assertSessionHasErrors('from');

    $this->actingAs($owner)->get(route('user.reservations.edit', $reservation->id))->assertForbidden();

    expect($reservation->refresh()->total_price)->toBe('340.00');
})->with([
    'po terminie potwierdzenia' => [fn () => ['confirmation_deadline' => now()->subMinute()]],
    'anulowana' => [['status' => ReservationStatus::Cancelled]],
    'wygasła' => [['status' => ReservationStatus::Expired]],
    'trwa' => [fn () => ['status' => ReservationStatus::Confirmed, 'start_date' => now()->subHour(), 'end_date' => now()->addDay()]],
    'zakończona' => [fn () => ['status' => ReservationStatus::Confirmed, 'start_date' => now()->subDays(5), 'end_date' => now()->subDays(3)]],
]);

// Usuwanie

test('owner removes a reservation and the car returns to the pool', function () {
    Event::fake([ReservationPlaced::class]);

    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);
    $term = ['from' => '2026-06-23', 'to' => '2026-06-24'];

    auth()->logout();
    $this->get(route('cars', $term))->assertInertia(fn (Assert $page) => $page->has('cars', 7));

    $this->actingAs($owner)
        ->from(route('user.rentals'))
        ->delete(route('user.reservations.destroy', $reservation->id))
        ->assertRedirect(route('user.rentals'))
        ->assertSessionHas('status');

    auth()->logout();
    $this->get(route('cars', $term))->assertInertia(fn (Assert $page) => $page->has('cars', 8));

    Event::assertDispatched(ReservationPlaced::class);
});

test('removed reservation disappears for the client and stays marked for the administrator', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id));

    expect(Reservation::query()->count())->toBe(0)
        ->and(DB::table('reservations')->where('id', $reservation->id)->whereNotNull('deleted_at')->exists())->toBeTrue();

    $this->actingAs($owner)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->has('rentals', 0));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservations', 1)
            ->where('reservations.0.status', 'deleted')
            ->where('notifications.reservations.count', 0));
});

test('confirmed reservation can be removed before it starts', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner, ['status' => ReservationStatus::Confirmed]);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id))->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(0);
});

test('cancelled and expired reservations can be removed from history', function (ReservationStatus $status) {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner, ['status' => $status]);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id))->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(0);
})->with([ReservationStatus::Cancelled, ReservationStatus::Expired]);

test('started and finished rentals cannot be removed', function (Closure $dates) {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner, ['status' => ReservationStatus::Confirmed, ...$dates()]);

    $this->actingAs($owner)
        ->delete(route('user.reservations.destroy', $reservation->id))
        ->assertSessionHasErrors('reservation');

    expect(Reservation::query()->count())->toBe(1);
})->with([
    'trwa' => [fn () => ['start_date' => now()->subHour(), 'end_date' => now()->addDay()]],
    'zakończony' => [fn () => ['start_date' => now()->subDays(5), 'end_date' => now()->subDays(3)]],
]);

// Tylko właściciel

test('other client cannot see, edit or remove a reservation', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs(User::factory()->create());

    $this->get(route('user.reservations.edit', $reservation->id))->assertNotFound();
    $this->put(route('user.reservations.update', $reservation->id), reservationChanges())->assertNotFound();
    $this->delete(route('user.reservations.destroy', $reservation->id))->assertNotFound();

    expect($reservation->refresh()->total_price)->toBe('340.00')
        ->and(Reservation::query()->count())->toBe(1);
});

test('administrator cannot use client routes even without role middleware', function () {
    $reservation = ownedReservation(User::factory()->create());
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->delete(route('user.reservations.destroy', $reservation->id))->assertForbidden();

    $this->withoutMiddleware(CheckRole::class);

    $this->delete(route('user.reservations.destroy', $reservation->id))->assertNotFound();
    $this->put(route('user.reservations.update', $reservation->id), reservationChanges())->assertNotFound();

    expect(Reservation::query()->count())->toBe(1);
});

test('guest cannot change a reservation', function () {
    $reservation = ownedReservation(User::factory()->create());

    auth()->logout();

    $this->put(route('user.reservations.update', $reservation->id), reservationChanges())->assertRedirect(route('login'));
    $this->delete(route('user.reservations.destroy', $reservation->id))->assertRedirect(route('login'));

    expect(Reservation::query()->count())->toBe(1);
});

test('policy allows only the owner', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    expect($owner->can('update', $reservation))->toBeTrue()
        ->and($owner->can('delete', $reservation))->toBeTrue()
        ->and(User::factory()->create()->can('update', $reservation))->toBeFalse()
        ->and(User::factory()->admin()->create()->can('delete', $reservation))->toBeFalse();
});

test('service refuses wrong client', function () {
    $reservation = ownedReservation(User::factory()->create());

    expect(fn () => app(ReservationService::class)->removeByClient(User::factory()->create(), $reservation->id))
        ->toThrow(HttpException::class);
});

test('removed reservation returns 404', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);
    $this->actingAs($owner);

    $this->delete(route('user.reservations.destroy', $reservation->id));

    $this->delete(route('user.reservations.destroy', $reservation->id))->assertNotFound();
    $this->get(route('user.reservations.edit', $reservation->id))->assertNotFound();
});

// Przyciski w historii

test('history tells which actions are available', function (array $overrides, bool $edit, bool $delete) {
    $owner = User::factory()->create();
    ownedReservation($owner, $overrides);

    $this->actingAs($owner)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->where('rentals.0.can_edit', $edit)->where('rentals.0.can_delete', $delete));
})->with([
    'oczekuje' => [[], true, true],
    'nadchodzący' => [['status' => ReservationStatus::Confirmed], true, true],
    'anulowany' => [['status' => ReservationStatus::Cancelled], false, true],
    'trwa' => [fn () => ['status' => ReservationStatus::Confirmed, 'start_date' => now()->subHour()], false, false],
]);

// Adnotacja dla administracji

test('administrator is told that a client removed a reservation', function () {
    $owner = User::factory()->create(['name' => 'Jan Testowy']);
    $reservation = ownedReservation($owner, ['status' => ReservationStatus::Confirmed]);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.reservations.removed.count', 1)
            ->where('notifications.reservations.removed.items.0.client', 'Jan Testowy')
            ->where('notifications.reservations.removed.items.0.car', 'Toyota Corolla')
            ->where('notifications.reservations.removed.items.0.was_confirmed', true)
            ->where('notifications.reservations.removed.items.0.from', '2026-06-23T08:00:00.000000Z')
            ->missing('notifications.reservations.removed.items.0.user_id'));
});

test('removing a pending reservation also notifies the administrator', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.reservations.removed.count', 1)
            ->where('notifications.reservations.removed.items.0.was_confirmed', false)
            ->where('notifications.reservations.count', 0));
});

test('removing an inactive reservation does not bother the administrator', function (array $overrides) {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner, $overrides);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id))->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.reservations.removed.count', 0));
})->with([
    'anulowana' => [['status' => ReservationStatus::Cancelled]],
    'wygasła' => [['status' => ReservationStatus::Expired]],
    'po terminie potwierdzenia' => [fn () => ['confirmation_deadline' => now()->subMinute()]],
]);

test('notification disappears after marking as seen', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);
    $staff = User::factory()->admin()->create();

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id));
    $this->actingAs($staff)->post(route('admin.reservations.seen'))->assertRedirect();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.reservations.removed.count', 0));
});

test('client does not see removal notes of anyone', function () {
    $owner = User::factory()->create();
    $reservation = ownedReservation($owner);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id));

    $this->actingAs(User::factory()->create())
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.reservations', null));
});
