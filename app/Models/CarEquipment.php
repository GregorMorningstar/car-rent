<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $car_id
 * @property int $equipment_id
 * @property-read Car $car
 * @property-read Equipment $equipment
 */
class CarEquipment extends Pivot
{
    protected $table = 'car_equipment';

    public $incrementing = false;

    public $timestamps = false;

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    /**
     * @return BelongsTo<Equipment, $this>
     */
    public function equipment(): BelongsTo
    {
        return $this->belongsTo(Equipment::class);
    }
}
