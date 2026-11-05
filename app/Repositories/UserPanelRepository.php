<?php

namespace App\Repositories;

use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Repositories\Contracts\UserPanelRepositoryInterface;
use App\Support\LocalDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPanelRepository implements UserPanelRepositoryInterface
{
    public function __construct(
        private readonly Reservation $model,
        private readonly ReservationPayment $paymentModel,
    ) {}

    public function rentalsForUser(int $userId): array
    {
        return $this->model->newQuery()
            // Historia zostaje także po usunięciu samochodu z floty.
            ->with([
                'car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model']),
                'pickupLocation:id,name',
                'dropoffLocation:id,name',
                'payments',
            ])
            ->where('user_id', $userId)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Reservation $reservation) => [
                'id' => $reservation->id,
                'user_id' => $reservation->user_id,
                'brand' => $reservation->car?->brand,
                'model' => $reservation->car?->model,
                'pickup' => $reservation->pickupLocation?->name,
                'dropoff' => $reservation->dropoffLocation?->name,
                'start_date' => $reservation->start_date,
                'end_date' => $reservation->end_date,
                'status' => $reservation->status,
                'confirmation_deadline' => $reservation->confirmation_deadline,
                'daily_rate' => $reservation->daily_rate,
                'total_price' => $reservation->total_price,
                'year' => $reservation->created_at?->setTimezone(LocalDate::timezone())->year,
                'can_edit' => $reservation->isEditableByClient(),
                'can_delete' => $reservation->isRemovableByClient(),
            ])
            ->all();
    }

    public function paymentsForUser(int $userId): array
    {
        return $this->paymentModel->newQuery()
            ->with(['reservation.car' => fn (BelongsTo $car) => $car->withTrashed()->select(['id', 'brand', 'model'])])
            ->whereHas('reservation', fn (Builder $reservation) => $reservation->where('user_id', $userId))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReservationPayment $payment) => [
                'id' => $payment->id,
                'user_id' => $payment->reservation->user_id,
                'reservation_id' => $payment->reservation_id,
                'year' => $payment->reservation->created_at?->setTimezone(LocalDate::timezone())->year,
                'brand' => $payment->reservation->car?->brand,
                'model' => $payment->reservation->car?->model,
                'type' => $payment->type->value,
                'payer' => $payment->payer,
                'amount' => $payment->amount,
                'method' => $payment->method,
                'paid_at' => $payment->paid_at,
                'refunded_amount' => $payment->refunded_amount,
                'refund_reason' => $payment->refund_reason,
                'refunded_at' => $payment->refunded_at,
            ])
            ->all();
    }
}
