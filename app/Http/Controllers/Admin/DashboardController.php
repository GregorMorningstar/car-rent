<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Reservation;
use App\Services\ClientService;
use App\Services\ReservationService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pulpit administratora - rezerwacje do potwierdzenia i stan wypożyczalni.
 */
class DashboardController extends Controller
{
    public function __invoke(ReservationService $reservations, ClientService $clients): Response
    {
        Gate::authorize('manage', Reservation::class);

        return Inertia::render('admin/dashboard', [
            ...$reservations->dashboard(),
            'clientCount' => $clients->count(),
        ]);
    }
}
