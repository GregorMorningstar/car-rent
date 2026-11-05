<?php

namespace App\Http\Middleware;

use App\Services\AuditLogService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zapisuje każdą zmianę w panelu administratora: kto, co, na czym i czy się udało.
 * Treść formularzy nie trafia do dziennika, więc nie ma w nim haseł ani danych klientów.
 */
class AuditAdminActions
{
    public function __construct(
        private readonly AuditLogService $auditLog,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodSafe()) {
            $failedValidation = in_array('errors', $request->session()->get('_flash.new', []), true);
            $subject = collect($request->route()?->parameters() ?? [])->map(fn (mixed $value, string $key) => "{$key}={$value}")->implode(' ');

            $this->auditLog->record(
                $request->user(),
                (string) $request->route()?->getName(),
                substr($subject, 0, 100) ?: null,
                $request->ip(),
                $response->getStatusCode() < 400 && ! $failedValidation,
            );
        }

        return $response;
    }
}
