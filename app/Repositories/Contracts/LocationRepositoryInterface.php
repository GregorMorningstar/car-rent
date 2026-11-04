<?php

namespace App\Repositories\Contracts;

interface LocationRepositoryInterface
{
    public function allLocations(): array;

    public function activeLocations(): array;

    public function pickupLocations(): array;

    public function dropoffLocations(): array;

    /**
     * @return array<int, string>
     */
    public function locationNames(?int $ignoreId = null): array;

    public function createLocation(array $data): array;

    public function updateLocation(int $id, array $data): array;

    public function toggleLocation(int $id): void;
}
