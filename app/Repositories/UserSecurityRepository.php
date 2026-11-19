<?php

namespace App\Repositories;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class UserSecurityRepository implements UserSecurityRepositoryInterface
{
    public function __construct(
        private readonly User $model,
        private readonly TrustedDevice $device,
    ) {}

    public function find(int $id): ?User
    {
        return $this->model->newQuery()->find($id);
    }

    public function enableTwoFactor(User $user): void
    {
        $user->forceFill(['two_factor_enabled_at' => now()])->save();
    }

    public function disableTwoFactor(User $user): void
    {
        $user->forceFill(['two_factor_enabled_at' => null])->save();
    }

    public function forgetSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
        }
    }

    public function trustedDevices(int $userId): array
    {
        return $this->device->newQuery()
            ->where('user_id', $userId)
            ->where('expires_at', '>', now())
            ->latest('last_used_at')
            ->get()
            ->all();
    }

    public function addTrustedDevice(int $userId, string $tokenHash, string $name, CarbonInterface $expiresAt): void
    {
        $this->device->create([
            'user_id' => $userId,
            'token_hash' => $tokenHash,
            'name' => $name,
            'last_used_at' => now(),
            'expires_at' => $expiresAt,
        ]);
    }

    public function findTrustedDevice(int $userId, string $tokenHash): ?TrustedDevice
    {
        return $this->device->newQuery()
            ->where('user_id', $userId)
            ->where('token_hash', $tokenHash)
            ->where('expires_at', '>', now())
            ->first();
    }

    public function touchTrustedDevice(TrustedDevice $device): void
    {
        $device->update(['last_used_at' => now()]);
    }

    public function removeTrustedDevice(int $userId, int $deviceId): bool
    {
        return $this->device->newQuery()->where('user_id', $userId)->whereKey($deviceId)->delete() === 1;
    }

    public function removeTrustedDeviceByHash(string $tokenHash): void
    {
        $this->device->newQuery()->where('token_hash', $tokenHash)->delete();
    }

    public function removeTrustedDevices(int $userId): void
    {
        $this->device->newQuery()->where('user_id', $userId)->delete();
    }
}
