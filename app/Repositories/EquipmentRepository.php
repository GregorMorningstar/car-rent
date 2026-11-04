<?php

namespace App\Repositories;

use App\Models\Equipment;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class EquipmentRepository implements EquipmentRepositoryInterface
{
    public function __construct(
        private readonly Equipment $model,
    ) {}

    public function paginateEquipment(string $search, int $perPage): array
    {
        return $this->model->newQuery()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'icon', 'icon_svg', 'is_active'], 'equipment_page')
            ->toArray();
    }

    public function activeEquipment(): array
    {
        return $this->model->newQuery()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'icon', 'icon_svg'])
            ->toArray();
    }

    public function findEquipment(int $id): array
    {
        return $this->model->newQuery()->findOrFail($id)->toArray();
    }

    public function equipmentNames(?int $ignoreId = null): array
    {
        return $this->model->newQuery()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->pluck('name')
            ->all();
    }

    public function createEquipment(array $data): array
    {
        return $this->model->create($data)->toArray();
    }

    public function updateEquipment(int $id, array $data): array
    {
        $equipment = $this->model->newQuery()->findOrFail($id);

        $equipment->update($data);

        return $equipment->toArray();
    }

    public function toggleEquipment(int $id): void
    {
        $equipment = $this->model->newQuery()->findOrFail($id);

        $equipment->update(['is_active' => ! $equipment->is_active]);
    }
}
