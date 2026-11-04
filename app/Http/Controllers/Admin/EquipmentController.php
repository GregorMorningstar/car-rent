<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EquipmentService;

class EquipmentController extends Controller
{
    public function __construct(
        private readonly EquipmentService $equipmentService,
    ) {}
}
