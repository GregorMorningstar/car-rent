<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Miejsca odbioru i dostawy samochodów.
 * Dostęp ma tylko administrator: middleware "role:administrator" oraz LocationPolicy.
 * Walidacja jest w LocationService.
 */
class LocationController extends Controller
{
    public function __construct(
        private readonly LocationService $locationService,
    ) {}

    public function index(): Response
    {
        Gate::authorize('manage', Location::class);

        return Inertia::render('admin/locations/index', [
            'locations' => $this->locationService->allLocations(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Location::class);

        $this->locationService->createLocation($request->all());

        return to_route('admin.locations.index');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        Gate::authorize('manage', Location::class);

        $this->locationService->updateLocation($id, $request->all());

        return to_route('admin.locations.index');
    }

    /** Ukrywa miejsce na listach wyboru albo je przywraca. */
    public function toggle(int $id): RedirectResponse
    {
        Gate::authorize('manage', Location::class);

        $this->locationService->toggleLocation($id);

        return to_route('admin.locations.index');
    }
}
