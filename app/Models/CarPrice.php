<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Próg ceny za dobę zależny od długości wynajmu, np. 2-3 dni po 450 zł.
 *
 * @property int $id
 * @property int $car_id
 * @property int $min_days
 * @property int|null $max_days
 * @property string $daily_rate
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Car $car
 */
#[Fillable(['min_days', 'max_days', 'daily_rate'])]
class CarPrice extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_days' => 'integer',
            'max_days' => 'integer',
            'daily_rate' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Car, $this>
     */
    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }
}
