<?php

namespace App\Repositories;

use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use App\Models\TrustedDevice;
use App\Models\User;
use App\Models\UserProfile;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountRepository implements AccountRepositoryInterface
{
    public const REMOVED_NAME = 'Konto usunięte';

    public function __construct(
        private readonly User $model,
    ) {}

    public function hasOpenMatters(int $userId): bool
    {
        $reservation = Reservation::query()
            ->where('user_id', $userId)
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $pending) => $pending
                    ->where('status', ReservationStatus::Pending)
                    ->where('confirmation_deadline', '>=', now()))
                ->orWhere(fn (Builder $confirmed) => $confirmed
                    ->where('status', ReservationStatus::Confirmed)
                    ->where('end_date', '>=', now()))
                ->orWhere(fn (Builder $out) => $out
                    ->whereNotNull('picked_up_at')
                    ->whereNull('returned_at')))
            ->exists();

        return $reservation || ReservationPayment::query()
            ->whereNull('refunded_at')
            ->whereHas('reservation', fn (Builder $query) => $query->where('user_id', $userId))
            ->exists();
    }

    public function anonymize(User $user): void
    {
        $email = $user->email;

        DB::transaction(function () use ($user, $email) {
            UserProfile::query()->where('user_id', $user->id)->delete();
            TrustedDevice::query()->where('user_id', $user->id)->delete();
            Conversation::query()->where('user_id', $user->id)->delete();

            $reservations = Reservation::query()->withTrashed()->where('user_id', $user->id);

            ReservationPayment::query()
                ->whereIn('reservation_id', (clone $reservations)->select('id'))
                ->update(['payer' => self::REMOVED_NAME]);

            $reservations->update(['driver_name' => null, 'driver_license_number' => null, 'notes' => null]);

            DB::table('password_reset_tokens')->where('email', $email)->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            }

            // Losowe hasło i nieistniejący adres: na to konto nie da się już wejść ani odzyskać go resetem.
            $user->forceFill([
                'name' => self::REMOVED_NAME,
                'email' => "usuniete-{$user->id}@deleted.invalid",
                'password' => Str::random(64),
                'remember_token' => null,
                'email_verified_at' => null,
                'last_login_at' => null,
                'two_factor_enabled_at' => null,
                'anonymized_at' => now(),
            ])->save();
        });
    }

    public function inactiveAccounts(CarbonInterface $before): array
    {
        return $this->accounts()
            ->where(fn (Builder $query) => $query
                ->where('last_login_at', '<', $before)
                ->orWhere(fn (Builder $never) => $never->whereNull('last_login_at')->where('created_at', '<', $before)))
            ->get()
            ->all();
    }

    public function abandonedAccounts(CarbonInterface $before): array
    {
        return $this->accounts()
            ->whereNull('email_verified_at')
            ->where('created_at', '<', $before)
            ->whereDoesntHave('reservations', fn (Builder $query) => $query->withTrashed())
            ->get()
            ->all();
    }

    public function delete(User $user): void
    {
        $user->delete();
    }

    /**
     * Konta klientów, których dane jeszcze istnieją.
     *
     * @return Builder<User>
     */
    private function accounts(): Builder
    {
        return $this->model->newQuery()->where('role', UserRole::User)->whereNull('anonymized_at');
    }
}
