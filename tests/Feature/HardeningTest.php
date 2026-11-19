<?php

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Middleware\SecurityHeaders;
use App\Models\AuditLog;
use App\Models\Car;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\TwoFactorCode;
use App\Repositories\Contracts\ReservationPaymentRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use App\Services\TwoFactorService;
use App\Support\CookieConsent;
use Carbon\CarbonInterface;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function enabledTwoFactor(User $user): User
{
    $user->forceFill(['two_factor_enabled_at' => now()])->save();

    return $user;
}

/** Ostatni kod wysłany na adres konta, odczytany z przechwyconej wiadomości. */
function sentCode(User $user): string
{
    $notification = Notification::sent($user, TwoFactorCode::class)->last();

    return (new ReflectionProperty($notification, 'code'))->getValue($notification);
}

/** Dodaje urządzenie i zwraca jego token. */
function trustedDevice(User $user, ?CarbonInterface $expiresAt = null): string
{
    $token = Str::random(64);

    TrustedDevice::query()->create([
        'user_id' => $user->id,
        'token_hash' => hash('sha256', $token),
        'name' => 'Chrome na Windows',
        'last_used_at' => now(),
        'expires_at' => $expiresAt ?? now()->addDays(30),
    ]);

    return $token;
}

function deviceToken(TrustedDevice $device): string
{
    $token = Str::random(64);
    $device->update(['token_hash' => hash('sha256', $token)]);

    return $token;
}

function hardeningReservation(array $overrides = []): Reservation
{
    Storage::fake('public');

    if (Car::query()->count() === 0) {
        test()->seed([LocationSeeder::class, CarSeeder::class]);
    }

    return Reservation::query()->create([
        'user_id' => User::factory()->create()->id,
        'car_id' => Car::query()->orderBy('id')->value('id'),
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

// Rezerwacja tylko dla zalogowanego klienta

test('guest cannot open or submit a booking and returns to the form after login', function () {
    Storage::fake('public');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
    $car = Car::query()->where('required_license', 'b')->orderBy('id')->firstOrFail();
    $user = User::factory()->create();

    $this->get(route('cars.book', $car->id))->assertRedirect(route('login'));
    $this->post(route('cars.book.store', $car->id), [])->assertRedirect(route('login'));

    expect(Reservation::query()->count())->toBe(0);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('cars.book', $car->id, absolute: false));
});

test('booking needs a verified client account', function () {
    Storage::fake('public');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
    $car = Car::query()->orderBy('id')->firstOrFail();

    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('cars.book', $car->id))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('cars.book.store', $car->id), [])
        ->assertForbidden();
});

// Pierwszy administrator

test('command creates an administrator', function () {
    $this->artisan('admin:create', ['email' => 'szef@example.com', 'name' => 'Szef'])
        ->expectsQuestion('Hasło', 'Trudne-Haslo-123!')
        ->assertSuccessful();

    $admin = User::query()->where('email', 'szef@example.com')->sole();

    expect($admin->role)->toBe(UserRole::Administrator)
        ->and($admin->hasVerifiedEmail())->toBeTrue()
        ->and(password_verify('Trudne-Haslo-123!', $admin->password))->toBeTrue();
});

test('command refuses a taken address', function () {
    User::factory()->create(['email' => 'szef@example.com']);

    $this->artisan('admin:create', ['email' => 'szef@example.com', 'name' => 'Szef'])
        ->expectsQuestion('Hasło', 'Trudne-Haslo-123!')
        ->assertFailed();

    expect(User::query()->count())->toBe(1);
});

// Szyfrowanie

test('profile and licence number are encrypted in the database', function () {
    $user = User::factory()->create();
    $user->profile()->save(new UserProfile(['phone' => '600100200', 'street' => 'Lwowska', 'building_number' => '1', 'apartment_number' => '2', 'postal_code' => '38-400', 'city' => 'Krosno']));
    $reservation = hardeningReservation(['driver_license_number' => 'ABC 123']);

    $row = (array) DB::table('user_profiles')->first();

    foreach (['phone' => '600100200', 'street' => 'Lwowska', 'city' => 'Krosno', 'postal_code' => '38-400'] as $column => $plain) {
        expect($row[$column])->not->toContain($plain);
    }

    expect(DB::table('reservations')->value('driver_license_number'))->not->toContain('ABC')
        ->and($user->refresh()->profile->phone)->toBe('600100200')
        ->and($reservation->refresh()->driver_license_number)->toBe('ABC 123');
});

test('licence number from the handover is encrypted too', function () {
    $reservation = hardeningReservation(['status' => ReservationStatus::Confirmed]);
    $category = Car::query()->findOrFail($reservation->car_id)->required_license->value;

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.handover', $reservation->id), ['driver_name' => 'Jan', 'driver_license_number' => 'XYZ 987', 'driver_license_category' => $category])
        ->assertSessionHasNoErrors();

    expect(DB::table('reservations')->value('driver_license_number'))->not->toContain('XYZ')
        ->and($reservation->refresh()->driver_license_number)->toBe('XYZ 987');
});

test('two clients may share a phone number', function () {
    foreach ([1, 2] as $number) {
        User::factory()->create()->profile()->save(new UserProfile(['phone' => '600100200', 'street' => 'A', 'building_number' => '1', 'postal_code' => '38-400', 'city' => 'B']));
    }

    expect(UserProfile::query()->count())->toBe(2);
});

// CSP

test('content security policy works on production only', function () {
    $response = fn () => (new SecurityHeaders)->handle(Request::create('/'), fn () => response('ok'));

    expect($response()->headers->has('Content-Security-Policy'))->toBeFalse();

    app()->detectEnvironment(fn () => 'production');
    $policy = $response()->headers->get('Content-Security-Policy');
    app()->detectEnvironment(fn () => 'testing');

    expect($policy)->toContain("default-src 'self'", "script-src 'self' 'nonce-", "object-src 'none'", 'tile.openstreetmap.org')
        ->and($policy)->not->toContain("script-src 'self' 'unsafe-inline'");
});

// Logowanie dwuetapowe kodem z e-maila

test('account has no second step until its owner adds it', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $this->assertAuthenticatedAs($user);
});

test('password alone does not open an account with two factor and a code is emailed', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->admin()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('login.code'));

    $this->assertGuest();
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    Notification::assertSentTo($user, TwoFactorCode::class);
});

test('correct code finishes the login and is logged', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->admin()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.verify'), ['code' => sentCode($user)])
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(AuditLog::query()->where('action', 'auth.login')->count())->toBe(1);
});

test('code is stored only as a hash', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect(Cache::get('two-factor:code:'.$user->id))->not->toBe(sentCode($user));
});

test('wrong code does not log in and five mistakes burn the code', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.code.verify'), ['code' => sentCode($user) === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');
    }

    $this->post(route('login.code.verify'), ['code' => sentCode($user)])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('code cannot be used twice', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $code = sentCode($user);
    $this->post(route('login.code.verify'), ['code' => $code]);
    $this->post(route('logout'));

    $this->travel(2)->minutes();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    Cache::forget('two-factor:code:'.$user->id);
    $this->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('code of one account does not open another', function () {
    Notification::fake();
    $first = enabledTwoFactor(User::factory()->create());
    $second = enabledTwoFactor(User::factory()->create());

    $this->post(route('login.store'), ['email' => $first->email, 'password' => 'password']);
    $code = sentCode($first);
    $this->post(route('logout'));
    session()->flush();

    $this->post(route('login.store'), ['email' => $second->email, 'password' => 'password']);

    if ($code !== sentCode($second)) {
        $this->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');
    }

    $this->assertGuest();
});

test('pending login expires', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $code = sentCode($user);
    $this->travel(11)->minutes();

    $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('login'));

    $this->assertGuest();
});

test('challenge needs a correct password first', function () {
    $this->get(route('login.code'))->assertRedirect(route('login'));
    $this->post(route('login.code.verify'), ['code' => '123456'])->assertRedirect(route('login'));
    $this->post(route('login.code.resend'))->assertRedirect(route('login'));
});

test('codes are sent at most once a minute', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.resend'));

    Notification::assertSentToTimes($user, TwoFactorCode::class, 1);

    $this->travel(61)->seconds();
    $this->post(route('login.code.resend'));

    Notification::assertSentToTimes($user, TwoFactorCode::class, 2);
});

test('code is sent right away after the previous one was used', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('security.code'));
    $this->post(route('security.enable'), ['code' => sentCode($user)]);
    $this->post(route('logout'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('login.code'));

    Notification::assertSentToTimes($user, TwoFactorCode::class, 2);
});

test('mail failure does not open the account', function () {
    $user = enabledTwoFactor(User::factory()->create());
    Notification::shouldReceive('sendNow')->andThrow(new RuntimeException('smtp'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

// Włączanie i wyłączanie

test('owner adds the protection after confirming a code from the mailbox', function (Closure $account) {
    Notification::fake();
    $user = $account();

    $this->actingAs($user)->get(route('security.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->where('enabled', false));

    $this->post(route('security.code'))->assertRedirect(route('security.index'));
    $this->post(route('security.enable'), ['code' => sentCode($user) === '000000' ? '111111' : '000000'])->assertSessionHasErrors('code');

    expect($user->refresh()->hasTwoFactor())->toBeFalse();

    $this->post(route('security.enable'), ['code' => sentCode($user)])->assertSessionHasNoErrors();

    expect($user->refresh()->hasTwoFactor())->toBeTrue();
})->with([
    'klient' => [fn () => User::factory()->create()],
    'administrator' => [fn () => User::factory()->admin()->create()],
]);

test('settings page matches the role', function () {
    $this->actingAs(User::factory()->create())->get(route('security.index'))->assertInertia(fn (Assert $page) => $page->component('user/security'));
    $this->actingAs(User::factory()->admin()->create())->get(route('security.index'))->assertInertia(fn (Assert $page) => $page->component('admin/security/index'));
});

test('guest and unverified account cannot reach the settings', function () {
    $this->get(route('security.index'))->assertRedirect(route('login'));
    $this->post(route('security.code'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())
        ->post(route('security.code'))
        ->assertRedirect(route('verification.notice'));
});

test('disabling needs the password and removes trusted devices', function () {
    $user = enabledTwoFactor(User::factory()->create());
    trustedDevice($user);

    $this->actingAs($user);

    $this->delete(route('security.disable'), ['password' => 'zle'])->assertSessionHasErrors('password');

    expect($user->refresh()->hasTwoFactor())->toBeTrue();

    $this->delete(route('security.disable'), ['password' => 'password'])->assertSessionHasNoErrors();

    expect($user->refresh()->hasTwoFactor())->toBeFalse()
        ->and(TrustedDevice::query()->count())->toBe(0);
});

test('command helps an account that lost its mailbox', function () {
    $user = enabledTwoFactor(User::factory()->create());
    trustedDevice($user);

    $this->artisan('two-factor:disable', ['email' => $user->email])->assertSuccessful();

    expect($user->refresh()->hasTwoFactor())->toBeFalse()
        ->and(TrustedDevice::query()->count())->toBe(0);

    $this->artisan('two-factor:disable', ['email' => 'brak@example.com'])->assertFailed();
});

// Zaufane urządzenia

test('device is added only when the owner asks for it', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.verify'), ['code' => sentCode($user)])->assertCookieMissing(TwoFactorService::COOKIE);

    expect(TrustedDevice::query()->count())->toBe(0);
});

test('trusted device skips the code and the token is stored as a hash', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->withCookie(CookieConsent::COOKIE, CookieConsent::encode(['functional' => true, 'analytics' => false, 'marketing' => false]));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $response = $this->post(route('login.code.verify'), ['code' => sentCode($user), 'trust' => true])
        ->assertCookie(TwoFactorService::COOKIE);

    $cookie = collect($response->headers->getCookies())->first(fn ($item) => $item->getName() === TwoFactorService::COOKIE);
    $device = TrustedDevice::query()->sole();

    expect($cookie->isHttpOnly())->toBeTrue()
        ->and($device->user_id)->toBe($user->id)
        ->and($device->expires_at->diffInDays(now(), true))->toBeGreaterThan(29.0)
        ->and(json_encode($device->toArray()))->not->toContain($device->token_hash);

    $this->post(route('logout'));

    Notification::fake();
    $this->withCookie(TwoFactorService::COOKIE, deviceToken($device))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect();

    $this->assertAuthenticatedAs($user);
    Notification::assertNothingSent();
});

test('device login still needs the correct password', function () {
    $user = enabledTwoFactor(User::factory()->create());
    $token = trustedDevice($user);

    $this->withCookie(CookieConsent::COOKIE, CookieConsent::encode(['functional' => true, 'analytics' => false, 'marketing' => false]))
        ->withCookie(TwoFactorService::COOKIE, $token)
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'zle'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('wrong, foreign or expired device does not skip the code', function (Closure $cookie) {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());

    $this->withCookie(CookieConsent::COOKIE, CookieConsent::encode(['functional' => true, 'analytics' => false, 'marketing' => false]))
        ->withCookie(TwoFactorService::COOKIE, $cookie($user))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('login.code'));

    $this->assertGuest();
})->with([
    'obcy token' => [fn () => 'zmyslony-token'],
    'urządzenie innego konta' => [fn () => trustedDevice(User::factory()->create())],
    'wygasłe' => [fn (User $user) => trustedDevice($user, now()->subDay())],
]);

test('owner removes own devices only', function () {
    $user = enabledTwoFactor(User::factory()->create());
    $other = enabledTwoFactor(User::factory()->create());
    trustedDevice($user);
    trustedDevice($other);
    $foreign = TrustedDevice::query()->where('user_id', $other->id)->sole();
    $own = TrustedDevice::query()->where('user_id', $user->id)->sole();

    $this->actingAs($user);

    $this->delete(route('security.devices.destroy', $foreign->id))->assertNotFound();
    $this->delete(route('security.devices.destroy', $own->id))->assertRedirect();
    $this->delete(route('security.devices.clear'))->assertRedirect();

    expect(TrustedDevice::query()->pluck('user_id')->all())->toBe([$other->id]);
});

test('settings list only own devices without tokens', function () {
    $user = enabledTwoFactor(User::factory()->create());
    trustedDevice($user);
    trustedDevice(User::factory()->create());

    $this->actingAs($user)
        ->get(route('security.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('devices', 1)
            ->missing('devices.0.token_hash'));
});

test('password reset removes trusted devices', function () {
    $user = enabledTwoFactor(User::factory()->create());
    trustedDevice($user);

    $this->post(route('password.update'), ['token' => Password::createToken($user), 'email' => $user->email, 'password' => 'NoweHaslo123!', 'password_confirmation' => 'NoweHaslo123!'])
        ->assertRedirect(route('login'));

    expect(TrustedDevice::query()->count())->toBe(0);
});

test('expired devices are pruned', function () {
    $user = User::factory()->create();
    trustedDevice($user, now()->subDay());
    trustedDevice($user);

    $this->artisan('model:prune', ['--model' => [TrustedDevice::class]])->assertSuccessful();

    expect(TrustedDevice::query()->count())->toBe(1);
});

// Dziennik

test('admin changes are logged without form content', function () {
    $reservation = hardeningReservation();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.reservations.confirm', $reservation->id), ['secret_note' => 'poufne']);

    $log = AuditLog::query()->sole();

    expect($log->user_id)->toBe($admin->id)
        ->and($log->action)->toBe('admin.reservations.confirm')
        ->and($log->subject)->toBe("id={$reservation->id}")
        ->and($log->succeeded)->toBeTrue()
        ->and(json_encode($log->toArray()))->not->toContain('poufne');
});

test('failed admin action is logged as failed and reads are not logged', function () {
    $reservation = hardeningReservation(['status' => ReservationStatus::Cancelled]);

    $this->actingAs(User::factory()->admin()->create())->get(route('admin.reservations.index'));

    expect(AuditLog::query()->count())->toBe(0);

    $this->post(route('admin.reservations.confirm', $reservation->id));

    expect(AuditLog::query()->sole()->succeeded)->toBeFalse();
});

test('administrator reads the log, a user cannot', function () {
    AuditLog::query()->create(['action' => 'admin.test', 'succeeded' => true, 'created_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.audit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/audit')->has('entries', 1));

    $this->actingAs(User::factory()->create())->get(route('admin.audit'))->assertForbidden();
});

test('old log entries are pruned after a year', function () {
    AuditLog::query()->create(['action' => 'stary', 'succeeded' => true, 'created_at' => now()->subMonths(13)]);
    AuditLog::query()->create(['action' => 'nowy', 'succeeded' => true, 'created_at' => now()->subMonths(2)]);

    $this->artisan('model:prune', ['--model' => [AuditLog::class]])->assertSuccessful();

    expect(AuditLog::query()->pluck('action')->all())->toBe(['nowy']);
});

// Wyścigi

test('stale confirm is refused when the reservation is gone or decided', function () {
    $reservation = hardeningReservation();
    $repository = app(ReservationRepositoryInterface::class);
    $stale = Reservation::query()->findOrFail($reservation->id);

    $reservation->delete();
    expect($repository->confirm($stale))->toBeFalse();

    $other = hardeningReservation(['status' => ReservationStatus::Cancelled]);
    expect($repository->confirm($other))->toBeFalse();
});

test('stale cancel is refused after the car was handed over', function () {
    $reservation = hardeningReservation(['status' => ReservationStatus::Confirmed]);
    $stale = Reservation::query()->findOrFail($reservation->id);

    $reservation->update(['picked_up_at' => now()]);

    expect(app(ReservationRepositoryInterface::class)->cancel($stale))->toBeFalse()
        ->and($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
});

test('stale removal is refused after a deposit was recorded', function () {
    $reservation = hardeningReservation();
    $stale = Reservation::query()->findOrFail($reservation->id);

    ReservationPayment::query()->create(['reservation_id' => $reservation->id, 'type' => 'deposit', 'payer' => 'Jan', 'amount' => '100.00', 'method' => 'Gotówka', 'paid_at' => now()]);

    expect(app(ReservationRepositoryInterface::class)->remove($stale, true))->toBeFalse()
        ->and(Reservation::query()->count())->toBe(1);
});

test('payment limit is checked again when saving', function () {
    $reservation = hardeningReservation();
    $repository = app(ReservationPaymentRepositoryInterface::class);
    $attributes = ['type' => 'advance', 'payer' => 'Jan', 'amount' => '300.00', 'method' => 'Gotówka', 'paid_at' => now()];

    expect($repository->createWithinLimit($reservation->id, $attributes, 500))->not->toBeNull()
        ->and($repository->createWithinLimit($reservation->id, $attributes, 500))->toBeNull()
        ->and(ReservationPayment::query()->count())->toBe(1);
});

// Reset hasła

test('reset link is sent and the answer does not reveal accounts', function () {
    Notification::fake();
    $user = User::factory()->create();

    $known = $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email]);
    $unknown = $this->from(route('password.request'))->post(route('password.email'), ['email' => 'brak@example.com']);

    Notification::assertSentTo($user, ResetPassword::class);

    expect($known->getSession()->get('status'))->toBe($unknown->getSession()->get('status'));
});

test('password can be reset with a valid token only', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    DB::table('sessions')->insert(['id' => 'stara', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);

    $this->post(route('password.update'), ['token' => 'zly', 'email' => $user->email, 'password' => 'NoweHaslo123!', 'password_confirmation' => 'NoweHaslo123!'])
        ->assertSessionHasErrors('email');

    $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'NoweHaslo123!', 'password_confirmation' => 'NoweHaslo123!'])
        ->assertRedirect(route('login'));

    config(['session.driver' => 'database']);

    expect(password_verify('NoweHaslo123!', $user->refresh()->password))->toBeTrue();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'NoweHaslo123!']);
    $this->assertAuthenticatedAs($user);
});

test('token works only once', function () {
    $user = User::factory()->create();
    $token = Password::createToken($user);
    $data = ['token' => $token, 'email' => $user->email, 'password' => 'NoweHaslo123!', 'password_confirmation' => 'NoweHaslo123!'];

    $this->post(route('password.update'), $data)->assertRedirect(route('login'));
    $this->post(route('password.update'), [...$data, 'password' => 'Inne123!Haslo', 'password_confirmation' => 'Inne123!Haslo'])->assertSessionHasErrors('email');
});

test('password reset ends existing sessions', function () {
    config(['session.driver' => 'database']);
    $user = User::factory()->create();
    DB::table('sessions')->insert(['id' => 'stara', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()]);

    Password::reset(
        ['email' => $user->email, 'password' => 'NoweHaslo123!', 'password_confirmation' => 'NoweHaslo123!', 'token' => Password::createToken($user)],
        function (User $resetUser) {
            app(UserSecurityRepositoryInterface::class)->forgetSessions($resetUser);
        },
    );

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0);
});

test('password reset does not bypass two factor', function () {
    Notification::fake();
    $user = enabledTwoFactor(User::factory()->create());
    $token = Password::createToken($user);

    $this->post(route('password.update'), ['token' => $token, 'email' => $user->email, 'password' => 'NoweHaslo123!', 'password_confirmation' => 'NoweHaslo123!']);
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'NoweHaslo123!'])->assertRedirect(route('login.code'));

    $this->assertGuest();
});

test('reset attempts are limited', function () {
    foreach (range(1, 5) as $attempt) {
        $this->post(route('password.email'), ['email' => 'zly']);
    }

    $this->post(route('password.email'), ['email' => 'zly'])->assertTooManyRequests();
});
