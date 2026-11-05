<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /** Rozmowę widzi jej klient i administracja, nikt więcej. */
    public function view(User $user, Conversation $conversation): bool
    {
        return $user->role === UserRole::Administrator || $conversation->user_id === $user->id;
    }

    /** Skrzynkę wszystkich rozmów ma tylko administracja. */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }
}
