<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $car_id
 * @property CarbonImmutable $start_date
 * @property CarbonImmutable $end_date
 * @property ReservationStatus $status
 * @property string $daily_rate
 * @property string $deposit_amount
 * @property string $total_price
 * @property CarbonImmutable $confirmation_deadline
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 * @property-read Car $car
 */
#[Fillable([
    'user_id',
    'car_id',
    'start_date',
    'end_date',
    'status',
    'daily_rate',
    'deposit_amount',
    'total_price',
    'confirmation_deadline',
    'confirmed_at',
    'cancelled_at',
    'notes',
])]
class Reservation extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => ReservationStatus::class,
            'daily_rate' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'total_price' => 'decimal:2',
            'confirmation_deadline' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
