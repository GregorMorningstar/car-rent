<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\BookingOptionsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Zakładka Opcje. Dostęp: "role:administrator" oraz SettingPolicy.
 * Walidacja jest w BookingOptionsService.
 */
class OptionsController extends Controller
{
    public function __construct(
        private readonly BookingOptionsService $bookingOptions,
    ) {}

    public function edit(): Response
    {
        Gate::authorize('manage', Setting::class);

        return Inertia::render('admin/options/index', [
            'options' => $this->bookingOptions->all(),
            'holidays' => $this->bookingOptions->upcomingHolidays(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $this->bookingOptions->save($request->all());

        return to_route('admin.options.edit')->with('status', 'Zapisano opcje.');
    }
}
