<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $phone
 * @property string $street
 * @property string $building_number
 * @property string|null $apartment_number
 * @property string $postal_code
 * @property string $city
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable([
    'phone',
    'street',
    'building_number',
    'apartment_number',
    'postal_code',
    'city',
])]
class UserProfile extends Model
{
    /**
     * Dane kontaktowe są szyfrowane w bazie.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'phone' => 'encrypted',
            'street' => 'encrypted',
            'building_number' => 'encrypted',
            'apartment_number' => 'encrypted',
            'postal_code' => 'encrypted',
            'city' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
