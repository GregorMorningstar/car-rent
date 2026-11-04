<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Klienci (użytkownicy z rolą "user") w panelu administratora.
 * Na razie tylko widoki - dane przykładowe są po stronie frontu.
 */
class ClientController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/clients/index');
    }

    public function show(int $id): Response
    {
        return Inertia::render('admin/clients/show', ['id' => $id]);
    }
}
