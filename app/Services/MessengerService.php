<?php

namespace App\Services;

use App\Exceptions\MessengerException;
use App\Models\ChatMessage;
use App\Repositories\Contracts\ChatRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Przekazywanie wiadomości klientów na Messengera przez Send API strony na Facebooku.
 * Dane dostępowe są w tabeli settings, a token nigdy nie wraca do przeglądarki.
 */
class MessengerService
{
    private const ENABLED = 'messenger.enabled';

    private const PAGE_TOKEN = 'messenger.page_token';

    private const RECIPIENT = 'messenger.recipient_id';

    private const MAX_TEXT = 2000;

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly ChatRepositoryInterface $chatRepository,
    ) {}

    /**
     * Ustawienia do formularza - zamiast tokena tylko informacja, czy jest zapisany.
     *
     * @return array{enabled: bool, recipient_id: string, has_token: bool}
     */
    public function settings(): array
    {
        return [
            'enabled' => $this->settings->get(self::ENABLED) === '1',
            'recipient_id' => $this->settings->get(self::RECIPIENT) ?? '',
            'has_token' => filled($this->settings->get(self::PAGE_TOKEN)),
        ];
    }

    /**
     * Puste pole tokena zostawia zapisaną wartość.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function saveSettings(array $data): void
    {
        $validated = Validator::make(
            $data,
            [
                'enabled' => ['required', 'boolean'],
                'page_token' => ['nullable', 'string', 'max:1000'],
                'recipient_id' => ['nullable', 'required_if_accepted:enabled', 'string', 'regex:/^\d{5,30}$/'],
            ],
            ['recipient_id.regex' => 'Identyfikator odbiorcy może zawierać tylko cyfry.'],
            ['page_token' => 'token strony', 'recipient_id' => 'identyfikator odbiorcy'],
        )->validate();

        $enabled = filter_var($validated['enabled'], FILTER_VALIDATE_BOOL);

        if ($enabled && blank($validated['page_token'] ?? null) && ! $this->settings()['has_token']) {
            throw ValidationException::withMessages(['page_token' => 'Podaj token strony, żeby włączyć przekazywanie.']);
        }

        $this->settings->put(self::ENABLED, $enabled ? '1' : '0');
        $this->settings->put(self::RECIPIENT, $validated['recipient_id'] ?? null);

        if (filled($validated['page_token'] ?? null)) {
            $this->settings->put(self::PAGE_TOKEN, $validated['page_token']);
        }
    }

    /**
     * Wiadomość próbna z formularza ustawień.
     *
     * @throws MessengerException
     */
    public function sendTest(): void
    {
        $this->send('Test połączenia z panelu wypożyczalni.');
    }

    /**
     * Wiadomość na czacie jest już zapisana, więc błąd Messengera trafia tylko do logu.
     */
    public function forward(ChatMessage $message, string $clientName): void
    {
        if (! $this->settings()['enabled']) {
            return;
        }

        try {
            $this->send("Wiadomość od {$clientName}:\n{$message->body}");
            $this->chatRepository->markForwarded($message);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @throws MessengerException
     */
    private function send(string $text): void
    {
        $token = $this->settings->get(self::PAGE_TOKEN);
        $recipient = $this->settings->get(self::RECIPIENT);

        if (blank($token) || blank($recipient)) {
            throw new MessengerException('Uzupełnij token strony i identyfikator odbiorcy.');
        }

        try {
            // Token w nagłówku, żeby nie trafił do logów razem z adresem.
            Http::timeout(5)
                ->withOptions(['verify' => config('services.messenger.ca_bundle') ?: true])
                ->withToken($token)
                ->post('https://graph.facebook.com/'.config('services.messenger.graph_version').'/me/messages', [
                    'recipient' => ['id' => $recipient],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => Str::limit($text, self::MAX_TEXT - 3)],
                ])
                ->throw();
        } catch (ConnectionException|RequestException $exception) {
            throw new MessengerException('Messenger odrzucił wiadomość: '.$this->reason($exception), previous: $exception);
        }
    }

    private function reason(ConnectionException|RequestException $exception): string
    {
        if ($exception instanceof RequestException) {
            return $exception->response->json('error.message') ?? 'kod '.$exception->response->status();
        }

        return 'brak połączenia';
    }
}
