<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rezerwacje w panelu administratora.
 * Na razie tylko widoki - dane przykładowe są po stronie frontu.
 */
class ReservationController extends Controller
{
    /** Aktualne rezerwacje (oczekujące i potwierdzone, jeszcze niezakończone). */
    public function index(): Response
    {
        return Inertia::render('admin/reservations/index');
    }

    /** Wszystkie rezerwacje niezależnie od statusu. */
    public function history(): Response
    {
        return Inertia::render('admin/reservations/history');
    }

    /** Potwierdzone rezerwacje, których termin już minął. */
    public function completed(): Response
    {
        return Inertia::render('admin/reservations/completed');
    }

    public function edit(int $id): Response
    {
        return Inertia::render('admin/reservations/edit', ['id' => $id]);
    }
}
