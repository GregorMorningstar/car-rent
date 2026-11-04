<?php

namespace App\Repositories;

use App\Models\Car;
use App\Models\Color;
use App\Repositories\Contracts\ColorRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ColorRepository implements ColorRepositoryInterface
{
    public function __construct(
        private readonly Color $model,
    ) {}

    public function paginateColors(string $search, int $perPage): array
    {
        return $this->model->newQuery()
            ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->orderBy('name')
            ->paginate($perPage, ['id', 'name', 'hex', 'is_active'], 'colors_page')
            ->toArray();
    }

    public function activeColors(): array
    {
        return $this->model->newQuery()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'hex'])
            ->toArray();
    }

    public function colorNames(?int $ignoreId = null): array
    {
        return $this->model->newQuery()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->pluck('name')
            ->all();
    }

    public function createColor(array $data): array
    {
        return $this->model->create($data)->toArray();
    }

    /** Samochody zapisują nazwę koloru, więc zmiana nazwy obejmuje też ich kolumnę color. */
    public function updateColor(int $id, array $data): array
    {
        return DB::transaction(function () use ($id, $data) {
            $color = $this->model->newQuery()->findOrFail($id);
            $previousName = $color->name;

            $color->update($data);

            Car::query()->withTrashed()->where('color', $previousName)->update(['color' => $color->name]);

            return $color->toArray();
        });
    }

    public function toggleColor(int $id): void
    {
        $color = $this->model->newQuery()->findOrFail($id);

        $color->update(['is_active' => ! $color->is_active]);
    }
}
