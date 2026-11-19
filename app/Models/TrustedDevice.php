<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Urządzenie, na którym logowanie nie wymaga kodu z e-maila.
 *
 * @property int $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $name
 * @property CarbonImmutable $last_used_at
 * @property CarbonImmutable $expires_at
 * @property-read User $user
 */
#[Fillable(['user_id', 'token_hash', 'name', 'last_used_at', 'expires_at'])]
#[Hidden(['token_hash'])]
class TrustedDevice extends Model
{
    use MassPrunable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<', now());
    }
}
