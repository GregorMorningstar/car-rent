<?php

use App\Enums\ReservationStatus;
use App\Events\ChatMessageSent;
use App\Events\ReservationPlaced;
use App\Exceptions\MessengerException;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Services\ChatService;
use App\Services\MessengerService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

// Żaden test nie może wyjść do prawdziwego Messengera; lokalny SSR Inertii działa po http.
beforeEach(function () {
    Http::preventStrayRequests();
    Http::allowStrayRequests(['http://*']);
});

function chatClient(): User
{
    return User::factory()->create();
}

function chatAdmin(): User
{
    return User::factory()->admin()->create();
}

function chatWith(User $client, string $body = 'Dzień dobry'): Conversation
{
    $conversation = Conversation::query()->create(['user_id' => $client->id, 'last_message_at' => now()]);
    $conversation->messages()->create(['sender_id' => $client->id, 'body' => $body]);

    return $conversation;
}

function enableMessenger(): void
{
    app(MessengerService::class)->saveSettings([
        'enabled' => true,
        'page_token' => 'sekretny-token',
        'recipient_id' => '1234567890',
    ]);
}

function placeReservation(User $client, array $overrides = []): Reservation
{
    $car = Car::query()->create([
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'registration_number' => fake()->unique()->bothify('KR #####'),
        'vin' => fake()->unique()->bothify('JTDBR32E72#######'),
        'production_year' => 2022,
        'fuel_type' => 'hybrid',
        'transmission' => 'automatic',
        'doors' => 5,
        'seats' => 5,
        'mileage_km' => 1000,
        'daily_rate' => '100.00',
        'deposit_amount' => '500.00',
    ]);

    return Reservation::query()->create([
        'user_id' => $client->id,
        'car_id' => $car->id,
        'start_date' => now()->addDays(3)->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => ReservationStatus::Pending,
        'daily_rate' => '100.00',
        'deposit_amount' => '500.00',
        'total_price' => '200.00',
        'confirmation_deadline' => now()->addDay(),
        ...$overrides,
    ]);
}

// Tylko klient i administracja

test('client message goes to own conversation', function () {
    $client = chatClient();

    $this->actingAs($client)
        ->post(route('user.messages.store'), ['body' => '  Pytanie o auto  '])
        ->assertRedirect();

    $conversation = Conversation::query()->sole();

    expect($conversation->user_id)->toBe($client->id)
        ->and($conversation->messages()->sole()->body)->toBe('Pytanie o auto')
        ->and($conversation->last_message_at)->not->toBeNull();
});

test('client cannot point at another conversation or recipient', function () {
    $client = chatClient();
    $other = chatClient();
    $foreign = chatWith($other);

    $this->actingAs($client)->post(route('user.messages.store'), [
        'body' => 'Do innego klienta',
        'conversation_id' => $foreign->id,
        'conversation' => $foreign->id,
        'user_id' => $other->id,
        'recipient_id' => $other->id,
        'sender_id' => $other->id,
    ]);

    expect($foreign->messages()->count())->toBe(1)
        ->and(ChatMessage::query()->where('body', 'Do innego klienta')->sole()->sender_id)->toBe($client->id)
        ->and(Conversation::query()->where('user_id', $client->id)->exists())->toBeTrue();
});

test('client cannot reply through administrator route', function () {
    $client = chatClient();
    $foreign = chatWith(chatClient());

    $this->actingAs($client)
        ->post(route('admin.messages.store', $foreign->id), ['body' => 'Cześć'])
        ->assertForbidden();

    $this->withoutMiddleware(CheckRole::class)
        ->post(route('admin.messages.store', $foreign->id), ['body' => 'Cześć'])
        ->assertForbidden();

    expect($foreign->messages()->count())->toBe(1);
});

test('client sees only own messages', function () {
    $client = chatClient();
    chatWith($client, 'Moja wiadomość');
    chatWith(chatClient(), 'Cudza wiadomość');

    $this->actingAs($client)
        ->get(route('user.messages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/messages')
            ->has('messages', 1)
            ->where('messages.0.body', 'Moja wiadomość')
            ->where('messages.0.mine', true)
            ->missing('messages.0.sender_id')
            ->missing('messages.0.conversation_id'));
});

test('client cannot open administrator inbox', function () {
    $conversation = chatWith(chatClient());

    $this->actingAs(chatClient())
        ->get(route('admin.messages.index', ['conversation' => $conversation->id]))
        ->assertForbidden();

    $this->withoutMiddleware(CheckRole::class)
        ->get(route('admin.messages.index', ['conversation' => $conversation->id]))
        ->assertForbidden();
});

test('service refuses wrong role', function () {
    $client = chatClient();
    $staff = chatAdmin();
    $service = app(ChatService::class);

    expect(fn () => $service->sendFromClient($staff, ['body' => 'x']))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->clientThread($staff))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->sendFromStaff($client, 1, ['body' => 'x']))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->staffInbox($client))->toThrow(AuthorizationException::class);
});

test('guest cannot use chat', function () {
    $this->post(route('user.messages.store'), ['body' => 'x'])->assertRedirect(route('login'));
    $this->get(route('admin.messages.index'))->assertRedirect(route('login'));

    expect(ChatMessage::query()->count())->toBe(0);
});

test('administrator replies to a client', function () {
    $client = chatClient();
    $staff = chatAdmin();
    $conversation = chatWith($client);

    $this->actingAs($staff)
        ->post(route('admin.messages.store', $conversation->id), ['body' => 'Odpowiedź'])
        ->assertRedirect();

    expect($conversation->messages()->latest('id')->first()->sender_id)->toBe($staff->id);

    $this->actingAs($client)
        ->get(route('user.messages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('messages', 2)
            ->where('messages.1.body', 'Odpowiedź')
            ->where('messages.1.mine', false));
});

test('administrator inbox lists conversations with unread count', function () {
    $client = chatClient();
    $conversation = chatWith($client);
    $conversation->messages()->create(['sender_id' => $client->id, 'body' => 'Druga']);

    $this->actingAs(chatAdmin())
        ->get(route('admin.messages.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/messages/index')
            ->has('conversations', 1)
            ->where('conversations.0.unread', 2)
            ->where('conversations.0.preview', 'Druga')
            ->where('active', null));
});

test('replying to unknown conversation returns 404', function () {
    $this->actingAs(chatAdmin())
        ->post(route('admin.messages.store', 999), ['body' => 'x'])
        ->assertNotFound();
});

// Walidacja

test('message body is validated', function (mixed $body) {
    $this->actingAs(chatClient())
        ->post(route('user.messages.store'), ['body' => $body])
        ->assertSessionHasErrors('body');

    expect(ChatMessage::query()->count())->toBe(0);
})->with([
    'pusta' => [''],
    'same spacje' => ['     '],
    'za długa' => [str_repeat('a', 2001)],
    'tablica' => [['a']],
]);

test('html in a message stays plain text', function () {
    $client = chatClient();

    $this->actingAs($client)->post(route('user.messages.store'), ['body' => '<script>alert(1)</script>']);

    expect(ChatMessage::query()->sole()->body)->toBe('<script>alert(1)</script>');
});

test('invalid conversation filter is rejected', function () {
    $this->actingAs(chatAdmin())
        ->get(route('admin.messages.index', ['conversation' => 'abc']))
        ->assertRedirect(route('admin.messages.index'))
        ->assertSessionHasErrors('conversation');
});

test('client message limit stops flooding', function () {
    $client = chatClient();
    $this->actingAs($client);

    foreach (range(1, 20) as $number) {
        $this->post(route('user.messages.store'), ['body' => "Wiadomość {$number}"])->assertRedirect();
    }

    $this->post(route('user.messages.store'), ['body' => 'Za dużo'])->assertTooManyRequests();
});

// Odczytanie

test('opening a thread marks incoming messages as read', function () {
    $client = chatClient();
    $staff = chatAdmin();
    $conversation = chatWith($client);
    $reply = $conversation->messages()->create(['sender_id' => $staff->id, 'body' => 'Odpowiedź']);

    $this->actingAs($staff)->get(route('admin.messages.index', ['conversation' => $conversation->id]));

    expect($conversation->messages()->where('sender_id', $client->id)->sole()->read_at)->not->toBeNull()
        ->and($reply->refresh()->read_at)->toBeNull();

    $this->actingAs($client)->get(route('user.messages.index'));

    expect($reply->refresh()->read_at)->not->toBeNull();
});

test('unread counters are shared with the panels', function () {
    $client = chatClient();
    $staff = chatAdmin();
    $conversation = chatWith($client);
    $conversation->messages()->create(['sender_id' => $staff->id, 'body' => 'Odpowiedź']);

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.messages', 1));

    $this->actingAs($client)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.messages', 1)
            ->where('notifications.reservations', null));
});

test('guest gets no notifications', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->where('notifications', null));
});

// Echo

test('message event goes only to client and staff channels and carries no content', function () {
    $client = chatClient();
    $event = new ChatMessageSent($client->id);

    expect(collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all())
        ->toBe(['private-App.Models.User.'.$client->id, 'private-admin.inbox'])
        ->and($event->broadcastWith())->toBe([]);
});

test('sending a message announces it', function () {
    Event::fake([ChatMessageSent::class]);

    $this->actingAs(chatClient())->post(route('user.messages.store'), ['body' => 'Cześć']);

    Event::assertDispatched(ChatMessageSent::class);
});

test('channels allow only their owners', function () {
    $client = chatClient();
    $other = chatClient();
    $staff = chatAdmin();
    $channels = Broadcast::getChannels();

    expect($channels['App.Models.User.{id}']($client, $client->id))->toBeTrue()
        ->and($channels['App.Models.User.{id}']($other, $client->id))->toBeFalse()
        ->and($channels['admin.inbox']($staff))->toBeTrue()
        ->and($channels['admin.inbox']($client))->toBeFalse();
});

test('message is saved when the websocket server is down', function () {
    Exceptions::fake();
    Event::listen(ChatMessageSent::class, fn () => throw new RuntimeException('Reverb niedostępny'));

    $this->actingAs(chatClient())
        ->post(route('user.messages.store'), ['body' => 'Cześć'])
        ->assertSessionHasNoErrors();

    expect(ChatMessage::query()->count())->toBe(1);
    Exceptions::assertReported(RuntimeException::class);
});

// Błędy

test('failed save returns a message to the form', function () {
    Exceptions::fake();

    $this->mock(ChatRepositoryInterface::class)
        ->shouldReceive('firstOrCreateForClient')
        ->andThrow(new RuntimeException('Baza niedostępna'));

    $this->actingAs(chatClient())
        ->post(route('user.messages.store'), ['body' => 'Cześć'])
        ->assertSessionHasErrors('body');
});

test('failed read shows a message instead of an error page', function () {
    Exceptions::fake();

    $repository = $this->mock(ChatRepositoryInterface::class);
    $repository->shouldReceive('findForClient')->andThrow(new RuntimeException('Baza niedostępna'));
    $repository->shouldReceive('unreadForClient')->andReturn(0);

    $this->actingAs(chatClient())
        ->get(route('user.messages.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('failed', true)->has('messages', 0));
});

// Messenger

test('client message is forwarded to messenger', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['message_id' => 'm1'])]);
    enableMessenger();

    $client = User::factory()->create(['name' => 'Jan Testowy']);

    $this->actingAs($client)->post(route('user.messages.store'), ['body' => 'Pytanie'])->assertRedirect();

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/me/messages')
        && ! str_contains($request->url(), 'sekretny-token')
        && $request->hasHeader('Authorization', 'Bearer sekretny-token')
        && $request['recipient']['id'] === '1234567890'
        && str_contains($request['message']['text'], 'Jan Testowy')
        && str_contains($request['message']['text'], 'Pytanie'));

    expect(ChatMessage::query()->sole()->messenger_forwarded_at)->not->toBeNull();
});

test('administrator reply is not forwarded', function () {
    Http::fake();
    enableMessenger();

    $conversation = chatWith(chatClient());

    $this->actingAs(chatAdmin())->post(route('admin.messages.store', $conversation->id), ['body' => 'Odpowiedź']);

    Http::assertNothingSent();
});

test('nothing is forwarded when messenger is off', function () {
    Http::fake();

    $this->actingAs(chatClient())->post(route('user.messages.store'), ['body' => 'Pytanie']);

    Http::assertNothingSent();
});

test('messenger failure does not stop the chat', function () {
    Exceptions::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Zły token']], 400)]);
    enableMessenger();

    $this->actingAs(chatClient())
        ->post(route('user.messages.store'), ['body' => 'Pytanie'])
        ->assertSessionHasNoErrors();

    expect(ChatMessage::query()->sole()->messenger_forwarded_at)->toBeNull();
    Exceptions::assertReported(fn (MessengerException $exception) => str_contains($exception->getMessage(), 'Zły token'));
});

// Ustawienia

test('administrator saves messenger settings', function () {
    $this->actingAs(chatAdmin())
        ->put(route('admin.settings.messenger.update'), ['enabled' => true, 'page_token' => 'abc123', 'recipient_id' => '1234567890'])
        ->assertRedirect(route('admin.settings.messenger.edit'));

    expect(app(MessengerService::class)->settings())->toBe([
        'enabled' => true,
        'recipient_id' => '1234567890',
        'has_token' => true,
    ]);
});

test('token is encrypted in the database and never sent to the browser', function () {
    $staff = chatAdmin();

    $this->actingAs($staff)
        ->put(route('admin.settings.messenger.update'), ['enabled' => true, 'page_token' => 'sekretny-token', 'recipient_id' => '1234567890']);

    expect(DB::table('settings')->where('key', 'messenger.page_token')->value('value'))->not->toContain('sekretny-token');

    $response = $this->actingAs($staff)->get(route('admin.settings.messenger.edit'));

    expect($response->getContent())->not->toContain('sekretny-token');
    $response->assertInertia(fn (Assert $page) => $page->where('settings.has_token', true)->missing('settings.page_token'));
});

test('empty token keeps the saved one', function () {
    enableMessenger();

    $this->actingAs(chatAdmin())
        ->put(route('admin.settings.messenger.update'), ['enabled' => true, 'page_token' => '', 'recipient_id' => '999999'])
        ->assertSessionHasNoErrors();

    expect(app(MessengerService::class)->settings()['has_token'])->toBeTrue();
});

test('messenger settings are validated', function (array $data, string $field) {
    $this->actingAs(chatAdmin())
        ->put(route('admin.settings.messenger.update'), $data)
        ->assertSessionHasErrors($field);

    expect(Setting::query()->count())->toBe(0);
})->with([
    'bez tokena' => [['enabled' => true, 'page_token' => '', 'recipient_id' => '1234567890'], 'page_token'],
    'bez odbiorcy' => [['enabled' => true, 'page_token' => 'abc', 'recipient_id' => ''], 'recipient_id'],
    'zły odbiorca' => [['enabled' => true, 'page_token' => 'abc', 'recipient_id' => 'abc'], 'recipient_id'],
    'zły przełącznik' => [['enabled' => 'tak', 'page_token' => 'abc', 'recipient_id' => '1234567890'], 'enabled'],
]);

test('test message reports the messenger error', function () {
    Exceptions::fake();
    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Zły token']], 400)]);
    enableMessenger();

    $this->actingAs(chatAdmin())
        ->from(route('admin.settings.messenger.edit'))
        ->post(route('admin.settings.messenger.test'))
        ->assertSessionHasErrors('test');
});

test('test message confirms success', function () {
    Http::fake(['graph.facebook.com/*' => Http::response(['message_id' => 'm1'])]);
    enableMessenger();

    $this->actingAs(chatAdmin())
        ->from(route('admin.settings.messenger.edit'))
        ->post(route('admin.settings.messenger.test'))
        ->assertSessionHas('status');
});

test('only administrator can open and change messenger settings', function () {
    $this->actingAs(chatClient());

    $this->get(route('admin.settings.messenger.edit'))->assertForbidden();
    $this->put(route('admin.settings.messenger.update'), ['enabled' => false])->assertForbidden();
    $this->post(route('admin.settings.messenger.test'))->assertForbidden();

    $this->withoutMiddleware(CheckRole::class);

    $this->get(route('admin.settings.messenger.edit'))->assertForbidden();
    $this->put(route('admin.settings.messenger.update'), ['enabled' => false])->assertForbidden();
    $this->post(route('admin.settings.messenger.test'))->assertForbidden();

    expect(Setting::query()->count())->toBe(0);
});

// Nowe zamówienia

test('new reservation is announced to staff', function () {
    Event::fake([ReservationPlaced::class]);

    placeReservation(chatClient());

    Event::assertDispatched(ReservationPlaced::class);
});

test('reservation event goes only to staff channel', function () {
    $event = new ReservationPlaced;

    expect(collect($event->broadcastOn())->map(fn ($channel) => $channel->name)->all())->toBe(['private-admin.inbox'])
        ->and($event->broadcastWith())->toBe([]);
});

test('reservation is saved when the websocket server is down', function () {
    Exceptions::fake();
    Event::listen(ReservationPlaced::class, fn () => throw new RuntimeException('Reverb niedostępny'));

    placeReservation(chatClient());

    expect(Reservation::query()->count())->toBe(1);
});

test('administrator panel gets new orders', function () {
    $client = User::factory()->create(['name' => 'Jan Testowy']);
    placeReservation($client);
    placeReservation($client);

    $this->actingAs(chatAdmin())
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.reservations.count', 2)
            ->where('notifications.reservations.items.0.client', 'Jan Testowy')
            ->where('notifications.reservations.items.0.car', 'Toyota Corolla')
            ->missing('notifications.reservations.items.0.vin'));
});

test('client does not get information about orders', function () {
    $client = chatClient();
    placeReservation(chatClient());

    $this->actingAs($client)
        ->get(route('user.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.reservations', null));
});

test('seen orders leave the notification', function () {
    placeReservation(chatClient());
    $staff = chatAdmin();

    $this->actingAs($staff)->post(route('admin.reservations.seen'))->assertRedirect();

    $this->actingAs($staff)
        ->get(route('admin.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('notifications.reservations.count', 0));
});

test('client cannot mark orders as seen', function () {
    $reservation = placeReservation(chatClient());
    $this->actingAs(chatClient());

    $this->post(route('admin.reservations.seen'))->assertForbidden();

    $this->withoutMiddleware(CheckRole::class)
        ->post(route('admin.reservations.seen'))
        ->assertForbidden();

    expect($reservation->refresh()->seen_by_admin_at)->toBeNull();
});
