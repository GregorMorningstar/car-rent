<?php

namespace App\Http\Controllers;

use App\Services\CarService;
use App\Services\EquipmentService;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Strony serwisu. Część to na razie podstrony tymczasowe z samym nagłówkiem.
 */
class PageController extends Controller
{
    /** Strona startowa - wyszukiwarka dostaje listę dodanego wyposażenia. */
    public function home(
        CarService $carService,
        EquipmentService $equipmentService,
        LocationService $locationService,
    ): Response {
        return Inertia::render('home', [
            'cars' => $carService->randomCars(),
            'equipment' => $equipmentService->activeEquipment(),
            'pickupLocations' => $locationService->pickupLocations(),
            'dropoffLocations' => $locationService->dropoffLocations(),
        ]);
    }

    /** Wszystkie dostępne samochody z filtrami po lewej stronie. */
    public function cars(
        Request $request,
        CarService $carService,
        EquipmentService $equipmentService,
        LocationService $locationService,
    ): Response {
        return Inertia::render('cars', [
            'cars' => $carService->searchCars($request->query(), 'cars'),
            'equipment' => $equipmentService->activeEquipment(),
            'pickupLocations' => $locationService->pickupLocations(),
            'filters' => $request->only([
                'pickup', 'from', 'to', 'days', 'vehicle_type', 'license', 'fuel', 'transmission', 'doors', 'priceFrom', 'priceTo', 'equipment',
            ]),
        ]);
    }

    /** Szczegóły samochodu ze zdjęciami. */
    public function car(int $id, CarService $carService): Response
    {
        return Inertia::render('car', [
            'car' => $carService->findPublicCar($id),
        ]);
    }

    /** Wyniki wyszukiwarki ze strony startowej - wyświetlane w oknie nad stroną. */
    public function searchCars(Request $request, CarService $carService): JsonResponse
    {
        return response()->json(['cars' => $carService->searchForRental($request->query())]);
    }

    public function contact(): Response
    {
        return $this->placeholder('Kontakt');
    }

    public function about(): Response
    {
        return $this->placeholder('O nas');
    }

    /** Wyniki wyszukiwarki ze strony startowej - walidacja filtrów jest w CarService::searchCars. */
    public function reservation(Request $request, CarService $carService): Response
    {
        return Inertia::render('reservation', [
            'cars' => $carService->searchCars($request->query()),
        ]);
    }

    private function placeholder(string $title): Response
    {
        return Inertia::render('placeholder', ['title' => $title]);
    }
}
