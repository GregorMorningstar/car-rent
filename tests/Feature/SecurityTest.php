<?php

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\ChatMessage;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-06-17 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
    $this->car = Car::query()->where('required_license', 'b')->where('operational_status', 'active')->orderBy('id')->firstOrFail();
    $this->location = $this->car->locations()->firstOrFail();
});

function securityReservation(User $owner, array $overrides = []): Reservation
{
    return Reservation::query()->create([
        'user_id' => $owner->id,
        'car_id' => test()->car->id,
        'pickup_location_id' => test()->location->id,
        'dropoff_location_id' => test()->location->id,
        'start_date' => now()->addDays(7),
        'end_date' => now()->addDays(9),
        'status' => ReservationStatus::Pending,
        'daily_rate' => '170.00',
        'deposit_amount' => '1000.00',
        'total_price' => '500.00',
        'confirmation_deadline' => now()->addDay(),
        ...$overrides,
    ]);
}

function securityBooking(array $overrides = []): array
{
    return [
        'from' => '2026-06-23',
        'to' => '2026-06-26',
        'from_time' => '10:00',
        'to_time' => '12:00',
        'pickup' => test()->location->id,
        'dropoff' => test()->location->id,
        'driver_name' => 'Jan Kowalski',
        'driver_license_category' => 'b',
        ...$overrides,
    ];
}

/** Trasy panelu administratora z podstawionym identyfikatorem. */
function adminRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (LaravelRoute $route) => str_starts_with((string) $route->getName(), 'admin.'))
        ->map(fn (LaravelRoute $route) => [
            'name' => $route->getName(),
            'method' => $route->methods()[0],
            'uri' => '/'.preg_replace('/\{\w+\}/', '1', $route->uri()),
            'middleware' => $route->gatherMiddleware(),
        ])
        ->values()
        ->all();
}

// Gość

test('guest is sent to login from every private page', function (string $route, array $parameters) {
    $this->get(route($route, $parameters))->assertRedirect(route('login'));
})->with([
    ['user.dashboard', []],
    ['user.rentals', []],
    ['user.messages.index', []],
    ['user.payments', []],
    ['user.profile', []],
    ['user.reservations.edit', ['id' => 1]],
    ['cars.book', ['id' => 1]],
    ['admin.dashboard', []],
    ['admin.reservations.index', []],
    ['admin.clients.index', []],
    ['admin.settings.messenger.edit', []],
]);

test('guest cannot change a reservation', function () {
    $reservation = securityReservation(User::factory()->create());

    $this->put(route('user.reservations.update', $reservation->id), securityBooking())->assertRedirect(route('login'));
    $this->delete(route('user.reservations.destroy', $reservation->id))->assertRedirect(route('login'));
    $this->post(route('cars.book.store', $this->car->id), securityBooking())->assertRedirect(route('login'));

    expect(Reservation::query()->sole()->total_price)->toBe('500.00');
});

// Panel administratora

test('every admin route requires login, verified email and the administrator role', function () {
    $routes = adminRoutes();

    expect(count($routes))->toBeGreaterThan(40);

    foreach ($routes as $route) {
        expect($route['middleware'])->toContain('auth', 'verified', 'role:administrator');
    }
});

test('user is refused on every admin route', function () {
    $this->actingAs(User::factory()->create());

    foreach (adminRoutes() as $route) {
        expect($this->call($route['method'], $route['uri'])->getStatusCode())->toBe(403, $route['name']);
    }
});

test('controllers refuse a user on every admin route also without the route middleware', function () {
    $this->withoutMiddleware(CheckRole::class);
    $this->actingAs(User::factory()->create());

    foreach (adminRoutes() as $route) {
        expect($this->call($route['method'], $route['uri'])->getStatusCode())->toBe(403, $route['name']);
    }
});

test('administrator keeps access to the admin panel', function () {
    $staff = User::factory()->admin()->create();
    $reservation = securityReservation(User::factory()->create());

    $this->actingAs($staff);

    $this->get(route('admin.cars.index'))->assertOk();
    $this->get(route('admin.cars.create'))->assertOk();
    $this->get(route('admin.cars.edit', $this->car->id))->assertOk();
    $this->get(route('admin.dictionary.index'))->assertOk();
    $this->get(route('admin.reservations.edit', $reservation->id))->assertOk();
    $this->post(route('admin.reservations.confirm', $reservation->id))->assertSessionHasNoErrors();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('administrator without a verified email has no access to the panel', function () {
    $this->actingAs(User::factory()->admin()->unverified()->create())
        ->get(route('admin.dashboard'))
        ->assertRedirect(route('verification.notice'));
});

// Dane innych klientów

test('user cannot read, change or remove a reservation of another user', function () {
    $reservation = securityReservation(User::factory()->create());

    $this->actingAs(User::factory()->create());

    $this->get(route('user.reservations.edit', $reservation->id))->assertNotFound();
    $this->put(route('user.reservations.update', $reservation->id), securityBooking(['notes' => 'cudze']))->assertNotFound();
    $this->delete(route('user.reservations.destroy', $reservation->id))->assertNotFound();

    expect(Reservation::query()->sole()->notes)->toBeNull();
});

test('lists of a user contain only their own data', function () {
    $other = User::factory()->create();
    $reservation = securityReservation($other);
    ReservationPayment::query()->create(['reservation_id' => $reservation->id, 'type' => 'deposit', 'payer' => 'Inny', 'amount' => '100.00', 'method' => 'Gotówka', 'paid_at' => now()]);

    $this->actingAs(User::factory()->create());

    $this->get(route('user.rentals'))->assertInertia(fn (Assert $page) => $page->has('rentals', 0));
    $this->get(route('user.payments'))->assertInertia(fn (Assert $page) => $page->has('payments', 0));
    $this->get(route('user.dashboard'))->assertInertia(fn (Assert $page) => $page->has('rentals', 0));
});

test('user id from the request is ignored', function () {
    $client = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($client)
        ->post(route('cars.book.store', $this->car->id), securityBooking(['user_id' => $other->id]))
        ->assertSessionHasNoErrors();

    expect(Reservation::query()->sole()->user_id)->toBe($client->id);
});

// Role

test('role cannot be set through a crafted registration', function () {
    $this->post(route('register.store'), [
        'name' => 'Jan',
        'email' => 'jan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '+48 600 100 200',
        'street' => 'Lwowska',
        'building_number' => '1',
        'postal_code' => '38-400',
        'city' => 'Krosno',
        'role' => UserRole::Administrator->value,
        'is_admin' => 1,
        'email_verified_at' => '2026-01-01',
    ]);

    $user = User::query()->where('email', 'jan@example.com')->sole();

    expect($user->role)->toBe(UserRole::User)
        ->and($user->email_verified_at)->toBeNull();
});

test('role is not mass assignable', function () {
    expect((new User)->fill(['role' => 'administrator', 'name' => 'Jan'])->getAttributes())->not->toHaveKey('role');
});

test('sending a role in a user request changes nothing', function () {
    $client = User::factory()->create();

    $this->actingAs($client)->post(route('cars.book.store', $this->car->id), securityBooking(['role' => 'administrator']));

    expect($client->refresh()->role)->toBe(UserRole::User);
});

// Cena, płatność i pola administracyjne

test('price and payment fields from the client are ignored when booking', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('cars.book.store', $this->car->id), securityBooking([
            'total_price' => '1',
            'daily_rate' => '1',
            'deposit_amount' => '0',
            'status' => 'confirmed',
            'confirmed_at' => '2026-01-01 10:00:00',
            'picked_up_at' => '2026-01-01 10:00:00',
            'paid' => true,
            'payments' => [['amount' => 99999, 'type' => 'deposit']],
        ]))
        ->assertSessionHasNoErrors();

    $reservation = Reservation::query()->sole();
    $days = 3;

    expect((float) $reservation->total_price)->toBe((float) $reservation->daily_rate * $days)
        ->and((float) $reservation->daily_rate)->toBeGreaterThan(1.0)
        ->and($reservation->deposit_amount)->toBe($this->car->deposit_amount)
        ->and($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->confirmed_at)->toBeNull()
        ->and($reservation->picked_up_at)->toBeNull()
        ->and(ReservationPayment::query()->count())->toBe(0);
});

test('price and status from the client are ignored when editing', function () {
    $client = User::factory()->create();
    $reservation = securityReservation($client);

    $this->actingAs($client)
        ->put(route('user.reservations.update', $reservation->id), securityBooking([
            'total_price' => '1',
            'status' => 'confirmed',
            'car_id' => Car::query()->whereKeyNot($this->car->id)->value('id'),
            'user_id' => User::factory()->create()->id,
        ]))
        ->assertSessionHasNoErrors();

    $reservation->refresh();

    expect((float) $reservation->total_price)->toBeGreaterThan(1.0)
        ->and($reservation->status)->toBe(ReservationStatus::Pending)
        ->and($reservation->car_id)->toBe($this->car->id)
        ->and($reservation->user_id)->toBe($client->id);
});

// Dane wejściowe

test('malformed dates are refused instead of crashing the server', function (string $field, string $value) {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->post(route('cars.book.store', $this->car->id), securityBooking([$field => $value]))
        ->assertRedirect()
        ->assertSessionHasErrors($field);

    expect(Reservation::query()->count())->toBe(0);
})->with([
    ['from', '2026-06-23 13:00'],
    ['from', '2026-06-23T10:00'],
    ['from', 'next tuesday'],
    ['to', '2026-06-26 13:00'],
]);

test('malformed dates do not crash the car search', function () {
    $this->get(route('cars', ['from' => '2026-06-23 13:00', 'to' => '2026-06-26 13:00']))->assertRedirect(route('cars'));
});

// Statusy

test('handed over or returned rental cannot be cancelled', function (array $state) {
    $staff = User::factory()->admin()->create();
    $reservation = securityReservation(User::factory()->create(), ['status' => ReservationStatus::Confirmed, 'picked_up_at' => now(), ...$state]);

    $this->actingAs($staff)
        ->post(route('admin.reservations.reject', $reservation->id))
        ->assertSessionHasErrors('reservation');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
})->with([
    'u klienta' => [[]],
    'zwrócony' => [fn () => ['returned_at' => now(), 'returned_mileage_km' => 100]],
]);

function staffStatusChange(Reservation $reservation, string $status): array
{
    $timezone = 'Europe/Warsaw';

    return [
        'car_id' => $reservation->car_id,
        'from' => $reservation->start_date->setTimezone($timezone)->toDateString(),
        'to' => $reservation->end_date->setTimezone($timezone)->toDateString(),
        'from_time' => $reservation->start_date->setTimezone($timezone)->format('H:i'),
        'to_time' => $reservation->end_date->setTimezone($timezone)->format('H:i'),
        'pickup' => $reservation->pickup_location_id,
        'dropoff' => $reservation->dropoff_location_id,
        'status' => $status,
    ];
}

test('status of a returned rental cannot be changed in the form', function () {
    $reservation = securityReservation(User::factory()->create(), [
        'status' => ReservationStatus::Confirmed,
        'picked_up_at' => now(),
        'returned_at' => now(),
        'returned_mileage_km' => 100,
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.update', $reservation->id), staffStatusChange($reservation, 'cancelled'))
        ->assertSessionHasErrors('status');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('expired cannot be set by hand', function () {
    $reservation = securityReservation(User::factory()->create());

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.update', $reservation->id), staffStatusChange($reservation, 'expired'))
        ->assertSessionHasErrors('status');

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Pending);
});

test('form of an already expired reservation can still be saved', function () {
    $reservation = securityReservation(User::factory()->create(), ['status' => ReservationStatus::Expired]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.update', $reservation->id), [...staffStatusChange($reservation, 'expired'), 'notes' => 'Notatka'])
        ->assertSessionHasNoErrors();

    expect($reservation->refresh()->notes)->toBe('Notatka');
});

// Ograniczanie prób

test('login attempts from one address are limited across accounts', function () {
    foreach (range(1, 20) as $attempt) {
        $this->post(route('login.store'), ['email' => "konto{$attempt}@example.com", 'password' => 'zle'])->assertRedirect();
    }

    $this->post(route('login.store'), ['email' => 'kolejne@example.com', 'password' => 'zle'])->assertTooManyRequests();
});

test('one account is locked after five wrong passwords', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'zle']);
    }

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('registration attempts are limited', function () {
    foreach (range(1, 6) as $attempt) {
        $this->post(route('register.store'), ['email' => 'zly'])->assertRedirect();
    }

    $this->post(route('register.store'), ['email' => 'zly'])->assertTooManyRequests();
});

// Czat

test('chat needs a verified email', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('user.messages.store'), ['body' => 'spam'])
        ->assertRedirect(route('verification.notice'));

    expect(ChatMessage::query()->count())->toBe(0);
});

// Konfiguracja

test('starter accounts with a known password are refused on production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(UserSeeder::class)->run())->toThrow(RuntimeException::class);

    app()->detectEnvironment(fn () => 'testing');

    expect(User::query()->count())->toBe(0);
});

test('responses carry the security headers', function () {
    $this->get(route('login'))
        ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');
});

test('hidden account fields never reach the page', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.password')
            ->missing('auth.user.remember_token'));
});

// Zwykłe działania użytkownika

test('regular client flow still works', function () {
    $client = User::factory()->create();

    $this->actingAs($client)
        ->post(route('cars.book.store', $this->car->id), securityBooking(['notes' => 'Proszę o fotelik']))
        ->assertRedirect(route('user.rentals'));

    $reservation = Reservation::query()->sole();

    $this->get(route('user.rentals'))->assertInertia(fn (Assert $page) => $page->where('rentals.0.id', $reservation->id));
    $this->get(route('user.reservations.edit', $reservation->id))->assertOk();
    $this->put(route('user.reservations.update', $reservation->id), securityBooking(['to' => '2026-06-29']))
        ->assertRedirect(route('user.rentals'));
    $this->post(route('user.messages.store'), ['body' => 'Dzień dobry'])->assertSessionHasNoErrors();
    $this->delete(route('user.reservations.destroy', $reservation->id))->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(0)
        ->and(ChatMessage::query()->count())->toBe(1)
        ->and($client->refresh()->role)->toBe(UserRole::User);
});
