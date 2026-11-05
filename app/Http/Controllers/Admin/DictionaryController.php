<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\FontAwesomeException;
use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Services\ColorService;
use App\Services\EquipmentService;
use App\Services\FontAwesomeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Słowniki panelu administratora: wyposażenie i kolory do wyboru w formularzu samochodu.
 * Dostęp ma tylko administrator: middleware "role:administrator" oraz CarPolicy::manageDictionary.
 * Walidacja jest w EquipmentService i ColorService.
 */
class DictionaryController extends Controller
{
    public function __construct(
        private readonly EquipmentService $equipmentService,
        private readonly ColorService $colorService,
        private readonly FontAwesomeService $fontAwesome,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('manageDictionary', Car::class);

        $equipmentSearch = $this->searchTerm($request, 'equipment_search');
        $colorsSearch = $this->searchTerm($request, 'colors_search');

        return Inertia::render('admin/dictionary/index', [
            'equipment' => $this->equipmentService->paginateEquipment($equipmentSearch),
            'colors' => $this->colorService->paginateColors($colorsSearch),
            'filters' => ['equipment_search' => $equipmentSearch, 'colors_search' => $colorsSearch],
        ]);
    }

    /** Wyszukiwarka ikon Font Awesome w formularzu wyposażenia. */
    public function icons(Request $request): JsonResponse
    {
        Gate::authorize('manageDictionary', Car::class);

        try {
            return response()->json(['icons' => $this->fontAwesome->search($request->query('q'))]);
        } catch (FontAwesomeException $exception) {
            report($exception);

            return response()->json(['message' => 'Font Awesome jest teraz niedostępny.'], 503);
        }
    }

    public function storeEquipment(Request $request): RedirectResponse
    {
        Gate::authorize('manageDictionary', Car::class);

        $this->equipmentService->createEquipment($request->all());

        return $this->backToList();
    }

    public function updateEquipment(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manageDictionary', Car::class);

        $this->equipmentService->updateEquipment($id, $request->all());

        return $this->backToList();
    }

    /** Ukrywa pozycję na liście wyboru albo ją przywraca. */
    public function toggleEquipment(int $id): RedirectResponse
    {
        Gate::authorize('manageDictionary', Car::class);

        $this->equipmentService->toggleEquipment($id);

        return $this->backToList();
    }

    public function storeColor(Request $request): RedirectResponse
    {
        Gate::authorize('manageDictionary', Car::class);

        $this->colorService->createColor($request->all());

        return $this->backToList();
    }

    public function updateColor(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manageDictionary', Car::class);

        $this->colorService->updateColor($id, $request->all());

        return $this->backToList();
    }

    /** Ukrywa pozycję na liście wyboru albo ją przywraca. */
    public function toggleColor(int $id): RedirectResponse
    {
        Gate::authorize('manageDictionary', Car::class);

        $this->colorService->toggleColor($id);

        return $this->backToList();
    }

    private function searchTerm(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? mb_substr(trim($value), 0, 100) : '';
    }

    /** Wraca na tę samą stronę listy, z wpisanym wyszukiwaniem. */
    private function backToList(): RedirectResponse
    {
        return back(fallback: route('admin.dictionary.index'));
    }
}
