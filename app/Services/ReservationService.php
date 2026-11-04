<?php

namespace App\Services;

use App\Repositories\Contracts\ReservationRepositoryInterface;

class ReservationService
{
    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
    ) {}
}
