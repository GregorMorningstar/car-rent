<?php

namespace App\Services;

use App\Models\User;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Logowanie dwuetapowe do istniejących kont. Sekret i kody zapasowe są szyfrowane w bazie.
 */
class TwoFactorService
{
    private const RECOVERY_CODES = 8;

    public function __construct(
        private readonly UserSecurityRepositoryInterface $securityRepository,
    ) {}

    /**
     * @return array{enabled: bool, secret: string|null, otpauth: string|null}
     */
    public function status(User $user): array
    {
        $pending = ! $user->hasTwoFactor() && $user->two_factor_secret !== null;

        return [
            'enabled' => $user->hasTwoFactor(),
            'secret' => $pending ? $user->two_factor_secret : null,
            'otpauth' => $pending ? Totp::uri($user->two_factor_secret, $user->email, (string) config('app.name')) : null,
        ];
    }

    /**
     * @throws ValidationException
     */
    public function start(User $user): void
    {
        if ($user->hasTwoFactor()) {
            throw ValidationException::withMessages(['code' => 'Logowanie dwuetapowe jest już włączone.']);
        }

        $this->securityRepository->startTwoFactor($user, Totp::generateSecret());
    }

    /**
     * Pierwszy poprawny kod potwierdza, że aplikacja jest ustawiona. Kody zapasowe są widoczne tylko raz.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, string>
     *
     * @throws ValidationException
     */
    public function confirm(User $user, array $data): array
    {
        $code = $this->validatedCode($data);
        $step = $user->two_factor_secret === null || $user->hasTwoFactor() ? null : Totp::matchingStep($user->two_factor_secret, $code);

        if ($step === null) {
            throw ValidationException::withMessages(['code' => 'Nieprawidłowy kod.']);
        }

        $codes = array_map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)), range(1, self::RECOVERY_CODES));

        $this->securityRepository->confirmTwoFactor($user, array_map($this->hash(...), $codes), $step);

        return $codes;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function disable(User $user, array $data): void
    {
        $validated = Validator::make(
            $data,
            ['password' => ['required', 'string'], 'code' => ['required', 'string', 'max:20']],
            [],
            ['password' => 'hasło', 'code' => 'kod'],
        )->validate();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Nieprawidłowe hasło.']);
        }

        if (! $this->verify($user, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'Nieprawidłowy kod.']);
        }

        $this->securityRepository->disableTwoFactor($user);
    }

    /** Kod z aplikacji albo kod zapasowy. Każdy działa tylko raz. */
    public function verify(User $user, string $code): bool
    {
        $code = trim($code);

        if (! $user->hasTwoFactor() || $user->two_factor_secret === null) {
            return false;
        }

        if (ctype_digit($code)) {
            $step = Totp::matchingStep($user->two_factor_secret, $code);

            return $step !== null && $this->securityRepository->useStep($user, $step);
        }

        return $this->securityRepository->useRecoveryCode($user, $this->hash(Str::lower($code)));
    }

    private function hash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validatedCode(array $data): string
    {
        return Validator::make($data, ['code' => ['required', 'digits:6']], [], ['code' => 'kod'])->validate()['code'];
    }
}
