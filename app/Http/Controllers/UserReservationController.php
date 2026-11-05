<?php

namespace App\Http\Controllers;

use App\Services\BookingOptionsService;
use App\Services\CarService;
use App\Services\LocationService;
use App\Services\ReservationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Edycja i usuwanie rezerwacji przez jej właściciela. Identyfikator konta pochodzi z sesji,
 * a ReservationService szuka rezerwacji po numerze i właścicielu razem.
 */
class UserReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
    ) {}

    public function edit(
        Request $request,
        int $id,
        CarService $carService,
        LocationService $locationService,
        BookingOptionsService $bookingOptions,
    ): Response {
        $reservation = $this->reservationService->formData($request->user(), $id);

        return Inertia::render('booking', [
            'car' => $carService->findPublicCar($reservation['car_id']),
            'pickupLocations' => $locationService->pickupLocations(),
            'dropoffLocations' => $locationService->dropoffLocations(),
            'limits' => $bookingOptions->limits(),
            'defaults' => [],
            'reservation' => $reservation,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->reservationService->updateByClient($request->user(), $id, $request->all());

        return to_route('user.rentals')->with('status', 'Zmiany zapisane. Rezerwacja czeka na potwierdzenie obsługi.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->reservationService->removeByClient($request->user(), $id);

        return back(fallback: route('user.rentals'))->with('status', 'Rezerwacja została usunięta.');
    }
}
