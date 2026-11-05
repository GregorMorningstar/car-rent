<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class UserPolicy
{
    /** Dane klientów ogląda tylko administrator. */
    public function manageClients(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }
}
