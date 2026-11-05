<?php

use App\Models\AuditLog;
use App\Services\ReservationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('reservations:expire', function (ReservationService $reservations) {
    $this->info('Wygasłe rezerwacje: '.$reservations->expireOverdue());
})->purpose('Mark unconfirmed reservations past their deadline as expired');

Schedule::command('reservations:expire')->everyMinute()->withoutOverlapping();

// Dziennik działań trzyma wpisy przez rok.
Schedule::command('model:prune', ['--model' => [AuditLog::class]])->daily();
