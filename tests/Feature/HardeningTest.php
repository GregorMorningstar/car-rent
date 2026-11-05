<?php

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Http\Middleware\SecurityHeaders;
use App\Models\AuditLog;
use App\Models\Car;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use App\Models\UserProfile;
use App\Repositories\Contracts\ReservationPaymentRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use App\Support\Totp;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function enabledTwoFactor(User $user, string $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', array $recovery = []): User
{
    $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => $recovery])->save();

    return $user;
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

// TOTP

test('codes match the RFC 6238 test vector', function () {
    expect(Totp::code('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 1))->toBe('287082');
});

test('code is accepted one step around the current time only', function () {
    $this->travelTo('2026-06-17 12:00:00');
    $secret = Totp::generateSecret();
    $step = Totp::currentStep();

    expect(Totp::matchingStep($secret, Totp::code($secret, $step)))->toBe($step)
        ->and(Totp::matchingStep($secret, Totp::code($secret, $step + 1)))->toBe($step + 1)
        ->and(Totp::matchingStep($secret, Totp::code($secret, $step + 5)))->toBeNull();
});

// Logowanie dwuetapowe

test('password alone does not open an account with two factor', function () {
    $user = enabledTwoFactor(User::factory()->admin()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('login.code'));

    $this->assertGuest();
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('correct code finishes the login', function () {
    $this->travelTo('2026-06-17 12:00:00');
    $user = enabledTwoFactor(User::factory()->admin()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.verify'), ['code' => Totp::code($user->two_factor_secret, Totp::currentStep())])
        ->assertRedirect(route('admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
    expect(AuditLog::query()->where('action', 'auth.login')->count())->toBe(1);
});

test('wrong code does not log in and attempts are limited', function () {
    $this->travelTo('2026-06-17 12:00:00');
    $user = enabledTwoFactor(User::factory()->admin()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    foreach (range(1, 5) as $attempt) {
        $this->post(route('login.code.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
    }

    // Poprawny kod po wyczerpaniu prób też jest odrzucany.
    $this->post(route('login.code.verify'), ['code' => Totp::code($user->two_factor_secret, Totp::currentStep())])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('code cannot be used twice', function () {
    $this->travelTo('2026-06-17 12:00:00');
    $user = enabledTwoFactor(User::factory()->admin()->create());
    $code = Totp::code($user->two_factor_secret, Totp::currentStep());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.verify'), ['code' => $code]);
    $this->post(route('logout'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.verify'), ['code' => $code])->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('recovery code works once', function () {
    $hash = hash_hmac('sha256', 'abcde-fghij', (string) config('app.key'));
    $user = enabledTwoFactor(User::factory()->admin()->create(), recovery: [$hash]);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.verify'), ['code' => 'ABCDE-FGHIJ'])->assertRedirect();
    $this->assertAuthenticatedAs($user);
    $this->post(route('logout'));

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->post(route('login.code.verify'), ['code' => 'abcde-fghij'])->assertSessionHasErrors('code');
    $this->assertGuest();
});

test('pending login expires', function () {
    $this->travelTo('2026-06-17 12:00:00');
    $user = enabledTwoFactor(User::factory()->admin()->create());

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);
    $this->travel(6)->minutes();

    $this->post(route('login.code.verify'), ['code' => Totp::code($user->two_factor_secret, Totp::currentStep())])
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('challenge page needs a correct password first', function () {
    $this->get(route('login.code'))->assertRedirect(route('login'));
    $this->post(route('login.code.verify'), ['code' => '123456'])->assertRedirect(route('login'));
});

test('administrator enables two factor and sees recovery codes once', function () {
    $this->travelTo('2026-06-17 12:00:00');
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('admin.security.start'))->assertRedirect();

    $admin->refresh();

    expect($admin->hasTwoFactor())->toBeFalse()
        ->and(DB::table('users')->where('id', $admin->id)->value('two_factor_secret'))->not->toBe($admin->two_factor_secret);

    $this->post(route('admin.security.confirm'), ['code' => '000000'])->assertSessionHasErrors('code');
    $this->post(route('admin.security.confirm'), ['code' => Totp::code($admin->two_factor_secret, Totp::currentStep())])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('recovery_codes', fn (array $codes) => count($codes) === 8);

    expect($admin->refresh()->hasTwoFactor())->toBeTrue();

    $this->get(route('admin.security.index'))
        ->assertInertia(fn (Assert $page) => $page->where('enabled', true)->where('secret', null)->where('otpauth', null));
});

test('disabling two factor needs the password and a code', function () {
    $this->travelTo('2026-06-17 12:00:00');
    $admin = enabledTwoFactor(User::factory()->admin()->create());
    $code = Totp::code($admin->two_factor_secret, Totp::currentStep());

    $this->actingAs($admin);

    $this->delete(route('admin.security.disable'), ['password' => 'zle', 'code' => $code])->assertSessionHasErrors('password');
    $this->delete(route('admin.security.disable'), ['password' => 'password', 'code' => '000000'])->assertSessionHasErrors('code');

    expect($admin->refresh()->hasTwoFactor())->toBeTrue();

    $this->delete(route('admin.security.disable'), ['password' => 'password', 'code' => $code])->assertSessionHasNoErrors();

    expect($admin->refresh()->hasTwoFactor())->toBeFalse();
});

test('two factor data never reaches the page', function () {
    $admin = enabledTwoFactor(User::factory()->admin()->create());

    $this->actingAs($admin)
        ->get(route('admin.security.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->missing('auth.user.two_factor_secret')
            ->missing('auth.user.two_factor_recovery_codes'));
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
        ->get(route('admin.security.audit'))
        ->assertInertia(fn (Assert $page) => $page->component('admin/security/audit')->has('entries', 1));

    $this->actingAs(User::factory()->create())->get(route('admin.security.audit'))->assertForbidden();
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
