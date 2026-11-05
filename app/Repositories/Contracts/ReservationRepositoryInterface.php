<?php

namespace App\Repositories\Contracts;

use App\Models\Car;
use App\Models\Reservation;
use Carbon\CarbonInterface;

interface ReservationRepositoryInterface
{
    /**
     * Najnowsze rezerwacje czekające na decyzję, których administracja jeszcze nie widziała.
     *
     * @return array<int, array<string, mixed>>
     */
    public function latestUnseen(int $limit): array;

    public function unseenCount(): int;

    /**
     * Najnowsze rezerwacje usunięte przez klientów, o których administracja jeszcze nie wie.
     *
     * @return array<int, array<string, mixed>>
     */
    public function latestRemoved(int $limit): array;

    public function removedCount(): int;

    public function markAllSeen(): void;

    /** Aktywny samochód z cenami i miejscami albo null. */
    public function findBookableCar(int $carId): ?Car;

    public function carIsAtLocation(int $carId, int $locationId): bool;

    /** Liczba rezerwacji klienta czekających jeszcze na decyzję. */
    public function pendingCountFor(int $userId): int;

    /**
     * Zapisuje rezerwację z blokadą samochodu. Zwraca null, gdy termin jest już zajęty.
     * W zakresie $from - $to nie może być innego wynajmu tego auta: to termin z doliczoną przerwą.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function createIfAvailable(int $carId, array $attributes, CarbonInterface $from, CarbonInterface $to): ?Reservation;

    /**
     * Rezerwacje czekające na decyzję i potwierdzone, które się jeszcze nie skończyły,
     * także usunięte przez klienta - do końca swojego terminu.
     *
     * @return array<int, array<string, mixed>>
     */
    public function current(): array;

    /**
     * Zmienia rezerwację, jeśli nowy termin jest wolny. Zwraca false, gdy jest zajęty.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateIfAvailable(Reservation $reservation, array $attributes, CarbonInterface $from, CarbonInterface $to): bool;

    /** Rezerwacja tego klienta - cudzej zapytanie w ogóle nie znajdzie. */
    public function findOwned(int $id, int $userId): ?Reservation;

    /**
     * Usuwa rezerwację, jeśli pod blokadą wiersza nadal wolno ją usunąć.
     * Z $notifyStaff = false administracja nie dostaje adnotacji.
     */
    public function remove(Reservation $reservation, bool $notifyStaff): bool;

    public function find(int $id): ?Reservation;

    /** Rezerwacja ze wszystkim, czego potrzebuje formularz administracji, także usunięta przez klienta. */
    public function findForStaff(int $id): ?Reservation;

    /** Samochód z cenami, także wycofany albo usunięty - dotyczy aut już przypisanych do rezerwacji. */
    public function findCarWithPrices(int $carId): ?Car;

    /**
     * Rezerwacje do list administracji, od najnowszej, także usunięte przez klienta.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(?int $userId = null, ?int $carId = null): array;

    /** Zapis bez sprawdzania terminu - dla rezerwacji, które nie blokują samochodu. */
    public function saveStaffChanges(Reservation $reservation, array $attributes): void;

    /**
     * Samochody do wyboru: aktywne oraz $currentCarId, z miejscami odbioru.
     *
     * @return array<int, array<string, mixed>>
     */
    public function carOptions(?int $currentCarId = null): array;

    /**
     * Miejsca do wyboru: aktywne oraz wskazane, nawet jeśli wyłączono je po rezerwacji.
     *
     * @param  array<int, int|null>  $currentIds
     * @return array<int, array<string, mixed>>
     */
    public function locationOptions(array $currentIds): array;

    public function activeCarCount(): int;

    /**
     * Wydanie samochodu. Zwraca false, gdy ktoś zrobił to wcześniej.
     *
     * @param  array<string, mixed>  $driver
     */
    public function markPickedUp(Reservation $reservation, array $driver): bool;

    /**
     * Przyjęcie zwrotu i nowy aktualny przebieg auta, pod blokadą wiersza samochodu.
     * Zwraca false, gdy podany przebieg jest mniejszy od aktualnego albo zwrot już przyjęto.
     */
    public function recordReturn(Reservation $reservation, int $mileageKm): bool;

    /**
     * Rezerwacja z danymi klienta i wpłatami do formularza administracji.
     *
     * @return array<string, mixed>
     */
    public function staffDetails(Reservation $reservation): array;

    /** Zwraca false, gdy rezerwacja w międzyczasie przestała czekać na decyzję. */
    public function confirm(Reservation $reservation): bool;

    /** Zwraca false, gdy rezerwacji nie można już anulować. */
    public function cancel(Reservation $reservation): bool;

    /** Oznacza oczekujące po terminie jako wygasłe i zwraca ich liczbę. */
    public function expireOverdue(): int;
}
