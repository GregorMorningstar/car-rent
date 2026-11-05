<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Kanał klienta: tylko właściciel konta.
Broadcast::channel('App.Models.User.{id}', function (User $user, $id) {
    return (int) $user->id === (int) $id;
});

// Kanał administracji: wiadomości od klientów i nowe rezerwacje.
Broadcast::channel('admin.inbox', function (User $user) {
    return $user->role === UserRole::Administrator;
});
