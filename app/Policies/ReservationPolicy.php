<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /** Rezerwację zmienia wyłącznie jej właściciel. */
    public function update(User $user, Reservation $reservation): bool
    {
        return $user->role === UserRole::User && $reservation->user_id === $user->id;
    }

    /** Rezerwację usuwa wyłącznie jej właściciel. */
    public function delete(User $user, Reservation $reservation): bool
    {
        return $this->update($user, $reservation);
    }

    /** Panel rezerwacji, ich edycję i wpłaty obsługuje tylko administrator. */
    public function manage(User $user): bool
    {
        return $user->role === UserRole::Administrator;
    }
}
