<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ClientService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Klienci (użytkownicy z rolą "user") w panelu administratora.
 */
class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService,
    ) {}

    public function index(): Response
    {
        Gate::authorize('manageClients', User::class);

        return Inertia::render('admin/clients/index', $this->clientService->list());
    }

    public function show(int $id): Response
    {
        Gate::authorize('manageClients', User::class);

        return Inertia::render('admin/clients/show', $this->clientService->show($id));
    }
}
