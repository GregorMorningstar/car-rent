<?php

namespace App\Models;

use App\Enums\PaymentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Zaliczka lub kaucja wpisana ręcznie przez administrację. Płatności online nie są podłączone.
 * Wypełnione refunded_at oznacza rozliczenie, także gdy klient dostał mniej albo nic.
 *
 * @property int $id
 * @property int $reservation_id
 * @property PaymentType $type
 * @property string $payer
 * @property string $amount
 * @property string $method
 * @property CarbonImmutable $paid_at
 * @property string|null $refunded_amount
 * @property string|null $refund_reason
 * @property CarbonImmutable|null $refunded_at
 * @property int|null $recorded_by
 * @property int|null $refunded_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Reservation $reservation
 */
#[Fillable(['reservation_id', 'type', 'payer', 'amount', 'method', 'paid_at', 'refunded_amount', 'refund_reason', 'refunded_at', 'recorded_by', 'refunded_by'])]
class ReservationPayment extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentType::class,
            'amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * Także rezerwacja usunięta przez klienta - historia wpłat zostaje.
     *
     * @return BelongsTo<Reservation, $this>
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class)->withTrashed();
    }
}
