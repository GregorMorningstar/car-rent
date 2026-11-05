<?php

namespace App\Providers;

use App\Repositories\AuditLogRepository;
use App\Repositories\CarRepository;
use App\Repositories\ChatRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ColorRepository;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\CarRepositoryInterface;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Repositories\Contracts\ClientRepositoryInterface;
use App\Repositories\Contracts\ColorRepositoryInterface;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Repositories\Contracts\ReservationPaymentRepositoryInterface;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\UserPanelRepositoryInterface;
use App\Repositories\Contracts\UserSecurityRepositoryInterface;
use App\Repositories\EquipmentRepository;
use App\Repositories\LocationRepository;
use App\Repositories\ReservationPaymentRepository;
use App\Repositories\ReservationRepository;
use App\Repositories\SettingRepository;
use App\Repositories\UserPanelRepository;
use App\Repositories\UserSecurityRepository;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CarRepositoryInterface::class, CarRepository::class);
        $this->app->bind(ChatRepositoryInterface::class, ChatRepository::class);
        $this->app->bind(ColorRepositoryInterface::class, ColorRepository::class);
        $this->app->bind(SettingRepositoryInterface::class, SettingRepository::class);
        $this->app->bind(EquipmentRepositoryInterface::class, EquipmentRepository::class);
        $this->app->bind(LocationRepositoryInterface::class, LocationRepository::class);
        $this->app->bind(ReservationRepositoryInterface::class, ReservationRepository::class);
        $this->app->bind(ReservationPaymentRepositoryInterface::class, ReservationPaymentRepository::class);
        $this->app->bind(ClientRepositoryInterface::class, ClientRepository::class);
        $this->app->bind(UserSecurityRepositoryInterface::class, UserSecurityRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->bind(UserPanelRepositoryInterface::class, UserPanelRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DevCommands::artisan('schedule:work', 'schedule');

        $this->configureDefaults();
        $this->configureVerificationEmail();
        $this->configurePasswordResetEmail();
        $this->configureTrustedProxies();
    }

    /**
     * Polish content of the email address verification message.
     */
    protected function configureVerificationEmail(): void
    {
        VerifyEmail::toMailUsing(fn (object $notifiable, string $url): MailMessage => (new MailMessage)
            ->subject('Potwierdź adres e-mail')
            ->greeting('Witaj!')
            ->line('Kliknij przycisk poniżej, aby potwierdzić swój adres e-mail.')
            ->action('Potwierdź adres e-mail', $url)
            ->line('Jeśli nie zakładałeś konta, zignoruj tę wiadomość.')
            ->salutation('Pozdrawiamy, zespół '.config('app.name')));
    }

    /**
     * Polish content of the password reset message.
     */
    protected function configurePasswordResetEmail(): void
    {
        ResetPassword::toMailUsing(fn (object $notifiable, string $token): MailMessage => (new MailMessage)
            ->subject('Zmiana hasła')
            ->greeting('Witaj!')
            ->line('Otrzymaliśmy prośbę o zmianę hasła do Twojego konta.')
            ->action('Ustaw nowe hasło', route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('Link wygasa za '.config('auth.passwords.users.expire').' minut. Jeśli to nie Ty, zignoruj tę wiadomość.')
            ->salutation('Pozdrawiamy, zespół '.config('app.name')));
    }

    /**
     * Za proxy limity prób muszą liczyć adres klienta, a nie adres proxy.
     */
    protected function configureTrustedProxies(): void
    {
        $proxies = config('app.trusted_proxies');

        if (filled($proxies)) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
