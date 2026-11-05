<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Drugi krok logowania. Sesja pamięta tylko konto po poprawnym haśle, a zalogowanie następuje po kodzie.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'two_factor.login';

    public const MINUTES = 5;

    private const MAX_ATTEMPTS = 5;

    public function create(Request $request): Response|RedirectResponse
    {
        return $this->pending($request) === null
            ? to_route('login')
            : Inertia::render('auth/two-factor');
    }

    public function store(
        Request $request,
        TwoFactorService $twoFactor,
        UserSecurityRepositoryInterface $users,
        AuditLogService $auditLog,
    ): RedirectResponse {
        $pending = $this->pending($request);

        if ($pending === null) {
            return to_route('login');
        }

        $validated = $request->validate(['code' => ['required', 'string', 'max:20']], [], ['code' => 'kod']);
        $limiterKey = 'two-factor:'.$pending['id'];

        if (RateLimiter::tooManyAttempts($limiterKey, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => 'Zbyt wiele prób. Spróbuj ponownie za kilka minut.']);
        }

        $user = $users->find($pending['id']);

        if ($user === null || ! $twoFactor->verify($user, $validated['code'])) {
            RateLimiter::hit($limiterKey, self::MINUTES * 60);

            throw ValidationException::withMessages(['code' => 'Nieprawidłowy kod.']);
        }

        RateLimiter::clear($limiterKey);
        $request->session()->forget(self::SESSION_KEY);

        Auth::guard('web')->login($user, $pending['remember']);

        $request->session()->regenerate();
        $auditLog->recordLogin($user, $request->ip());

        return redirect()->intended(route($user->role->homeRoute(), absolute: false));
    }

    /**
     * @return array{id: int, remember: bool, expires_at: int}|null
     */
    private function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || $pending['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        return $pending;
    }
}
