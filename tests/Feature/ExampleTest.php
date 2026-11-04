<?php

use App\Models\User;

test('returns a successful response', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertOk();
});
