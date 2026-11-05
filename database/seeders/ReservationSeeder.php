<?php

namespace Database\Seeders;

use App\Enums\PaymentType;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\User;
use App\Support\LocalDate;
use App\Support\PolishHolidays;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Przykładowa historia wynajmów konta użytkownika - w każdym stanie, z datami liczonymi od dziś.
 * Ponowne uruchomienie trwale zastępuje rezerwacje tego konta, także usunięte przez klienta.
 */
class ReservationSeeder extends Seeder
{
    private const USER_EMAIL = 'user_kwsmis@mis.pl';

    /** Numer auta z CarSeeder, początek i koniec względem dzisiaj oraz status. */
    private const RENTALS = [
        [0, -60, -56, ReservationStatus::Confirmed],
        [2, -30, -29, ReservationStatus::Confirmed],
        [1, -20, -18, ReservationStatus::Cancelled],
        [3, -1, 2, ReservationStatus::Confirmed],
        [4, 7, 10, ReservationStatus::Confirmed],
        [5, 14, 21, ReservationStatus::Pending],
    ];

    public function run(): void
    {
        $user = User::query()->where('email', self::USER_EMAIL)->first();
        $cars = Car::query()->with('prices')->orderBy('id')->get();

        if ($user === null || $cars->count() <= max(array_column(self::RENTALS, 0))) {
            return;
        }

        $locations = Location::query()->orderBy('id')->pluck('id');

        // Trwale, bo zwykłe usunięcie zostawiłoby rekordy i adnotacje dla administracji.
        Reservation::query()->withTrashed()->where('user_id', $user->id)->forceDelete();

        foreach (self::RENTALS as $index => [$carIndex, $fromDays, $toDays, $status]) {
            $car = $cars[$carIndex];
            $start = LocalDate::at($this->openDay($fromDays), '10:00');
            $end = LocalDate::at($this->openDay($toDays), '16:00');
            $days = LocalDate::rentalDays($start, $end);
            $rate = (float) $car->prices->first(
                fn ($price) => $price->min_days <= $days && ($price->max_days === null || $price->max_days >= $days),
            )->daily_rate;

            $reservation = Reservation::query()->create([
                'user_id' => $user->id,
                'car_id' => $car->id,
                'pickup_location_id' => $locations->first(),
                'dropoff_location_id' => $index % 2 === 0 ? $locations->first() : $locations->last(),
                'start_date' => $start,
                'end_date' => $end,
                'status' => $status,
                'daily_rate' => $rate,
                'deposit_amount' => $car->deposit_amount,
                'total_price' => $rate * $days,
                'confirmation_deadline' => $status === ReservationStatus::Pending ? LocalDate::businessDayAfter(now()) : $start,
                'confirmed_at' => $status === ReservationStatus::Confirmed ? now()->addDays($fromDays - 3) : null,
                'cancelled_at' => $status === ReservationStatus::Cancelled ? now()->addDays($fromDays - 1) : null,
                // Tylko oczekująca rezerwacja jest nowym zamówieniem w chmurce.
                'seen_by_admin_at' => $status === ReservationStatus::Pending ? null : now(),
                'driver_name' => $user->name,
                'driver_license_number' => '01234/26/0001',
                'driver_license_category' => $car->required_license,
                'picked_up_at' => in_array($index, [0, 1, 3], true) ? $start : null,
                'returned_at' => in_array($index, [0, 1], true) ? $end : null,
                'returned_mileage_km' => in_array($index, [0, 1], true) ? $car->mileage_km : null,
            ]);

            $this->seedPayments($reservation, $index, $user->name);
        }
    }

    /** Wpłaty w każdym stanie: zwrot pełny, częściowy, oraz wciąż nierozliczone. */
    private function seedPayments(Reservation $reservation, int $index, string $payer): void
    {
        $deposit = (float) $reservation->deposit_amount;
        $advance = round((float) $reservation->total_price * 0.2, 2);

        $pay = fn (PaymentType $type, float $amount, array $settlement = []) => ReservationPayment::query()->create([
            'reservation_id' => $reservation->id,
            'type' => $type,
            'payer' => $payer,
            'amount' => $amount,
            'method' => 'Gotówka',
            'paid_at' => min($reservation->start_date, now()),
            ...$settlement,
        ]);

        match ($index) {
            0 => $pay(PaymentType::Deposit, $deposit, [
                'refunded_amount' => round($deposit / 2, 2),
                'refund_reason' => 'Niezgłoszona szkoda: zarysowany zderzak.',
                'refunded_at' => $reservation->end_date,
            ]),
            1 => $pay(PaymentType::Deposit, $deposit, ['refunded_amount' => $deposit, 'refunded_at' => $reservation->end_date]),
            2 => $pay(PaymentType::Advance, $advance, ['paid_at' => $reservation->cancelled_at->subDay(), 'refunded_amount' => $advance, 'refunded_at' => $reservation->cancelled_at]),
            3 => [$pay(PaymentType::Advance, $advance), $pay(PaymentType::Deposit, $deposit)],
            4 => $pay(PaymentType::Advance, $advance),
            default => null,
        };
    }

    /** Dzień względem dzisiaj. Odbiór i zwrot nie mogą wypaść w święto, więc przechodzi na kolejny. */
    private function openDay(int $days): string
    {
        $day = CarbonImmutable::parse(LocalDate::today())->addDays($days);

        while (PolishHolidays::name($day->toDateString()) !== null) {
            $day = $day->addDay();
        }

        return $day->toDateString();
    }
}
