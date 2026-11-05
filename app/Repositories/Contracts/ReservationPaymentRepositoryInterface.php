<?php

namespace App\Repositories\Contracts;

use App\Enums\PaymentType;
use App\Models\ReservationPayment;

interface ReservationPaymentRepositoryInterface
{
    /** Suma nierozliczonych wpłat danego rodzaju. */
    public function unsettledTotal(int $reservationId, PaymentType $type): float;

    /**
     * Zapisuje wpłatę pod blokadą rezerwacji. Zwraca null, gdy suma przekroczyłaby limit.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createWithinLimit(int $reservationId, array $attributes, float $limit): ?ReservationPayment;

    /** Wpłata tej rezerwacji - inną zapytanie w ogóle nie znajdzie. */
    public function findFor(int $reservationId, int $paymentId): ?ReservationPayment;

    /** Zwraca false, gdy ktoś rozliczył tę wpłatę wcześniej. */
    public function settle(ReservationPayment $payment, int $staffId, string $refundedAmount, ?string $reason): bool;
}
