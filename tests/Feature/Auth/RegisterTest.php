<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

function registrationData(array $overrides = []): array
{
    return [
        'name' => 'Jan Kowalski',
        'email' => 'jan@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '+48 600 100 200',
        'street' => 'Marszałkowska',
        'building_number' => '10',
        'apartment_number' => '5',
        'postal_code' => '00-001',
        'city' => 'Warszawa',
        ...$overrides,
    ];
}

test('registration page can be rendered', function () {
    $this->get(route('register'))->assertOk();
});

test('user can register with phone and address', function () {
    Notification::fake();

    $this->post(route('register.store'), registrationData())
        ->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'jan@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->role)->toBe(UserRole::User)
        ->and($user->profile->phone)->toBe('+48 600 100 200')
        ->and($user->profile->city)->toBe('Warszawa')
        ->and($user->profile->apartment_number)->toBe('5')
        ->and($user->hasVerifiedEmail())->toBeFalse();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('role cannot be set during registration', function () {
    $this->post(route('register.store'), registrationData(['role' => 'administrator']));

    expect(User::where('email', 'jan@example.com')->firstOrFail()->role)
        ->toBe(UserRole::User);
});

test('registration requires valid phone and postal code', function () {
    $this->post(route('register.store'), registrationData([
        'phone' => 'abc',
        'postal_code' => '00001',
    ]))->assertSessionHasErrors(['phone', 'postal_code']);

    $this->assertGuest();
    expect(User::count())->toBe(0);
});

test('email must be unique', function () {
    User::factory()->create(['email' => 'jan@example.com']);

    $this->post(route('register.store'), registrationData())
        ->assertSessionHasErrors('email');
});
