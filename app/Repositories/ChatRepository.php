<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\ChatMessage;
use App\Models\Conversation;
use App\Models\User;
use App\Repositories\Contracts\ChatRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ChatRepository implements ChatRepositoryInterface
{
    public function __construct(
        private readonly Conversation $conversation,
        private readonly ChatMessage $message,
        private readonly User $user,
    ) {}

    public function findForClient(int $clientId): ?Conversation
    {
        return $this->conversation->newQuery()->where('user_id', $clientId)->first();
    }

    public function find(int $conversationId): ?Conversation
    {
        return $this->conversation->newQuery()->with('user:id,name,email')->find($conversationId);
    }

    public function firstOrCreateForClient(int $clientId): Conversation
    {
        return $this->conversation->newQuery()->firstOrCreate(['user_id' => $clientId]);
    }

    public function clients(): array
    {
        return $this->user->newQuery()
            ->where('role', UserRole::User)
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $client) => ['id' => $client->id, 'name' => $client->name, 'email' => $client->email])
            ->all();
    }

    public function isClient(int $userId): bool
    {
        return $this->user->newQuery()->whereKey($userId)->where('role', UserRole::User)->exists();
    }

    public function allWithUnread(): array
    {
        return $this->conversation->newQuery()
            ->with(['user:id,name,email', 'latestMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $this->fromClient($query)->whereNull('read_at')])
            ->orderByDesc('last_message_at')
            ->get()
            ->all();
    }

    public function latestMessages(int $conversationId, int $limit): array
    {
        return $this->message->newQuery()
            ->where('conversation_id', $conversationId)
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->all();
    }

    public function addMessage(Conversation $conversation, int $senderId, string $body): ChatMessage
    {
        return DB::transaction(function () use ($conversation, $senderId, $body) {
            $message = $conversation->messages()->create(['sender_id' => $senderId, 'body' => $body]);

            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });
    }

    public function markForwarded(ChatMessage $message): void
    {
        $message->update(['messenger_forwarded_at' => now()]);
    }

    public function markClientMessagesRead(Conversation $conversation): void
    {
        $conversation->messages()
            ->where('sender_id', $conversation->user_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function markStaffMessagesRead(Conversation $conversation): void
    {
        $conversation->messages()
            ->where('sender_id', '!=', $conversation->user_id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function unreadForClient(int $clientId): int
    {
        return $this->message->newQuery()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $clientId)
            ->whereHas('conversation', fn (Builder $query) => $query->where('user_id', $clientId))
            ->count();
    }

    public function unreadForStaff(): int
    {
        return $this->fromClient($this->message->newQuery())->whereNull('read_at')->count();
    }

    /**
     * Wiadomości napisane przez klienta danej rozmowy.
     *
     * @param  Builder<ChatMessage>  $query
     * @return Builder<ChatMessage>
     */
    private function fromClient(Builder $query): Builder
    {
        return $query->whereExists(fn ($conversation) => $conversation
            ->selectRaw('1')
            ->from('conversations as owner')
            ->whereColumn('owner.id', 'chat_messages.conversation_id')
            ->whereColumn('owner.user_id', 'chat_messages.sender_id'));
    }
}
