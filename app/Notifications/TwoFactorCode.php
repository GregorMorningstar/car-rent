<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Kod logowania. Wysyłany od razu, bo użytkownik czeka na niego na ekranie.
 */
class TwoFactorCode extends Notification
{
    public function __construct(
        private readonly string $code,
        private readonly int $minutes,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Kod logowania: '.$this->code)
            ->greeting('Witaj!')
            ->line('Twój kod logowania:')
            ->line('**'.$this->code.'**')
            ->line("Kod jest ważny przez {$this->minutes} minut i działa tylko raz.")
            ->line('Jeśli to nie Ty się logujesz, zmień hasło. Ktoś je zna.')
            ->salutation('Pozdrawiamy, zespół '.config('app.name'));
    }
}
