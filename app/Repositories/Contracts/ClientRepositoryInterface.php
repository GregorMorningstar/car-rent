<?php

namespace App\Repositories\Contracts;

interface ClientRepositoryInterface
{
    /**
     * Konta klientów z profilem i liczbą rezerwacji, od najnowszego.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array;

    /**
     * Konto klienta. Administrator nie jest klientem, więc tu go nie ma.
     *
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array;

    public function count(): int;
}
