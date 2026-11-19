<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\AccountRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Usuwanie konta przez klienta i czyszczenie kont po terminach z config/retention.php.
 */
class AccountService
{
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
    ) {}

    /**
     * Klient usuwa własne konto. Dane osobowe znikają, a rozliczenia zostają bez powiązania z osobą.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function deleteOwn(User $user, array $data): void
    {
        if ($user->role !== UserRole::User) {
            throw new AuthorizationException('Konto administratora usuwa się osobno.');
        }

        $validated = Validator::make($data, ['password' => ['required', 'string']], [], ['password' => 'hasło'])->validate();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Nieprawidłowe hasło.']);
        }

        if ($this->accountRepository->hasOpenMatters($user->id)) {
            throw ValidationException::withMessages([
                'password' => 'Konta nie można usunąć: masz aktywną rezerwację, wynajem albo nierozliczoną wpłatę. Napisz do obsługi.',
            ]);
        }

        $this->accountRepository->anonymize($user);
        Cookie::queue(Cookie::forget(TwoFactorService::COOKIE));
    }

    /**
     * Czyści konta po terminach. Z $dryRun tylko liczy, co zostałoby usunięte.
     *
     * @return array{abandoned: int, inactive: int}
     */
    public function prune(bool $dryRun = false): array
    {
        $abandoned = $this->accountRepository->abandonedAccounts(now()->subDays(config('retention.unverified_account_days')));

        $inactive = array_filter(
            $this->accountRepository->inactiveAccounts(now()->subMonths(config('retention.inactive_account_months'))),
            fn (User $user) => ! $this->accountRepository->hasOpenMatters($user->id)
                && ! in_array($user->id, array_column($abandoned, 'id'), true),
        );

        if (! $dryRun) {
            array_map($this->accountRepository->delete(...), $abandoned);
            array_map($this->accountRepository->anonymize(...), $inactive);
        }

        return [
            'abandoned' => count($abandoned),
            'inactive' => count($inactive),
        ];
    }
}
