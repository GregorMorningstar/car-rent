<?php

namespace App\Repositories\Contracts;

use App\Models\ChatMessage;
use App\Models\Conversation;

interface ChatRepositoryInterface
{
    public function findForClient(int $clientId): ?Conversation;

    public function find(int $conversationId): ?Conversation;

    public function firstOrCreateForClient(int $clientId): Conversation;

    /**
     * Klienci, do których może napisać administracja.
     *
     * @return array<int, array{id: int, name: string, email: string}>
     */
    public function clients(): array;

    public function isClient(int $userId): bool;

    /**
     * @return array<int, Conversation>
     */
    public function allWithUnread(): array;

    /**
     * Ostatnie wiadomości od najstarszej.
     *
     * @return array<int, ChatMessage>
     */
    public function latestMessages(int $conversationId, int $limit): array;

    public function addMessage(Conversation $conversation, int $senderId, string $body): ChatMessage;

    public function markForwarded(ChatMessage $message): void;

    /** Oznacza jako przeczytane wiadomości klienta, które przeczytała administracja. */
    public function markClientMessagesRead(Conversation $conversation): void;

    /** Oznacza jako przeczytane wiadomości administracji, które przeczytał klient. */
    public function markStaffMessagesRead(Conversation $conversation): void;

    public function unreadForClient(int $clientId): int;

    public function unreadForStaff(): int;
}
