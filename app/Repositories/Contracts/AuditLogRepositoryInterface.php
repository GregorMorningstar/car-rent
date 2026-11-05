<?php

namespace App\Repositories\Contracts;

interface AuditLogRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $entry
     */
    public function add(array $entry): void;

    /**
     * Najnowsze wpisy.
     *
     * @return array<int, array<string, mixed>>
     */
    public function latest(int $limit): array;
}
