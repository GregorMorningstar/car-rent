<?php

namespace App\Repositories;

use App\Enums\CarStatus;
use App\Enums\PaymentType;
use App\Enums\ReservationStatus;
use App\Models\Car;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Support\LocalDate;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ReservationRepository implements ReservationRepositoryInterface
{
    public function __construct(
        private readonly Reservation $model,
        private readonly Car $car,
    ) {}

    public function latestUnseen(int $limit): array
    {
        return $this->awaitingDecision()
            ->with([
                'user:id,name',
                'car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model']),
            ])
            ->whereNull('seen_by_admin_at')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'client' => $reservation->user?->name,
                'car' => trim(($reservation->car?->brand ?? '').' '.($reservation->car?->model ?? '')),
                'from' => $reservation->start_date->toJSON(),
                'to' => $reservation->end_date->toJSON(),
                'total_price' => $reservation->total_price,
                'created_at' => $reservation->created_at?->toJSON(),
            ])
            ->all();
    }

    public function unseenCount(): int
    {
        return $this->awaitingDecision()->whereNull('seen_by_admin_at')->count();
    }

    public function latestRemoved(int $limit): array
    {
        return $this->removedAwaitingNotice()
            ->with([
                'user:id,name',
                'car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model']),
            ])
            ->latest('deleted_at')
            ->limit($limit)
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'client' => $reservation->user?->name,
                'car' => trim(($reservation->car?->brand ?? '').' '.($reservation->car?->model ?? '')),
                'from' => $reservation->start_date->toJSON(),
                'to' => $reservation->end_date->toJSON(),
                'was_confirmed' => $reservation->status === ReservationStatus::Confirmed,
                'removed_at' => $reservation->deleted_at?->toJSON(),
            ])
            ->all();
    }

    public function removedCount(): int
    {
        return $this->removedAwaitingNotice()->count();
    }

    public function markAllSeen(): void
    {
        $this->model->newQuery()->whereNull('seen_by_admin_at')->update(['seen_by_admin_at' => now()]);
        $this->removedAwaitingNotice()->update(['removal_seen_by_admin_at' => now()]);
    }

    public function findBookableCar(int $carId): ?Car
    {
        return $this->car->newQuery()
            ->with('prices')
            ->where('operational_status', CarStatus::Active)
            ->find($carId);
    }

    public function carIsAtLocation(int $carId, int $locationId): bool
    {
        return $this->car->newQuery()
            ->whereKey($carId)
            ->whereHas('locations', fn (Builder $location) => $location->whereKey($locationId))
            ->exists();
    }

    public function pendingCountFor(int $userId): int
    {
        return $this->awaitingDecision()->where('user_id', $userId)->count();
    }

    public function createIfAvailable(int $carId, array $attributes, CarbonInterface $from, CarbonInterface $to): ?Reservation
    {
        return DB::transaction(function () use ($carId, $attributes, $from, $to) {
            // Blokada wiersza: druga osoba rezerwująca to auto czeka na koniec tej transakcji.
            $this->car->newQuery()->whereKey($carId)->lockForUpdate()->first();

            $available = $this->car->newQuery()
                ->whereKey($carId)
                ->availableBetween($from, $to)
                ->exists();

            return $available ? $this->model->create(['car_id' => $carId, ...$attributes]) : null;
        });
    }

    public function updateIfAvailable(Reservation $reservation, array $attributes, CarbonInterface $from, CarbonInterface $to): bool
    {
        return DB::transaction(function () use ($reservation, $attributes, $from, $to) {
            // Administracja może przenieść rezerwację na inne auto.
            $carId = $attributes['car_id'] ?? $reservation->car_id;

            $this->car->newQuery()->whereKey($carId)->lockForUpdate()->first();

            // Rezerwację mógł właśnie usunąć klient.
            $exists = $this->model->newQuery()->whereKey($reservation->id)->lockForUpdate()->exists();

            $available = $exists && $this->car->newQuery()
                ->whereKey($carId)
                ->availableBetween($from, $to, $reservation->id)
                ->exists();

            return $available && $reservation->update($attributes);
        });
    }

    public function findOwned(int $id, int $userId): ?Reservation
    {
        return $this->model->newQuery()->where('user_id', $userId)->find($id);
    }

    public function remove(Reservation $reservation, bool $notifyStaff): bool
    {
        return DB::transaction(function () use ($reservation, $notifyStaff) {
            $fresh = $this->model->newQuery()->whereKey($reservation->id)->lockForUpdate()->first();

            if ($fresh === null || ! $fresh->isRemovableByClient()) {
                return false;
            }

            if (! $notifyStaff) {
                $fresh->update(['removal_seen_by_admin_at' => now()]);
            }

            return (bool) $fresh->delete();
        });
    }

    public function current(): array
    {
        return $this->model->newQuery()
            ->with([
                'user:id,name,email',
                'car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model', 'registration_number']),
                'pickupLocation:id,name',
                'dropoffLocation:id,name',
                'payments',
            ])
            ->withTrashed()
            ->where(fn (Builder $visible) => $visible
                // Usunięta przez klienta zostaje tu do końca swojego terminu, a potem jest w historii.
                ->where(fn (Builder $removed) => $removed
                    ->whereNotNull('deleted_at')
                    ->where('end_date', '>=', now())
                    ->where(fn (Builder $held) => $held
                        ->where('status', ReservationStatus::Confirmed)
                        ->orWhere(fn (Builder $pending) => $pending
                            ->where('status', ReservationStatus::Pending)
                            ->whereColumn('confirmation_deadline', '>=', 'deleted_at'))))
                ->orWhere(fn (Builder $live) => $live
                    ->whereNull('deleted_at')
                    ->where(fn (Builder $query) => $query
                        ->where(fn (Builder $pending) => $pending
                            ->where('status', ReservationStatus::Pending)
                            ->where('confirmation_deadline', '>=', now()))
                        ->orWhere(fn (Builder $confirmed) => $confirmed
                            ->where('status', ReservationStatus::Confirmed)
                            ->where('end_date', '>=', now()))
                // Kaucję rozlicza się po zwrocie auta, więc zakończony wynajem czeka na liście.
                        ->orWhereHas('payments', fn (Builder $payment) => $payment
                            ->where('type', PaymentType::Deposit)
                            ->whereNull('refunded_at'))
                // Nieaktualna rezerwacja zostaje na liście, dopóki nie rozliczono jej wpłat.
                        ->orWhere(fn (Builder $closed) => $closed
                            ->whereHas('payments', fn (Builder $payment) => $payment->whereNull('refunded_at'))
                            ->where(fn (Builder $status) => $status
                                ->whereIn('status', [ReservationStatus::Cancelled, ReservationStatus::Expired])
                                ->orWhere(fn (Builder $overdue) => $overdue
                                    ->where('status', ReservationStatus::Pending)
                                    ->where('confirmation_deadline', '<', now())))))))
            ->orderBy('confirmation_deadline')
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'client_id' => $reservation->user_id,
                'number' => sprintf('REZ/%d/%04d', $reservation->created_at?->year ?? now()->year, $reservation->id),
                'status' => $reservation->trashed() ? 'deleted' : $reservation->currentStatus()->value,
                'deleted_at' => $reservation->deleted_at?->toJSON(),
                'finished' => $reservation->status === ReservationStatus::Confirmed && $reservation->end_date->isPast(),
                'client' => $reservation->user?->name,
                'email' => $reservation->user?->email,
                'car' => trim(($reservation->car?->brand ?? '').' '.($reservation->car?->model ?? '')),
                'registration_number' => $reservation->car?->registration_number,
                'from' => $reservation->start_date->toJSON(),
                'to' => $reservation->end_date->toJSON(),
                'pickup' => $reservation->pickupLocation?->name,
                'dropoff' => $reservation->dropoffLocation?->name,
                'total_price' => $reservation->total_price,
                'deposit_amount' => $reservation->deposit_amount,
                'confirmation_deadline' => $reservation->confirmation_deadline->toJSON(),
                'notes' => $reservation->notes,
                'driver_name' => $reservation->driver_name,
                'payments' => $this->paymentRows($reservation),
            ])
            ->all();
    }

    public function find(int $id): ?Reservation
    {
        return $this->model->newQuery()
            ->with(['car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model'])])
            ->find($id);
    }

    public function findForStaff(int $id): ?Reservation
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->with([
                'user.profile',
                'car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model', 'registration_number', 'mileage_km', 'required_license']),
                'payments',
                'termAcceptedBy:id,name',
            ])
            ->find($id);
    }

    public function findCarWithPrices(int $carId): ?Car
    {
        return $this->car->newQuery()->withTrashed()->with('prices')->find($carId);
    }

    public function rows(?int $userId = null, ?int $carId = null): array
    {
        return $this->model->newQuery()
            ->withTrashed()
            ->with([
                'user.profile',
                'car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model', 'registration_number']),
            ])
            ->when($userId !== null, fn (Builder $query) => $query->where('user_id', $userId))
            ->when($carId !== null, fn (Builder $query) => $query->where('car_id', $carId))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map($this->toRow(...))
            ->all();
    }

    public function saveStaffChanges(Reservation $reservation, array $attributes): void
    {
        $reservation->update($attributes);
    }

    public function carOptions(?int $currentCarId = null): array
    {
        return $this->car->newQuery()
            ->withTrashed()
            ->with('locations:id')
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $active) => $active
                    ->whereNull('deleted_at')
                    ->where('operational_status', CarStatus::Active))
                ->when($currentCarId !== null, fn (Builder $current) => $current->orWhereKey($currentCarId)))
            ->orderBy('brand')
            ->orderBy('model')
            ->get()
            ->map(fn (Car $car) => [
                'id' => $car->id,
                'name' => trim($car->brand.' '.$car->model),
                'registration_number' => $car->registration_number,
                'location_ids' => $car->locations->pluck('id')->all(),
            ])
            ->all();
    }

    public function locationOptions(array $currentIds): array
    {
        return Location::query()
            ->where('is_active', true)
            ->orWhereIn('id', array_filter($currentIds))
            ->orderBy('name')
            ->get(['id', 'name', 'is_pickup', 'is_dropoff'])
            ->map->only(['id', 'name', 'is_pickup', 'is_dropoff'])
            ->all();
    }

    public function markPickedUp(Reservation $reservation, array $driver): bool
    {
        // Przez model, bo numer prawa jazdy jest szyfrowany przy zapisie.
        return DB::transaction(function () use ($reservation, $driver) {
            $fresh = $this->model->newQuery()->whereKey($reservation->id)->lockForUpdate()->first();

            if ($fresh === null || $fresh->picked_up_at !== null || $fresh->status !== ReservationStatus::Confirmed) {
                return false;
            }

            return $fresh->update([...$driver, 'picked_up_at' => now()]);
        });
    }

    public function recordReturn(Reservation $reservation, int $mileageKm): bool
    {
        return DB::transaction(function () use ($reservation, $mileageKm) {
            $car = $this->car->newQuery()->withTrashed()->whereKey($reservation->car_id)->lockForUpdate()->first();
            $fresh = $this->model->newQuery()->whereKey($reservation->id)->lockForUpdate()->first();

            if ($car === null || $fresh === null || ! $fresh->isOut() || $mileageKm < $car->mileage_km) {
                return false;
            }

            $fresh->update(['returned_at' => now(), 'returned_mileage_km' => $mileageKm]);
            $car->update(['mileage_km' => $mileageKm]);

            return true;
        });
    }

    public function activeCarCount(): int
    {
        return $this->car->newQuery()->where('operational_status', CarStatus::Active)->count();
    }

    public function confirm(Reservation $reservation): bool
    {
        return $this->model->newQuery()
            ->whereKey($reservation->id)
            ->where('status', ReservationStatus::Pending)
            ->where('confirmation_deadline', '>=', now())
            ->update([
                'status' => ReservationStatus::Confirmed,
                'confirmed_at' => now(),
                'seen_by_admin_at' => now(),
            ]) === 1;
    }

    public function cancel(Reservation $reservation): bool
    {
        return $this->model->newQuery()
            ->whereKey($reservation->id)
            ->whereIn('status', [ReservationStatus::Pending, ReservationStatus::Confirmed])
            ->whereNull('picked_up_at')
            ->update([
                'status' => ReservationStatus::Cancelled,
                'cancelled_at' => now(),
                'seen_by_admin_at' => now(),
            ]) === 1;
    }

    public function expireOverdue(): int
    {
        return $this->model->newQuery()
            ->where('status', ReservationStatus::Pending)
            ->where('confirmation_deadline', '<', now())
            ->update(['status' => ReservationStatus::Expired]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(Reservation $reservation): array
    {
        return [
            'id' => $reservation->id,
            'number' => sprintf('REZ/%d/%04d', $reservation->created_at?->year ?? now()->year, $reservation->id),
            'status' => $reservation->currentStatus()->value,
            'state' => $reservation->displayState(),
            'deleted_at' => $reservation->deleted_at?->toJSON(),
            'created_at' => $reservation->created_at?->toJSON(),
            'start_date' => $reservation->start_date->toJSON(),
            'end_date' => $reservation->end_date->toJSON(),
            'days' => LocalDate::rentalDays($reservation->start_date, $reservation->end_date),
            'total_price' => $reservation->total_price,
            'notes' => $reservation->notes,
            'client' => [
                'id' => $reservation->user_id,
                'name' => $reservation->user?->name ?? 'Konto usunięte',
                'email' => $reservation->user?->email ?? '',
                'phone' => $reservation->user?->profile?->phone,
            ],
            'car' => [
                'id' => $reservation->car_id,
                'name' => trim(($reservation->car?->brand ?? '').' '.($reservation->car?->model ?? '')) ?: 'Samochód niedostępny',
                'registration_number' => $reservation->car?->registration_number ?? '',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffDetails(Reservation $reservation): array
    {
        $profile = $reservation->user?->profile;

        return [
            ...$this->toRow($reservation),
            'daily_rate' => $reservation->daily_rate,
            'deposit_amount' => $reservation->deposit_amount,
            'finished' => $reservation->displayState() === 'completed',
            'driver' => [
                'name' => $reservation->driver_name,
                'license_number' => $reservation->driver_license_number,
                'license_category' => $reservation->driver_license_category?->value,
            ],
            'required_license' => $reservation->car?->required_license?->value,
            'car_mileage_km' => $reservation->car?->mileage_km,
            'picked_up_at' => $reservation->picked_up_at?->toJSON(),
            'returned_at' => $reservation->returned_at?->toJSON(),
            'returned_mileage_km' => $reservation->returned_mileage_km,
            'acceptance' => $reservation->term_accepted_at === null ? null : [
                'via' => $reservation->term_accepted_via,
                'at' => $reservation->term_accepted_at->toJSON(),
                'by' => $reservation->termAcceptedBy?->name,
            ],
            'address' => $profile === null ? null : sprintf(
                '%s %s%s, %s %s',
                $profile->street,
                $profile->building_number,
                $profile->apartment_number ? '/'.$profile->apartment_number : '',
                $profile->postal_code,
                $profile->city,
            ),
            'payments' => $this->paymentRows($reservation),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function paymentRows(Reservation $reservation): array
    {
        return $reservation->payments->map(fn (ReservationPayment $payment) => [
            'id' => $payment->id,
            'type' => $payment->type->value,
            'payer' => $payment->payer,
            'amount' => $payment->amount,
            'method' => $payment->method,
            'paid_at' => $payment->paid_at->toJSON(),
            'refunded_amount' => $payment->refunded_amount,
            'refund_reason' => $payment->refund_reason,
            'refunded_at' => $payment->refunded_at?->toJSON(),
        ])->all();
    }

    /**
     * @return Builder<Reservation>
     */
    private function removedAwaitingNotice(): Builder
    {
        return $this->model->newQuery()->onlyTrashed()->whereNull('removal_seen_by_admin_at');
    }

    /**
     * Rezerwacje oczekujące, których termin potwierdzenia jeszcze nie minął.
     *
     * @return Builder<Reservation>
     */
    private function awaitingDecision(): Builder
    {
        return $this->model->newQuery()
            ->where('status', ReservationStatus::Pending)
            ->where('confirmation_deadline', '>=', now());
    }
}
