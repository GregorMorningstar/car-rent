<?php

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserSecurityRepositoryInterface
{
    public function find(int $id): ?User;

    public function startTwoFactor(User $user, string $secret): void;

    /**
     * @param  array<int, string>  $recoveryHashes
     */
    public function confirmTwoFactor(User $user, array $recoveryHashes, int $step): void;

    public function disableTwoFactor(User $user): void;

    /** Zapisuje użyty krok. Zwraca false, gdy ten albo późniejszy kod już wykorzystano. */
    public function useStep(User $user, int $step): bool;

    /** Zużywa kod zapasowy. Zwraca false, gdy takiego kodu nie ma. */
    public function useRecoveryCode(User $user, string $hash): bool;

    /** Kończy wszystkie sesje konta zapisane w bazie. */
    public function forgetSessions(User $user): void;
}
