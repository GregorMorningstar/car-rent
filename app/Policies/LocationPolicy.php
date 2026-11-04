<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class LocationPolicy
{
    /** Miejsca może dodawać i zmieniać tylko administrator. */
    public function manage(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }
}
