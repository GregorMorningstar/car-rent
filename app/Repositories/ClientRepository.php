<?php

namespace App\Repositories;

use App\Enums\UserRole;
use App\Models\User;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class ClientRepository implements ClientRepositoryInterface
{
    public function __construct(
        private readonly User $model,
    ) {}

    public function all(): array
    {
        return $this->clients()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map($this->toRow(...))
            ->all();
    }

    public function find(int $id): ?array
    {
        $client = $this->clients()->find($id);

        return $client === null ? null : $this->toRow($client);
    }

    public function count(): int
    {
        return $this->model->newQuery()->where('role', UserRole::User)->count();
    }

    /**
     * @return Builder<User>
     */
    private function clients(): Builder
    {
        return $this->model->newQuery()
            ->with('profile')
            ->withCount(['reservations' => fn (Builder $reservation) => $reservation->withTrashed()])
            ->where('role', UserRole::User);
    }

    /**
     * @return array<string, mixed>
     */
    private function toRow(User $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'email_verified_at' => $client->email_verified_at?->toJSON(),
            'created_at' => $client->created_at?->toJSON(),
            'reservationsCount' => $client->reservations_count,
            'profile' => $client->profile?->only(['phone', 'street', 'building_number', 'apartment_number', 'postal_code', 'city']),
        ];
    }
}
