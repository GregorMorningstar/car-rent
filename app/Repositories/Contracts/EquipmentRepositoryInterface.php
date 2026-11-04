<?php

namespace App\Repositories\Contracts;

interface EquipmentRepositoryInterface
{
    public function paginateEquipment(string $search, int $perPage): array;

    public function activeEquipment(): array;

    public function findEquipment(int $id): array;

    /**
     * @return array<int, string>
     */
    public function equipmentNames(?int $ignoreId = null): array;

    public function createEquipment(array $data): array;

    public function updateEquipment(int $id, array $data): array;

    public function toggleEquipment(int $id): void;
}
