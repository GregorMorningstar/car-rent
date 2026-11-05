<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ReservationPaymentService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rezerwacje w panelu administratora. Każda akcja sprawdza rolę także tutaj, nie tylko na trasie.
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    /** Oznacza nowe zamówienia z chmurki jako przeczytane. */
    public function markSeen(): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $this->reservationService->markAllSeen();

        return back(fallback: route('admin.dashboard'));
    }

    /** Aktualne rezerwacje (oczekujące i potwierdzone, jeszcze niezakończone). */
    public function index(): Response
    {
        Gate::authorize('manage', Reservation::class);

        return Inertia::render('admin/reservations/index', [
            ...$this->reservationService->current(),
            'paymentMethods' => ReservationPaymentService::METHODS,
        ]);
    }

    /** Zaliczka lub kaucja wpisana ręcznie - walidacja jest w ReservationPaymentService. */
    public function storePayment(Request $request, int $id, ReservationPaymentService $payments): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $payments->add($request->user(), $id, $request->all());

        return back(fallback: route('admin.reservations.index'));
    }

    public function refundPayment(Request $request, int $id, int $payment, ReservationPaymentService $payments): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $payments->refund($request->user(), $id, $payment, $request->all());

        return back(fallback: route('admin.reservations.index'));
    }

    /** Wydanie samochodu z danymi kierowcy. */
    public function handOver(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $this->reservationService->handOver($request->user(), $id, $request->all());

        return back(fallback: route('admin.reservations.edit', $id));
    }

    /** Przyjęcie zwrotu z nowym przebiegiem auta. */
    public function takeBack(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $this->reservationService->takeBack($request->user(), $id, $request->all());

        return back(fallback: route('admin.reservations.edit', $id));
    }

    public function confirm(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $this->reservationService->confirm($id, $request->user());

        return back(fallback: route('admin.reservations.index'));
    }

    public function reject(int $id): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $this->reservationService->reject($id);

        return back(fallback: route('admin.reservations.index'));
    }

    /** Wszystkie rezerwacje niezależnie od statusu. */
    public function history(): Response
    {
        Gate::authorize('manage', Reservation::class);

        return Inertia::render('admin/reservations/history', $this->reservationService->list());
    }

    /** Potwierdzone rezerwacje, których termin już minął. */
    public function completed(): Response
    {
        Gate::authorize('manage', Reservation::class);

        return Inertia::render('admin/reservations/completed', $this->reservationService->completed());
    }

    public function edit(Request $request, int $id): Response
    {
        Gate::authorize('manage', Reservation::class);

        return Inertia::render('admin/reservations/edit', $this->reservationService->staffFormData($request->user(), $id));
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manage', Reservation::class);

        $this->reservationService->updateByStaff($request->user(), $id, $request->all());

        return to_route('admin.reservations.edit', $id)->with('status', 'Zapisano zmiany rezerwacji.');
    }
}
