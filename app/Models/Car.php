<?php

namespace App\Models;

use App\Enums\CarStatus;
use App\Enums\DrivingLicense;
use App\Enums\FuelType;
use App\Enums\ReservationStatus;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $brand
 * @property string $model
 * @property VehicleType $vehicle_type
 * @property DrivingLicense $required_license
 * @property string $registration_number
 * @property string $vin
 * @property int $production_year
 * @property FuelType $fuel_type
 * @property Transmission $transmission
 * @property int $seats
 * @property int $doors
 * @property int $mileage_km
 * @property string|null $color
 * @property string $daily_rate najniższa cena za dobę ze wszystkich progów, czyli "od X zł"
 * @property int|null $km_limit_per_day limit kilometrów na dobę, null to bez limitu
 * @property string $deposit_amount
 * @property CarStatus $operational_status
 * @property string|null $description
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Collection<int, Equipment> $equipment
 * @property-read Collection<int, CarImage> $images
 * @property-read Color|null $colorOption
 * @property-read Collection<int, Reservation> $reservations
 * @property-read Collection<int, Location> $locations
 * @property-read Collection<int, CarPrice> $prices
 */
#[Fillable([
    'brand',
    'model',
    'vehicle_type',
    'required_license',
    'registration_number',
    'vin',
    'production_year',
    'fuel_type',
    'transmission',
    'seats',
    'doors',
    'mileage_km',
    'color',
    'daily_rate',
    'km_limit_per_day',
    'deposit_amount',
    'operational_status',
    'description',
])]
class Car extends Model
{
    use SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'vehicle_type' => VehicleType::class,
            'required_license' => DrivingLicense::class,
            'production_year' => 'integer',
            'fuel_type' => FuelType::class,
            'transmission' => Transmission::class,
            'seats' => 'integer',
            'doors' => 'integer',
            'mileage_km' => 'integer',
            'daily_rate' => 'decimal:2',
            'km_limit_per_day' => 'integer',
            'deposit_amount' => 'decimal:2',
            'operational_status' => CarStatus::class,
        ];
    }

    /**
     * Progi cen od najkrótszego wynajmu.
     *
     * @return HasMany<CarPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(CarPrice::class)->orderBy('min_days');
    }

    /**
     * Aktualne miejsce samochodu - jedno albo kilka.
     *
     * @return BelongsToMany<Location, $this>
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class);
    }

    /**
     * Auto jest zajęte przez potwierdzoną rezerwację albo oczekującą, której termin potwierdzenia nie minął.
     * Odbiór i zwrot są porównywane co do minuty, a podany zakres jest w UTC.
     * Końce zakresu mogą się stykać, więc przerwę między wynajmami dolicza wywołujący.
     * Przy edycji rezerwacja nie może blokować sama siebie.
     *
     * @param  Builder<Car>  $query
     */
    public function scopeAvailableBetween(Builder $query, CarbonInterface $from, CarbonInterface $to, ?int $exceptReservationId = null): void
    {
        $query->whereDoesntHave('reservations', fn (Builder $reservation) => $reservation
            ->when($exceptReservationId !== null, fn (Builder $own) => $own->whereKeyNot($exceptReservationId))
            ->where('start_date', '<', $to)
            ->where('end_date', '>', $from)
            ->where(fn (Builder $status) => $status
                ->where('status', ReservationStatus::Confirmed)
                ->orWhere(fn (Builder $pending) => $pending
                    ->where('status', ReservationStatus::Pending)
                    ->where('confirmation_deadline', '>=', now()))));
    }

    /**
     * Samochody, których cena za dobę przy tej długości wynajmu mieści się w zakresie.
     *
     * @param  Builder<Car>  $query
     */
    public function scopeWithRateForDays(Builder $query, int $days, mixed $from, mixed $to): void
    {
        $query->whereHas('prices', fn (Builder $price) => $price
            ->where('min_days', '<=', $days)
            ->where(fn (Builder $limit) => $limit->whereNull('max_days')->orWhere('max_days', '>=', $days))
            ->when($from !== null, fn (Builder $rate) => $rate->where('daily_rate', '>=', $from))
            ->when($to !== null, fn (Builder $rate) => $rate->where('daily_rate', '<=', $to)));
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Pozycja słownika kolorów - samochód zapisuje jej nazwę w kolumnie color.
     *
     * @return BelongsTo<Color, $this>
     */
    public function colorOption(): BelongsTo
    {
        return $this->belongsTo(Color::class, 'color', 'name');
    }

    /**
     * @return BelongsToMany<Equipment, $this, CarEquipment>
     */
    public function equipment(): BelongsToMany
    {
        return $this->belongsToMany(Equipment::class)->using(CarEquipment::class);
    }

    /**
     * Zdjęcia w kolejności wyświetlania - pierwsze to okładka.
     *
     * @return HasMany<CarImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(CarImage::class)->orderBy('position')->orderBy('id');
    }
}
