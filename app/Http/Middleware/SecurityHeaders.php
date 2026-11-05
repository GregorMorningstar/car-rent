<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Nagłówki chroniące panel przed osadzaniem w ramce, zgadywaniem typów plików i wstrzyknięciem skryptów.
 * Lokalizacja zostaje dostępna dla własnej strony, bo korzysta z niej wybór miejsca na mapie.
 * CSP działa tylko na produkcji, bo serwer Vite z trybu deweloperskiego używa skryptów inline i innego portu.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $production = app()->isProduction();
        $nonce = $production ? Vite::useCspNonce() : null;

        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(self), camera=(), microphone=()');

        if ($nonce !== null) {
            $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy($nonce));
        }

        if ($request->isSecure() && $production) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $reverb = config('broadcasting.connections.reverb.options');
        $socket = sprintf(
            '%s://%s:%s',
            ($reverb['scheme'] ?? 'https') === 'https' ? 'wss' : 'ws',
            $reverb['host'] ?? 'localhost',
            $reverb['port'] ?? 443,
        );

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}'",
            // Leaflet i React ustawiają style bezpośrednio na elementach.
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https://tile.openstreetmap.org https://*.tile.openstreetmap.org https://cdn.jsdelivr.net",
            "font-src 'self' data:",
            "connect-src 'self' {$socket}",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]);
    }
}
