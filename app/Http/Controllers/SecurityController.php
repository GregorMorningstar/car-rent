<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bezpieczeństwo własnego konta. Każda akcja dotyczy zalogowanej osoby, a identyfikator konta pochodzi z sesji.
 */
class SecurityController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function index(Request $request): Response
    {
        $page = $request->user()->role === UserRole::Administrator ? 'admin/security/index' : 'user/security';

        return Inertia::render($page, [
            ...$this->twoFactor->status($request->user(), $request),
            'codeSent' => $request->session()->get('code_sent', false),
            'canDeleteAccount' => $request->user()->role === UserRole::User,
        ]);
    }

    /** Pierwszy krok włączenia: kod sprawdza, że poczta dochodzi. */
    public function sendCode(Request $request): RedirectResponse
    {
        if ($request->user()->hasTwoFactor()) {
            throw ValidationException::withMessages(['code' => 'Logowanie dwuetapowe jest już włączone.']);
        }

        $this->twoFactor->sendCode($request->user());

        return to_route('security.index')->with('code_sent', true);
    }

    public function enable(Request $request): RedirectResponse
    {
        $this->twoFactor->enable($request->user(), $request->all());

        return to_route('security.index')->with('status', 'Logowanie dwuetapowe zostało włączone.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->twoFactor->disable($request->user(), $request->all());

        return to_route('security.index')->with('status', 'Logowanie dwuetapowe zostało wyłączone.');
    }

    public function destroyDevice(Request $request, int $id): RedirectResponse
    {
        $this->twoFactor->removeDevice($request->user(), $id);

        return to_route('security.index')->with('status', 'Urządzenie zostało usunięte z zaufanych.');
    }

    public function destroyDevices(Request $request): RedirectResponse
    {
        $this->twoFactor->removeAllDevices($request->user());

        return to_route('security.index')->with('status', 'Usunięto wszystkie zaufane urządzenia.');
    }
}
