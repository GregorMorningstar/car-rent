<?php

use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Services\BookingOptionsService;
use App\Support\LocalDate;
use App\Support\PolishHolidays;
use Carbon\CarbonImmutable;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-06-17 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
});

function optionsData(array $overrides = []): array
{
    return [
        'pickup_min_days' => 1,
        'pickup_max_days' => 60,
        'rental_min_days' => 2,
        'rental_max_days' => 14,
        'pickup_time_from' => '08:00',
        'pickup_time_to' => '18:00',
        'return_time_from' => '09:00',
        'return_time_to' => '17:00',
        'saturday_enabled' => true,
        'saturday_time_from' => '09:00',
        'saturday_time_to' => '13:00',
        'availability_mode' => 'after_time',
        'buffer_minutes' => 60,
        ...$overrides,
    ];
}

function reserveWithOptions(User $client, array $overrides = [])
{
    $location = Location::query()->orderBy('id')->value('id');

    return test()->actingAs($client)->post(route('cars.book.store', Car::query()->orderBy('id')->value('id')), [
        'from' => '2026-06-20',
        'to' => '2026-06-24',
        'from_time' => '10:00',
        'to_time' => '12:00',
        'pickup' => $location,
        'dropoff' => $location,
        'driver_name' => 'Jan Kowalski',
        'driver_license_category' => 'b',
        ...$overrides,
    ]);
}

// Opcje

test('administrator saves booking options', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.options.update'), optionsData())
        ->assertRedirect(route('admin.options.edit'));

    $this->get(route('admin.options.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('options.pickup_max_days', 60)
            ->where('options.rental_min_days', 2)
            ->where('options.pickup_time_to', '18:00'));
});

test('default options do not limit anything', function () {
    expect(app(BookingOptionsService::class)->all())->toMatchArray([
        'pickup_min_days' => 0,
        'pickup_max_days' => 365,
        'rental_min_days' => 1,
        'pickup_time_from' => '00:00',
        'pickup_time_to' => '23:59',
    ]);

    reserveWithOptions(User::factory()->create(), ['from_time' => '03:00'])->assertSessionHasNoErrors();
});

test('options are validated', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.options.update'), optionsData($overrides))
        ->assertSessionHasErrors($field);

    expect(Setting::query()->count())->toBe(0);
})->with([
    'ujemne dni' => [['pickup_min_days' => -1], 'pickup_min_days'],
    'max mniejsze niż min' => [['pickup_min_days' => 10, 'pickup_max_days' => 5], 'pickup_max_days'],
    'wynajem max mniejszy' => [['rental_min_days' => 5, 'rental_max_days' => 3], 'rental_max_days'],
    'zerowy wynajem' => [['rental_min_days' => 0], 'rental_min_days'],
    'zła godzina' => [['pickup_time_from' => '25:00'], 'pickup_time_from'],
    'odbiór do przed od' => [['pickup_time_from' => '18:00', 'pickup_time_to' => '08:00'], 'pickup_time_to'],
    'zwrot do przed od' => [['return_time_from' => '18:00', 'return_time_to' => '08:00'], 'return_time_to'],
    'tekst' => [['rental_max_days' => 'dużo'], 'rental_max_days'],
    'sobota bez godzin' => [['saturday_time_from' => ''], 'saturday_time_from'],
    'sobota do przed od' => [['saturday_time_from' => '13:00', 'saturday_time_to' => '09:00'], 'saturday_time_to'],
    'zły przełącznik soboty' => [['saturday_enabled' => 'może'], 'saturday_enabled'],
    'nieznany tryb' => [['availability_mode' => 'nigdy'], 'availability_mode'],
    'brak trybu' => [['availability_mode' => ''], 'availability_mode'],
    'ujemna przerwa' => [['buffer_minutes' => -1], 'buffer_minutes'],
    'za długa przerwa' => [['buffer_minutes' => 1441], 'buffer_minutes'],
    'przerwa tekstem' => [['buffer_minutes' => 'godzina'], 'buffer_minutes'],
    'brak przerwy' => [['buffer_minutes' => ''], 'buffer_minutes'],
]);

test('only administrator can change options', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.options.edit'))->assertForbidden();
    $this->put(route('admin.options.update'), optionsData())->assertForbidden();

    $this->withoutMiddleware(CheckRole::class);

    $this->get(route('admin.options.edit'))->assertForbidden();
    $this->put(route('admin.options.update'), optionsData())->assertForbidden();

    expect(Setting::query()->count())->toBe(0);
});

test('booking follows the options', function (array $overrides, ?string $field) {
    app(BookingOptionsService::class)->save(optionsData());

    $response = reserveWithOptions(User::factory()->create(), $overrides);

    $field === null ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors($field);
})->with([
    'w limitach' => [[], null],
    'odbiór za wcześnie' => [['from' => '2026-06-17', 'to' => '2026-06-20'], 'from'],
    'najwcześniejszy dzień' => [['from' => '2026-06-18', 'to' => '2026-06-20'], null],
    'odbiór za późno' => [['from' => '2026-08-17', 'to' => '2026-08-20'], 'from'],
    'najpóźniejszy dzień' => [['from' => '2026-08-16', 'to' => '2026-08-20'], null],
    'wynajem za krótki' => [['to' => '2026-06-21'], 'to'],
    'najkrótszy wynajem' => [['to' => '2026-06-22'], null],
    'wynajem za długi' => [['to' => '2026-07-05'], 'to'],
    'najdłuższy wynajem' => [['to' => '2026-07-04'], null],
    'odbiór za wcześnie rano' => [['from_time' => '07:59'], 'from_time'],
    'odbiór na granicy' => [['from' => '2026-06-22', 'from_time' => '08:00'], null],
    'odbiór za późno wieczorem' => [['from_time' => '18:01'], 'from_time'],
    'zwrot za późno' => [['to_time' => '17:01'], 'to_time'],
    'zwrot za wcześnie' => [['to_time' => '08:59'], 'to_time'],
]);

test('booking page gets limits', function () {
    app(BookingOptionsService::class)->save(optionsData());

    $this->actingAs(User::factory()->create())
        ->get(route('cars.book', Car::query()->orderBy('id')->value('id')))
        ->assertInertia(fn (Assert $page) => $page
            ->where('limits.pickup_earliest', '2026-06-18')
            ->where('limits.pickup_latest', '2026-08-16')
            ->where('limits.pickup_time_from', '08:00'));
});

// Soboty

test('saturday options are saved', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.options.update'), optionsData(['saturday_enabled' => false, 'saturday_time_from' => null, 'saturday_time_to' => null]))
        ->assertSessionHasNoErrors();

    expect(app(BookingOptionsService::class)->all()['saturday_enabled'])->toBeFalse();

    $this->put(route('admin.options.update'), optionsData())->assertSessionHasNoErrors();

    expect(app(BookingOptionsService::class)->all())->toMatchArray([
        'saturday_enabled' => true,
        'saturday_time_from' => '09:00',
        'saturday_time_to' => '13:00',
    ]);
});

test('saturday can be switched off', function (array $overrides, string $field) {
    app(BookingOptionsService::class)->save(optionsData(['saturday_enabled' => false]));

    reserveWithOptions(User::factory()->create(), $overrides)->assertSessionHasErrors($field);
})->with([
    'odbiór w sobotę' => [['from' => '2026-06-20', 'to' => '2026-06-24'], 'from'],
    'zwrot w sobotę' => [['from' => '2026-06-23', 'to' => '2026-06-27'], 'to'],
]);

test('saturday has its own hours', function (array $overrides, ?string $field) {
    app(BookingOptionsService::class)->save(optionsData());

    $response = reserveWithOptions(User::factory()->create(), $overrides);

    $field === null ? $response->assertSessionHasNoErrors() : $response->assertSessionHasErrors($field);
})->with([
    'odbiór w godzinach' => [['from' => '2026-06-20', 'from_time' => '12:00'], null],
    'odbiór po sobotnich godzinach' => [['from' => '2026-06-20', 'from_time' => '14:00'], 'from_time'],
    'zwrot w godzinach' => [['from' => '2026-06-23', 'to' => '2026-06-27', 'to_time' => '09:30'], null],
    'zwrot po sobotnich godzinach' => [['from' => '2026-06-23', 'to' => '2026-06-27', 'to_time' => '15:00'], 'to_time'],
    'zwykły dzień dalej ma swoje' => [['from' => '2026-06-23', 'to' => '2026-06-26', 'from_time' => '17:00'], null],
]);

// Święta

test('holidays are correct', function (string $date, ?string $name) {
    expect(PolishHolidays::name($date))->toBe($name);
})->with([
    'Nowy Rok' => ['2027-01-01', 'Nowy Rok'],
    'Trzech Króli' => ['2027-01-06', 'Święto Trzech Króli'],
    'Wielkanoc 2026' => ['2026-04-05', 'Wielkanoc'],
    'Poniedziałek 2026' => ['2026-04-06', 'Poniedziałek Wielkanocny'],
    'Wielkanoc 2025' => ['2025-04-20', 'Wielkanoc'],
    'Wielkanoc 2027' => ['2027-03-28', 'Wielkanoc'],
    'Wielkanoc 2028' => ['2028-04-16', 'Wielkanoc'],
    '1 maja' => ['2026-05-01', 'Święto Pracy'],
    '3 maja' => ['2026-05-03', 'Święto Konstytucji 3 Maja'],
    'Zielone Świątki 2026' => ['2026-05-24', 'Zielone Świątki'],
    'Boże Ciało 2026' => ['2026-06-04', 'Boże Ciało'],
    'Boże Ciało 2027' => ['2027-05-27', 'Boże Ciało'],
    '15 sierpnia' => ['2026-08-15', 'Wniebowzięcie NMP'],
    '1 listopada' => ['2026-11-01', 'Wszystkich Świętych'],
    '11 listopada' => ['2026-11-11', 'Święto Niepodległości'],
    'Wigilia' => ['2026-12-24', 'Wigilia Bożego Narodzenia'],
    '25 grudnia' => ['2026-12-25', 'Boże Narodzenie'],
    '26 grudnia' => ['2026-12-26', 'Drugi dzień Bożego Narodzenia'],
    'zwykły dzień' => ['2026-11-12', null],
]);

test('holidays in a range cross the new year', function () {
    expect(array_keys(PolishHolidays::between('2026-12-26', '2027-01-06')))->toBe(['2026-12-26', '2027-01-01', '2027-01-06']);
});

test('car cannot be picked up or returned on a holiday', function (array $overrides, string $field) {
    reserveWithOptions(User::factory()->create(), $overrides)->assertSessionHasErrors($field);

    expect(Reservation::query()->count())->toBe(0);
})->with([
    'odbiór 11 listopada' => [['from' => '2026-11-11', 'to' => '2026-11-13'], 'from'],
    'zwrot 11 listopada' => [['from' => '2026-11-09', 'to' => '2026-11-11'], 'to'],
    'odbiór w Boże Narodzenie' => [['from' => '2026-12-25', 'to' => '2026-12-28'], 'from'],
    'zwrot w Wigilię' => [['from' => '2026-12-21', 'to' => '2026-12-24'], 'to'],
    'odbiór w Wielkanoc' => [['from' => '2027-03-28', 'to' => '2027-03-30'], 'from'],
    'zwrot w Poniedziałek Wielkanocny' => [['from' => '2027-03-25', 'to' => '2027-03-29'], 'to'],
]);

test('rental can last through a holiday', function () {
    reserveWithOptions(User::factory()->create(), ['from' => '2026-11-10', 'to' => '2026-11-12'])->assertSessionHasNoErrors();
});

test('holiday cannot be booked even when saturday options allow it', function () {
    // 15 sierpnia 2026 to sobota.
    app(BookingOptionsService::class)->save(optionsData());

    reserveWithOptions(User::factory()->create(), ['from' => '2026-08-15', 'to' => '2026-08-18'])->assertSessionHasErrors('from');
});

test('booking page gets holidays and options page lists them', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cars.book', Car::query()->orderBy('id')->value('id')))
        ->assertInertia(fn (Assert $page) => $page->where('limits.holidays.2026-11-11', 'Święto Niepodległości'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.options.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('holidays.2026-12-25', 'Boże Narodzenie'));
});

test('confirmation deadline skips holidays', function (string $local, string $expected) {
    $deadline = LocalDate::businessDayAfter(CarbonImmutable::parse($local, 'Europe/Warsaw'));

    expect($deadline->setTimezone('Europe/Warsaw')->format('Y-m-d H:i'))->toBe($expected);
})->with([
    'dzień przed świętem' => ['2026-11-10 12:00', '2026-11-12 12:00'],
    'w święto' => ['2026-11-11 10:00', '2026-11-13 00:00'],
    'przed Bożym Narodzeniem' => ['2026-12-23 12:00', '2026-12-28 12:00'],
]);

// Pisanie do klienta

test('administrator opens a conversation with a client who never wrote', function () {
    $client = User::factory()->create();

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.messages.open'), ['client_id' => $client->id]);

    $conversation = Conversation::query()->sole();

    $response->assertRedirect(route('admin.messages.index', ['conversation' => $conversation->id]));

    expect($conversation->user_id)->toBe($client->id);

    $this->post(route('admin.messages.store', $conversation->id), ['body' => 'Dzień dobry'])->assertRedirect();

    $this->actingAs($client)
        ->get(route('user.messages.index'))
        ->assertInertia(fn (Assert $page) => $page->where('messages.0.body', 'Dzień dobry')->where('messages.0.mine', false));
});

test('opening twice keeps one conversation', function () {
    $client = User::factory()->create();
    $this->actingAs(User::factory()->admin()->create());

    $this->post(route('admin.messages.open'), ['client_id' => $client->id]);
    $this->post(route('admin.messages.open'), ['client_id' => $client->id]);

    expect(Conversation::query()->count())->toBe(1);
});

test('inbox lists clients but not administrators', function () {
    $staff = User::factory()->admin()->create();
    User::factory()->create(['name' => 'Klient']);

    $this->actingAs($staff)
        ->get(route('admin.messages.index'))
        ->assertInertia(fn (Assert $page) => $page->has('clients', 1)->where('clients.0.name', 'Klient'));
});

test('conversation cannot be opened with non-client', function (Closure $target, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.messages.open'), ['client_id' => $target()])
        ->assertSessionHasErrors($field);

    expect(Conversation::query()->count())->toBe(0);
})->with([
    'administrator' => [fn () => User::factory()->admin()->create()->id, 'client_id'],
    'nieznany' => [fn () => 999, 'client_id'],
    'tekst' => [fn () => 'abc', 'client_id'],
]);

test('client cannot open a conversation with someone else', function () {
    $other = User::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->post(route('admin.messages.open'), ['client_id' => $other->id])->assertForbidden();
    $this->withoutMiddleware(CheckRole::class)
        ->post(route('admin.messages.open'), ['client_id' => $other->id])
        ->assertForbidden();

    expect(Conversation::query()->count())->toBe(0);
});

// Wiadomość po potwierdzeniu

test('client gets a message when the reservation is confirmed', function () {
    $client = User::factory()->create();
    reserveWithOptions($client);
    $reservation = Reservation::query()->sole();
    $staff = User::factory()->admin()->create();

    $this->actingAs($staff)->post(route('admin.reservations.confirm', $reservation->id))->assertRedirect();

    $message = ChatMessage::query()->sole();

    expect($message->sender_id)->toBe($staff->id)
        ->and($message->conversation->user_id)->toBe($client->id)
        ->and($message->body)->toContain('potwierdzona')
        ->and($message->body)->toContain('Toyota Corolla')
        ->and($message->body)->toContain(sprintf('WYN/2026/%04d', $reservation->id))
        ->and($message->body)->toContain('20.06.2026 10:00')
        ->and($message->body)->toContain('24.06.2026 12:00');

    $this->actingAs($client)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.messages', 1));
});

test('message goes only to the owner of the reservation', function () {
    $client = User::factory()->create();
    $other = User::factory()->create();
    Conversation::query()->create(['user_id' => $other->id]);
    reserveWithOptions($client);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.confirm', Reservation::query()->sole()->id));

    expect(Conversation::query()->where('user_id', $other->id)->sole()->messages()->count())->toBe(0);
});

test('rejecting and failed confirmation send no message', function () {
    reserveWithOptions(User::factory()->create());
    $reservation = Reservation::query()->sole();
    $staff = User::factory()->admin()->create();

    $this->actingAs($staff)->post(route('admin.reservations.reject', $reservation->id));
    $this->actingAs($staff)->post(route('admin.reservations.confirm', $reservation->id))->assertSessionHasErrors('reservation');

    expect(ChatMessage::query()->count())->toBe(0);
});

test('confirmation stays when the message fails', function () {
    Exceptions::fake();
    reserveWithOptions(User::factory()->create());
    $reservation = Reservation::query()->sole();
    $staff = User::factory()->admin()->create();

    $repository = $this->partialMock(ChatRepositoryInterface::class);
    $repository->shouldReceive('firstOrCreateForClient')->andThrow(new RuntimeException('Baza niedostępna'));
    $repository->shouldReceive('unreadForStaff')->andReturn(0);

    $this->actingAs($staff)->post(route('admin.reservations.confirm', $reservation->id))->assertRedirect();

    expect($reservation->refresh()->status)->toBe(ReservationStatus::Confirmed);
    Exceptions::assertReported(RuntimeException::class);
});
