<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Reset hasła linkiem z e-maila, na mechanizmie Laravela. Logowanie dwuetapowe pozostaje włączone.
 */
class PasswordResetController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('auth/forgot-password');
    }

    /** Odpowiedź jest zawsze taka sama, żeby nie zdradzać, czy adres ma konto. */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['email' => ['required', 'string', 'email', 'max:255']], [], ['email' => 'e-mail']);

        try {
            Password::sendResetLink(['email' => $validated['email']]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return back()->with('status', 'Jeśli ten adres ma konto, wysłaliśmy na niego link do zmiany hasła.');
    }

    public function edit(Request $request, string $token): Response
    {
        return Inertia::render('auth/reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function update(Request $request, UserSecurityRepositoryInterface $users): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ], [], ['email' => 'e-mail', 'password' => 'hasło']);

        $status = Password::reset($validated, function (User $user, string $password) use ($users) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();

            // Ktoś mógł być zalogowany na tym koncie, więc wszystkie sesje się kończą.
            $users->forgetSessions($user);

            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'Link jest nieprawidłowy albo wygasł.']);
        }

        return to_route('login')->with('status', 'Hasło zostało zmienione. Możesz się zalogować.');
    }
}
