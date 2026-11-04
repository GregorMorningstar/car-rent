<?php

namespace App\Repositories\Contracts;

interface ColorRepositoryInterface
{
    public function paginateColors(string $search, int $perPage): array;

    public function activeColors(): array;

    /**
     * @return array<int, string>
     */
    public function colorNames(?int $ignoreId = null): array;

    public function createColor(array $data): array;

    public function updateColor(int $id, array $data): array;

    public function toggleColor(int $id): void;
}
