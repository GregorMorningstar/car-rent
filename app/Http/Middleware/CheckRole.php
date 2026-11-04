<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(
        Request $request,
        Closure $next,
        string ...$roles
    ): Response {
        $user = $request->user();

        // Dodatkowe zabezpieczenie.
        // Logowanie na trasach obsługuje middleware "auth".
        if ($user === null) {
            abort(401, 'Musisz się zalogować.');
        }

        // Dostęp, jeśli rola użytkownika jest na liście.
        if (! in_array($user->role->value, $roles, true)) {
            abort(403, 'Nie masz uprawnień do tej strony.');
        }

        return $next($request);
    }
}
