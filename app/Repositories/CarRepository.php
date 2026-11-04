<?php

namespace App\Repositories;

use App\Enums\CarStatus;
use App\Models\Car;
use App\Repositories\Contracts\CarRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CarRepository implements CarRepositoryInterface
{
    /** Kolumny widoczne dla gości - bez VIN, rejestracji i przebiegu. */
    private const PUBLIC_COLUMNS = [
        'id',
        'brand',
        'model',
        'vehicle_type',
        'production_year',
        'fuel_type',
        'transmission',
        'seats',
        'doors',
        'color',
        'daily_rate',
        'km_limit_per_day',
        'deposit_amount',
        'description',
    ];

    private const PRICES = 'prices:id,car_id,min_days,max_days,daily_rate';

    public function __construct(
        private readonly Car $model,
    ) {}

    public function findCarById(int $id): ?array
    {
        $car = $this->model->find($id);

        return $car ? $car->toArray() : null;
    }

    public function findCarForEdit(int $id): array
    {
        return $this->model->newQuery()
            ->with(['equipment:id,name,icon,icon_svg', 'images', 'colorOption:id,name,hex', 'locations:id,name', self::PRICES])
            ->findOrFail($id)
            ->toArray();
    }

    public function allCars(): array
    {
        return $this->model->newQuery()
            ->with(['images', 'equipment:id,name,icon,icon_svg', 'colorOption:id,name,hex', 'locations:id,name', self::PRICES])
            ->latest('id')
            ->get()
            ->toArray();
    }

    public function createCar(
        array $data,
        array $equipmentIds = [],
        array $imagePaths = [],
        array $locationIds = [],
        array $prices = [],
    ): array {
        return DB::transaction(function () use ($data, $equipmentIds, $imagePaths, $locationIds, $prices) {
            $car = $this->model->create($data);
            $car->equipment()->sync($equipmentIds);
            $car->locations()->sync($locationIds);
            $this->replacePrices($car, $prices);
            $this->addImages($car, $imagePaths);

            return $car->toArray();
        });
    }

    public function updateCar(
        int $id,
        array $data,
        array $equipmentIds = [],
        array $imagePaths = [],
        array $removedImageIds = [],
        array $locationIds = [],
        array $prices = [],
    ): array {
        return DB::transaction(function () use ($id, $data, $equipmentIds, $imagePaths, $removedImageIds, $locationIds, $prices) {
            $car = $this->model->newQuery()->findOrFail($id);

            $car->update($data);
            $car->equipment()->sync($equipmentIds);
            $car->locations()->sync($locationIds);
            $this->replacePrices($car, $prices);

            $removedPaths = $car->images()->whereKey($removedImageIds)->pluck('path')->all();
            $car->images()->whereKey($removedImageIds)->delete();

            $this->addImages($car, $imagePaths);

            return $removedPaths;
        });
    }

    /** Widoczne są tylko aktywne samochody - inne dają 404. */
    public function findPublicCar(int $id): array
    {
        return $this->publicCars()
            ->with('colorOption:id,name,hex')
            ->whereKey($id)
            ->firstOrFail(self::PUBLIC_COLUMNS)
            ->toArray();
    }

    public function randomCars(int $limit): array
    {
        return $this->publicCars()
            ->inRandomOrder()
            ->limit($limit)
            ->get(self::PUBLIC_COLUMNS)
            ->toArray();
    }

    public function searchCars(array $filters): array
    {
        return $this->publicCars()
            ->when(isset($filters['vehicle_type']), fn (Builder $query) => $query->where('vehicle_type', $filters['vehicle_type']))
            ->when(isset($filters['doors']), fn (Builder $query) => $query->where('doors', $filters['doors']))
            ->when(isset($filters['fuel']), fn (Builder $query) => $query->where('fuel_type', $filters['fuel']))
            ->when(isset($filters['transmission']), fn (Builder $query) => $query->where('transmission', $filters['transmission']))
            ->when(isset($filters['from'], $filters['to']), fn (Builder $query) => $query->availableBetween($filters['from'], $filters['to']))
            ->when(isset($filters['pickup']), fn (Builder $query) => $query->whereHas(
                'locations',
                fn (Builder $location) => $location->whereKey($filters['pickup']),
            ))
            ->when(isset($filters['days']) && (isset($filters['priceFrom']) || isset($filters['priceTo'])), fn (Builder $query) => $query
                ->withRateForDays($filters['days'], $filters['priceFrom'] ?? null, $filters['priceTo'] ?? null))
            ->when(! isset($filters['days']) && isset($filters['priceFrom']), fn (Builder $query) => $query->where('daily_rate', '>=', $filters['priceFrom']))
            ->when(! isset($filters['days']) && isset($filters['priceTo']), fn (Builder $query) => $query->where('daily_rate', '<=', $filters['priceTo']))
            ->when($filters['equipment'] ?? [], function (Builder $query, array $equipmentIds) {
                // Auto musi mieć całe zaznaczone wyposażenie.
                foreach ($equipmentIds as $id) {
                    $query->whereHas('equipment', fn (Builder $equipment) => $equipment->whereKey($id));
                }
            })
            ->orderBy('daily_rate')
            ->get(self::PUBLIC_COLUMNS)
            ->toArray();
    }

    /**
     * Samochody widoczne w serwisie.
     *
     * @return Builder<Car>
     */
    private function publicCars(): Builder
    {
        return $this->model->newQuery()
            ->with(['images', 'equipment:id,name,icon,icon_svg', 'locations:id,name', self::PRICES])
            ->where('operational_status', CarStatus::Active);
    }

    private function replacePrices(Car $car, array $prices): void
    {
        $car->prices()->reorder()->delete();
        $car->prices()->createMany($prices);
    }

    /** Nowe zdjęcia trafiają na koniec galerii. */
    private function addImages(Car $car, array $paths): void
    {
        $position = (int) $car->images()->reorder()->max('position') + 1;

        foreach (array_values($paths) as $index => $path) {
            $car->images()->create(['path' => $path, 'position' => $position + $index]);
        }
    }
}
