<?php

use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\Setting;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserProfile;
use App\Repositories\AccountRepository;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

function privacyClient(): User
{
    $user = User::factory()->create(['name' => 'Jan Kowalski', 'email' => 'jan@example.com']);
    $user->profile()->save(new UserProfile(['phone' => '600100200', 'street' => 'Lwowska', 'building_number' => '1', 'postal_code' => '38-400', 'city' => 'Krosno']));

    return $user;
}

function privacyReservation(User $user, array $overrides = []): Reservation
{
    Storage::fake('public');

    if (Car::query()->count() === 0) {
        test()->seed([LocationSeeder::class, CarSeeder::class]);
    }

    return Reservation::query()->create([
        'user_id' => $user->id,
        'car_id' => Car::query()->orderBy('id')->value('id'),
        'start_date' => now()->subDays(9),
        'end_date' => now()->subDays(7),
        'status' => ReservationStatus::Confirmed,
        'daily_rate' => '170.00',
        'deposit_amount' => '1000.00',
        'total_price' => '500.00',
        'confirmation_deadline' => now()->subDays(10),
        'picked_up_at' => now()->subDays(9),
        'returned_at' => now()->subDays(7),
        'driver_name' => 'Jan Kowalski',
        'driver_license_number' => 'ABC 123',
        'notes' => 'Prywatna uwaga',
        ...$overrides,
    ]);
}

function privacyRegistration(array $overrides = []): array
{
    return [
        'name' => 'Jan',
        'email' => 'nowy@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'phone' => '+48 600 100 200',
        'street' => 'Lwowska',
        'building_number' => '1',
        'postal_code' => '38-400',
        'city' => 'Krosno',
        ...$overrides,
    ];
}

// Akceptacja przy rejestracji

test('registration needs accepted terms', function (array $overrides) {
    $this->post(route('register.store'), privacyRegistration($overrides))->assertSessionHasErrors('terms');

    expect(User::query()->count())->toBe(0);
})->with([
    'brak' => [[]],
    'odmowa' => [['terms' => '0']],
]);

test('accepted terms are recorded', function () {
    Notification::fake();
    $this->travelTo('2026-06-17 12:00:00');

    $this->post(route('register.store'), privacyRegistration(['terms' => 'on']))->assertSessionHasNoErrors();

    expect(User::query()->sole()->terms_accepted_at->toDateString())->toBe('2026-06-17');
});

// Informacja na czacie

test('chat tells the client when messages go to Messenger', function (bool $enabled) {
    $client = User::factory()->create();

    if ($enabled) {
        Setting::query()->create(['key' => 'messenger.enabled', 'value' => '1']);
    }

    $this->actingAs($client)
        ->get(route('user.messages.index'))
        ->assertInertia(fn (Assert $page) => $page->where('forwarded', $enabled));
})->with([true, false]);

// Usuwanie konta

test('client deletes own account and personal data disappears', function () {
    $client = privacyClient();
    $reservation = privacyReservation($client);
    ReservationPayment::query()->create(['reservation_id' => $reservation->id, 'type' => 'deposit', 'payer' => 'Jan Kowalski', 'amount' => '100.00', 'method' => 'Gotówka', 'paid_at' => now(), 'refunded_amount' => '100.00', 'refunded_at' => now()]);
    $conversation = Conversation::query()->create(['user_id' => $client->id, 'last_message_at' => now()]);
    ChatMessage::query()->create(['conversation_id' => $conversation->id, 'sender_id' => $client->id, 'body' => 'Dzień dobry']);
    TrustedDevice::query()->create(['user_id' => $client->id, 'token_hash' => str_repeat('a', 64), 'name' => 'Chrome', 'last_used_at' => now(), 'expires_at' => now()->addDay()]);
    DB::table('password_reset_tokens')->insert(['email' => 'jan@example.com', 'token' => 'x']);

    $this->actingAs($client)
        ->delete(route('account.destroy'), ['password' => 'password'])
        ->assertRedirect(route('login'));

    $this->assertGuest();

    $client->refresh();
    $reservation->refresh();

    expect($client->name)->toBe(AccountRepository::REMOVED_NAME)
        ->and($client->email)->not->toContain('jan@example.com')
        ->and($client->anonymized_at)->not->toBeNull()
        ->and($client->email_verified_at)->toBeNull()
        ->and(UserProfile::query()->count())->toBe(0)
        ->and(TrustedDevice::query()->count())->toBe(0)
        ->and(Conversation::query()->count())->toBe(0)
        ->and(ChatMessage::query()->count())->toBe(0)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and($reservation->driver_name)->toBeNull()
        ->and($reservation->driver_license_number)->toBeNull()
        ->and($reservation->notes)->toBeNull()
        ->and($reservation->total_price)->toBe('500.00')
        ->and(ReservationPayment::query()->sole()->payer)->toBe(AccountRepository::REMOVED_NAME);
});

test('removed account cannot log in or recover the password', function () {
    $client = privacyClient();

    $this->actingAs($client)->delete(route('account.destroy'), ['password' => 'password']);

    $this->post(route('login.store'), ['email' => 'jan@example.com', 'password' => 'password'])->assertSessionHasErrors('email');

    expect(Hash::check('password', $client->refresh()->password))->toBeFalse();

    $this->assertGuest();
});

test('email of a removed account can register again', function () {
    Notification::fake();
    $client = privacyClient();

    $this->actingAs($client)->delete(route('account.destroy'), ['password' => 'password']);

    $this->post(route('register.store'), privacyRegistration(['email' => 'jan@example.com', 'terms' => '1']))->assertSessionHasNoErrors();

    expect(User::query()->where('email', 'jan@example.com')->count())->toBe(1);
});

test('account stays with a wrong password', function () {
    $client = privacyClient();

    $this->actingAs($client)->delete(route('account.destroy'), ['password' => 'zle'])->assertSessionHasErrors('password');

    expect($client->refresh()->anonymized_at)->toBeNull();
    $this->assertAuthenticatedAs($client);
});

test('account stays while something is open', function (Closure $prepare) {
    $client = privacyClient();
    $prepare($client);

    $this->actingAs($client)->delete(route('account.destroy'), ['password' => 'password'])->assertSessionHasErrors('password');

    expect($client->refresh()->anonymized_at)->toBeNull();
})->with([
    'oczekująca rezerwacja' => [fn (User $user) => privacyReservation($user, ['status' => ReservationStatus::Pending, 'start_date' => now()->addDays(5), 'end_date' => now()->addDays(7), 'confirmation_deadline' => now()->addDay(), 'picked_up_at' => null, 'returned_at' => null])],
    'potwierdzona rezerwacja' => [fn (User $user) => privacyReservation($user, ['start_date' => now()->addDays(5), 'end_date' => now()->addDays(7), 'picked_up_at' => null, 'returned_at' => null])],
    'auto u klienta' => [fn (User $user) => privacyReservation($user, ['returned_at' => null])],
    'nierozliczona kaucja' => [function (User $user) {
        $reservation = privacyReservation($user);
        ReservationPayment::query()->create(['reservation_id' => $reservation->id, 'type' => 'deposit', 'payer' => 'Jan', 'amount' => '100.00', 'method' => 'Gotówka', 'paid_at' => now()]);
    }],
]);

test('only the logged in client can delete an account', function () {
    $client = privacyClient();
    $admin = User::factory()->admin()->create();

    $this->delete(route('account.destroy'), ['password' => 'password'])->assertRedirect(route('login'));

    $this->actingAs($admin)->delete(route('account.destroy'), ['password' => 'password'])->assertForbidden();

    $this->withoutMiddleware(CheckRole::class);
    $this->actingAs($admin)->delete(route('account.destroy'), ['password' => 'password'])->assertForbidden();

    $other = User::factory()->create();
    $this->actingAs($other)->delete(route('account.destroy'), ['password' => 'password', 'id' => $client->id, 'user_id' => $client->id]);

    expect($client->refresh()->anonymized_at)->toBeNull()
        ->and($other->refresh()->anonymized_at)->not->toBeNull()
        ->and($admin->refresh()->anonymized_at)->toBeNull();
});

test('removed account is not offered to staff, history stays', function () {
    $client = privacyClient();
    privacyReservation($client);
    $staff = User::factory()->admin()->create();

    $this->actingAs($client)->delete(route('account.destroy'), ['password' => 'password']);

    $this->actingAs($staff);

    $this->get(route('admin.clients.index'))->assertInertia(fn (Assert $page) => $page->has('clients', 0));
    $this->get(route('admin.clients.show', $client->id))->assertNotFound();
    $this->get(route('admin.messages.index'))->assertInertia(fn (Assert $page) => $page->has('clients', 0));
    $this->post(route('admin.messages.open'), ['client_id' => $client->id])->assertSessionHasErrors('client_id');
    $this->get(route('admin.reservations.history'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 1)->where('reservations.0.client.name', AccountRepository::REMOVED_NAME));
});

test('settings offer deletion to clients only', function () {
    $this->actingAs(User::factory()->create())->get(route('security.index'))->assertInertia(fn (Assert $page) => $page->where('canDeleteAccount', true));
    $this->actingAs(User::factory()->admin()->create())->get(route('security.index'))->assertInertia(fn (Assert $page) => $page->where('canDeleteAccount', false));
});

// Czyszczenie po terminach

test('prune removes abandoned accounts only', function () {
    $old = User::factory()->unverified()->create(['created_at' => now()->subDays(31)]);
    $fresh = User::factory()->unverified()->create(['created_at' => now()->subDays(29)]);
    $verified = User::factory()->create(['created_at' => now()->subDays(31), 'last_login_at' => now()]);
    $withReservation = User::factory()->unverified()->create(['created_at' => now()->subDays(31)]);
    privacyReservation($withReservation, ['driver_name' => null]);
    $admin = User::factory()->admin()->unverified()->create(['created_at' => now()->subDays(31)]);

    $this->artisan('data:prune')->assertSuccessful();

    expect(User::query()->find($old->id))->toBeNull()
        ->and(User::query()->find($fresh->id))->not->toBeNull()
        ->and(User::query()->find($verified->id))->not->toBeNull()
        ->and(User::query()->find($withReservation->id))->not->toBeNull()
        ->and(User::query()->find($admin->id))->not->toBeNull();
});

test('prune anonymizes inactive accounts without open matters', function () {
    $inactive = privacyClient();
    $inactive->forceFill(['last_login_at' => now()->subMonths(37)])->save();
    $busy = User::factory()->create(['last_login_at' => now()->subMonths(37)]);
    privacyReservation($busy, ['returned_at' => null]);
    $active = User::factory()->create(['last_login_at' => now()->subMonths(35)]);
    $admin = User::factory()->admin()->create(['last_login_at' => now()->subMonths(40)]);

    $this->artisan('data:prune')->assertSuccessful();

    expect($inactive->refresh()->anonymized_at)->not->toBeNull()
        ->and($busy->refresh()->anonymized_at)->toBeNull()
        ->and($active->refresh()->anonymized_at)->toBeNull()
        ->and($admin->refresh()->anonymized_at)->toBeNull();
});

test('driver data and chats of active accounts are never pruned', function () {
    $client = User::factory()->create(['last_login_at' => now()]);
    $reservation = privacyReservation($client, ['start_date' => now()->subYears(5), 'end_date' => now()->subYears(5)->addDay()]);
    $conversation = Conversation::query()->create(['user_id' => $client->id, 'last_message_at' => now()->subYears(5)]);

    $this->artisan('data:prune')->assertSuccessful();

    expect($reservation->refresh()->driver_name)->toBe('Jan Kowalski')
        ->and($reservation->driver_license_number)->toBe('ABC 123')
        ->and(Conversation::query()->find($conversation->id))->not->toBeNull();
});

test('dry run changes nothing', function () {
    $old = User::factory()->unverified()->create(['created_at' => now()->subDays(31)]);

    $this->artisan('data:prune', ['--dry-run' => true])
        ->expectsOutputToContain('Do usunięcia: konta niepotwierdzone 1, konta nieaktywne 0.')
        ->assertSuccessful();

    expect(User::query()->find($old->id))->not->toBeNull();
});

test('retention periods come from configuration', function () {
    config(['retention.unverified_account_days' => 5]);
    $old = User::factory()->unverified()->create(['created_at' => now()->subDays(6)]);

    $this->artisan('data:prune')->assertSuccessful();

    expect(User::query()->find($old->id))->toBeNull();
});
