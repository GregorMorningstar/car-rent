<?php

namespace App\Repositories\Contracts;

interface CarRepositoryInterface
{
    public function findCarById(int $id): ?array;

    public function findCarForEdit(int $id): array;

    public function allCars(): array;

    public function createCar(
        array $data,
        array $equipmentIds = [],
        array $imagePaths = [],
        array $locationIds = [],
        array $prices = [],
    ): array;

    /**
     * @return array<int, string> ścieżki usuniętych zdjęć - do skasowania z dysku po zapisie
     */
    public function updateCar(
        int $id,
        array $data,
        array $equipmentIds = [],
        array $imagePaths = [],
        array $removedImageIds = [],
        array $locationIds = [],
        array $prices = [],
    ): array;

    public function searchCars(array $filters): array;

    public function randomCars(int $limit): array;

    public function findPublicCar(int $id): array;
}
