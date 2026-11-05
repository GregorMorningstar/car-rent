<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Komunikator klient - administracja. Klient nigdy nie wskazuje adresata: rozmowa wynika
 * z jego konta, więc napisać może wyłącznie do administracji, a nie do innego klienta.
 */
class ChatService
{
    private const MAX_BODY = 2000;

    private const THREAD_LIMIT = 200;

    public function __construct(
        private readonly ChatRepositoryInterface $chatRepository,
        private readonly MessengerService $messengerService,
    ) {}

    /**
     * Rozmowa klienta. Otwarcie oznacza odpowiedzi administracji jako przeczytane.
     *
     * @return array{messages: array<int, array<string, mixed>>, failed: bool}
     *
     * @throws AuthorizationException
     */
    public function clientThread(User $client): array
    {
        $this->assertRole($client, UserRole::User);

        try {
            $conversation = $this->chatRepository->findForClient($client->id);

            if ($conversation === null) {
                return ['messages' => [], 'failed' => false];
            }

            $this->assertCanView($client, $conversation);
            $this->chatRepository->markStaffMessagesRead($conversation);

            return ['messages' => $this->thread($conversation, $client), 'failed' => false];
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return ['messages' => [], 'failed' => true];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function sendFromClient(User $client, array $data): void
    {
        $this->assertRole($client, UserRole::User);

        $body = $this->validateBody($data);

        $message = $this->store(fn () => $this->chatRepository->addMessage(
            $this->chatRepository->firstOrCreateForClient($client->id),
            $client->id,
            $body,
        ));

        $this->announce($client->id);
        $this->messengerService->forward($message, $client->name);
    }

    /**
     * Skrzynka administracji. Otwarcie rozmowy oznacza wiadomości klienta jako przeczytane.
     *
     * @param  array<string, mixed>  $filters
     * @return array{conversations: array<int, array<string, mixed>>, clients: array<int, array<string, mixed>>, active: array<string, mixed>|null, messages: array<int, array<string, mixed>>, failed: bool}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function staffInbox(User $staff, array $filters = []): array
    {
        $this->assertRole($staff, UserRole::Administrator);

        $validator = Validator::make(
            $filters,
            ['conversation' => ['nullable', 'integer', 'min:1']],
            [],
            ['conversation' => 'rozmowa'],
        );

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route('admin.messages.index'));
        }

        $activeId = $validator->validated()['conversation'] ?? null;

        try {
            $active = $activeId === null ? null : $this->chatRepository->find((int) $activeId);

            if ($active !== null) {
                $this->chatRepository->markClientMessagesRead($active);
            }

            return [
                'conversations' => array_map($this->toConversation(...), $this->chatRepository->allWithUnread()),
                'clients' => $this->chatRepository->clients(),
                'active' => $active === null ? null : [
                    'id' => $active->id,
                    'client' => $active->user->name,
                    'email' => $active->user->email,
                ],
                'messages' => $active === null ? [] : $this->thread($active, $staff),
                'failed' => false,
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['conversations' => [], 'clients' => [], 'active' => null, 'messages' => [], 'failed' => true];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function sendFromStaff(User $staff, int $conversationId, array $data): void
    {
        $this->assertRole($staff, UserRole::Administrator);

        $body = $this->validateBody($data);
        $conversation = $this->chatRepository->find($conversationId);

        abort_if($conversation === null, 404);

        $this->store(fn () => $this->chatRepository->addMessage($conversation, $staff->id, $body));
        $this->announce($conversation->user_id);
    }

    /**
     * Administracja zaczyna rozmowę z klientem. Zwraca identyfikator rozmowy.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function openConversation(User $staff, array $data): int
    {
        $this->assertRole($staff, UserRole::Administrator);

        $validated = Validator::make(
            $data,
            ['client_id' => ['required', 'integer', 'min:1']],
            [],
            ['client_id' => 'klient'],
        )->validate();

        if (! $this->chatRepository->isClient((int) $validated['client_id'])) {
            throw ValidationException::withMessages(['client_id' => 'Można pisać tylko do klientów.']);
        }

        return $this->chatRepository->firstOrCreateForClient((int) $validated['client_id'])->id;
    }

    /** Wiadomość automatyczna. Jej błąd nie może cofnąć operacji, która ją wywołała. */
    public function notifyClient(User $staff, int $clientId, string $body): void
    {
        try {
            $this->chatRepository->addMessage($this->chatRepository->firstOrCreateForClient($clientId), $staff->id, $body);
            $this->announce($clientId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /** Licznik nieprzeczytanych. Błąd odczytu nie może wyłączyć całego panelu. */
    public function unreadCount(User $user): int
    {
        try {
            return $user->role === UserRole::Administrator
                ? $this->chatRepository->unreadForStaff()
                : $this->chatRepository->unreadForClient($user->id);
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertRole(User $user, UserRole $role): void
    {
        if ($user->role !== $role) {
            throw new AuthorizationException('Brak dostępu do tej rozmowy.');
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function assertCanView(User $user, Conversation $conversation): void
    {
        if ($user->cannot('view', $conversation)) {
            throw new AuthorizationException('Brak dostępu do tej rozmowy.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    private function validateBody(array $data): string
    {
        $validated = Validator::make(
            $data,
            ['body' => ['required', 'string', 'max:'.self::MAX_BODY]],
            [],
            ['body' => 'wiadomość'],
        )->validate();

        return trim($validated['body']);
    }

    /**
     * @throws ValidationException
     */
    private function store(callable $save): ChatMessage
    {
        try {
            return $save();
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['body' => 'Nie udało się wysłać wiadomości. Spróbuj ponownie.']);
        }
    }

    /** Wiadomość jest już zapisana, więc wyłączony serwer Reverb tylko opóźnia jej pojawienie się. */
    private function announce(int $clientId): void
    {
        try {
            ChatMessageSent::dispatch($clientId);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function thread(Conversation $conversation, User $reader): array
    {
        return array_map(fn (ChatMessage $message) => [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => $message->sender_id === $reader->id,
            'from_client' => $message->sender_id === $conversation->user_id,
            'read' => $message->read_at !== null,
            'created_at' => $message->created_at?->toJSON(),
        ], $this->chatRepository->latestMessages($conversation->id, self::THREAD_LIMIT));
    }

    /**
     * @return array<string, mixed>
     */
    private function toConversation(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'client' => $conversation->user->name,
            'preview' => $conversation->latestMessage?->body,
            'unread' => $conversation->unread_count,
            'last_message_at' => $conversation->last_message_at?->toJSON(),
        ];
    }
}
