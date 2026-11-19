<?php

use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\Reservation;
use App\Models\User;
use Database\Seeders\CarSeeder;
use Database\Seeders\LocationSeeder;
use Illuminate\Support\Facades\Storage;

// Jeden samochód nie może mieć dwóch rezerwacji w nakładających się terminach, np. 1-15 i 2-18 sierpnia.

beforeEach(function () {
    Storage::fake('public');
    $this->travelTo('2027-07-20 12:00:00');
    $this->seed([LocationSeeder::class, CarSeeder::class]);
    $this->car = Car::query()->where('required_license', 'b')->where('operational_status', 'active')->orderBy('id')->firstOrFail();
    $this->location = $this->car->locations()->firstOrFail()->id;
});

function overlapBooking(string $from, string $to, array $overrides = []): array
{
    return [
        'from' => $from,
        'to' => $to,
        'from_time' => '10:00',
        'to_time' => '10:00',
        'pickup' => test()->location,
        'dropoff' => test()->location,
        'driver_name' => 'Jan Kowalski',
        'driver_license_category' => 'b',
        ...$overrides,
    ];
}

function overlapBook(string $from, string $to, ?Car $car = null)
{
    return test()->actingAs(User::factory()->create())
        ->post(route('cars.book.store', ($car ?? test()->car)->id), overlapBooking($from, $to));
}

function overlapStaffData(Reservation $reservation, array $overrides = []): array
{
    $timezone = 'Europe/Warsaw';

    return [
        'car_id' => $reservation->car_id,
        'from' => $reservation->start_date->setTimezone($timezone)->toDateString(),
        'to' => $reservation->end_date->setTimezone($timezone)->toDateString(),
        'from_time' => $reservation->start_date->setTimezone($timezone)->format('H:i'),
        'to_time' => $reservation->end_date->setTimezone($timezone)->format('H:i'),
        'pickup' => test()->location,
        'dropoff' => test()->location,
        'status' => $reservation->status->value,
        'client_acceptance' => 'Telefonicznie',
        ...$overrides,
    ];
}

test('second client cannot book the same car for an overlapping term', function (string $from, string $to) {
    overlapBook('2027-08-01', '2027-08-14')->assertSessionHasNoErrors();

    overlapBook($from, $to)->assertSessionHasErrors('from');

    expect(Reservation::query()->count())->toBe(1);
})->with([
    'zaczyna się w trakcie i kończy później (2-18)' => ['2027-08-02', '2027-08-18'],
    'zaczyna się wcześniej i kończy w trakcie' => ['2027-07-28', '2027-08-03'],
    'mieści się w środku' => ['2027-08-05', '2027-08-07'],
    'obejmuje cały termin' => ['2027-07-30', '2027-08-20'],
    'ten sam termin' => ['2027-08-01', '2027-08-14'],
]);

test('confirmed reservation blocks the term too', function () {
    overlapBook('2027-08-01', '2027-08-14');
    Reservation::query()->update(['status' => ReservationStatus::Confirmed]);

    overlapBook('2027-08-02', '2027-08-18')->assertSessionHasErrors('from');

    expect(Reservation::query()->count())->toBe(1);
});

test('the same term on another car is fine', function () {
    $other = Car::query()->where('required_license', 'b')->where('operational_status', 'active')->whereKeyNot($this->car->id)
        ->whereHas('locations', fn ($query) => $query->whereKey($this->location))->firstOrFail();

    overlapBook('2027-08-01', '2027-08-14')->assertSessionHasNoErrors();
    overlapBook('2027-08-02', '2027-08-18', $other)->assertSessionHasNoErrors();

    expect(Reservation::query()->count())->toBe(2);
});

test('next rental may start after the return and the break', function () {
    overlapBook('2027-08-01', '2027-08-14')->assertSessionHasNoErrors();

    // Zwrot 14 sierpnia o 10:00, przerwa 60 minut.
    $this->actingAs(User::factory()->create())
        ->post(route('cars.book.store', $this->car->id), overlapBooking('2027-08-14', '2027-08-18', ['from_time' => '10:30']))
        ->assertSessionHasErrors('from');

    $this->post(route('cars.book.store', $this->car->id), overlapBooking('2027-08-14', '2027-08-18', ['from_time' => '11:00']))
        ->assertSessionHasNoErrors();
});

test('client cannot move own reservation onto a taken term', function () {
    overlapBook('2027-08-01', '2027-08-14');
    $client = User::factory()->create();
    $this->actingAs($client)->post(route('cars.book.store', $this->car->id), overlapBooking('2027-08-20', '2027-08-25'));
    $own = Reservation::query()->where('user_id', $client->id)->sole();

    $this->put(route('user.reservations.update', $own->id), overlapBooking('2027-08-02', '2027-08-18'))
        ->assertSessionHasErrors('from');

    expect($own->refresh()->start_date->toDateString())->toBe('2027-08-20');
});

test('administrator cannot create an overlap either', function () {
    $staff = User::factory()->admin()->create();
    overlapBook('2027-08-01', '2027-08-14');
    $first = Reservation::query()->sole();
    $first->update(['end_date' => '2027-08-15 08:00:00']);

    $second = Reservation::query()->create([
        'user_id' => User::factory()->create()->id,
        'car_id' => Car::query()->whereKeyNot($this->car->id)->value('id'),
        'pickup_location_id' => $this->location,
        'dropoff_location_id' => $this->location,
        'start_date' => '2027-08-02 08:00:00',
        'end_date' => '2027-08-18 08:00:00',
        'status' => ReservationStatus::Pending,
        'daily_rate' => '100.00',
        'deposit_amount' => '1000.00',
        'total_price' => '1600.00',
        'confirmation_deadline' => now()->addDay(),
    ]);

    // Przeniesienie na zajęte auto w terminie 2-18 sierpnia, gdy pierwsze trwa 1-15.
    $this->actingAs($staff)
        ->post(route('admin.reservations.update', $second->id), overlapStaffData($second, ['car_id' => $this->car->id]))
        ->assertSessionHasErrors('from');

    expect($second->refresh()->car_id)->not->toBe($this->car->id);
});

test('expired request does not block, and cannot be revived over the new one', function () {
    overlapBook('2027-08-01', '2027-08-14');
    $expired = Reservation::query()->sole();
    $expired->update(['confirmation_deadline' => now()->subMinute()]);

    overlapBook('2027-08-02', '2027-08-18')->assertSessionHasNoErrors();

    $staff = User::factory()->admin()->create();

    $this->actingAs($staff)->post(route('admin.reservations.confirm', $expired->id))->assertSessionHasErrors('reservation');
    $this->post(route('admin.reservations.update', $expired->id), overlapStaffData($expired, ['status' => 'confirmed']))
        ->assertSessionHasErrors('from');

    expect($expired->refresh()->status)->toBe(ReservationStatus::Pending);
});

test('cancelled reservation cannot be restored over a newer one', function () {
    overlapBook('2027-08-01', '2027-08-14');
    $old = Reservation::query()->sole();
    $old->update(['status' => ReservationStatus::Cancelled]);

    overlapBook('2027-08-02', '2027-08-18')->assertSessionHasNoErrors();

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.reservations.update', $old->id), overlapStaffData($old, ['status' => 'pending']))
        ->assertSessionHasErrors('from');

    expect($old->refresh()->status)->toBe(ReservationStatus::Cancelled);
});

test('no two blocking reservations of one car overlap after all attempts', function () {
    foreach ([['2027-08-01', '2027-08-14'], ['2027-08-02', '2027-08-18'], ['2027-08-05', '2027-08-07'], ['2027-07-29', '2027-08-03'], ['2027-08-16', '2027-08-20']] as [$from, $to]) {
        overlapBook($from, $to);
    }

    $blocking = Reservation::query()->where('car_id', $this->car->id)->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])->orderBy('start_date')->get();

    foreach ($blocking->slice(1)->values() as $index => $reservation) {
        expect($reservation->start_date->greaterThanOrEqualTo($blocking[$index]->end_date))->toBeTrue();
    }

    expect($blocking)->toHaveCount(2);
});
