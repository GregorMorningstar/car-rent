<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use Throwable;

/**
 * Dziennik działań administracji. Jego błąd nie może zablokować samego działania.
 */
class AuditLogService
{
    private const LATEST = 200;

    public function __construct(
        private readonly AuditLogRepositoryInterface $auditLogRepository,
    ) {}

    public function record(?User $user, string $action, ?string $subject, ?string $ip, bool $succeeded): void
    {
        try {
            $this->auditLogRepository->add([
                'user_id' => $user?->id,
                'action' => $action,
                'subject' => $subject,
                'ip_address' => $ip,
                'succeeded' => $succeeded,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function recordLogin(User $user, ?string $ip): void
    {
        if ($user->role === UserRole::Administrator) {
            $this->record($user, 'auth.login', null, $ip, true);
        }
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, failed: bool}
     */
    public function latest(): array
    {
        try {
            return ['entries' => $this->auditLogRepository->latest(self::LATEST), 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            return ['entries' => [], 'failed' => true];
        }
    }
}
