<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

dataset('admin panel pages', [
    'pulpit' => [fn () => route('admin.dashboard'), 'admin/dashboard'],
    'aktualne rezerwacje' => [fn () => route('admin.reservations.index'), 'admin/reservations/index'],
    'historia rezerwacji' => [fn () => route('admin.reservations.history'), 'admin/reservations/history'],
    'zrealizowane' => [fn () => route('admin.reservations.completed'), 'admin/reservations/completed'],
    'edycja rezerwacji' => [fn () => route('admin.reservations.edit', 130), 'admin/reservations/edit'],
    'klienci' => [fn () => route('admin.clients.index'), 'admin/clients/index'],
    'dane klienta' => [fn () => route('admin.clients.show', 2), 'admin/clients/show'],
    'samochody' => [fn () => route('admin.cars.index'), 'admin/cars/index'],
    'dodaj samochód' => [fn () => route('admin.cars.create'), 'admin/cars/create'],
    'historia wypożyczeń' => [fn () => route('admin.cars.history'), 'admin/cars/history'],
    'wyposażenie i kolory' => [fn () => route('admin.dictionary.index'), 'admin/dictionary/index'],
    'miejsca' => [fn () => route('admin.locations.index'), 'admin/locations/index'],
]);

test('administrator can open admin panel pages', function (Closure $url, string $component) {
    $this->actingAs(User::factory()->admin()->create())
        ->get($url())
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with('admin panel pages');

test('user cannot open admin panel pages', function (Closure $url) {
    $this->actingAs(User::factory()->create())
        ->get($url())
        ->assertForbidden();
})->with('admin panel pages');

test('edit and show pages receive the id from the url', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reservations.edit', 130))
        ->assertInertia(fn (Assert $page) => $page->where('id', 130));

    $this->actingAs($admin)
        ->get(route('admin.clients.show', 2))
        ->assertInertia(fn (Assert $page) => $page->where('id', 2));
});

test('car history receives the selected car from the query string', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.cars.history', ['car' => 3]))
        ->assertInertia(fn (Assert $page) => $page->where('carId', 3));

    $this->actingAs($admin)
        ->get(route('admin.cars.history'))
        ->assertInertia(fn (Assert $page) => $page->where('carId', null));
});
