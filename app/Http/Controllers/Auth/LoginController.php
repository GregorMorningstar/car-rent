<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/login');
    }

    public function store(LoginRequest $request, AuditLogService $auditLog, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->authenticate();

        $user = $request->user();

        // Hasło jest poprawne, ale przy logowaniu dwuetapowym konto otwiera dopiero kod z e-maila,
        // chyba że użytkownik dodał to urządzenie do zaufanych.
        if ($user->hasTwoFactor() && ! $twoFactor->isTrustedDevice($user, $request)) {
            Auth::guard('web')->logout();

            $request->session()->regenerate();
            $request->session()->put(TwoFactorChallengeController::SESSION_KEY, [
                'id' => $user->id,
                'remember' => $request->boolean('remember') && CookieConsent::allows($request, 'functional'),
                'expires_at' => now()->addMinutes(TwoFactorService::CODE_MINUTES)->getTimestamp(),
            ]);

            $twoFactor->sendCode($user);

            return to_route('login.code');
        }

        $request->session()->regenerate();
        $auditLog->recordLogin($user, $request->ip());

        return redirect()->intended(
            route($user->role->homeRoute(), absolute: false)
        );
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
