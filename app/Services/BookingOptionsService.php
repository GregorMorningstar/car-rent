<?php

namespace App\Services;

use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Support\LocalDate;
use App\Support\PolishHolidays;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Limity rezerwacji z zakładki Opcje. Domyślne wartości niczego nie ograniczają.
 */
class BookingOptionsService
{
    /** Po zwrocie auto czeka określoną liczbę minut. */
    public const AFTER_TIME = 'after_time';

    /** Po zwrocie auto jest dostępne dopiero następnego dnia. */
    public const NEXT_DAY = 'next_day';

    private const PREFIX = 'booking.';

    private const DEFAULTS = [
        'pickup_min_days' => 0,
        'pickup_max_days' => 365,
        'rental_min_days' => 1,
        'rental_max_days' => 365,
        'pickup_time_from' => '00:00',
        'pickup_time_to' => '23:59',
        'return_time_from' => '00:00',
        'return_time_to' => '23:59',
        'saturday_enabled' => true,
        'saturday_time_from' => '00:00',
        'saturday_time_to' => '23:59',
        'availability_mode' => self::AFTER_TIME,
        'buffer_minutes' => 60,
    ];

    private const ATTRIBUTES = [
        'pickup_min_days' => 'najwcześniejszy odbiór',
        'pickup_max_days' => 'najpóźniejszy odbiór',
        'rental_min_days' => 'najkrótszy wynajem',
        'rental_max_days' => 'najdłuższy wynajem',
        'pickup_time_from' => 'godzina odbioru od',
        'pickup_time_to' => 'godzina odbioru do',
        'return_time_from' => 'godzina zwrotu od',
        'return_time_to' => 'godzina zwrotu do',
        'saturday_enabled' => 'odbiór i zwrot w soboty',
        'saturday_time_from' => 'godzina w sobotę od',
        'saturday_time_to' => 'godzina w sobotę do',
        'availability_mode' => 'dostępność po zwrocie',
        'buffer_minutes' => 'przerwa między wynajmami',
    ];

    public function __construct(
        private readonly SettingRepositoryInterface $settings,
    ) {}

    /**
     * @return array<string, int|string>
     */
    public function all(): array
    {
        $options = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $this->settings->get(self::PREFIX.$key);

            $options[$key] = match (true) {
                $value === null => $default,
                is_int($default) => (int) $value,
                is_bool($default) => $value === '1',
                default => $value,
            };
        }

        return $options;
    }

    /**
     * Limity w postaci gotowej dla formularza rezerwacji.
     *
     * @return array<string, int|string>
     */
    public function limits(): array
    {
        $options = $this->all();

        return [
            ...$options,
            'pickup_earliest' => $this->dayFromToday($options['pickup_min_days']),
            'pickup_latest' => $this->dayFromToday($options['pickup_max_days']),
            'holidays' => PolishHolidays::between(
                LocalDate::today(),
                $this->dayFromToday($options['pickup_max_days'] + $options['rental_max_days']),
            ),
        ];
    }

    /**
     * Święta najbliższego roku do podglądu w zakładce Opcje.
     *
     * @return array<string, string> data (Y-m-d) => nazwa
     */
    public function upcomingHolidays(): array
    {
        return PolishHolidays::between(LocalDate::today(), $this->dayFromToday(365));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function save(array $data): void
    {
        $validated = Validator::make(
            $data,
            [
                'pickup_min_days' => ['required', 'integer', 'min:0', 'max:730'],
                'pickup_max_days' => ['required', 'integer', 'gte:pickup_min_days', 'max:730'],
                'rental_min_days' => ['required', 'integer', 'min:1', 'max:365'],
                'rental_max_days' => ['required', 'integer', 'gte:rental_min_days', 'max:365'],
                'pickup_time_from' => ['required', 'date_format:H:i'],
                'pickup_time_to' => ['required', 'date_format:H:i', 'after:pickup_time_from'],
                'return_time_from' => ['required', 'date_format:H:i'],
                'return_time_to' => ['required', 'date_format:H:i', 'after:return_time_from'],
                'availability_mode' => ['required', Rule::in([self::AFTER_TIME, self::NEXT_DAY])],
                'buffer_minutes' => ['required', 'integer', 'min:0', 'max:1440'],
                'saturday_enabled' => ['required', 'boolean'],
                'saturday_time_from' => ['nullable', 'required_if_accepted:saturday_enabled', 'date_format:H:i'],
                'saturday_time_to' => ['nullable', 'required_if_accepted:saturday_enabled', 'date_format:H:i', 'after:saturday_time_from'],
            ],
            [],
            self::ATTRIBUTES,
        )->validate();

        foreach ($validated as $key => $value) {
            $this->settings->put(self::PREFIX.$key, match (true) {
                $value === null => null,
                $key === 'saturday_enabled' => filter_var($value, FILTER_VALIDATE_BOOL) ? '1' : '0',
                default => (string) $value,
            });
        }
    }

    private function dayFromToday(int $days): string
    {
        return now(LocalDate::timezone())->addDays($days)->toDateString();
    }
}
