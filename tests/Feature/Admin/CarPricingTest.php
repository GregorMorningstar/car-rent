<?php

use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('public'));

function pricedCarData(array $prices, array $overrides = []): array
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
        'deposit_amount' => '1000.00',
        'operational_status' => 'active',
        'prices' => $prices,
        ...$overrides,
    ];
}

function tiers(): array
{
    return [
        ['min_days' => 1, 'max_days' => 1, 'daily_rate' => '500'],
        ['min_days' => 2, 'max_days' => 3, 'daily_rate' => '450'],
        ['min_days' => 4, 'max_days' => null, 'daily_rate' => '400'],
    ];
}

test('car is saved with price tiers and lowest rate', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), pricedCarData(tiers()))
        ->assertRedirect(route('admin.cars.index'));

    $car = Car::query()->sole();

    expect($car->daily_rate)->toBe('400.00')
        ->and($car->prices->map(fn ($price) => [$price->min_days, $price->max_days, $price->daily_rate])->all())
        ->toBe([[1, 1, '500.00'], [2, 3, '450.00'], [4, null, '400.00']]);
});

test('single tier for every length is allowed', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), pricedCarData([['min_days' => 1, 'max_days' => '', 'daily_rate' => '300']]))
        ->assertSessionHasNoErrors();

    expect(Car::query()->sole()->prices)->toHaveCount(1);
});

test('tiers may be sent in any order', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), pricedCarData(array_reverse(tiers())))
        ->assertSessionHasNoErrors();

    expect(Car::query()->sole()->prices->pluck('min_days')->all())->toBe([1, 2, 4]);
});

test('editing replaces price tiers and updates lowest rate', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('admin.cars.store'), pricedCarData(tiers()));
    $car = Car::query()->sole();

    $this->post(route('admin.cars.update', $car->id), pricedCarData([
        ['min_days' => 1, 'max_days' => 6, 'daily_rate' => '350'],
        ['min_days' => 7, 'max_days' => null, 'daily_rate' => '300'],
    ]))->assertSessionHasNoErrors();

    $car->refresh();

    expect($car->daily_rate)->toBe('300.00')
        ->and($car->prices->pluck('min_days')->all())->toBe([1, 7]);
});

test('price tiers must cover every rental length', function (array $prices, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), pricedCarData($prices))
        ->assertSessionHasErrors($field);

    expect(Car::query()->count())->toBe(0);
})->with([
    'brak progów' => [[], 'prices'],
    'nie od 1 dnia' => [[['min_days' => 2, 'max_days' => null, 'daily_rate' => '400']], 'prices'],
    'przerwa' => [[
        ['min_days' => 1, 'max_days' => 2, 'daily_rate' => '500'],
        ['min_days' => 4, 'max_days' => null, 'daily_rate' => '400'],
    ], 'prices'],
    'nakładanie się' => [[
        ['min_days' => 1, 'max_days' => 3, 'daily_rate' => '500'],
        ['min_days' => 3, 'max_days' => null, 'daily_rate' => '400'],
    ], 'prices'],
    'ostatni z limitem' => [[['min_days' => 1, 'max_days' => 3, 'daily_rate' => '500']], 'prices'],
    'limit w środku' => [[
        ['min_days' => 1, 'max_days' => null, 'daily_rate' => '500'],
        ['min_days' => 2, 'max_days' => null, 'daily_rate' => '400'],
    ], 'prices'],
    'do mniejsze niż od' => [[
        ['min_days' => 1, 'max_days' => 1, 'daily_rate' => '500'],
        ['min_days' => 2, 'max_days' => 1, 'daily_rate' => '450'],
        ['min_days' => 2, 'max_days' => null, 'daily_rate' => '400'],
    ], 'prices'],
    'ujemna cena' => [[['min_days' => 1, 'max_days' => null, 'daily_rate' => '-1']], 'prices.0.daily_rate'],
    'brak ceny' => [[['min_days' => 1, 'max_days' => null, 'daily_rate' => '']], 'prices.0.daily_rate'],
    'tekst zamiast dni' => [[['min_days' => 'a', 'max_days' => null, 'daily_rate' => '100']], 'prices.0.min_days'],
]);

test('edit form gets price tiers', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('admin.cars.store'), pricedCarData(tiers()));

    $this->get(route('admin.cars.edit', Car::query()->value('id')))
        ->assertInertia(fn (Assert $page) => $page->has('car.prices', 3));
});

test('regular user cannot change prices', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('admin.cars.store'), pricedCarData(tiers()));
    $car = Car::query()->sole();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.cars.update', $car->id), pricedCarData([['min_days' => 1, 'max_days' => null, 'daily_rate' => '1']]))
        ->assertForbidden();

    expect($car->refresh()->daily_rate)->toBe('400.00');
});

// Limit kilometrów

test('car can have a daily mileage limit or no limit', function (mixed $limit, ?int $expected) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), pricedCarData(tiers(), ['km_limit_per_day' => $limit]))
        ->assertSessionHasNoErrors();

    expect(Car::query()->sole()->km_limit_per_day)->toBe($expected);
})->with([
    'limit' => [300, 300],
    'bez limitu' => ['', null],
]);

test('mileage limit can be changed and removed', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('admin.cars.store'), pricedCarData(tiers(), ['km_limit_per_day' => 300]));
    $car = Car::query()->sole();

    $this->post(route('admin.cars.update', $car->id), pricedCarData(tiers(), ['km_limit_per_day' => 500]));
    expect($car->refresh()->km_limit_per_day)->toBe(500);

    $this->post(route('admin.cars.update', $car->id), pricedCarData(tiers(), ['km_limit_per_day' => '']));
    expect($car->refresh()->km_limit_per_day)->toBeNull();
});

test('mileage limit is validated', function (mixed $limit) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.cars.store'), pricedCarData(tiers(), ['km_limit_per_day' => $limit]))
        ->assertSessionHasErrors('km_limit_per_day');

    expect(Car::query()->count())->toBe(0);
})->with([0, -5, 100001, 'abc', 1.5]);

test('regular user cannot change mileage limit', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('admin.cars.store'), pricedCarData(tiers(), ['km_limit_per_day' => 300]));
    $car = Car::query()->sole();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.cars.update', $car->id), pricedCarData(tiers(), ['km_limit_per_day' => '']))
        ->assertForbidden();

    $this->withoutMiddleware(CheckRole::class)
        ->post(route('admin.cars.update', $car->id), pricedCarData(tiers(), ['km_limit_per_day' => '']))
        ->assertForbidden();

    expect($car->refresh()->km_limit_per_day)->toBe(300);
});

test('mileage limit is shown publicly', function () {
    $this->actingAs(User::factory()->admin()->create());
    $this->post(route('admin.cars.store'), pricedCarData(tiers(), ['km_limit_per_day' => 300]));

    $this->get(route('cars.show', Car::query()->value('id')))
        ->assertInertia(fn (Assert $page) => $page->where('car.km_limit_per_day', 300));
});
