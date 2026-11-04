<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserProfile;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Hash;

test('seeder creates administrator and user accounts with hashed passwords', function (string $email, UserRole $role) {
    $this->seed(UserSeeder::class);

    $user = User::query()->where('email', $email)->firstOrFail();

    expect($user->role)->toBe($role)
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->password)->not->toBe('qwer1234')
        ->and(Hash::check('qwer1234', $user->password))->toBeTrue();
})->with([
    'administrator' => ['admin_kwsmis@mis.pl', UserRole::Administrator],
    'użytkownik' => ['user_kwsmis@mis.pl', UserRole::User],
]);

test('seeded accounts can log in and land on their home route', function (string $email, string $route) {
    $this->seed(UserSeeder::class);

    $this->post(route('login.store'), [
        'email' => $email,
        'password' => 'qwer1234',
    ])->assertRedirect(route($route, absolute: false));
})->with([
    'administrator' => ['admin_kwsmis@mis.pl', 'admin.dashboard'],
    'użytkownik' => ['user_kwsmis@mis.pl', 'home'],
]);

test('seeded accounts have address data in user profiles', function (string $email) {
    $this->seed(UserSeeder::class);

    $profile = User::query()->where('email', $email)->firstOrFail()->profile;

    expect($profile)->not->toBeNull()
        ->and($profile->phone)->not->toBeEmpty()
        ->and($profile->street)->not->toBeEmpty()
        ->and($profile->building_number)->not->toBeEmpty()
        ->and($profile->postal_code)->toMatch('/^\d{2}-\d{3}$/')
        ->and($profile->city)->toBe('Krosno');
})->with(['admin_kwsmis@mis.pl', 'user_kwsmis@mis.pl']);

test('running the seeder twice does not duplicate accounts or profiles', function () {
    $this->seed(UserSeeder::class);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(2)
        ->and(UserProfile::query()->count())->toBe(2);
});
