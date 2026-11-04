<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel użytkownika (rola "user").
 *
 * Na razie zwraca tylko widoki - dane są przykładowe po stronie frontu
 * (resources/js/components/user/data.ts) do czasu podłączenia modeli.
 */
class UserController extends Controller
{
    public function dashboard(): Response
    {
        return Inertia::render('user/dashboard');
    }

    public function rentals(): Response
    {
        return Inertia::render('user/rentals');
    }

    public function payments(): Response
    {
        return Inertia::render('user/payments');
    }

    public function profile(): Response
    {
        return Inertia::render('user/profile');
    }
}
