<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;

class SettingPolicy
{
    /** Dane dostępowe zmienia tylko administrator. */
    public function manage(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }
}
