<?php

namespace App\Repositories\Contracts;

use App\Models\TrustedDevice;
use App\Models\User;
use Carbon\CarbonInterface;

interface UserSecurityRepositoryInterface
{
    public function find(int $id): ?User;

    public function enableTwoFactor(User $user): void;

    public function disableTwoFactor(User $user): void;

    /** Kończy wszystkie sesje konta zapisane w bazie. */
    public function forgetSessions(User $user): void;

    /**
     * Aktualne zaufane urządzenia konta, od ostatnio używanego.
     *
     * @return array<int, TrustedDevice>
     */
    public function trustedDevices(int $userId): array;

    public function addTrustedDevice(int $userId, string $tokenHash, string $name, CarbonInterface $expiresAt): void;

    /** Aktualne urządzenie tego konta - token innego konta nie zostanie znaleziony. */
    public function findTrustedDevice(int $userId, string $tokenHash): ?TrustedDevice;

    public function touchTrustedDevice(TrustedDevice $device): void;

    /** Usuwa urządzenie tego konta. Zwraca false, gdy konto takiego nie ma. */
    public function removeTrustedDevice(int $userId, int $deviceId): bool;

    public function removeTrustedDevices(int $userId): void;

    public function removeTrustedDeviceByHash(string $tokenHash): void;
}
