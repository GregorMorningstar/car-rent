<?php

namespace App\Services;

use App\Enums\CarStatus;
use App\Enums\DrivingLicense;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Enums\VehicleType;
use App\Models\Car;
use App\Models\Color;
use App\Models\Equipment;
use App\Models\Location;
use App\Repositories\Contracts\CarRepositoryInterface;
use App\Support\LocalDate;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\ValidationException;
use Throwable;

class CarService
{
    private const MAX_SEATS = 9;

    private const MAX_BUS_SEATS = 60;

    private const MAX_IMAGES = 10;

    private const HOME_CARS = 8;

    private const MAX_PRICE_TIERS = 10;

    private const MAX_RENTAL_DAYS = 365;

    private const MAX_IMAGE_KILOBYTES = 5120;

    private const CREATE_ATTRIBUTES = [
        'brand' => 'marka',
        'model' => 'model',
        'vehicle_type' => 'rodzaj pojazdu',
        'required_license' => 'wymagane prawo jazdy',
        'registration_number' => 'numer rejestracyjny',
        'vin' => 'VIN',
        'production_year' => 'rok produkcji',
        'color' => 'kolor',
        'equipment' => 'wyposażenie',
        'equipment.*' => 'wyposażenie',
        'locations' => 'aktualne miejsce',
        'locations.*' => 'aktualne miejsce',
        'images' => 'zdjęcia',
        'images.*' => 'zdjęcie',
        'removed_images.*' => 'usuwane zdjęcie',
        'fuel_type' => 'rodzaj paliwa',
        'transmission' => 'skrzynia biegów',
        'doors' => 'ilość drzwi',
        'seats' => 'liczba miejsc',
        'mileage_km' => 'przebieg',
        'prices' => 'ceny',
        'prices.*.min_days' => 'od ilu dni',
        'prices.*.max_days' => 'do ilu dni',
        'prices.*.daily_rate' => 'cena za dzień',
        'km_limit_per_day' => 'limit kilometrów na dzień',
        'deposit_amount' => 'kaucja',
        'operational_status' => 'status',
        'description' => 'opis',
    ];

    private const SEARCH_ATTRIBUTES = [
        'pickup' => 'miejsce odbioru',
        'dropoff' => 'miejsce zwrotu',
        'from' => 'data odbioru',
        'to' => 'data zwrotu',
        'vehicle_type' => 'rodzaj pojazdu',
        'license' => 'wymagane prawo jazdy',
        'doors' => 'ilość drzwi',
        'transmission' => 'skrzynia biegów',
        'days' => 'liczba dni',
        'priceFrom' => 'cena wynajmu od',
        'priceTo' => 'cena wynajmu do',
        'fuel' => 'rodzaj paliwa',
        'equipment' => 'wyposażenie',
        'equipment.*' => 'wyposażenie',
    ];

    public function __construct(
        private readonly CarRepositoryInterface $carRepository,
    ) {}

    public function findCarById(int $id): ?array
    {
        return $this->carRepository->findCarById($id);
    }

    /**
     * Dane samochodu do formularza edycji - z wyposażeniem i zdjęciami.
     */
    public function findCarForEdit(int $id): array
    {
        return $this->carRepository->findCarForEdit($id);
    }

    public function allCars(): array
    {
        return $this->carRepository->allCars();
    }

    /**
     * Sprawdza dane i zapisuje samochód ze zdjęciami.
     * Przy błędach rzuca ValidationException - Laravel wraca do formularza z błędami i danymi.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createCar(array $data): array
    {
        $data = $this->normalize($data);

        $validated = $this->validateCar($data);

        $equipmentIds = Arr::pull($validated, 'equipment') ?? [];
        $locationIds = Arr::pull($validated, 'locations') ?? [];
        $prices = $this->pullPrices($validated);
        $paths = $this->storeImages(Arr::pull($validated, 'images') ?? []);

        try {
            return $this->carRepository->createCar($validated, $equipmentIds, $paths, $locationIds, $prices);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($paths);

            throw $exception;
        }
    }

    /**
     * Sprawdza dane i aktualizuje samochód: pola, wyposażenie, nowe i usunięte zdjęcia.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateCar(int $id, array $data): array
    {
        $car = $this->carRepository->findCarForEdit($id);
        $data = $this->normalize($data);

        $validated = $this->validateCar($data, $car);

        $equipmentIds = Arr::pull($validated, 'equipment') ?? [];
        $locationIds = Arr::pull($validated, 'locations') ?? [];
        $prices = $this->pullPrices($validated);
        $removedImageIds = Arr::pull($validated, 'removed_images') ?? [];
        $paths = $this->storeImages(Arr::pull($validated, 'images') ?? []);

        try {
            $removedPaths = $this->carRepository->updateCar($id, $validated, $equipmentIds, $paths, $removedImageIds, $locationIds, $prices);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($paths);

            throw $exception;
        }

        // Pliki kasujemy dopiero po zapisie, żeby błąd bazy nie zostawił auta bez zdjęć.
        Storage::disk('public')->delete($removedPaths);

        return $this->carRepository->findCarForEdit($id);
    }

    /** Szczegóły samochodu w serwisie. */
    public function findPublicCar(int $id): array
    {
        return $this->carRepository->findPublicCar($id);
    }

    /** Losowe samochody na stronę startową. */
    public function randomCars(): array
    {
        return $this->carRepository->randomCars(self::HOME_CARS);
    }

    /**
     * Sprawdza filtry i wyszukuje samochody.
     * Przy błędach przekierowuje na stałą trasę, bo powrót na poprzedni adres mógłby zapętlić przekierowania.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws ValidationException
     */
    public function searchCars(array $filters, string $errorRoute = 'home'): array
    {
        $validator = Validator::make($filters, $this->searchRules($filters), [], self::SEARCH_ATTRIBUTES);

        if ($validator->fails()) {
            throw (new ValidationException($validator))->redirectTo(route($errorRoute));
        }

        return $this->carRepository->searchCars($this->withRentalDays($validator->validated()));
    }

    /**
     * Wyszukiwarka ze strony startowej: miejsca i termin są obowiązkowe.
     *
     * @param  array<string, mixed>  $filters
     *
     * @throws ValidationException
     */
    public function searchForRental(array $filters): array
    {
        $rules = [
            ...$this->searchRules($filters),
            'pickup' => ['required', 'integer', $this->locationRule('is_pickup')],
            'dropoff' => ['required', 'integer', $this->locationRule('is_dropoff')],
            'from' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.LocalDate::today()],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];

        return $this->carRepository->searchCars($this->withRentalDays(
            Validator::make($filters, $rules, [], self::SEARCH_ATTRIBUTES)->validate(),
        ));
    }

    /**
     * Termin ma pierwszeństwo przed wpisaną liczbą dni. Liczenie jak na froncie: różnica dat, co najmniej 1.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function withRentalDays(array $filters): array
    {
        if (isset($filters['from'], $filters['to'])) {
            $filters['days'] = max(1, (int) Carbon::parse($filters['from'])->diffInDays($filters['to']));
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $car  edytowany samochód
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validateCar(array $data, ?array $car = null): array
    {
        return Validator::make($data, $this->carRules($data, $car), [], self::CREATE_ATTRIBUTES)
            ->after($this->checkPriceTiers(...))
            ->validate();
    }

    /** Progi muszą obejmować każdą długość wynajmu: od 1 dnia, bez przerw i z ostatnim bez limitu. */
    private function checkPriceTiers(ValidatorContract $validator): void
    {
        $errors = $validator->errors();

        if (collect($errors->keys())->contains(fn (string $key) => str_starts_with($key, 'prices'))) {
            return;
        }

        $prices = collect($validator->getData()['prices'] ?? [])->sortBy(fn (array $price) => (int) $price['min_days'])->values();
        $expected = 1;

        foreach ($prices as $index => $price) {
            $max = $price['max_days'] ?? null;
            $isLast = $index === $prices->count() - 1;

            if ((int) $price['min_days'] !== $expected) {
                $errors->add('prices', 'Progi cen muszą zaczynać się od 1 dnia i nie mogą mieć przerw ani nakładać się.');

                return;
            }

            if ($max === null || $max === '') {
                if (! $isLast) {
                    $errors->add('prices', 'Tylko ostatni próg może być bez limitu dni.');
                }

                return;
            }

            if ((int) $max < (int) $price['min_days']) {
                $errors->add('prices', 'Wartość "do ilu dni" nie może być mniejsza niż "od ilu dni".');

                return;
            }

            $expected = (int) $max + 1;
        }

        $errors->add('prices', 'Ostatni próg musi być bez limitu dni, np. 4+.');
    }

    /**
     * Zabiera progi z danych auta i ustawia cenę "od X zł" jako najniższą z nich.
     *
     * @param  array<string, mixed>  $validated
     * @return array<int, array<string, mixed>>
     */
    private function pullPrices(array &$validated): array
    {
        $prices = collect(Arr::pull($validated, 'prices'))
            ->map(fn (array $price) => [
                'min_days' => (int) $price['min_days'],
                'max_days' => isset($price['max_days']) ? (int) $price['max_days'] : null,
                'daily_rate' => $price['daily_rate'],
            ])
            ->sortBy('min_days')
            ->values();

        $validated['daily_rate'] = $prices->min('daily_rate');

        return $prices->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data): array
    {
        foreach (['registration_number', 'vin'] as $field) {
            if (is_string($data[$field] ?? null)) {
                $data[$field] = mb_strtoupper(trim($data[$field]));
            }
        }

        return $data;
    }

    /**
     * Nazwy plików generuje Laravel, a rozszerzenie wynika z zawartości pliku.
     *
     * @param  array<int, UploadedFile>  $images
     * @return array<int, string>
     */
    private function storeImages(array $images): array
    {
        return array_map(fn (UploadedFile $image) => $image->store('cars', 'public'), $images);
    }

    /**
     * Przy edycji auto zachowuje swój kolor i wyposażenie, nawet jeśli są już ukryte w słowniku.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $car  edytowany samochód
     * @return array<string, array<int, mixed>>
     */
    private function carRules(array $data, ?array $car = null): array
    {
        $attachedIds = array_column($car['equipment'] ?? [], 'id');
        $attachedLocationIds = array_column($car['locations'] ?? [], 'id');
        $imageIds = array_column($car['images'] ?? [], 'id');
        $removedIds = is_array($data['removed_images'] ?? null) ? $data['removed_images'] : [];
        $keptImages = count(array_diff($imageIds, $removedIds));

        $colorRules = $car !== null && ($data['color'] ?? null) === $car['color']
            ? []
            : [Rule::exists(Color::class, 'name')->where('is_active', true)];

        $maxSeats = ($data['vehicle_type'] ?? null) === VehicleType::Bus->value
            ? self::MAX_BUS_SEATS
            : self::MAX_SEATS;

        return [
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'vehicle_type' => ['required', Rule::enum(VehicleType::class)],
            'required_license' => ['required', Rule::enum(DrivingLicense::class)],
            'registration_number' => ['required', 'string', 'max:20', Rule::unique(Car::class)->ignore($car['id'] ?? null)],
            'vin' => ['required', 'string', 'size:17', Rule::unique(Car::class)->ignore($car['id'] ?? null)],
            'production_year' => ['required', 'integer', 'min:1990', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:50', ...$colorRules],
            'equipment' => ['nullable', 'array'],
            'equipment.*' => [
                'integer',
                'distinct',
                Rule::exists(Equipment::class, 'id')->where(
                    fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $attachedIds),
                ),
            ],
            'locations' => ['nullable', 'array'],
            'locations.*' => [
                'integer',
                'distinct',
                Rule::exists(Location::class, 'id')->where(
                    fn (Builder $query) => $query->where('is_active', true)->orWhereIn('id', $attachedLocationIds),
                ),
            ],
            'images' => ['nullable', 'array', 'max:'.max(self::MAX_IMAGES - $keptImages, 0)],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_IMAGE_KILOBYTES],
            'removed_images' => ['nullable', 'array'],
            'removed_images.*' => ['integer', Rule::in($imageIds)],
            'fuel_type' => ['required', Rule::enum(FuelType::class)],
            'transmission' => ['required', Rule::enum(Transmission::class)],
            'doors' => ['required', 'integer', 'between:2,5'],
            'seats' => ['required', 'integer', 'min:2', 'max:'.$maxSeats],
            'mileage_km' => ['required', 'integer', 'min:0'],
            'prices' => ['required', 'array', 'min:1', 'max:'.self::MAX_PRICE_TIERS],
            'prices.*.min_days' => ['required', 'integer', 'min:1', 'max:'.self::MAX_RENTAL_DAYS],
            'prices.*.max_days' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_RENTAL_DAYS],
            'prices.*.daily_rate' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'km_limit_per_day' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'deposit_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'operational_status' => ['required', Rule::enum(CarStatus::class)],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /** Aktywne miejsce odbioru albo dostawy. */
    private function locationRule(string $type): Exists
    {
        return Rule::exists(Location::class, 'id')->where('is_active', true)->where($type, true);
    }

    /**
     * Wszystkie filtry są opcjonalne - /rezerwacja można otworzyć też bez wyszukiwania.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array<int, mixed>>
     */
    private function searchRules(array $filters): array
    {
        return [
            'pickup' => ['nullable', 'integer', $this->locationRule('is_pickup')],
            'dropoff' => ['nullable', 'integer', $this->locationRule('is_dropoff')],
            'from' => ['nullable', 'required_with:to', 'date_format:Y-m-d', 'after_or_equal:'.LocalDate::today()],
            'to' => ['nullable', 'required_with:from', 'date_format:Y-m-d', 'after_or_equal:from'],
            'days' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_RENTAL_DAYS],
            'vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'license' => ['nullable', Rule::enum(DrivingLicense::class)],
            'doors' => ['nullable', 'integer', 'between:2,5'],
            'priceFrom' => ['nullable', 'numeric', 'min:0'],
            'priceTo' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::when(isset($filters['priceFrom']) && $filters['priceFrom'] !== '', 'gte:priceFrom'),
            ],
            'fuel' => ['nullable', Rule::enum(FuelType::class)],
            'transmission' => ['nullable', Rule::enum(Transmission::class)],
            'equipment' => ['nullable', 'array', 'max:50'],
            'equipment.*' => ['integer', Rule::exists(Equipment::class, 'id')->where('is_active', true)],
        ];
    }
}
