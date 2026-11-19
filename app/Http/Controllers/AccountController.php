<?php

namespace App\Http\Controllers;

use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Usunięcie własnego konta. Identyfikator konta pochodzi z sesji.
 */
class AccountController extends Controller
{
    public function destroy(Request $request, AccountService $accounts): RedirectResponse
    {
        $accounts->deleteOwn($request->user(), $request->all());

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('login')->with('status', 'Konto zostało usunięte.');
    }
}
