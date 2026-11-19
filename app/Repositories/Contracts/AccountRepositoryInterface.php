<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Carbon\CarbonInterface;

interface AccountRepositoryInterface
{
    /** Czy klient ma aktywną rezerwację, auto u siebie albo nierozliczoną wpłatę. */
    public function hasOpenMatters(int $userId): bool;

    /** Wymazuje dane osobowe konta. Rezerwacje i wpłaty zostają bez powiązania z osobą. */
    public function anonymize(User $user): void;

    /**
     * Konta bez logowania od podanej chwili.
     *
     * @return array<int, User>
     */
    public function inactiveAccounts(CarbonInterface $before): array;

    /**
     * Niepotwierdzone konta bez żadnej rezerwacji.
     *
     * @return array<int, User>
     */
    public function abandonedAccounts(CarbonInterface $before): array;

    public function delete(User $user): void;
}
