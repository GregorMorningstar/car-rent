<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dzień kalendarzowy w wypożyczalni. Zapis jest w UTC, więc tuż po północy czasu lokalnego
 * data UTC to jeszcze poprzedni dzień - do porównań z terminami wynajmu służy ta klasa.
 */
class LocalDate
{
    public static function timezone(): string
    {
        return config('app.local_timezone');
    }

    public static function today(): string
    {
        return now(self::timezone())->toDateString();
    }

    /** Początek dnia kalendarzowego w wypożyczalni, w UTC. */
    public static function startOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->startOfDay()->utc();
    }

    /** Koniec dnia kalendarzowego w wypożyczalni, w UTC. */
    public static function endOfDay(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, self::timezone())->endOfDay()->utc();
    }

    /** Data i godzina wpisane w czasie wypożyczalni, zamienione na UTC. */
    public static function at(string $date, string $time): CarbonImmutable
    {
        return CarbonImmutable::parse("{$date} {$time}", self::timezone())->utc();
    }

    /** Dzień kalendarzowy wypożyczalni, w którym wypada ta chwila. */
    public static function dayOf(CarbonInterface $moment): string
    {
        return CarbonImmutable::instance($moment)->setTimezone(self::timezone())->toDateString();
    }

    /** Długość wynajmu w dniach kalendarzowych wypożyczalni: różnica dat, co najmniej 1. */
    public static function rentalDays(CarbonInterface $from, CarbonInterface $to): int
    {
        $start = CarbonImmutable::instance($from)->setTimezone(self::timezone())->startOfDay();
        $end = CarbonImmutable::instance($to)->setTimezone(self::timezone())->startOfDay();

        return max(1, (int) $start->diffInDays($end));
    }

    /**
     * Doba robocza od podanej chwili, w UTC. Soboty, niedziele i święta się nie liczą:
     * piątek 15:00 daje poniedziałek 15:00, a sobota 10:00 wtorek 00:00.
     */
    public static function businessDayAfter(CarbonInterface $from): CarbonImmutable
    {
        $deadline = CarbonImmutable::instance($from)->setTimezone(self::timezone());

        while (! self::isBusinessDay($deadline)) {
            $deadline = $deadline->addDay()->startOfDay();
        }

        $deadline = $deadline->addDay();

        while (! self::isBusinessDay($deadline)) {
            $deadline = $deadline->addDay();
        }

        return $deadline->utc();
    }

    private static function isBusinessDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && PolishHolidays::name($date->toDateString()) === null;
    }
}
