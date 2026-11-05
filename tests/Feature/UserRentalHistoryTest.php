<?php

use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Contracts\UserPanelRepositoryInterface;
use App\Services\UserPanelService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Exceptions;
use Inertia\Testing\AssertableInertia as Assert;

// Stała godzina, żeby daty UTC i lokalne wskazywały ten sam dzień.
beforeEach(fn () => $this->travelTo('2026-06-15 12:00:00'));

function historyCar(string $model = 'Corolla'): Car
{
    return Car::query()->create([
        'brand' => 'Toyota',
        'model' => $model,
        'registration_number' => fake()->unique()->bothify('KR #####'),
        'vin' => fake()->unique()->bothify('JTDBR32E72#######'),
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

function historyRental(User $user, array $overrides = [], ?Car $car = null): Reservation
{
    return Reservation::query()->create([
        'user_id' => $user->id,
        'car_id' => ($car ?? historyCar())->id,
        'start_date' => now()->subDays(10)->toDateString(),
        'end_date' => now()->subDays(8)->toDateString(),
        'status' => ReservationStatus::Confirmed,
        'daily_rate' => '100.00',
        'deposit_amount' => '500.00',
        'total_price' => '200.00',
        'confirmation_deadline' => now()->addDay(),
        ...$overrides,
    ]);
}

test('rental history shows only own rentals', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $own = historyRental($user, car: historyCar('Moja'));
    historyRental($other, car: historyCar('Cudza'));

    $this->actingAs($user)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/rentals')
            ->has('rentals', 1)
            ->where('rentals.0.id', $own->id)
            ->where('rentals.0.car', 'Toyota Moja')
            ->where('failed', false));
});

test('profile shows recent own rentals', function () {
    $user = User::factory()->create();
    $car = historyCar();

    foreach (range(1, 7) as $index) {
        historyRental($user, [
            'start_date' => now()->subDays($index * 10)->toDateString(),
            'end_date' => now()->subDays($index * 10 - 1)->toDateString(),
        ], $car);
    }

    historyRental(User::factory()->create(), car: $car);

    $this->actingAs($user)
        ->get(route('user.profile'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/profile')
            ->has('rentals', 5)
            ->where('rentals.0.from', now()->subDays(10)->startOfDay()->toJSON()));
});

test('rental state comes from status and dates', function (array $reservation, string $state) {
    $user = User::factory()->create();
    historyRental($user, $reservation);

    $this->actingAs($user)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->where('rentals.0.state', $state));
})->with([
    'zakończony' => [[], 'completed'],
    'w trakcie' => [fn () => ['start_date' => now()->subDay()->toDateString(), 'end_date' => now()->addDay()->toDateString()], 'active'],
    'kończy się za godzinę' => [fn () => ['start_date' => now()->subDay(), 'end_date' => now()->addHour()], 'active'],
    'skończył się godzinę temu' => [fn () => ['start_date' => now()->subDay(), 'end_date' => now()->subHour()], 'completed'],
    'zaczął się godzinę temu' => [fn () => ['start_date' => now()->subHour(), 'end_date' => now()->addDay()], 'active'],
    'zaczyna się za godzinę' => [fn () => ['start_date' => now()->addHour(), 'end_date' => now()->addDay()], 'upcoming'],
    'nadchodzący' => [fn () => ['start_date' => now()->addDay()->toDateString(), 'end_date' => now()->addDays(2)->toDateString()], 'upcoming'],
    'oczekuje' => [['status' => ReservationStatus::Pending], 'pending'],
    'oczekuje po terminie' => [fn () => ['status' => ReservationStatus::Pending, 'confirmation_deadline' => now()->subHour()], 'cancelled'],
    'anulowany' => [['status' => ReservationStatus::Cancelled], 'cancelled'],
    'wygasły' => [['status' => ReservationStatus::Expired], 'cancelled'],
]);

test('history can be filtered by state and counts every state', function () {
    $user = User::factory()->create();
    historyRental($user);
    historyRental($user);
    historyRental($user, ['status' => ReservationStatus::Cancelled]);

    $this->actingAs($user)
        ->get(route('user.rentals', ['status' => 'cancelled']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rentals', 1)
            ->where('status', 'cancelled')
            ->where('counts.completed', 2)
            ->where('counts.cancelled', 1)
            ->where('counts.active', 0));
});

test('invalid state filter is rejected', function (mixed $status) {
    $this->actingAs(User::factory()->create())
        ->get(route('user.rentals', ['status' => $status]))
        ->assertRedirect(route('user.rentals'))
        ->assertSessionHasErrors('status');
})->with(['nieznany', "' OR 1=1 --", [['a']]]);

test('rentals contain no private data', function () {
    $user = User::factory()->create();
    historyRental($user);

    $this->actingAs($user)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('rentals.0.user_id')
            ->missing('rentals.0.car_id')
            ->missing('rentals.0.vin')
            ->missing('rentals.0.confirmation_deadline'));
});

test('rental stays in history after the car is removed', function () {
    $user = User::factory()->create();
    $car = historyCar('Usunięta');
    historyRental($user, car: $car);
    $car->delete();

    $this->actingAs($user)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->where('rentals.0.car', 'Toyota Usunięta'));
});

test('user without rentals gets empty history', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->has('rentals', 0)->where('failed', false)->where('counts.active', 0));
});

test('rental number and days are calculated', function () {
    $user = User::factory()->create();
    $rental = historyRental($user);

    $this->actingAs($user)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rentals.0.number', sprintf('WYN/%d/%04d', now()->year, $rental->id))
            ->where('rentals.0.days', 2));
});

// Sprawdzanie identyfikatora

test('service refuses data of another account', function (string $method) {
    $user = User::factory()->create();
    $other = User::factory()->create();
    historyRental($other);

    $this->actingAs($user);

    expect(fn () => app(UserPanelService::class)->{$method}($other->id))->toThrow(AuthorizationException::class);
})->with(['rentalHistory', 'recentRentals', 'dashboard']);

test('service refuses data without logged in user', function () {
    $user = User::factory()->create();

    expect(fn () => app(UserPanelService::class)->rentalHistory($user->id))->toThrow(AuthorizationException::class);
});

test('user id from address is ignored', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    historyRental($other);

    $this->actingAs($user)
        ->get(route('user.rentals', ['user_id' => $other->id, 'id' => $other->id]))
        ->assertInertia(fn (Assert $page) => $page->has('rentals', 0));
});

test('rows of another owner are dropped even if repository returns them', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $row = fn (User $owner) => [
        'id' => 1,
        'user_id' => $owner->id,
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'start_date' => now()->subDays(5),
        'end_date' => now()->subDays(3),
        'status' => ReservationStatus::Confirmed,
        'confirmation_deadline' => now(),
        'daily_rate' => '100.00',
        'total_price' => '200.00',
        'year' => 2026,
    ];

    $this->mock(UserPanelRepositoryInterface::class)
        ->shouldReceive('rentalsForUser')
        ->with($user->id)
        ->andReturn([$row($other), $row($user)]);

    $this->actingAs($user)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->has('rentals', 1)->where('counts.completed', 1));
});

// Błędy odczytu

test('read failure shows a message instead of an error page', function (string $route, string $component) {
    Exceptions::fake();

    $this->mock(UserPanelRepositoryInterface::class)
        ->shouldReceive('rentalsForUser')
        ->andThrow(new RuntimeException('Baza niedostępna'));

    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component)->has('rentals', 0)->where('failed', true));

    Exceptions::assertReported(RuntimeException::class);
})->with([
    'historia' => ['user.rentals', 'user/rentals'],
    'profil' => ['user.profile', 'user/profile'],
]);

// Dostęp

test('guest cannot read rental history', function () {
    $this->get(route('user.rentals'))->assertRedirect(route('login'));
});

test('administrator cannot open user history even without role middleware', function () {
    $admin = User::factory()->admin()->create();
    historyRental($admin);

    $this->actingAs($admin)->get(route('user.rentals'))->assertForbidden();

    // Bez middleware serwis nadal pokazuje wyłącznie własne dane.
    $other = User::factory()->create();
    historyRental($other);

    $this->withoutMiddleware(CheckRole::class)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->has('rentals', 1));
});

// Pulpit

function pastRental(User $user, array $overrides = [], ?Car $car = null): Reservation
{
    return historyRental($user, ['total_price' => '200.00', ...$overrides], $car);
}

function activeRental(User $user, array $overrides = []): Reservation
{
    return historyRental($user, [
        'start_date' => now()->subDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'total_price' => '300.50',
        ...$overrides,
    ]);
}

function futureRental(User $user, int $fromDays, array $overrides = []): Reservation
{
    return historyRental($user, [
        'start_date' => now()->addDays($fromDays)->toDateString(),
        'end_date' => now()->addDays($fromDays + 2)->toDateString(),
        'total_price' => '999.00',
        ...$overrides,
    ]);
}

test('dashboard sums only own started rentals', function () {
    $user = User::factory()->create();
    pastRental($user);
    activeRental($user);
    futureRental($user, 5);
    pastRental($user, ['status' => ReservationStatus::Cancelled]);
    futureRental($user, 9, ['status' => ReservationStatus::Pending]);
    pastRental(User::factory()->create(), ['total_price' => '5000.00']);

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/dashboard')
            ->where('stats.rentals', 3)
            ->where('stats.days', 5)
            ->where('stats.spent', '500.50')
            ->where('failed', false));
});

test('dashboard shows current and nearest rental', function () {
    $user = User::factory()->create();
    pastRental($user);
    $current = activeRental($user);
    futureRental($user, 20);
    $nearest = futureRental($user, 5);

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('current.id', $current->id)
            ->where('next.id', $nearest->id));
});

test('dashboard falls back to pending rental as next one', function () {
    $user = User::factory()->create();
    $pending = futureRental($user, 5, ['status' => ReservationStatus::Pending]);

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('current', null)->where('next.id', $pending->id));
});

test('dashboard lists three latest rentals', function () {
    $user = User::factory()->create();
    $car = historyCar();

    foreach (range(1, 5) as $index) {
        pastRental($user, [
            'start_date' => now()->subDays($index * 10)->toDateString(),
            'end_date' => now()->subDays($index * 10 - 1)->toDateString(),
        ], $car);
    }

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rentals', 3)
            ->where('rentals.0.from', now()->subDays(10)->startOfDay()->toJSON()));
});

test('dashboard shows pickup and dropoff places', function () {
    $user = User::factory()->create();
    $pickup = Location::query()->create(['name' => 'Krosno', 'street' => 'Rynek', 'postal_code' => '38-400', 'city' => 'Krosno']);
    $dropoff = Location::query()->create(['name' => 'Jasło', 'street' => 'Rynek', 'postal_code' => '38-200', 'city' => 'Jasło']);
    activeRental($user, ['pickup_location_id' => $pickup->id, 'dropoff_location_id' => $dropoff->id]);

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('current.pickup', 'Krosno')
            ->where('current.dropoff', 'Jasło')
            ->missing('current.pickup_location_id'));
});

test('new user gets an empty dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.rentals', 0)
            ->where('stats.days', 0)
            ->where('stats.spent', '0.00')
            ->where('current', null)
            ->where('next', null)
            ->has('rentals', 0));
});

test('dashboard read failure shows a message instead of an error page', function () {
    Exceptions::fake();

    $this->mock(UserPanelRepositoryInterface::class)
        ->shouldReceive('rentalsForUser')
        ->andThrow(new RuntimeException('Baza niedostępna'));

    $this->actingAs(User::factory()->create())
        ->get(route('user.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('failed', true)->where('stats.spent', '0.00'));

    Exceptions::assertReported(RuntimeException::class);
});

test('dashboard drops rows of another owner', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->mock(UserPanelRepositoryInterface::class)
        ->shouldReceive('rentalsForUser')
        ->andReturn([[
            'id' => 1,
            'user_id' => $other->id,
            'brand' => 'Toyota',
            'model' => 'Corolla',
            'start_date' => now()->subDays(5),
            'end_date' => now()->subDays(3),
            'status' => ReservationStatus::Confirmed,
            'confirmation_deadline' => now(),
            'daily_rate' => '100.00',
            'total_price' => '200.00',
            'year' => 2026,
        ]]);

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('stats.rentals', 0)->has('rentals', 0));
});

test('dashboard contains no private data', function () {
    $user = User::factory()->create();
    activeRental($user);

    $this->actingAs($user)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('current.user_id')
            ->missing('current.car_id')
            ->missing('current.vin'));
});
