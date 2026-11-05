<?php

use App\Enums\ReservationStatus;
use App\Enums\VehicleType;
use App\Models\Car;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Support\LocalDate;
use App\Support\PolishHolidays;
use Carbon\CarbonImmutable;
use Database\Seeders\CarSeeder;
use Database\Seeders\EquipmentSeeder;
use Database\Seeders\LocationSeeder;
use Database\Seeders\ReservationSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Storage::fake('public'));

test('seeder creates cars of every vehicle type', function (VehicleType $type) {
    $this->seed(CarSeeder::class);

    expect(Car::query()->where('vehicle_type', $type)->count())->toBe(2);
})->with(VehicleType::cases());

test('seeded cars have valid vin length', function () {
    $this->seed(CarSeeder::class);

    Car::query()->each(fn (Car $car) => expect(strlen($car->vin))->toBe(17));
});

test('seeded cars have images stored on disk', function () {
    $this->seed(CarSeeder::class);

    Car::query()->with('images')->get()->each(function (Car $car) {
        expect($car->images)->toHaveCount(3);

        foreach ($car->images as $image) {
            Storage::disk('public')->assertExists($image->path);

            expect(simplexml_load_string(Storage::disk('public')->get($image->path)))->not->toBeFalse()
                ->and(Storage::disk('public')->get($image->path))->not->toContain('{{');
        }
    });
});

test('seeded cars have equipment', function () {
    $this->seed([EquipmentSeeder::class, CarSeeder::class]);

    Car::query()->each(fn (Car $car) => expect($car->equipment)->not->toBeEmpty());
});

test('seeded reservations have pickup and return hours', function () {
    $this->seed([UserSeeder::class, LocationSeeder::class, CarSeeder::class, ReservationSeeder::class]);

    Reservation::query()->each(function (Reservation $reservation) {
        expect($reservation->start_date->setTimezone('Europe/Warsaw')->format('H:i'))->toBe('10:00')
            ->and($reservation->end_date->setTimezone('Europe/Warsaw')->format('H:i'))->toBe('16:00');
    });

    expect(Reservation::query()->count())->toBe(6);
});

test('running all seeders twice replaces reservations without traces', function () {
    $this->seed();
    $this->seed();

    expect(Reservation::query()->count())->toBe(6)
        ->and(Reservation::query()->withTrashed()->count())->toBe(6)
        ->and(app(ReservationRepositoryInterface::class)->removedCount())->toBe(0)
        ->and(Conversation::query()->count())->toBe(1)
        ->and(ChatMessage::query()->count())->toBe(3)
        ->and(Car::query()->count())->toBe(8);
});

test('seeder replaces reservations removed by the client', function () {
    $this->seed();

    Reservation::query()->firstOrFail()->delete();

    $this->seed();

    expect(Reservation::query()->withTrashed()->count())->toBe(6)
        ->and(Reservation::query()->count())->toBe(6);
});

test('seeded pending reservation has the business day deadline', function () {
    $this->travelTo(CarbonImmutable::parse('2026-06-19 15:00', 'Europe/Warsaw'));

    $this->seed();

    $pending = Reservation::query()->where('status', ReservationStatus::Pending)->sole();

    expect($pending->confirmation_deadline->setTimezone('Europe/Warsaw')->format('Y-m-d H:i'))->toBe('2026-06-22 15:00')
        ->and($pending->seen_by_admin_at)->toBeNull();
});

test('seeded reservations avoid holidays and keep the right price', function (string $today) {
    $this->travelTo($today);

    $this->seed();

    Reservation::query()->each(function (Reservation $reservation) {
        $start = $reservation->start_date->setTimezone('Europe/Warsaw')->toDateString();
        $end = $reservation->end_date->setTimezone('Europe/Warsaw')->toDateString();
        $days = LocalDate::rentalDays($reservation->start_date, $reservation->end_date);

        expect(PolishHolidays::name($start))->toBeNull()
            ->and(PolishHolidays::name($end))->toBeNull()
            ->and((float) $reservation->total_price)->toBe((float) $reservation->daily_rate * $days);
    });
})->with([
    'przed Bożym Narodzeniem' => ['2026-12-18 12:00:00'],
    'w Wigilię' => ['2026-12-24 12:00:00'],
    'przed Niepodległości' => ['2026-11-04 12:00:00'],
    'przed majówką' => ['2027-04-24 12:00:00'],
]);

test('seeded rentals keep their states', function () {
    $this->seed();

    $this->actingAs(User::query()->where('email', 'user_kwsmis@mis.pl')->sole())
        ->get(route('user.rentals'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('counts.pending', 1)
            ->where('counts.upcoming', 1)
            ->where('counts.active', 1)
            ->where('counts.completed', 2)
            ->where('counts.cancelled', 1));
});

test('running seeder twice does not create duplicates', function () {
    $this->seed(CarSeeder::class);
    $this->seed(CarSeeder::class);

    expect(Car::query()->count())->toBe(8);
});

test('seeded cars have mileage limits or no limit', function () {
    $this->seed(CarSeeder::class);

    expect(Car::query()->where('vehicle_type', VehicleType::Passenger)->whereNull('km_limit_per_day')->count())->toBe(2)
        ->and(Car::query()->where('vehicle_type', VehicleType::Van)->value('km_limit_per_day'))->toBe(300);
});

test('seeded cars have price tiers starting from one day', function () {
    $this->seed(CarSeeder::class);

    Car::query()->with('prices')->get()->each(function (Car $car) {
        expect($car->prices)->toHaveCount(3)
            ->and($car->prices[0]->min_days)->toBe(1)
            ->and($car->prices[2]->max_days)->toBeNull()
            ->and($car->daily_rate)->toBe($car->prices[2]->daily_rate);
    });
});
