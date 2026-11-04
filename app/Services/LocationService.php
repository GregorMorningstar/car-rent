<?php

namespace App\Services;

use App\Repositories\Contracts\LocationRepositoryInterface;
use App\Services\Concerns\ChecksDuplicateNames;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LocationService
{
    use ChecksDuplicateNames;

    private const ATTRIBUTES = [
        'name' => 'nazwa',
        'street' => 'ulica',
        'building_number' => 'numer budynku',
        'postal_code' => 'kod pocztowy',
        'city' => 'miejscowość',
        'latitude' => 'szerokość geograficzna',
        'longitude' => 'długość geograficzna',
        'details' => 'szczegóły',
        'is_pickup' => 'miejsce odbioru',
        'is_dropoff' => 'miejsce dostawy',
    ];

    public function __construct(
        private readonly LocationRepositoryInterface $locationRepository,
    ) {}

    public function allLocations(): array
    {
        return $this->locationRepository->allLocations();
    }

    public function activeLocations(): array
    {
        return $this->locationRepository->activeLocations();
    }

    public function pickupLocations(): array
    {
        return $this->locationRepository->pickupLocations();
    }

    public function dropoffLocations(): array
    {
        return $this->locationRepository->dropoffLocations();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createLocation(array $data): array
    {
        $validated = $this->validate($data);

        return $this->saveUnique(fn () => $this->locationRepository->createLocation($validated));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateLocation(int $id, array $data): array
    {
        $validated = $this->validate($data, $id);

        return $this->saveUnique(fn () => $this->locationRepository->updateLocation($id, $validated));
    }

    public function toggleLocation(int $id): void
    {
        $this->locationRepository->toggleLocation($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $data, ?int $ignoreId = null): array
    {
        $data['name'] = $this->cleanName($data['name'] ?? null);

        return Validator::make(
            $data,
            [
                'name' => ['required', 'string', 'max:150', $this->uniqueNameRule($this->locationRepository->locationNames($ignoreId))],
                'street' => ['required', 'string', 'max:150'],
                'building_number' => ['nullable', 'string', 'max:10'],
                'postal_code' => ['required', 'string', 'regex:/^\d{2}-\d{3}$/'],
                'city' => ['required', 'string', 'max:100'],
                'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
                'details' => ['nullable', 'string', 'max:2000'],
                'is_pickup' => ['required', 'boolean'],
                'is_dropoff' => ['required', 'boolean', 'accepted_if:is_pickup,false'],
            ],
            [
                'postal_code.regex' => 'Kod pocztowy musi mieć format 00-000.',
                'is_dropoff.accepted_if' => 'Zaznacz odbiór, dostawę albo oba.',
            ],
            self::ATTRIBUTES,
        )->validate();
    }

    /** Dwa zapisy naraz mogą ominąć walidację - wtedy duplikat zatrzymuje unikalny indeks w bazie. */
    private function saveUnique(callable $save): array
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Taka pozycja już jest na liście.']);
        }
    }
}
