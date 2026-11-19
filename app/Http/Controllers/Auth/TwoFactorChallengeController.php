<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use App\Support\CookieConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Drugi krok logowania. Sesja pamięta tylko konto po poprawnym haśle, a zalogowanie następuje po kodzie z e-maila.
 */
class TwoFactorChallengeController extends Controller
{
    public const SESSION_KEY = 'two_factor.login';

    public function __construct(
        private readonly TwoFactorService $twoFactor,
        private readonly UserSecurityRepositoryInterface $users,
    ) {}

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->pendingUser($request);

        return $user === null
            ? to_route('login')
            : Inertia::render('auth/two-factor', [
                'email' => $user->email,
                'minutes' => TwoFactorService::CODE_MINUTES,
                'trustDays' => TwoFactorService::TRUST_DAYS,
            ]);
    }

    public function store(Request $request, AuditLogService $auditLog): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        $validated = $request->validate(
            ['code' => ['required', 'digits:6'], 'trust' => ['nullable', 'boolean']],
            [],
            ['code' => 'kod'],
        );

        $this->twoFactor->verifyCode($user, $validated['code']);

        $remember = (bool) $request->session()->get(self::SESSION_KEY.'.remember');

        $request->session()->forget(self::SESSION_KEY);

        Auth::guard('web')->login($user, $remember);

        $request->session()->regenerate();

        if ($request->boolean('trust') && CookieConsent::allows($request, 'functional')) {
            $this->twoFactor->trustDevice($user, $request);
        }

        $auditLog->recordLogin($user, $request->ip());

        return redirect()->intended(route($user->role->homeRoute(), absolute: false));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if ($user === null) {
            return to_route('login');
        }

        $this->twoFactor->sendCode($user);

        return back()->with('status', 'Jeśli poprzedni kod nie dotarł, nowy jest już w drodze. Kolejny możesz wysłać za minutę.');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending) || $pending['expires_at'] < now()->getTimestamp()) {
            return null;
        }

        return $this->users->find($pending['id']);
    }
}
