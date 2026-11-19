<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

dataset('legal pages', [
    'cookies' => ['cookies', 'Cookies'],
    'regulamin' => ['terms', 'Regulamin'],
    'polityka prywatności' => ['privacy', 'Polityka prywatności'],
]);

test('legal page is available to guests', function (string $route, string $title) {
    $this->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('placeholder')->where('title', $title));
})->with('legal pages');

test('legal page is available to logged in accounts', function (string $route) {
    $this->actingAs(User::factory()->create())->get(route($route))->assertOk();
    $this->actingAs(User::factory()->unverified()->create())->get(route($route))->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get(route($route))->assertOk();
})->with('legal pages');
