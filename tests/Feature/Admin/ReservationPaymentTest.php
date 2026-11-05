<?php

use App\Enums\PaymentType;
use App\Enums\ReservationStatus;
use App\Http\Middleware\CheckRole;
use App\Models\Car;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2026-06-17 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
});

function paymentReservation(array $overrides = [], ?User $owner = null): Reservation
{
    return Reservation::query()->create([
        'user_id' => ($owner ?? User::factory()->create())->id,
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

function paymentData(array $overrides = []): array
{
    return ['type' => 'advance', 'amount' => '200', 'payer' => 'Jan Kowalski', 'method' => 'Gotówka', 'paid_at' => '2026-06-17T13:30', ...$overrides];
}

function paidReservation(Reservation $reservation, array $overrides = []): ReservationPayment
{
    return ReservationPayment::query()->create([
        'reservation_id' => $reservation->id,
        'type' => PaymentType::Advance,
        'payer' => 'Jan Kowalski',
        'amount' => '200.00',
        'method' => 'Gotówka',
        'paid_at' => now(),
        ...$overrides,
    ]);
}

test('administrator records an advance and a deposit', function (string $type, PaymentType $expected) {
    $staff = User::factory()->admin()->create();

    $this->actingAs($staff)
        ->post(route('admin.reservations.payments.store', paymentReservation()->id), paymentData(['type' => $type]))
        ->assertRedirect();

    $payment = ReservationPayment::query()->sole();

    expect($payment->type)->toBe($expected)
        ->and($payment->amount)->toBe('200.00')
        ->and($payment->payer)->toBe('Jan Kowalski')
        ->and($payment->method)->toBe('Gotówka')
        ->and($payment->recorded_by)->toBe($staff->id)
        ->and($payment->refunded_at)->toBeNull();
})->with([
    'zaliczka' => ['advance', PaymentType::Advance],
    'kaucja' => ['deposit', PaymentType::Deposit],
]);

test('paid date is entered locally and stored in UTC', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.store', paymentReservation()->id), paymentData());

    expect(DB::table('reservation_payments')->value('paid_at'))->toStartWith('2026-06-17 11:30');
});

test('payment is validated', function (array $overrides, string $field) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.store', paymentReservation()->id), paymentData($overrides))
        ->assertSessionHasErrors($field);

    expect(ReservationPayment::query()->count())->toBe(0);
})->with([
    'brak rodzaju' => [['type' => ''], 'type'],
    'zły rodzaj' => [['type' => 'mandat'], 'type'],
    'brak kwoty' => [['amount' => ''], 'amount'],
    'zero' => [['amount' => '0'], 'amount'],
    'ujemna' => [['amount' => '-5'], 'amount'],
    'tekst' => [['amount' => 'dużo'], 'amount'],
    'trzy miejsca po przecinku' => [['amount' => '10.123'], 'amount'],
    'zaliczka ponad cenę' => [['amount' => '500.01'], 'amount'],
    'kaucja ponad kwotę kaucji' => [['type' => 'deposit', 'amount' => '1000.01'], 'amount'],
    'brak nadawcy' => [['payer' => ''], 'payer'],
    'zła metoda' => [['method' => 'Bitcoin'], 'method'],
    'zła data' => [['paid_at' => 'wczoraj'], 'paid_at'],
    'data z przyszłości' => [['paid_at' => '2026-06-20T10:00'], 'paid_at'],
]);

test('advances and deposits have separate limits', function () {
    $reservation = paymentReservation();
    paidReservation($reservation, ['amount' => '500.00']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.store', $reservation->id), paymentData(['amount' => '0.01']))
        ->assertSessionHasErrors('amount');

    $this->post(route('admin.reservations.payments.store', $reservation->id), paymentData(['type' => 'deposit', 'amount' => '1000']))
        ->assertSessionHasNoErrors();
});

test('settled payment frees the amount again', function () {
    $reservation = paymentReservation();
    paidReservation($reservation, ['amount' => '500.00', 'refunded_amount' => '500.00', 'refunded_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.store', $reservation->id), paymentData(['amount' => '500']))
        ->assertSessionHasNoErrors();
});

test('payment cannot be added to a closed reservation', function (array $overrides) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.store', paymentReservation($overrides)->id), paymentData())
        ->assertSessionHasErrors('reservation');

    expect(ReservationPayment::query()->count())->toBe(0);
})->with([
    'odrzucona' => [['status' => ReservationStatus::Cancelled]],
    'wygasła' => [['status' => ReservationStatus::Expired]],
    'po terminie potwierdzenia' => [fn () => ['confirmation_deadline' => now()->subMinute()]],
]);

test('administrator refunds the whole payment without a reason', function () {
    $staff = User::factory()->admin()->create();
    $reservation = paymentReservation();
    $payment = paidReservation($reservation);

    $this->actingAs($staff)
        ->post(route('admin.reservations.payments.refund', [$reservation->id, $payment->id]), ['refund_amount' => '200', 'refund_reason' => 'zbędny opis'])
        ->assertSessionHasNoErrors();

    $payment->refresh();

    expect($payment->refunded_amount)->toBe('200.00')
        ->and($payment->refund_reason)->toBeNull()
        ->and($payment->refunded_at->toDateString())->toBe('2026-06-17')
        ->and($payment->refunded_by)->toBe($staff->id);
});

test('reduced or withheld refund stores the reason', function (string $amount, string $expected) {
    $reservation = paymentReservation();
    $payment = paidReservation($reservation, ['type' => PaymentType::Deposit]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.refund', [$reservation->id, $payment->id]), [
            'refund_amount' => $amount,
            'refund_reason' => 'Niezgłoszenie szkody',
        ])
        ->assertSessionHasNoErrors();

    $payment->refresh();

    expect($payment->refunded_amount)->toBe($expected)
        ->and($payment->refund_reason)->toBe('Niezgłoszenie szkody')
        ->and($payment->refunded_at)->not->toBeNull();
})->with([
    'częściowy' => ['50.50', '50.50'],
    'bez zwrotu' => ['0', '0.00'],
]);

test('refund is validated', function (array $data, string $field) {
    $reservation = paymentReservation();
    $payment = paidReservation($reservation);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.refund', [$reservation->id, $payment->id]), $data)
        ->assertSessionHasErrors($field);

    expect($payment->refresh()->refunded_at)->toBeNull();
})->with([
    'brak kwoty' => [[], 'refund_amount'],
    'tekst' => [['refund_amount' => 'całość'], 'refund_amount'],
    'ujemna' => [['refund_amount' => '-1', 'refund_reason' => 'x'], 'refund_amount'],
    'ponad wpłatę' => [['refund_amount' => '200.01'], 'refund_amount'],
    'mniej bez powodu' => [['refund_amount' => '100'], 'refund_reason'],
    'zero bez powodu' => [['refund_amount' => '0'], 'refund_reason'],
    'powód z samych spacji' => [['refund_amount' => '100', 'refund_reason' => '   '], 'refund_reason'],
    'za długi powód' => [['refund_amount' => '100', 'refund_reason' => str_repeat('x', 501)], 'refund_reason'],
]);

test('payment cannot be settled twice', function () {
    $reservation = paymentReservation();
    $payment = paidReservation($reservation, ['refunded_amount' => '200.00', 'refunded_at' => now()->subDay()]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.refund', [$reservation->id, $payment->id]), ['refund_amount' => '0', 'refund_reason' => 'x'])
        ->assertSessionHasErrors('reservation');

    expect($payment->refresh()->refunded_amount)->toBe('200.00');
});

test('payment cannot be refunded through another reservation', function () {
    $payment = paidReservation(paymentReservation());
    $other = paymentReservation(['start_date' => now()->addDays(20), 'end_date' => now()->addDays(22)]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.refund', [$other->id, $payment->id]), ['refund_amount' => '200'])
        ->assertNotFound();

    expect($payment->refresh()->refunded_at)->toBeNull();
});

test('payment on unknown reservation returns 404', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.payments.store', 999), paymentData())
        ->assertNotFound();
});

test('list shows payments with payer, date and settlement', function () {
    $reservation = paymentReservation();
    paidReservation($reservation, [
        'type' => PaymentType::Deposit,
        'refunded_amount' => '50.00',
        'refund_reason' => 'Niezgłoszenie szkody',
        'refunded_at' => now(),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('reservations.0.deposit_amount', '1000.00')
            ->where('reservations.0.payments.0.type', 'deposit')
            ->where('reservations.0.payments.0.amount', '200.00')
            ->where('reservations.0.payments.0.payer', 'Jan Kowalski')
            ->where('reservations.0.payments.0.paid_at', '2026-06-17T12:00:00.000000Z')
            ->where('reservations.0.payments.0.refunded_amount', '50.00')
            ->where('reservations.0.payments.0.refund_reason', 'Niezgłoszenie szkody')
            ->has('paymentMethods', 4));
});

test('closed reservation with an unsettled payment stays on the list', function (array $overrides, string $status) {
    paidReservation(paymentReservation($overrides));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 1)->where('reservations.0.status', $status));
})->with([
    'odrzucona' => [['status' => ReservationStatus::Cancelled], 'cancelled'],
    'wygasła' => [['status' => ReservationStatus::Expired], 'expired'],
    'po terminie potwierdzenia' => [fn () => ['confirmation_deadline' => now()->subMinute()], 'expired'],
]);

test('closed reservation leaves the list after settlement, also without a refund', function () {
    $reservation = paymentReservation(['status' => ReservationStatus::Cancelled]);
    paidReservation($reservation, ['refunded_amount' => '0.00', 'refund_reason' => 'x', 'refunded_at' => now()]);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
});

test('finished rental waits on the list until the deposit is settled', function () {
    $reservation = paymentReservation([
        'status' => ReservationStatus::Confirmed,
        'start_date' => now()->subDays(5),
        'end_date' => now()->subDays(3),
    ]);
    $deposit = paidReservation($reservation, ['type' => PaymentType::Deposit]);

    $this->actingAs(User::factory()->admin()->create());

    $this->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 1)->where('reservations.0.finished', true));

    $this->post(route('admin.reservations.payments.refund', [$reservation->id, $deposit->id]), ['refund_amount' => '200']);

    $this->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
});

test('finished rental with only an advance does not stay on the list', function () {
    paidReservation(paymentReservation([
        'status' => ReservationStatus::Confirmed,
        'start_date' => now()->subDays(5),
        'end_date' => now()->subDays(3),
    ]));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.reservations.index'))
        ->assertInertia(fn (Assert $page) => $page->has('reservations', 0));
});

test('client cannot remove a reservation with an unsettled payment', function (PaymentType $type) {
    $owner = User::factory()->create();
    $reservation = paymentReservation(owner: $owner);
    paidReservation($reservation, ['type' => $type]);

    $this->actingAs($owner)
        ->delete(route('user.reservations.destroy', $reservation->id))
        ->assertSessionHasErrors('reservation');

    expect(Reservation::query()->count())->toBe(1);

    $this->actingAs($owner)
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page->where('rentals.0.can_delete', false));
})->with([PaymentType::Advance, PaymentType::Deposit]);

test('client can remove the reservation after settlement', function () {
    $owner = User::factory()->create();
    $reservation = paymentReservation(owner: $owner);
    paidReservation($reservation, ['refunded_amount' => '200.00', 'refunded_at' => now()]);

    $this->actingAs($owner)
        ->delete(route('user.reservations.destroy', $reservation->id))
        ->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(0);
});

test('client sees own payments with the settlement', function () {
    $owner = User::factory()->create();
    paidReservation(paymentReservation(owner: $owner), [
        'type' => PaymentType::Deposit,
        'refunded_amount' => '50.00',
        'refund_reason' => 'Niezgłoszenie szkody',
        'refunded_at' => now(),
    ]);

    $this->actingAs($owner)
        ->get(route('user.payments'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('user/payments')
            ->where('failed', false)
            ->has('payments', 1)
            ->where('payments.0.type', 'deposit')
            ->where('payments.0.amount', '200.00')
            ->where('payments.0.refunded_amount', '50.00')
            ->where('payments.0.refund_reason', 'Niezgłoszenie szkody')
            ->missing('payments.0.user_id')
            ->missing('payments.0.recorded_by'));
});

test('client does not see payments of other clients', function () {
    paidReservation(paymentReservation());

    $this->actingAs(User::factory()->create())
        ->get(route('user.payments'))
        ->assertInertia(fn (Assert $page) => $page->has('payments', 0));
});

test('client keeps the payment history of a removed reservation', function () {
    $owner = User::factory()->create();
    $reservation = paymentReservation(owner: $owner);
    paidReservation($reservation, ['refunded_amount' => '200.00', 'refunded_at' => now()]);
    $reservation->delete();

    $this->actingAs($owner)
        ->get(route('user.payments'))
        ->assertInertia(fn (Assert $page) => $page->has('payments', 1));
});

test('client cannot record or settle a payment', function () {
    $owner = User::factory()->create();
    $reservation = paymentReservation(owner: $owner);
    $payment = paidReservation($reservation);
    $refund = ['refund_amount' => '200'];

    $this->actingAs($owner);

    $this->post(route('admin.reservations.payments.store', $reservation->id), paymentData())->assertForbidden();
    $this->post(route('admin.reservations.payments.refund', [$reservation->id, $payment->id]), $refund)->assertForbidden();

    $this->withoutMiddleware(CheckRole::class);

    $this->post(route('admin.reservations.payments.store', $reservation->id), paymentData())->assertForbidden();
    $this->post(route('admin.reservations.payments.refund', [$reservation->id, $payment->id]), $refund)->assertForbidden();

    expect(ReservationPayment::query()->count())->toBe(1)
        ->and($payment->refresh()->refunded_at)->toBeNull();
});

test('guest cannot record a payment or see payments', function () {
    $this->post(route('admin.reservations.payments.store', paymentReservation()->id), paymentData())
        ->assertRedirect(route('login'));

    $this->get(route('user.payments'))->assertRedirect(route('login'));

    expect(ReservationPayment::query()->count())->toBe(0);
});
