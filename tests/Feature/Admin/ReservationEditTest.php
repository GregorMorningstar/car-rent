<?php

use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\ChatMessage;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\ReservationService;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-06-17 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
    $this->staff = User::factory()->admin()->create();
    $this->car = Car::query()->where('operational_status', 'active')->orderBy('id')->firstOrFail();
    $this->location = $this->car->locations()->firstOrFail();
});

function staffReservation(array $overrides = [], ?User $owner = null): Reservation
{
    $car = test()->car;

    return Reservation::query()->create([
        'user_id' => ($owner ?? User::factory()->create())->id,
        'car_id' => $car->id,
        'pickup_location_id' => test()->location->id,
        'dropoff_location_id' => test()->location->id,
        'start_date' => '2026-06-24 08:00:00',
        'end_date' => '2026-06-26 14:00:00',
        'status' => ReservationStatus::Pending,
        'daily_rate' => '170.00',
        'deposit_amount' => '1000.00',
        'total_price' => '500.00',
        'confirmation_deadline' => now()->addDay(),
        ...$overrides,
    ]);
}

function staffData(Reservation $reservation, array $overrides = []): array
{
    return [
        'car_id' => $reservation->car_id,
        'from' => '2026-06-24',
        'from_time' => '10:00',
        'to' => '2026-06-26',
        'to_time' => '16:00',
        'pickup' => $reservation->pickup_location_id,
        'dropoff' => $reservation->dropoff_location_id,
        'status' => $reservation->status->value,
        'notes' => 'Notatka',
        ...$overrides,
    ];
}

test('administrator sees reservation details with client and payments', function () {
    $owner = User::factory()->create(['name' => 'Jan Kowalski']);
    $owner->profile()->save(new UserProfile(['phone' => '600100200', 'street' => 'Lwowska', 'building_number' => '1', 'apartment_number' => '2', 'postal_code' => '38-400', 'city' => 'Krosno']));
    $reservation = staffReservation(owner: $owner);
    ReservationPayment::query()->create(['reservation_id' => $reservation->id, 'type' => 'deposit', 'payer' => 'Jan', 'amount' => '100.00', 'method' => 'Gotówka', 'paid_at' => now()]);

    $this->actingAs($this->staff)
        ->get(route('admin.reservations.edit', $reservation->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/reservations/edit')
            ->where('reservation.id', $reservation->id)
            ->where('reservation.client.name', 'Jan Kowalski')
            ->where('reservation.client.phone', '600100200')
            ->where('reservation.address', 'Lwowska 1/2, 38-400 Krosno')
            ->has('reservation.payments', 1)
            ->where('form.car_id', $this->car->id)
            ->where('form.from', '2026-06-24')
            ->where('form.from_time', '10:00')
            ->where('form.status', 'pending')
            ->has('cars')
            ->has('locations')
            ->has('paymentMethods', 4));
});

test('unknown reservation returns 404', function () {
    $this->actingAs($this->staff)->get(route('admin.reservations.edit', 999))->assertNotFound();
    $this->actingAs($this->staff)->post(route('admin.reservations.update', 999), [])->assertNotFound();
});

test('administrator updates the reservation in local time', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['from_time' => '11:30', 'client_acceptance' => 'Telefonicznie']))
        ->assertRedirect(route('admin.reservations.edit', $reservation->id))
        ->assertSessionHas('status');

    expect(DB::table('reservations')->value('start_date'))->toStartWith('2026-06-24 09:30')
        ->and($reservation->refresh()->notes)->toBe('Notatka');
});

test('changing only notes keeps the price', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation))
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->total_price)->toBe('500.00')
        ->and($reservation->daily_rate)->toBe('170.00');
});

test('changing the term recalculates the price on the server', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, [
            'to' => '2026-06-30',
            'client_acceptance' => 'Telefonicznie',
            'total_price' => '1',
            'daily_rate' => '1',
        ]))
        ->assertSessionHasNoErrors();

    $reservation->refresh();

    expect((float) $reservation->total_price)->toBe((float) $reservation->daily_rate * 6)
        ->and((float) $reservation->daily_rate)->toBeGreaterThan(1.0);
});

test('administrator is not bound by opening hours or holidays', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, [
            'from' => '2026-08-15',
            'from_time' => '23:00',
            'to' => '2026-08-16',
            'client_acceptance' => 'Telefonicznie',
            'to_time' => '03:00',
        ]))
        ->assertSessionHasNoErrors();
});

test('car change updates price, deposit and locations check', function () {
    $reservation = staffReservation();
    $other = Car::query()->where('operational_status', 'active')->whereKeyNot($this->car->id)->orderByDesc('id')->firstOrFail();
    $otherLocation = $other->locations()->firstOrFail();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, [
            'car_id' => $other->id,
            'pickup' => $otherLocation->id,
            'client_acceptance' => 'Telefonicznie',
        ]))
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->car_id)->toBe($other->id)
        ->and($reservation->deposit_amount)->toBe($other->deposit_amount);
});

test('pickup must be a location of the car', function () {
    $reservation = staffReservation();
    $elsewhere = Location::query()->create(['name' => 'Inne miejsce', 'street' => 'Testowa', 'building_number' => '1', 'postal_code' => '38-400', 'city' => 'Krosno', 'latitude' => 49.7, 'longitude' => 21.7]);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['pickup' => $elsewhere->id]))
        ->assertSessionHasErrors('pickup');
});

test('reservation cannot move onto a taken term or car', function () {
    staffReservation(['status' => ReservationStatus::Confirmed, 'start_date' => '2026-07-01 08:00:00', 'end_date' => '2026-07-03 08:00:00']);
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['from' => '2026-07-02', 'to' => '2026-07-05', 'client_acceptance' => 'Telefonicznie']))
        ->assertSessionHasErrors('from');

    expect($reservation->refresh()->start_date->toDateString())->toBe('2026-06-24');
});

test('closed reservation does not block and can be moved anywhere', function () {
    staffReservation(['status' => ReservationStatus::Confirmed, 'start_date' => '2026-07-01 08:00:00', 'end_date' => '2026-07-03 08:00:00']);
    $reservation = staffReservation(['status' => ReservationStatus::Cancelled]);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['from' => '2026-07-02', 'to' => '2026-07-05', 'client_acceptance' => 'Telefonicznie']))
        ->assertSessionHasNoErrors();
});

test('restoring a reservation checks that the car is free', function () {
    staffReservation(['status' => ReservationStatus::Confirmed]);
    $reservation = staffReservation(['status' => ReservationStatus::Cancelled]);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['status' => 'confirmed']))
        ->assertSessionHasErrors('from');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('return must be after pickup', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['to' => '2026-06-24', 'to_time' => '09:00']))
        ->assertSessionHasErrors('to_time');
});

test('update is validated', function (array $overrides, string $field) {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, $overrides))
        ->assertSessionHasErrors($field);

    expect($reservation->refresh()->notes)->toBeNull();
})->with([
    'brak auta' => [['car_id' => ''], 'car_id'],
    'nieistniejące auto' => [['car_id' => 9999], 'car_id'],
    'zły status' => [['status' => 'zrobione'], 'status'],
    'zła data' => [['from' => 'jutro'], 'from'],
    'brak godziny' => [['to_time' => ''], 'to_time'],
    'zła godzina' => [['from_time' => '25:00'], 'from_time'],
    'nieistniejące miejsce' => [['pickup' => 9999], 'pickup'],
    'brak zwrotu' => [['dropoff' => ''], 'dropoff'],
    'za długa notatka' => [fn () => ['notes' => str_repeat('x', 501)], 'notes'],
]);

test('confirming through the form records it and tells the client', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['status' => 'confirmed']))
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->confirmed_at)->not->toBeNull()
        ->and(ChatMessage::query()->sole()->body)->toContain('została potwierdzona');
});

test('cancelling through the form records the date', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['status' => 'cancelled']))
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->cancelled_at)->not->toBeNull()
        ->and(ChatMessage::query()->count())->toBe(0);
});

test('returning to pending gives a new decision deadline', function () {
    $reservation = staffReservation(['status' => ReservationStatus::Cancelled, 'confirmation_deadline' => now()->subDays(3)]);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['status' => 'pending']))
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->confirmation_deadline->isFuture())->toBeTrue()
        ->and($reservation->cancelled_at)->toBeNull();
});

test('client is told when the term changes, but not about notes', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['from_time' => '10:00', 'notes' => 'x']));
    $count = ChatMessage::query()->count();
    $this->post(route('admin.reservations.update', $reservation->id), staffData($reservation->refresh(), ['notes' => 'y']));

    expect(ChatMessage::query()->count())->toBe($count);

    $this->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['to' => '2026-06-29', 'client_acceptance' => 'Telefonicznie']));

    expect(ChatMessage::query()->latest('id')->first()->body)->toContain('zmieniła Twoją rezerwację');
});

test('term cannot change without the client acceptance', function (array $change) {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, $change))
        ->assertSessionHasErrors('client_acceptance');

    $reservation->refresh();

    expect($reservation->start_date->format('Y-m-d H:i'))->toBe('2026-06-24 08:00')
        ->and($reservation->end_date->format('Y-m-d H:i'))->toBe('2026-06-26 14:00')
        ->and($reservation->term_accepted_at)->toBeNull()
        ->and($reservation->notes)->toBeNull();
})->with([
    'godzina odbioru' => [['from_time' => '11:00']],
    'godzina zwrotu' => [['to_time' => '12:00']],
    'data odbioru' => [['from' => '2026-06-23']],
    'data zwrotu' => [['to' => '2026-06-27']],
    'pusta akceptacja' => [['to_time' => '12:00', 'client_acceptance' => '']],
]);

test('acceptance must be one of the known methods', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['to_time' => '12:00', 'client_acceptance' => 'bo tak']))
        ->assertSessionHasErrors('client_acceptance');

    expect($reservation->refresh()->end_date->format('H:i'))->toBe('14:00');
});

test('accepted term change records how, when and who', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['to_time' => '12:00', 'client_acceptance' => 'Osobiście']))
        ->assertSessionHasNoErrors();

    $reservation->refresh();

    expect($reservation->end_date->format('H:i'))->toBe('10:00')
        ->and($reservation->term_accepted_via)->toBe('Osobiście')
        ->and($reservation->term_accepted_at->toDateString())->toBe('2026-06-17')
        ->and($reservation->term_accepted_by)->toBe($this->staff->id);

    $this->get(route('admin.reservations.edit', $reservation->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reservation.acceptance.via', 'Osobiście')
            ->where('reservation.acceptance.by', $this->staff->name)
            ->has('acceptanceMethods', 4));
});

test('car and locations cannot change without the client acceptance', function (string $field) {
    $reservation = staffReservation();
    $other = Car::query()->where('operational_status', 'active')->whereKeyNot($this->car->id)->orderByDesc('id')->firstOrFail();
    $location = Location::query()->create(['name' => 'Drugie miejsce', 'street' => 'Testowa', 'building_number' => '1', 'postal_code' => '38-400', 'city' => 'Krosno', 'latitude' => 49.7, 'longitude' => 21.7]);
    $this->car->locations()->attach($location->id);

    $change = match ($field) {
        'car' => ['car_id' => $other->id, 'pickup' => $other->locations()->firstOrFail()->id],
        'pickup' => ['pickup' => $location->id],
        'dropoff' => ['dropoff' => $location->id],
    };
    $before = $reservation->only(['car_id', 'pickup_location_id', 'dropoff_location_id']);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, $change))
        ->assertSessionHasErrors('client_acceptance');

    expect($reservation->refresh()->only(['car_id', 'pickup_location_id', 'dropoff_location_id']))->toBe($before);

    $this->post(route('admin.reservations.update', $reservation->id), staffData($reservation, [...$change, 'client_acceptance' => 'Telefonicznie']))
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->term_accepted_via)->toBe('Telefonicznie')
        ->and($reservation->term_accepted_by)->toBe($this->staff->id);
})->with(['car', 'pickup', 'dropoff']);

test('client is told about a changed location', function () {
    $reservation = staffReservation();
    $location = Location::query()->create(['name' => 'Drugie miejsce', 'street' => 'Testowa', 'building_number' => '1', 'postal_code' => '38-400', 'city' => 'Krosno', 'latitude' => 49.7, 'longitude' => 21.7]);

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['dropoff' => $location->id, 'client_acceptance' => 'Telefonicznie']))
        ->assertSessionHasNoErrors();

    expect(ChatMessage::query()->sole()->body)->toContain('miejsce zwrotu: Drugie miejsce');
});

test('other changes need no acceptance and leave no trace', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->post(route('admin.reservations.update', $reservation->id), staffData($reservation, ['status' => 'cancelled', 'client_acceptance' => 'Telefonicznie']))
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->term_accepted_at)->toBeNull()
        ->and($reservation->term_accepted_via)->toBeNull();
});

test('reservation removed by the client stays visible with status and date', function () {
    $owner = User::factory()->create();
    $reservation = staffReservation(owner: $owner);

    $this->actingAs($owner)->delete(route('user.reservations.destroy', $reservation->id))->assertSessionHasNoErrors();

    $this->actingAs($this->staff);

    $this->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservations', 1)
            ->where('reservations.0.status', 'deleted')
            ->where('reservations.0.deleted_at', '2026-06-17T12:00:00.000000Z'));

    $this->get(route('admin.reservations.history'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reservations.0.state', 'deleted')
            ->where('reservations.0.deleted_at', '2026-06-17T12:00:00.000000Z'));

    $this->get(route('admin.clients.show', $owner->id))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 1)->where('client.reservationsCount', 1));

    $this->get(route('admin.reservations.edit', $reservation->id))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reservation.state', 'deleted')
            ->where('reservation.deleted_at', '2026-06-17T12:00:00.000000Z'));
});

test('removed reservation leaves current list after its term but stays in history', function () {
    $reservation = staffReservation(['status' => ReservationStatus::Confirmed]);
    $reservation->delete();

    $this->travelTo('2026-06-27 12:00:00');

    $this->actingAs($this->staff);

    $this->get(route('admin.reservations.index'))->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
    $this->get(route('admin.reservations.history'))->assertInertia(fn (Assert $page) => $page->has('reservations', 1));
});

test('removed reservation that no longer held the car does not return to the current list', function () {
    staffReservation(['status' => ReservationStatus::Cancelled])->delete();
    staffReservation(['confirmation_deadline' => now()->subHour()])->delete();

    $this->actingAs($this->staff)
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
});

test('removed reservation does not count as work to do', function () {
    staffReservation()->delete();
    staffReservation(['status' => ReservationStatus::Confirmed, 'start_date' => now()->subDays(5), 'end_date' => now()->subDays(3)])->delete();

    $this->actingAs($this->staff);

    $this->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 0)->where('stats.waiting', 0));
    $this->get(route('admin.reservations.completed'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
});

test('removed reservation cannot be changed or paid, and frees the car', function () {
    $reservation = staffReservation();
    $data = staffData($reservation);
    $reservation->delete();

    $this->actingAs($this->staff);

    $this->post(route('admin.reservations.update', $reservation->id), $data)->assertNotFound();
    $this->post(route('admin.reservations.confirm', $reservation->id))->assertNotFound();
    $this->post(route('admin.reservations.payments.store', $reservation->id), [])->assertNotFound();

    expect(Car::query()->whereKey($this->car->id)->availableBetween($reservation->start_date, $reservation->end_date)->exists())->toBeTrue();
});

test('non administrators cannot view or change a reservation', function () {
    $owner = User::factory()->create();
    $reservation = staffReservation(owner: $owner);
    $data = staffData($reservation, ['status' => 'confirmed']);

    $this->actingAs($owner);

    $this->get(route('admin.reservations.edit', $reservation->id))->assertForbidden();
    $this->post(route('admin.reservations.update', $reservation->id), $data)->assertForbidden();

    // Bez ochrony na trasie nadal odmawia kontroler.
    $this->withoutMiddleware(CheckRole::class);

    $this->get(route('admin.reservations.edit', $reservation->id))->assertForbidden();
    $this->post(route('admin.reservations.update', $reservation->id), $data)->assertForbidden();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Pending);
});

test('service refuses a non administrator on its own', function () {
    $owner = User::factory()->create();
    $reservation = staffReservation(owner: $owner);
    $service = app(ReservationService::class);

    expect(fn () => $service->updateByStaff($owner, $reservation->id, staffData($reservation)))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => $service->staffFormData($owner, $reservation->id))
        ->toThrow(AuthorizationException::class);
});

test('guest is sent to login', function () {
    $reservation = staffReservation();

    $this->post(route('admin.reservations.update', $reservation->id), staffData($reservation))->assertRedirect(route('login'));
    $this->get(route('admin.reservations.edit', $reservation->id))->assertRedirect(route('login'));
});

test('lists are protected also without the route middleware', function (string $route) {
    $this->withoutMiddleware(CheckRole::class);

    $this->actingAs(User::factory()->create())->get(route($route))->assertForbidden();
})->with(['admin.dashboard', 'admin.reservations.history', 'admin.reservations.completed', 'admin.clients.index', 'admin.cars.history']);

test('history shows reservations from the database', function () {
    $reservation = staffReservation();

    $this->actingAs($this->staff)
        ->get(route('admin.reservations.history'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservations', 1)
            ->where('reservations.0.id', $reservation->id)
            ->where('reservations.0.state', 'pending')
            ->where('reservations.0.car.id', $this->car->id)
            ->where('failed', false));
});

test('completed lists only finished rentals', function () {
    staffReservation();
    $finished = staffReservation(['status' => ReservationStatus::Confirmed, 'start_date' => now()->subDays(5), 'end_date' => now()->subDays(3)]);

    $this->actingAs($this->staff)
        ->get(route('admin.reservations.completed'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 1)->where('reservations.0.id', $finished->id));
});

test('car history lists only the selected car', function () {
    staffReservation();
    $other = Car::query()->where('operational_status', 'active')->whereKeyNot($this->car->id)->firstOrFail();

    $this->actingAs($this->staff)
        ->get(route('admin.cars.history', ['car' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page->where('carId', $other->id)->has('reservations', 0)->has('cars'));

    $this->get(route('admin.cars.history', ['car' => $this->car->id]))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 1));
});

test('dashboard shows real pending reservations and stats', function () {
    staffReservation();
    staffReservation(['status' => ReservationStatus::Confirmed, 'start_date' => now()->subDay(), 'end_date' => now()->addDay()]);

    $this->actingAs($this->staff)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('reservations', 1)
            ->where('stats.waiting', 1)
            ->where('stats.ongoing', 1)
            ->where('clientCount', 2));
});

test('client list and details come from the database', function () {
    $client = User::factory()->create(['name' => 'Jan Kowalski']);
    staffReservation(owner: $client);

    $this->actingAs($this->staff)
        ->get(route('admin.clients.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('clients', 1)
            ->where('clients.0.name', 'Jan Kowalski')
            ->where('clients.0.reservationsCount', 1)
            ->missing('clients.0.password'));

    $this->get(route('admin.clients.show', $client->id))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/clients/show')
            ->where('client.id', $client->id)
            ->has('reservations', 1));
});

test('client details show only their own reservations', function () {
    $client = User::factory()->create();
    staffReservation();

    $this->actingAs($this->staff)
        ->get(route('admin.clients.show', $client->id))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
});

test('administrator account and unknown id are not clients', function () {
    $this->actingAs($this->staff);

    $this->get(route('admin.clients.show', $this->staff->id))->assertNotFound();
    $this->get(route('admin.clients.show', 999))->assertNotFound();
});

test('non administrator cannot open client details', function () {
    $client = User::factory()->create();
    $this->withoutMiddleware(CheckRole::class);

    $this->actingAs($client)->get(route('admin.clients.show', $client->id))->assertForbidden();
});
