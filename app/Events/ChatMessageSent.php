<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sygnał dla klienta i administracji, że rozmowa się zmieniła.
 * Nie niesie treści - przeglądarka pobiera ją zwykłym żądaniem, które sprawdza uprawnienia.
 */
class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        private readonly int $clientId,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('App.Models.User.'.$this->clientId),
            new PrivateChannel('admin.inbox'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
