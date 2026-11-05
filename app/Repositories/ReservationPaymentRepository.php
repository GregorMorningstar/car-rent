<?php

namespace App\Repositories;

use App\Enums\PaymentType;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Repositories\Contracts\ReservationPaymentRepositoryInterface;
use Illuminate\Support\Facades\DB;

class ReservationPaymentRepository implements ReservationPaymentRepositoryInterface
{
    public function __construct(
        private readonly ReservationPayment $model,
    ) {}

    public function unsettledTotal(int $reservationId, PaymentType $type): float
    {
        return (float) $this->model->newQuery()
            ->where('reservation_id', $reservationId)
            ->where('type', $type)
            ->whereNull('refunded_at')
            ->sum('amount');
    }

    public function createWithinLimit(int $reservationId, array $attributes, float $limit): ?ReservationPayment
    {
        return DB::transaction(function () use ($reservationId, $attributes, $limit) {
            Reservation::query()->whereKey($reservationId)->lockForUpdate()->first();

            $total = $this->unsettledTotal($reservationId, PaymentType::from($attributes['type'])) + (float) $attributes['amount'];

            return round($total, 2) > round($limit, 2)
                ? null
                : $this->model->create(['reservation_id' => $reservationId, ...$attributes]);
        });
    }

    public function findFor(int $reservationId, int $paymentId): ?ReservationPayment
    {
        return $this->model->newQuery()->where('reservation_id', $reservationId)->find($paymentId);
    }

    public function settle(ReservationPayment $payment, int $staffId, string $refundedAmount, ?string $reason): bool
    {
        return $this->model->newQuery()
            ->whereKey($payment->id)
            ->whereNull('refunded_at')
            ->update([
                'refunded_amount' => $refundedAmount,
                'refund_reason' => $reason,
                'refunded_at' => now(),
                'refunded_by' => $staffId,
            ]) === 1;
    }
}
