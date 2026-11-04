<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

class UpdateLastLoginAt
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $user = $event->user;

        // Bez zmiany updated_at - logowanie to nie edycja konta.
        User::withoutTimestamps(fn () => $user->forceFill([
            'last_login_at' => now(),
        ])->saveQuietly());
    }
}
