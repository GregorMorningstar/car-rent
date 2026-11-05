<?php

namespace App\Models;

use App\Enums\DrivingLicense;
use App\Enums\ReservationStatus;
use App\Events\ReservationPlaced;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Throwable;

/**
 * @property int $id
 * @property int $user_id
 * @property int $car_id
 * @property int|null $pickup_location_id
 * @property int|null $dropoff_location_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property ReservationStatus $status
 * @property string $daily_rate
 * @property string $deposit_amount
 * @property string $total_price
 * @property CarbonImmutable $confirmation_deadline
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property CarbonImmutable|null $seen_by_admin_at
 * @property CarbonImmutable|null $removal_seen_by_admin_at
 * @property CarbonImmutable|null $deleted_at
 * @property string|null $notes
 * @property string|null $driver_name
 * @property string|null $driver_license_number
 * @property DrivingLicense|null $driver_license_category
 * @property CarbonImmutable|null $picked_up_at
 * @property CarbonImmutable|null $returned_at
 * @property int|null $returned_mileage_km
 * @property string|null $term_accepted_via
 * @property CarbonImmutable|null $term_accepted_at
 * @property int|null $term_accepted_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Car $car
 * @property-read User|null $termAcceptedBy
 * @property-read Collection<int, ReservationPayment> $payments
 * @property-read Location|null $pickupLocation
 * @property-read Location|null $dropoffLocation
 */
#[Fillable([
    'user_id',
    'car_id',
    'pickup_location_id',
    'dropoff_location_id',
    'start_date',
    'end_date',
    'status',
    'daily_rate',
    'deposit_amount',
    'total_price',
    'confirmation_deadline',
    'confirmed_at',
    'cancelled_at',
    'seen_by_admin_at',
    'removal_seen_by_admin_at',
    'notes',
    'driver_name',
    'driver_license_number',
    'driver_license_category',
    'picked_up_at',
    'returned_at',
    'returned_mileage_km',
    'term_accepted_via',
    'term_accepted_at',
    'term_accepted_by',
])]
class Reservation extends Model
{
    use SoftDeletes;

    /** Nowa rezerwacja od razu pojawia się w panelu administratora. */
    protected static function booted(): void
    {
        static::created(function (): void {
            try {
                ReservationPlaced::dispatch();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'status' => ReservationStatus::class,
            'daily_rate' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'confirmation_deadline' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'seen_by_admin_at' => 'datetime',
            'removal_seen_by_admin_at' => 'datetime',
            'term_accepted_at' => 'datetime',
            'driver_license_number' => 'encrypted',
            'driver_license_category' => DrivingLicense::class,
            'picked_up_at' => 'datetime',
            'returned_at' => 'datetime',
            'returned_mileage_km' => 'integer',
        ];
    }

    /** Klient może zmienić rezerwację, dopóki czeka na decyzję albo wynajem się nie zaczął. */
    public function isEditableByClient(): bool
    {
        if ($this->picked_up_at !== null) {
            return false;
        }

        return match ($this->status) {
            ReservationStatus::Pending => $this->confirmation_deadline->isFuture(),
            ReservationStatus::Confirmed => $this->start_date->isFuture(),
            default => false,
        };
    }

    /** Trwającego ani zakończonego wynajmu nie można usunąć, tak samo jak z nierozliczoną wpłatą. */
    public function isRemovableByClient(): bool
    {
        if ($this->picked_up_at !== null || $this->payments->whereNull('refunded_at')->isNotEmpty()) {
            return false;
        }

        return $this->status !== ReservationStatus::Confirmed || $this->start_date->isFuture();
    }

    /** Samochód jest u klienta: wydany, a jeszcze nieprzyjęty. */
    public function isOut(): bool
    {
        return $this->picked_up_at !== null && $this->returned_at === null;
    }

    /** Oczekująca po terminie jest już wygasła, nawet przed uruchomieniem komendy. */
    public function currentStatus(): ReservationStatus
    {
        return $this->status === ReservationStatus::Pending && $this->confirmation_deadline->isPast()
            ? ReservationStatus::Expired
            : $this->status;
    }

    /** Status do wyświetlenia: usunięta przez klienta albo potwierdzona po terminie zwrotu, czyli zrealizowana. */
    public function displayState(): string
    {
        if ($this->trashed()) {
            return 'deleted';
        }

        return $this->status === ReservationStatus::Confirmed && $this->end_date->isPast()
            ? 'completed'
            : $this->currentStatus()->value;
    }

    /**
     * Zaliczki i kaucje od najstarszej.
     *
     * @return HasMany<ReservationPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ReservationPayment::class)->orderBy('paid_at')->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kto wpisał, że klient zaakceptował zmianę auta, miejsca lub terminu.
     *
     * @return BelongsTo<User, $this>
     */
    public function termAcceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'term_accepted_by');
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function pickupLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function dropoffLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'dropoff_location_id');
    }
}
