<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pulpit administratora - najnowsze rezerwacje do potwierdzenia.
 * Na razie bez bazy: dane przykładowe są po stronie frontu (resources/js/components/admin/data).
 */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('admin/dashboard');
    }
}
