<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Wybór cookies zapisany w zaszyfrowanym ciasteczku, dzięki czemu serwer może go egzekwować
 * i klient nie może go podrobić. Wymaganych cookies nie obejmuje, bo bez nich serwis nie działa.
 */
class CookieConsent
{
    public const COOKIE = 'cookie_consent';

    public const CATEGORIES = ['functional', 'analytics', 'marketing'];

    /** Zgoda i odrzucenie obowiązują rok, potem pytamy ponownie. */
    public const MINUTES = 60 * 24 * 365;

    /** Podnieś numer, gdy dojdą nowe rodzaje cookies: pasek zapyta wtedy o zgodę ponownie. */
    private const VERSION = 1;

    /**
     * Zapisany wybór albo null, gdy na tym urządzeniu jeszcze nie było decyzji.
     *
     * @return array<string, bool>|null
     */
    public static function fromRequest(Request $request): ?array
    {
        $cookie = $request->cookie(self::COOKIE);
        $stored = is_string($cookie) ? json_decode($cookie, true) : null;

        if (! is_array($stored) || ($stored['version'] ?? null) !== self::VERSION) {
            return null;
        }

        $choices = [];

        foreach (self::CATEGORIES as $category) {
            $choices[$category] = ($stored[$category] ?? false) === true;
        }

        return $choices;
    }

    /** Brak decyzji znaczy to samo co odrzucenie. */
    public static function allows(Request $request, string $category): bool
    {
        return self::fromRequest($request)[$category] ?? false;
    }

    /**
     * @param  array<string, bool>  $choices
     */
    public static function encode(array $choices): string
    {
        return (string) json_encode([...$choices, 'version' => self::VERSION]);
    }
}
