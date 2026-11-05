<?php

namespace App\Repositories;

use App\Enums\CarStatus;
use App\Models\Location;
use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Support\LocalDate;
use Illuminate\Database\Eloquent\Builder;

class LocationRepository implements LocationRepositoryInterface
{
    /** Dane miejsca w wyszukiwarce i na mapie. */
    private const PUBLIC_COLUMNS = [
        'id',
        'name',
        'street',
        'building_number',
        'postal_code',
        'city',
        'latitude',
        'longitude',
        'details',
    ];

    public function __construct(
        private readonly Location $model,
    ) {}

    /** Szczegółowa lista w panelu: "cars_count" to wszystkie auta, "available_cars_count" to wolne dzisiaj. */
    public function allLocations(): array
    {
        $today = LocalDate::today();

        return $this->model->newQuery()
            ->withCount([
                'cars',
                'cars as available_cars_count' => fn (Builder $query) => $query
                    ->where('operational_status', CarStatus::Active)
                    ->availableBetween(LocalDate::startOfDay($today), LocalDate::endOfDay($today)),
            ])
            ->orderBy('name')
            ->get()
            ->toArray();
    }

    public function activeLocations(): array
    {
        return $this->active()->get(['id', 'name'])->toArray();
    }

    public function pickupLocations(): array
    {
        return $this->active()->where('is_pickup', true)->get(self::PUBLIC_COLUMNS)->toArray();
    }

    public function dropoffLocations(): array
    {
        return $this->active()->where('is_dropoff', true)->get(self::PUBLIC_COLUMNS)->toArray();
    }

    public function locationNames(?int $ignoreId = null): array
    {
        return $this->model->newQuery()
            ->when($ignoreId !== null, fn (Builder $query) => $query->whereKeyNot($ignoreId))
            ->pluck('name')
            ->all();
    }

    public function createLocation(array $data): array
    {
        return $this->model->create($data)->toArray();
    }

    public function updateLocation(int $id, array $data): array
    {
        $location = $this->model->newQuery()->findOrFail($id);

        $location->update($data);

        return $location->toArray();
    }

    public function toggleLocation(int $id): void
    {
        $location = $this->model->newQuery()->findOrFail($id);

        $location->update(['is_active' => ! $location->is_active]);
    }

    /**
     * @return Builder<Location>
     */
    private function active(): Builder
    {
        return $this->model->newQuery()->where('is_active', true)->orderBy('name');
    }
}
