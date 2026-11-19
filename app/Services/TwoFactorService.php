<?php

namespace App\Services;

use App\Models\TrustedDevice;
use App\Models\User;
use App\Notifications\TwoFactorCode;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use App\Support\CookieConsent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Dobrowolne logowanie dwuetapowe kodem z e-maila i zaufane urządzenia.
 * Kod jest w cache tylko jako skrót, a token urządzenia w bazie tylko jako skrót.
 */
class TwoFactorService
{
    public const CODE_MINUTES = 10;

    public const TRUST_DAYS = 30;

    public const COOKIE = 'trusted_device';

    private const MAX_ATTEMPTS = 5;

    private const RESEND_SECONDS = 60;

    public function __construct(
        private readonly UserSecurityRepositoryInterface $securityRepository,
    ) {}

    /**
     * Stan zabezpieczenia do ustawień konta.
     *
     * @return array<string, mixed>
     */
    public function status(User $user, Request $request): array
    {
        $currentHash = $this->cookieHash($request);

        return [
            'enabled' => $user->hasTwoFactor(),
            'email' => $user->email,
            'trustDays' => self::TRUST_DAYS,
            'devices' => array_map(fn (TrustedDevice $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'last_used_at' => $device->last_used_at->toJSON(),
                'expires_at' => $device->expires_at->toJSON(),
                'current' => $currentHash !== null && hash_equals($device->token_hash, $currentHash),
            ], $this->securityRepository->trustedDevices($user->id)),
        ];
    }

    /**
     * Wysyła nowy kod. Dopóki poprzedni jest ważny, najwyżej raz na minutę, żeby nie zalać skrzynki.
     *
     * @throws ValidationException
     */
    public function sendCode(User $user): void
    {
        // Bez ważnego kodu wysyłka zawsze przechodzi, inaczej użytkownik nie mógłby się zalogować.
        if (RateLimiter::tooManyAttempts($this->sendKey($user), 1) && Cache::has($this->codeKey($user))) {
            return;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        try {
            $user->notifyNow(new TwoFactorCode($code, self::CODE_MINUTES));
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['code' => 'Nie udało się wysłać kodu. Spróbuj ponownie za chwilę.']);
        }

        RateLimiter::hit($this->sendKey($user), self::RESEND_SECONDS);
        Cache::put($this->codeKey($user), $this->hash($user, $code), now()->addMinutes(self::CODE_MINUTES));
    }

    /**
     * Kod działa raz. Po pięciu błędach przestaje działać nawet poprawny.
     *
     * @throws ValidationException
     */
    public function verifyCode(User $user, string $code): void
    {
        $attemptsKey = $this->attemptsKey($user);

        if (RateLimiter::tooManyAttempts($attemptsKey, self::MAX_ATTEMPTS)) {
            Cache::forget($this->codeKey($user));

            throw ValidationException::withMessages(['code' => 'Zbyt wiele prób. Spróbuj ponownie za kilka minut.']);
        }

        $stored = Cache::get($this->codeKey($user));

        if (! is_string($stored) || ! hash_equals($stored, $this->hash($user, $code)) || ! Cache::forget($this->codeKey($user))) {
            RateLimiter::hit($attemptsKey, self::CODE_MINUTES * 60);

            throw ValidationException::withMessages(['code' => 'Nieprawidłowy albo wygasły kod.']);
        }

        RateLimiter::clear($attemptsKey);
    }

    /**
     * Włączenie wymaga kodu, dzięki czemu nikt nie zablokuje konta adresem, na który nie dochodzi poczta.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function enable(User $user, array $data): void
    {
        $this->verifyCode($user, $this->validatedCode($data));
        $this->securityRepository->enableTwoFactor($user);
    }

    /**
     * Wyłączenie wymaga hasła i usuwa zaufane urządzenia.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function disable(User $user, array $data): void
    {
        $validated = Validator::make($data, ['password' => ['required', 'string']], [], ['password' => 'hasło'])->validate();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Nieprawidłowe hasło.']);
        }

        $this->securityRepository->disableTwoFactor($user);
        $this->securityRepository->removeTrustedDevices($user->id);
        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    /** Czy to urządzenie może pominąć kod. Dotyczy wyłącznie konta, które je dodało. */
    public function isTrustedDevice(User $user, Request $request): bool
    {
        $hash = CookieConsent::allows($request, 'functional') ? $this->cookieHash($request) : null;
        $device = $hash === null ? null : $this->securityRepository->findTrustedDevice($user->id, $hash);

        if ($device === null) {
            return false;
        }

        $this->securityRepository->touchTrustedDevice($device);

        return true;
    }

    public function trustDevice(User $user, Request $request): void
    {
        $token = Str::random(64);

        $this->securityRepository->addTrustedDevice(
            $user->id,
            hash('sha256', $token),
            $this->deviceName((string) $request->userAgent()),
            now()->addDays(self::TRUST_DAYS),
        );

        // Bez minut JavaScript nie ma dostępu do ciasteczka, a Secure i SameSite pochodzą z konfiguracji sesji.
        Cookie::queue(self::COOKIE, $token, self::TRUST_DAYS * 24 * 60);
    }

    /** Po odrzuceniu cookies urządzenie przestaje być zaufane także w bazie. */
    public function forgetCurrentDevice(Request $request): void
    {
        $hash = $this->cookieHash($request);

        if ($hash !== null) {
            $this->securityRepository->removeTrustedDeviceByHash($hash);
        }

        Cookie::queue(Cookie::forget(self::COOKIE));
    }

    public function removeDevice(User $user, int $deviceId): void
    {
        abort_unless($this->securityRepository->removeTrustedDevice($user->id, $deviceId), 404);
    }

    public function removeAllDevices(User $user): void
    {
        $this->securityRepository->removeTrustedDevices($user->id);
    }

    private function cookieHash(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && $token !== '' ? hash('sha256', $token) : null;
    }

    /** Czytelny opis, np. Chrome na Windows. */
    private function deviceName(string $userAgent): string
    {
        $browser = match (true) {
            str_contains($userAgent, 'Edg/') => 'Edge',
            str_contains($userAgent, 'OPR/') => 'Opera',
            str_contains($userAgent, 'Firefox/') => 'Firefox',
            str_contains($userAgent, 'Chrome/') => 'Chrome',
            str_contains($userAgent, 'Safari/') => 'Safari',
            default => 'Przeglądarka',
        };

        $system = match (true) {
            str_contains($userAgent, 'Android') => 'Android',
            str_contains($userAgent, 'iPhone'), str_contains($userAgent, 'iPad') => 'iOS',
            str_contains($userAgent, 'Windows') => 'Windows',
            str_contains($userAgent, 'Mac OS') => 'macOS',
            str_contains($userAgent, 'Linux') => 'Linux',
            default => 'nieznany system',
        };

        return "{$browser} na {$system}";
    }

    private function hash(User $user, string $code): string
    {
        return hash_hmac('sha256', $user->id.'|'.$code, (string) config('app.key'));
    }

    private function codeKey(User $user): string
    {
        return 'two-factor:code:'.$user->id;
    }

    private function attemptsKey(User $user): string
    {
        return 'two-factor:attempts:'.$user->id;
    }

    private function sendKey(User $user): string
    {
        return 'two-factor:send:'.$user->id;
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
