<?php

namespace App\Repositories\Contracts;

interface UserPanelRepositoryInterface
{
    /**
     * Rezerwacje wyłącznie tego użytkownika, od najnowszego terminu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rentalsForUser(int $userId): array;

    /**
     * Wpłaty wyłącznie do rezerwacji tego użytkownika, od najnowszej.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paymentsForUser(int $userId): array;
}
