<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Services\CarService;
use App\Services\ColorService;
use App\Services\EquipmentService;
use App\Services\LocationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Samochody w panelu administratora.
 * Historia wypożyczeń korzysta jeszcze z danych przykładowych po stronie frontu.
 */
class CarController extends Controller
{
    public function __construct(
        private readonly CarService $carService,
        private readonly EquipmentService $equipmentService,
        private readonly ColorService $colorService,
        private readonly LocationService $locationService,
    ) {}

    public function index(): Response
    {
        return Inertia::render('admin/cars/index', [
            'cars' => $this->carService->allCars(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cars/create', [
            'equipment' => $this->equipmentService->activeEquipment(),
            'colors' => $this->colorService->activeColors(),
            'locations' => $this->locationService->activeLocations(),
        ]);
    }

    public function edit(int $id): Response
    {
        return Inertia::render('admin/cars/edit', [
            'car' => $this->carService->findCarForEdit($id),
            'equipment' => $this->equipmentService->activeEquipment(),
            'colors' => $this->colorService->activeColors(),
            'locations' => $this->locationService->activeLocations(),
        ]);
    }

    /** Historia wypożyczeń - wszystkich aut albo jednego (?car=ID). */
    public function history(Request $request): Response
    {
        return Inertia::render('admin/cars/history', [
            'carId' => $request->integer('car') ?: null,
        ]);
    }

    /**
     * Dodanie samochodu przez administratora.
     * Token CSRF sprawdza grupa "web", rolę - middleware "role:administrator" oraz CarPolicy::create.
     * Walidacja jest w CarService::createCar - jej błędy wracają do formularza.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Car::class);

        return $this->save(
            fn () => $this->carService->createCar($request->all()),
            'Nie udało się dodać samochodu. Spróbuj ponownie.',
        );
    }

    /**
     * Edycja samochodu przez administratora - zabezpieczenia jak w store().
     * Walidacja jest w CarService::updateCar.
     */
    public function update(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('update', Car::class);

        return $this->save(
            fn () => $this->carService->updateCar($id, $request->all()),
            'Nie udało się zapisać zmian. Spróbuj ponownie.',
        );
    }

    /** Błędy walidacji i 404 przekazuje dalej, inne wraca do formularza z komunikatem. */
    private function save(callable $action, string $errorMessage): RedirectResponse
    {
        try {
            $action();
        } catch (ValidationException|ModelNotFoundException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['save' => $errorMessage]);
        }

        return to_route('admin.cars.index');
    }
}
