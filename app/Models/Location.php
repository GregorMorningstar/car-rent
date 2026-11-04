<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Miejsce odbioru i dostawy samochodów.
 *
 * @property int $id
 * @property string $name
 * @property string $street
 * @property string|null $building_number
 * @property string $postal_code
 * @property string $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property string|null $details
 * @property bool $is_pickup
 * @property bool $is_dropoff
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Car> $cars
 */
#[Fillable([
    'name',
    'street',
    'building_number',
    'postal_code',
    'city',
    'latitude',
    'longitude',
    'details',
    'is_pickup',
    'is_dropoff',
    'is_active',
])]
class Location extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'is_pickup' => 'boolean',
            'is_dropoff' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Car, $this>
     */
    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class);
    }
}
