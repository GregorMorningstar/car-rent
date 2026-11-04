<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class CarPolicy
{
    /** Samochody może dodawać tylko administrator. */
    public function create(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    /** Samochody może edytować tylko administrator. */
    public function update(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    /** Wyposażenie i kolory może zmieniać tylko administrator. */
    public function manageDictionary(User $user): bool
    {
        return $this->isAdministrator($user);
    }

    private function isAdministrator(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }
}
