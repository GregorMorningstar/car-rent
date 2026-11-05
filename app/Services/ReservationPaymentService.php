<?php

namespace App\Services;

use App\Enums\PaymentType;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Contracts\ReservationPaymentRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Zaliczki i kaucje wpisywane ręcznie przez administrację, bez bramki płatniczej.
 */
class ReservationPaymentService
{
    public const METHODS = ['Gotówka', 'Przelew', 'Karta', 'BLIK'];

    public function __construct(
        private readonly ReservationPaymentRepositoryInterface $paymentRepository,
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {}

    /**
     * Data wpłaty jest wpisana w czasie wypożyczalni, a w bazie jest w UTC.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function add(User $staff, int $reservationId, array $data): void
    {
        $reservation = $this->reservationRepository->find($reservationId);

        abort_if($reservation === null, 404);

        if (in_array($reservation->currentStatus(), [ReservationStatus::Cancelled, ReservationStatus::Expired], true)) {
            throw ValidationException::withMessages(['reservation' => 'Do nieaktualnej rezerwacji nie można dodać wpłaty.']);
        }

        $validated = Validator::make(
            $data,
            [
                'type' => ['required', Rule::enum(PaymentType::class)],
                'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:'.$this->remaining($reservation, $data['type'] ?? null)],
                'payer' => ['required', 'string', 'max:150'],
                'method' => ['required', Rule::in(self::METHODS)],
                'paid_at' => ['required', 'date_format:Y-m-d\TH:i'],
            ],
            ['amount.max' => 'Kwota nie może przekroczyć pozostałej sumy.'],
            ['type' => 'rodzaj wpłaty', 'amount' => 'kwota', 'payer' => 'od kogo', 'method' => 'sposób wpłaty', 'paid_at' => 'data wpłaty'],
        )->validate();

        [$date, $time] = explode('T', $validated['paid_at']);
        $paidAt = LocalDate::at($date, $time);

        if ($paidAt->isAfter(CarbonImmutable::now()->addMinute())) {
            throw ValidationException::withMessages(['paid_at' => 'Data wpłaty nie może być z przyszłości.']);
        }

        $payment = $this->paymentRepository->createWithinLimit($reservationId, [
            'type' => $validated['type'],
            'payer' => trim($validated['payer']),
            'amount' => $validated['amount'],
            'method' => $validated['method'],
            'paid_at' => $paidAt,
            'recorded_by' => $staff->id,
        ], $this->limit($reservation, PaymentType::from($validated['type'])));

        if ($payment === null) {
            throw ValidationException::withMessages(['amount' => 'Kwota nie może przekroczyć pozostałej sumy.']);
        }
    }

    /**
     * Zwrot w całości, częściowy albo żaden. Powód jest wymagany, gdy klient dostaje mniej, niż wpłacił.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function refund(User $staff, int $reservationId, int $paymentId, array $data): void
    {
        $payment = $this->paymentRepository->findFor($reservationId, $paymentId);

        abort_if($payment === null, 404);

        if ($payment->refunded_at !== null) {
            throw ValidationException::withMessages(['reservation' => 'Ta wpłata została już rozliczona.']);
        }

        $amount = $data['refund_amount'] ?? null;
        $reduced = is_numeric($amount) && (float) $amount < (float) $payment->amount;

        $validated = Validator::make(
            $data,
            [
                'refund_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$payment->amount],
                'refund_reason' => [Rule::requiredIf($reduced), 'nullable', 'string', 'max:500'],
            ],
            [
                'refund_amount.max' => 'Zwrot nie może przekroczyć wpłaconej kwoty.',
                'refund_reason.required' => 'Podaj powód zmniejszenia lub braku zwrotu.',
            ],
            ['refund_amount' => 'kwota zwrotu', 'refund_reason' => 'powód'],
        )->validate();

        $settled = $this->paymentRepository->settle(
            $payment,
            $staff->id,
            $validated['refund_amount'],
            $reduced ? trim($validated['refund_reason']) : null,
        );

        if (! $settled) {
            throw ValidationException::withMessages(['reservation' => 'Ta wpłata została już rozliczona.']);
        }
    }

    /** Ile jeszcze można wpłacić. Zaliczki liczą się do ceny, a kaucja do kwoty kaucji. */
    private function remaining(Reservation $reservation, mixed $type): float
    {
        $type = is_string($type) ? PaymentType::tryFrom($type) : null;

        if ($type === null) {
            return 0;
        }

        return max(round($this->limit($reservation, $type) - $this->paymentRepository->unsettledTotal($reservation->id, $type), 2), 0);
    }

    private function limit(Reservation $reservation, PaymentType $type): float
    {
        return (float) ($type === PaymentType::Deposit ? $reservation->deposit_amount : $reservation->total_price);
    }
}
