<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use Illuminate\Support\Facades\DB;

class UserSecurityRepository implements UserSecurityRepositoryInterface
{
    public function __construct(
        private readonly User $model,
    ) {}

    public function find(int $id): ?User
    {
        return $this->model->newQuery()->find($id);
    }

    public function startTwoFactor(User $user, string $secret): void
    {
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    public function confirmTwoFactor(User $user, array $recoveryHashes, int $step): void
    {
        $user->forceFill([
            'two_factor_recovery_codes' => $recoveryHashes,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step' => $step,
        ])->save();
    }

    public function disableTwoFactor(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    public function useStep(User $user, int $step): bool
    {
        return $this->model->newQuery()
            ->whereKey($user->id)
            ->where(fn ($query) => $query->whereNull('two_factor_last_step')->orWhere('two_factor_last_step', '<', $step))
            ->update(['two_factor_last_step' => $step]) === 1;
    }

    public function useRecoveryCode(User $user, string $hash): bool
    {
        return DB::transaction(function () use ($user, $hash) {
            $fresh = $this->model->newQuery()->whereKey($user->id)->lockForUpdate()->first();
            $codes = $fresh?->two_factor_recovery_codes ?? [];
            $remaining = array_values(array_filter($codes, fn (string $code) => ! hash_equals($code, $hash)));

            if ($fresh === null || count($remaining) === count($codes)) {
                return false;
            }

            $fresh->forceFill(['two_factor_recovery_codes' => $remaining])->save();

            return true;
        });
    }

    public function forgetSessions(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
        }
    }
}
