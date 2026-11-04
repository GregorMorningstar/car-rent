<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $icon
 * @property array{viewBox: string, path: string}|null $icon_svg
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'icon', 'icon_svg', 'is_active'])]
class Equipment extends Model
{
    protected $table = 'equipment';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'icon_svg' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Car, $this, CarEquipment>
     */
    public function cars(): BelongsToMany
    {
        return $this->belongsToMany(Car::class)->using(CarEquipment::class);
    }

    /**
     * Only equipment available to choose from the list.
     *
     * @param  Builder<Equipment>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
