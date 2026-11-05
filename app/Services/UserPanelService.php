<?php

namespace App\Services;

use App\Enums\RentalState;
use App\Enums\ReservationStatus;
use App\Repositories\Contracts\UserPanelRepositoryInterface;
use App\Support\LocalDate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dane panelu użytkownika. Każdy odczyt dotyczy tylko konta zalogowanej osoby.
 */
class UserPanelService
{
    private const RECENT_RENTALS = 5;

    private const DASHBOARD_RENTALS = 3;

    public function __construct(
        private readonly UserPanelRepositoryInterface $userPanelRepository,
    ) {}

    /**
     * Historia wynajmów z liczbą pozycji w każdym stanie.
     * "failed" oznacza, że nie udało się odczytać danych - lista jest wtedy pusta.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rentals: array<int, array<string, mixed>>, counts: array<string, int>, status: string|null, failed: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function rentalHistory(int $userId, array $filters = []): array
    {
        $this->assertOwnAccount($userId);

        $validator = Validator::make(
            $filters,
            ['status' => ['nullable', Rule::enum(RentalState::class)]],
            [],
            ['status' => 'stan wynajmu'],
        );

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('user.rentals'));
        }

        $status = $validator->validated()['status'] ?? null;
        $rentals = $this->loadRentals($userId);

        return [
            'rentals' => $rentals === null
                ? []
                : array_values(array_filter($rentals, fn (array $rental) => $status === null || $rental['state'] === $status)),
            'counts' => $this->countByState($rentals ?? []),
            'status' => $status,
            'failed' => $rentals === null,
        ];
    }

    /**
     * Ostatnie wynajmy do profilu.
     *
     * @return array{rentals: array<int, array<string, mixed>>, failed: bool}
     *
     * @throws AuthorizationException
     */
    public function recentRentals(int $userId): array
    {
        $this->assertOwnAccount($userId);

        $rentals = $this->loadRentals($userId);

        return [
            'rentals' => array_slice($rentals ?? [], 0, self::RECENT_RENTALS),
            'failed' => $rentals === null,
        ];
    }

    /**
     * Podsumowanie na pulpit. "spent" obejmuje wynajmy trwające i zakończone, bo płatności nie ma jeszcze w bazie.
     *
     * @return array{
     *     stats: array{rentals: int, days: int, spent: string},
     *     current: array<string, mixed>|null,
     *     next: array<string, mixed>|null,
     *     rentals: array<int, array<string, mixed>>,
     *     failed: bool,
     * }
     *
     * @throws AuthorizationException
     */
    public function dashboard(int $userId): array
    {
        $this->assertOwnAccount($userId);

        $rentals = $this->loadRentals($userId);
        $all = collect($rentals ?? []);
        $started = $all->whereIn('state', [RentalState::Active->value, RentalState::Completed->value]);
        $future = $all->sortBy('from');

        return [
            'stats' => [
                'rentals' => $started->count() + $all->where('state', RentalState::Upcoming->value)->count(),
                'days' => $started->sum('days'),
                'spent' => number_format($started->sum(fn (array $rental) => (float) $rental['total_price']), 2, '.', ''),
            ],
            'current' => $all->firstWhere('state', RentalState::Active->value),
            'next' => $future->firstWhere('state', RentalState::Upcoming->value)
                ?? $future->firstWhere('state', RentalState::Pending->value),
            'rentals' => $all->take(self::DASHBOARD_RENTALS)->values()->all(),
            'failed' => $rentals === null,
        ];
    }

    /**
     * Zaliczki i kaucje klienta wraz z rozliczeniem.
     *
     * @return array{payments: array<int, array<string, mixed>>, failed: bool}
     *
     * @throws AuthorizationException
     */
    public function payments(int $userId): array
    {
        $this->assertOwnAccount($userId);

        try {
            $payments = $this->userPanelRepository->paymentsForUser($userId);
        } catch (Throwable $exception) {
            report($exception);

            return ['payments' => [], 'failed' => true];
        }

        $own = array_filter($payments, fn (array $payment) => $payment['user_id'] === $userId);

        return ['payments' => array_values(array_map($this->toPayment(...), $own)), 'failed' => false];
    }

    /**
     * Zalogowana osoba może odczytać tylko własne dane.
     *
     * @throws AuthorizationException
     */
    private function assertOwnAccount(int $userId): void
    {
        if (Auth::id() === null || Auth::id() !== $userId) {
            throw new AuthorizationException('Możesz przeglądać tylko swoje dane.');
        }
    }

    /**
     * Wiersze są sprawdzane jeszcze raz po odczycie, więc błąd w zapytaniu nie pokaże cudzych danych.
     *
     * @return array<int, array<string, mixed>>|null null, gdy odczyt się nie udał
     */
    private function loadRentals(int $userId): ?array
    {
        try {
            $rentals = $this->userPanelRepository->rentalsForUser($userId);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $own = array_filter($rentals, fn (array $rental) => $rental['user_id'] === $userId);

        return array_values(array_map($this->toRental(...), $own));
    }

    /**
     * @param  array<string, mixed>  $rental
     * @return array<string, mixed>
     */
    private function toRental(array $rental): array
    {
        return [
            'id' => $rental['id'],
            'number' => $this->rentalNumber($rental['year'] ?? null, $rental['id']),
            'car' => $this->carName($rental),
            'pickup' => $rental['pickup'] ?? null,
            'dropoff' => $rental['dropoff'] ?? null,
            'from' => $rental['start_date']->toJSON(),
            'to' => $rental['end_date']->toJSON(),
            'days' => LocalDate::rentalDays($rental['start_date'], $rental['end_date']),
            'daily_rate' => $rental['daily_rate'],
            'total_price' => $rental['total_price'],
            'state' => $state = $this->state($rental)->value,
            'confirm_by' => $state === RentalState::Pending->value ? $rental['confirmation_deadline']->toJSON() : null,
            'can_edit' => $rental['can_edit'] ?? false,
            'can_delete' => $rental['can_delete'] ?? false,
        ];
    }

    /**
     * @param  array<string, mixed>  $payment
     * @return array<string, mixed>
     */
    private function toPayment(array $payment): array
    {
        return [
            'id' => $payment['id'],
            'type' => $payment['type'],
            'number' => $this->rentalNumber($payment['year'] ?? null, $payment['reservation_id']),
            'car' => $this->carName($payment),
            'payer' => $payment['payer'],
            'amount' => $payment['amount'],
            'method' => $payment['method'],
            'paid_at' => $payment['paid_at']->toJSON(),
            'refunded_amount' => $payment['refunded_amount'],
            'refund_reason' => $payment['refund_reason'],
            'refunded_at' => $payment['refunded_at']?->toJSON(),
        ];
    }

    private function rentalNumber(?int $year, int $id): string
    {
        return sprintf('WYN/%d/%04d', $year ?? now()->year, $id);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function carName(array $row): string
    {
        return trim(($row['brand'] ?? '').' '.($row['model'] ?? '')) ?: 'Samochód niedostępny';
    }

    /**
     * Oczekująca rezerwacja po terminie potwierdzenia nie blokuje już auta, więc dla klienta jest anulowana.
     *
     * @param  array<string, mixed>  $rental
     */
    private function state(array $rental): RentalState
    {
        return match ($rental['status']) {
            ReservationStatus::Cancelled, ReservationStatus::Expired => RentalState::Cancelled,
            ReservationStatus::Pending => $rental['confirmation_deadline']->isPast() ? RentalState::Cancelled : RentalState::Pending,
            ReservationStatus::Confirmed => match (true) {
                $rental['start_date']->isFuture() => RentalState::Upcoming,
                $rental['end_date']->isPast() => RentalState::Completed,
                default => RentalState::Active,
            },
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rentals
     * @return array<string, int>
     */
    private function countByState(array $rentals): array
    {
        $counts = array_fill_keys(RentalState::values(), 0);

        foreach ($rentals as $rental) {
            $counts[$rental['state']]++;
        }

        return $counts;
    }
}
