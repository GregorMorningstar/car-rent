<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

dataset('user panel pages', [
    'pulpit' => ['user.dashboard', 'user/dashboard'],
    'historia wynajmu' => ['user.rentals', 'user/rentals'],
    'płatności' => ['user.payments', 'user/payments'],
    'profil' => ['user.profile', 'user/profile'],
]);

test('user can open user panel pages', function (string $route, string $component) {
    $this->actingAs(User::factory()->create())
        ->get(route($route))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with('user panel pages');

test('guests are redirected to login from the user panel', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with('user panel pages');

test('administrator cannot access the user panel', function (string $route) {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route($route))
        ->assertForbidden();
})->with('user panel pages');
