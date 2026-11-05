<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Sygnał dla administracji o nowej rezerwacji. Szczegóły przeglądarka pobiera osobno.
 */
class ReservationPlaced implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.inbox')];
    }

    public function broadcastAs(): string
    {
        return 'reservation.created';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
