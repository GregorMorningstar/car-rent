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
 * Rezerwacja samochodu przez klienta. Walidacja jest w ReservationService.
 */
class BookingController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService,
        private readonly BookingOptionsService $bookingOptions,
    ) {}

    public function create(Request $request, int $id, CarService $carService, LocationService $locationService): Response
    {
        return Inertia::render('booking', [
            'car' => $carService->findPublicCar($id),
            'pickupLocations' => $locationService->pickupLocations(),
            'dropoffLocations' => $locationService->dropoffLocations(),
            'limits' => $this->bookingOptions->limits(),
            'defaults' => collect($request->only(['from', 'to', 'from_time', 'to_time', 'pickup', 'dropoff']))->filter(fn (mixed $value) => is_string($value)),
        ]);
    }

    public function store(Request $request, int $id): RedirectResponse
    {
        $this->reservationService->book($request->user(), $id, $request->all());

        return to_route('user.rentals')->with('status', 'Samochód jest zarezerwowany. Czekamy na potwierdzenie obsługi.');
    }
}
