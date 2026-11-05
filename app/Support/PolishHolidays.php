<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Dni ustawowo wolne od pracy w Polsce. Święta ruchome wynikają z daty Wielkanocy.
 */
class PolishHolidays
{
    private const FIXED = [
        '01-01' => 'Nowy Rok',
        '01-06' => 'Święto Trzech Króli',
        '05-01' => 'Święto Pracy',
        '05-03' => 'Święto Konstytucji 3 Maja',
        '08-15' => 'Wniebowzięcie NMP',
        '11-01' => 'Wszystkich Świętych',
        '11-11' => 'Święto Niepodległości',
        '12-24' => 'Wigilia Bożego Narodzenia',
        '12-25' => 'Boże Narodzenie',
        '12-26' => 'Drugi dzień Bożego Narodzenia',
    ];

    /**
     * Święta danego roku od najwcześniejszego.
     *
     * @return array<string, string> data (Y-m-d) => nazwa
     */
    public static function forYear(int $year): array
    {
        $easter = self::easterSunday($year);
        $holidays = [
            $easter->toDateString() => 'Wielkanoc',
            $easter->addDay()->toDateString() => 'Poniedziałek Wielkanocny',
            $easter->addDays(49)->toDateString() => 'Zielone Świątki',
            $easter->addDays(60)->toDateString() => 'Boże Ciało',
        ];

        foreach (self::FIXED as $day => $name) {
            $holidays["{$year}-{$day}"] = $name;
        }

        ksort($holidays);

        return $holidays;
    }

    /** Nazwa święta albo null, gdy to zwykły dzień. */
    public static function name(string $date): ?string
    {
        return self::forYear((int) substr($date, 0, 4))[substr($date, 0, 10)] ?? null;
    }

    /**
     * Święta w podanym zakresie dat, włącznie z jego końcami.
     *
     * @return array<string, string> data (Y-m-d) => nazwa
     */
    public static function between(string $from, string $to): array
    {
        $holidays = [];

        foreach (range((int) substr($from, 0, 4), (int) substr($to, 0, 4)) as $year) {
            $holidays += self::forYear($year);
        }

        return array_filter($holidays, fn (string $date) => $date >= $from && $date <= $to, ARRAY_FILTER_USE_KEY);
    }

    /** Niedziela Wielkanocna według algorytmu gregoriańskiego (Meeus/Jones/Butcher). */
    private static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day);
    }
}
