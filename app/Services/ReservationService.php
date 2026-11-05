<?php

namespace App\Services;

use App\Enums\DrivingLicense;
use App\Enums\ReservationStatus;
use App\Enums\UserRole;
use App\Events\ReservationPlaced;
use App\Models\Car;
use App\Models\Location;
use App\Models\Reservation;
use App\Models\User;
use App\Repositories\Contracts\ReservationRepositoryInterface;
use App\Support\LocalDate;
use App\Support\PolishHolidays;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ReservationService
{
    private const LATEST_UNSEEN = 5;

    /** Limit oczekujących rezerwacji jednego klienta - nie można zablokować całej floty. */
    private const MAX_PENDING = 3;

    private const BOOKING_LOCK_SECONDS = 10;

    private const BOOKING_LOCK_WAIT_SECONDS = 5;

    private const ATTRIBUTES = [
        'from' => 'data odbioru',
        'to' => 'data zwrotu',
        'from_time' => 'godzina odbioru',
        'to_time' => 'godzina zwrotu',
        'pickup' => 'miejsce odbioru',
        'dropoff' => 'miejsce zwrotu',
        'notes' => 'uwagi',
        'driver_name' => 'kierowca',
        'driver_license_number' => 'numer prawa jazdy',
        'driver_license_category' => 'kategoria prawa jazdy',
    ];

    /** Górna granica rozsądnego przebiegu - chroni przed literówką z dodatkowymi cyframi. */
    private const MAX_MILEAGE_KM = 9999999;

    /** Sposoby, w jakie klient może zaakceptować zmianę terminu. */
    public const CLIENT_ACCEPTANCE = ['Telefonicznie', 'Osobiście', 'E-mailem', 'SMS-em'];

    private const STAFF_ATTRIBUTES = self::ATTRIBUTES + [
        'car_id' => 'samochód',
        'status' => 'status',
        'client_acceptance' => 'akceptacja klienta',
    ];

    public function __construct(
        private readonly ReservationRepositoryInterface $reservationRepository,
        private readonly BookingOptionsService $bookingOptions,
        private readonly ChatService $chatService,
    ) {}

    /**
     * Nowe zamówienia do chmurki w panelu. Błąd odczytu nie może wyłączyć całego panelu.
     *
     * @return array{count: int, items: array<int, array<string, mixed>>, removed: array{count: int, items: array<int, array<string, mixed>>}}
     */
    public function unseen(): array
    {
        try {
            return [
                'count' => $this->reservationRepository->unseenCount(),
                'items' => $this->reservationRepository->latestUnseen(self::LATEST_UNSEEN),
                'removed' => [
                    'count' => $this->reservationRepository->removedCount(),
                    'items' => $this->reservationRepository->latestRemoved(self::LATEST_UNSEEN),
                ],
            ];
        } catch (Throwable $exception) {
            report($exception);

            return ['count' => 0, 'items' => [], 'removed' => ['count' => 0, 'items' => []]];
        }
    }

    public function markAllSeen(): void
    {
        $this->reservationRepository->markAllSeen();
    }

    /**
     * Rezerwacja klienta: auto jest wstrzymane na dobę roboczą i czeka na decyzję administracji.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function book(User $client, int $carId, array $data): Reservation
    {
        if ($client->role !== UserRole::User) {
            throw new AuthorizationException('Rezerwować może tylko klient.');
        }

        $car = $this->reservationRepository->findBookableCar($carId);

        abort_if($car === null, 404);

        $terms = $this->validatedTerms($car, $data);
        $options = $this->bookingOptions->all();
        [$blockedFrom, $blockedTo] = $this->blockedRange($terms, $options);

        // Blokada w cache, a nie w transakcji: odczyt liczby rezerwacji w transakcji ustaliłby migawkę
        // bazy jeszcze przed blokadą samochodu i sprawdzenie dostępności widziałoby stare dane.
        try {
            return Cache::lock('booking:client:'.$client->id, self::BOOKING_LOCK_SECONDS)->block(
                self::BOOKING_LOCK_WAIT_SECONDS,
                function () use ($client, $car, $carId, $terms, $options, $blockedFrom, $blockedTo) {
                    if ($this->reservationRepository->pendingCountFor($client->id) >= self::MAX_PENDING) {
                        throw ValidationException::withMessages([
                            'from' => 'Masz już '.self::MAX_PENDING.' rezerwacje czekające na potwierdzenie.',
                        ]);
                    }

                    try {
                        $reservation = $this->reservationRepository->createIfAvailable($carId, [
                            ...$terms,
                            'user_id' => $client->id,
                            'status' => ReservationStatus::Pending,
                            'deposit_amount' => $car->deposit_amount,
                            'confirmation_deadline' => LocalDate::businessDayAfter(now()),
                        ], $blockedFrom, $blockedTo);
                    } catch (Throwable $exception) {
                        report($exception);

                        throw ValidationException::withMessages(['from' => 'Nie udało się zapisać rezerwacji. Spróbuj ponownie.']);
                    }

                    if ($reservation === null) {
                        throw ValidationException::withMessages(['from' => $this->takenMessage($options)]);
                    }

                    return $reservation;
                },
            );
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['from' => 'Inna Twoja rezerwacja jest właśnie zapisywana. Spróbuj ponownie.']);
        }
    }

    /**
     * Dane rezerwacji klienta do formularza, w czasie wypożyczalni.
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function formData(User $client, int $id): array
    {
        $reservation = $this->ownedReservation($client, $id, 'update');

        if (! $reservation->isEditableByClient()) {
            throw new AuthorizationException('Tej rezerwacji nie można już zmienić.');
        }

        $start = $reservation->start_date->setTimezone(LocalDate::timezone());
        $end = $reservation->end_date->setTimezone(LocalDate::timezone());

        return [
            'id' => $reservation->id,
            'car_id' => $reservation->car_id,
            'confirmed' => $reservation->status === ReservationStatus::Confirmed,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'from_time' => $start->format('H:i'),
            'to_time' => $end->format('H:i'),
            'pickup' => $reservation->pickup_location_id,
            'dropoff' => $reservation->dropoff_location_id,
            'notes' => $reservation->notes,
            'driver_name' => $reservation->driver_name,
            'driver_license_number' => $reservation->driver_license_number,
            'driver_license_category' => $reservation->driver_license_category?->value,
        ];
    }

    /**
     * Zmiana rezerwacji przez właściciela. Obowiązują te same zasady co przy rezerwacji,
     * a cenę liczy serwer od nowa. Potwierdzona rezerwacja wraca do akceptacji administracji.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateByClient(User $client, int $id, array $data): void
    {
        $reservation = $this->ownedReservation($client, $id, 'update');

        if (! $reservation->isEditableByClient()) {
            throw ValidationException::withMessages(['from' => 'Tej rezerwacji nie można już zmienić.']);
        }

        $car = $this->reservationRepository->findBookableCar($reservation->car_id);

        abort_if($car === null, 404);

        $terms = $this->validatedTerms($car, $data);
        $options = $this->bookingOptions->all();
        [$blockedFrom, $blockedTo] = $this->blockedRange($terms, $options);

        try {
            $updated = $this->reservationRepository->updateIfAvailable($reservation, [
                ...$terms,
                'status' => ReservationStatus::Pending,
                'confirmation_deadline' => LocalDate::businessDayAfter(now()),
                'confirmed_at' => null,
                'seen_by_admin_at' => null,
            ], $blockedFrom, $blockedTo);
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['from' => 'Nie udało się zapisać zmian. Spróbuj ponownie.']);
        }

        if (! $updated) {
            throw ValidationException::withMessages(['from' => $this->takenMessage($options)]);
        }

        $this->announce();
    }

    /**
     * Usunięcie rezerwacji przez właściciela. Rekord zostaje w bazie, a samochód wraca do oferty.
     * Administracja dostaje adnotację, jeśli rezerwacja jeszcze wstrzymywała samochód.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function removeByClient(User $client, int $id): void
    {
        $reservation = $this->ownedReservation($client, $id, 'delete');

        if (! $reservation->isRemovableByClient()) {
            throw ValidationException::withMessages(['reservation' => 'Tej rezerwacji nie można usunąć: wynajem trwa, zakończył się albo ma nierozliczoną zaliczkę lub kaucję.']);
        }

        if (! $this->reservationRepository->remove($reservation, $reservation->isEditableByClient())) {
            throw ValidationException::withMessages(['reservation' => 'Tej rezerwacji nie można już usunąć.']);
        }

        $this->announce();
    }

    /**
     * Rezerwacje do obsługi w panelu administratora.
     *
     * @return array{reservations: array<int, array<string, mixed>>, failed: bool}
     */
    public function current(): array
    {
        try {
            return ['reservations' => $this->reservationRepository->current(), 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            return ['reservations' => [], 'failed' => true];
        }
    }

    /**
     * Rezerwacje do list administracji, opcjonalnie jednego klienta albo samochodu.
     *
     * @return array{reservations: array<int, array<string, mixed>>, failed: bool}
     */
    public function list(?int $userId = null, ?int $carId = null): array
    {
        try {
            return ['reservations' => $this->reservationRepository->rows($userId, $carId), 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            return ['reservations' => [], 'failed' => true];
        }
    }

    /**
     * Zrealizowane: potwierdzone, których termin zwrotu już minął.
     *
     * @return array{reservations: array<int, array<string, mixed>>, failed: bool}
     */
    public function completed(): array
    {
        $list = $this->list();

        return [
            ...$list,
            'reservations' => array_values(array_filter($list['reservations'], fn (array $row) => $row['state'] === 'completed')),
        ];
    }

    /**
     * Dane pulpitu: rezerwacje czekające na decyzję i stan wypożyczalni.
     *
     * @return array<string, mixed>
     */
    public function dashboard(): array
    {
        $list = $this->list();
        $rows = collect($list['reservations']);

        try {
            $cars = $this->reservationRepository->activeCarCount();
        } catch (Throwable $exception) {
            report($exception);
            $cars = 0;
        }

        return [
            'reservations' => $rows->where('state', ReservationStatus::Pending->value)->values()->all(),
            'failed' => $list['failed'],
            'stats' => [
                'waiting' => $rows->where('state', ReservationStatus::Pending->value)->count(),
                'ongoing' => $rows
                    ->where('state', ReservationStatus::Confirmed->value)
                    ->filter(fn (array $row) => $row['start_date'] <= now()->toJSON())
                    ->count(),
                'cars' => $cars,
            ],
        ];
    }

    /**
     * Rezerwacja do formularza administracji, w czasie wypożyczalni.
     *
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function staffFormData(User $staff, int $id): array
    {
        $this->assertStaff($staff);

        $reservation = $this->reservationRepository->findForStaff($id);

        abort_if($reservation === null, 404);

        $start = $reservation->start_date->setTimezone(LocalDate::timezone());
        $end = $reservation->end_date->setTimezone(LocalDate::timezone());

        return [
            'reservation' => $this->reservationRepository->staffDetails($reservation),
            'form' => [
                'car_id' => $reservation->car_id,
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
                'from_time' => $start->format('H:i'),
                'to_time' => $end->format('H:i'),
                'pickup' => $reservation->pickup_location_id,
                'dropoff' => $reservation->dropoff_location_id,
                'status' => $reservation->currentStatus()->value,
                'notes' => $reservation->notes,
            ],
            'cars' => $this->reservationRepository->carOptions($reservation->car_id),
            'locations' => $this->reservationRepository->locationOptions([$reservation->pickup_location_id, $reservation->dropoff_location_id]),
            'paymentMethods' => ReservationPaymentService::METHODS,
            'acceptanceMethods' => self::CLIENT_ACCEPTANCE,
            'licenses' => DrivingLicense::values(),
        ];
    }

    /**
     * Zmiana rezerwacji przez administrację. Godziny otwarcia i limity z Opcji jej nie obowiązują,
     * ale samochód nadal nie może być wynajęty dwa razy. Cenę liczy serwer tylko po zmianie auta lub terminu,
     * żeby dopisanie notatki nie zmieniło ustalonej kwoty.
     *
     * Auto, miejsca i termin ustala klient, więc administracja zmienia je tylko po jego akceptacji,
     * a zapis zostawia ślad: kto ją wpisał, kiedy i w jaki sposób.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function updateByStaff(User $staff, int $id, array $data): void
    {
        $this->assertStaff($staff);

        $reservation = $this->findOrFail($id);
        $validated = Validator::make($data, $this->staffRules(), [], self::STAFF_ATTRIBUTES)->validate();

        $carChanged = (int) $validated['car_id'] !== $reservation->car_id;
        $car = $carChanged
            ? $this->reservationRepository->findBookableCar((int) $validated['car_id'])
            : $this->reservationRepository->findCarWithPrices($reservation->car_id);

        if ($car === null) {
            throw ValidationException::withMessages(['car_id' => 'Ten samochód nie jest dostępny.']);
        }

        $pickupChanged = (int) $validated['pickup'] !== $reservation->pickup_location_id;

        if (($carChanged || $pickupChanged) && ! $this->reservationRepository->carIsAtLocation($car->id, (int) $validated['pickup'])) {
            throw ValidationException::withMessages(['pickup' => 'Tego samochodu nie ma w wybranym miejscu odbioru.']);
        }

        $start = LocalDate::at($validated['from'], $validated['from_time']);
        $end = LocalDate::at($validated['to'], $validated['to_time']);

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['to_time' => 'Zwrot musi być później niż odbiór.']);
        }

        $status = ReservationStatus::from($validated['status']);
        $statusChanged = $status !== $reservation->currentStatus();

        if ($reservation->picked_up_at !== null && ($carChanged || $statusChanged)) {
            throw ValidationException::withMessages(['status' => 'Samochód został już wydany: nie można zmienić auta ani statusu.']);
        }

        // Wygaśnięcie wynika z minięcia terminu decyzji, więc nie ustawia się go ręcznie.
        if ($statusChanged && $status === ReservationStatus::Expired) {
            throw ValidationException::withMessages(['status' => 'Rezerwacja wygasa sama. Żeby ją zakończyć, ustaw status Anulowana.']);
        }
        $periodChanged = ! $start->equalTo($reservation->start_date) || ! $end->equalTo($reservation->end_date);
        $termsChanged = $carChanged || $periodChanged;
        $dropoffChanged = (int) $validated['dropoff'] !== $reservation->dropoff_location_id;
        $agreementChanged = $termsChanged || $pickupChanged || $dropoffChanged;

        if ($agreementChanged && empty($validated['client_acceptance'])) {
            throw ValidationException::withMessages(['client_acceptance' => 'Zmiana auta, miejsca, daty lub godziny wymaga akceptacji klienta.']);
        }

        $attributes = [
            'car_id' => $car->id,
            'pickup_location_id' => (int) $validated['pickup'],
            'dropoff_location_id' => (int) $validated['dropoff'],
            'start_date' => $start,
            'end_date' => $end,
            'notes' => $validated['notes'] ?? null,
            ...($statusChanged ? $this->statusAttributes($status) : []),
            ...($agreementChanged ? [
                'term_accepted_via' => $validated['client_acceptance'],
                'term_accepted_at' => now(),
                'term_accepted_by' => $staff->id,
            ] : []),
        ];

        if ($termsChanged) {
            $days = LocalDate::rentalDays($start, $end);
            $rate = $this->rateFor($car, $days);

            $attributes += ['daily_rate' => $rate, 'total_price' => $rate * $days];
        }

        if ($carChanged) {
            $attributes['deposit_amount'] = $car->deposit_amount;
        }

        $blocksCar = in_array($status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true);

        $this->saveStaffChanges($reservation, $attributes, $blocksCar && ($termsChanged || $statusChanged));

        $this->notifyAboutStaffChanges($staff, $id, $statusChanged ? $status : null, $agreementChanged && $blocksCar);
        $this->announce();
    }

    /**
     * Wydanie samochodu: kierowcę sprawdza się przy odbiorze, więc numer prawa jazdy jest tu obowiązkowy.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handOver(User $staff, int $id, array $data): void
    {
        $this->assertStaff($staff);

        $reservation = $this->findOrFail($id);

        if ($reservation->status !== ReservationStatus::Confirmed) {
            throw ValidationException::withMessages(['reservation' => 'Wydać można tylko potwierdzoną rezerwację.']);
        }

        $validated = Validator::make($data, [
            'driver_name' => ['required', 'string', 'max:150'],
            'driver_license_number' => ['required', 'string', 'max:30'],
            'driver_license_category' => ['required', Rule::enum(DrivingLicense::class)],
        ], [], self::ATTRIBUTES)->validate();

        $car = $this->reservationRepository->findCarWithPrices($reservation->car_id);

        abort_if($car === null, 404);

        $this->assertDriverLicense($car, $validated['driver_license_category']);

        $handedOver = $this->reservationRepository->markPickedUp($reservation, [
            'driver_name' => trim($validated['driver_name']),
            'driver_license_number' => trim($validated['driver_license_number']),
            'driver_license_category' => $validated['driver_license_category'],
        ]);

        if (! $handedOver) {
            throw ValidationException::withMessages(['reservation' => 'Ten samochód został już wydany.']);
        }
    }

    /**
     * Przyjęcie zwrotu: podany przebieg staje się aktualnym przebiegiem auta i nie może być mniejszy.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function takeBack(User $staff, int $id, array $data): void
    {
        $this->assertStaff($staff);

        $reservation = $this->findOrFail($id);

        if (! $reservation->isOut()) {
            throw ValidationException::withMessages(['reservation' => 'Zwrot można przyjąć tylko dla wydanego samochodu.']);
        }

        $car = $this->reservationRepository->findCarWithPrices($reservation->car_id);

        abort_if($car === null, 404);

        $message = "Przebieg nie może być mniejszy niż aktualny: {$car->mileage_km} km.";

        $validated = Validator::make(
            $data,
            ['mileage_km' => ['required', 'integer', 'min:'.$car->mileage_km, 'max:'.self::MAX_MILEAGE_KM]],
            ['mileage_km.min' => $message],
            ['mileage_km' => 'przebieg'],
        )->validate();

        if (! $this->reservationRepository->recordReturn($reservation, (int) $validated['mileage_km'])) {
            throw ValidationException::withMessages(['mileage_km' => 'Nie udało się przyjąć zwrotu: przebieg auta właśnie się zmienił. Odśwież stronę.']);
        }
    }

    /**
     * Potwierdzenie rezerwacji. Klient dostaje o nim wiadomość na czacie.
     *
     * @throws ValidationException
     */
    public function confirm(int $id, User $staff): void
    {
        $reservation = $this->findOrFail($id);

        if ($reservation->status !== ReservationStatus::Pending || $reservation->confirmation_deadline->isPast()) {
            throw ValidationException::withMessages(['reservation' => 'Tej rezerwacji nie można już potwierdzić.']);
        }

        if (! $this->reservationRepository->confirm($reservation)) {
            throw ValidationException::withMessages(['reservation' => 'Tej rezerwacji nie można już potwierdzić.']);
        }

        $this->chatService->notifyClient($staff, $reservation->user_id, $this->confirmationMessage($reservation));
    }

    /**
     * @throws ValidationException
     */
    public function reject(int $id): void
    {
        $reservation = $this->findOrFail($id);

        if (! in_array($reservation->status, [ReservationStatus::Pending, ReservationStatus::Confirmed], true)) {
            throw ValidationException::withMessages(['reservation' => 'Tej rezerwacji nie można już odrzucić.']);
        }

        if ($reservation->picked_up_at !== null) {
            throw ValidationException::withMessages(['reservation' => 'Samochód został już wydany: tej rezerwacji nie można anulować.']);
        }

        if (! $this->reservationRepository->cancel($reservation)) {
            throw ValidationException::withMessages(['reservation' => 'Tej rezerwacji nie można już odrzucić.']);
        }
    }

    /** Porządkuje statusy. Samochód jest wolny już od chwili minięcia terminu, także bez tej operacji. */
    public function expireOverdue(): int
    {
        return $this->reservationRepository->expireOverdue();
    }

    /**
     * Druga kontrola obok trasy i kontrolera: formularz zmienia termin, cenę i status.
     *
     * @throws AuthorizationException
     */
    private function assertStaff(User $staff): void
    {
        if ($staff->cannot('manage', Reservation::class)) {
            throw new AuthorizationException('Rezerwacje zmienia tylko administrator.');
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function staffRules(): array
    {
        return [
            'car_id' => ['required', 'integer'],
            'status' => ['required', Rule::enum(ReservationStatus::class)],
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'from_time' => ['required', 'date_format:H:i'],
            'to_time' => ['required', 'date_format:H:i'],
            'pickup' => ['required', 'integer', Rule::exists(Location::class, 'id')],
            'dropoff' => ['required', 'integer', Rule::exists(Location::class, 'id')],
            'notes' => ['nullable', 'string', 'max:500'],
            'client_acceptance' => ['nullable', Rule::in(self::CLIENT_ACCEPTANCE)],
        ];
    }

    /**
     * Pola związane ze zmianą statusu. Oczekująca dostaje nowy termin decyzji, bo stary mógł minąć.
     *
     * @return array<string, mixed>
     */
    private function statusAttributes(ReservationStatus $status): array
    {
        return [
            'status' => $status,
            'seen_by_admin_at' => now(),
            ...match ($status) {
                ReservationStatus::Pending => ['confirmation_deadline' => LocalDate::businessDayAfter(now()), 'confirmed_at' => null, 'cancelled_at' => null],
                ReservationStatus::Confirmed => ['confirmed_at' => now(), 'cancelled_at' => null],
                ReservationStatus::Cancelled => ['cancelled_at' => now()],
                ReservationStatus::Expired => [],
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws ValidationException
     */
    private function saveStaffChanges(Reservation $reservation, array $attributes, bool $checkAvailability): void
    {
        $options = $this->bookingOptions->all();

        try {
            $saved = true;

            if ($checkAvailability) {
                [$blockedFrom, $blockedTo] = $this->blockedRange($attributes, $options);
                $saved = $this->reservationRepository->updateIfAvailable($reservation, $attributes, $blockedFrom, $blockedTo);
            } else {
                $this->reservationRepository->saveStaffChanges($reservation, $attributes);
            }
        } catch (Throwable $exception) {
            report($exception);

            throw ValidationException::withMessages(['from' => 'Nie udało się zapisać zmian. Spróbuj ponownie.']);
        }

        if (! $saved) {
            throw ValidationException::withMessages(['from' => $this->takenMessage($options)]);
        }
    }

    /** Klient dostaje wiadomość, gdy administracja potwierdzi rezerwację albo zmieni auto, miejsce lub termin. */
    private function notifyAboutStaffChanges(User $staff, int $id, ?ReservationStatus $newStatus, bool $agreementChanged): void
    {
        $reservation = $this->findOrFail($id);

        if ($newStatus === ReservationStatus::Confirmed) {
            $this->chatService->notifyClient($staff, $reservation->user_id, $this->confirmationMessage($reservation));
        } elseif ($agreementChanged) {
            $this->chatService->notifyClient($staff, $reservation->user_id, $this->changeMessage($reservation));
        }
    }

    /**
     * Cudza rezerwacja daje 404, a nie 403, żeby nie zdradzać, że taki numer istnieje.
     *
     * @throws AuthorizationException
     */
    private function ownedReservation(User $client, int $id, string $ability): Reservation
    {
        $reservation = $this->reservationRepository->findOwned($id, $client->id);

        abort_if($reservation === null, 404);

        if ($client->cannot($ability, $reservation)) {
            throw new AuthorizationException('To nie jest Twoja rezerwacja.');
        }

        return $reservation;
    }

    /**
     * Sprawdza termin i miejsca, a potem liczy cenę z progów samochodu.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validatedTerms(Car $car, array $data): array
    {
        $options = $this->bookingOptions->limits();
        $validated = Validator::make($data, $this->bookingRules($car->id, $options), [], self::ATTRIBUTES)->validate();

        $this->assertDriverLicense($car, $validated['driver_license_category']);

        $start = LocalDate::at($validated['from'], $validated['from_time']);
        $end = LocalDate::at($validated['to'], $validated['to_time']);

        if ($start->isPast()) {
            throw ValidationException::withMessages(['from_time' => 'Ta godzina odbioru już minęła.']);
        }

        if ($end->lessThanOrEqualTo($start)) {
            throw ValidationException::withMessages(['to_time' => 'Zwrot musi być później niż odbiór.']);
        }

        $days = LocalDate::rentalDays($start, $end);
        $rate = $this->rateFor($car, $days);

        return [
            'pickup_location_id' => $validated['pickup'],
            'dropoff_location_id' => $validated['dropoff'],
            'start_date' => $start,
            'end_date' => $end,
            'daily_rate' => $rate,
            'total_price' => $rate * $days,
            'notes' => $validated['notes'] ?? null,
            'driver_name' => trim($validated['driver_name']),
            'driver_license_number' => $validated['driver_license_number'] ?? null,
            'driver_license_category' => $validated['driver_license_category'],
        ];
    }

    /**
     * @throws ValidationException
     */
    private function assertDriverLicense(Car $car, string $category): void
    {
        if (! DrivingLicense::from($category)->covers($car->required_license)) {
            throw ValidationException::withMessages([
                'driver_license_category' => 'Do tego samochodu wymagana jest kategoria '.$car->required_license->label().'.',
            ]);
        }
    }

    /**
     * Termin razem z przerwą po zwrocie. Działa w obie strony: nowy wynajem nie może też
     * zaczynać się za wcześnie po poprzednim ani kończyć tuż przed następnym.
     *
     * @param  array<string, mixed>  $terms
     * @param  array<string, mixed>  $options
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function blockedRange(array $terms, array $options): array
    {
        if ($options['availability_mode'] === BookingOptionsService::NEXT_DAY) {
            return [
                // Sekunda wcześniej, bo końce zakresu mogą się stykać, a zwrot o północy to już ten dzień.
                LocalDate::startOfDay(LocalDate::dayOf($terms['start_date']))->subSecond(),
                LocalDate::endOfDay(LocalDate::dayOf($terms['end_date'])),
            ];
        }

        return [
            $terms['start_date']->subMinutes($options['buffer_minutes']),
            $terms['end_date']->addMinutes($options['buffer_minutes']),
        ];
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function takenMessage(array $options): string
    {
        if ($options['availability_mode'] === BookingOptionsService::NEXT_DAY) {
            return 'Ten samochód jest zajęty w wybranym terminie. Po zwrocie auto jest dostępne dopiero następnego dnia.';
        }

        return $options['buffer_minutes'] > 0
            ? "Ten samochód jest zajęty w wybranym terminie. Między wynajmami obowiązuje przerwa {$options['buffer_minutes']} min."
            : 'Ten samochód jest już zajęty w wybranym terminie.';
    }

    /** Odświeża panel administracji. Wyłączony serwer Reverb nie może zablokować zmiany. */
    private function announce(): void
    {
        try {
            ReservationPlaced::dispatch();
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function confirmationMessage(Reservation $reservation): string
    {
        return $this->reservationMessage('Twoja rezerwacja %s została potwierdzona.', $reservation);
    }

    private function changeMessage(Reservation $reservation): string
    {
        return sprintf(
            '%s Miejsce odbioru: %s, miejsce zwrotu: %s.',
            $this->reservationMessage('Administracja zmieniła Twoją rezerwację %s.', $reservation),
            $reservation->pickupLocation?->name ?? '-',
            $reservation->dropoffLocation?->name ?? '-',
        );
    }

    private function reservationMessage(string $intro, Reservation $reservation): string
    {
        $timezone = LocalDate::timezone();
        $car = trim(($reservation->car?->brand ?? '').' '.($reservation->car?->model ?? ''));

        return sprintf(
            $intro.' Samochód: %s. Odbiór: %s, zwrot: %s.',
            sprintf('WYN/%d/%04d', $reservation->created_at?->setTimezone($timezone)->year ?? now()->year, $reservation->id),
            $car,
            $reservation->start_date->setTimezone($timezone)->format('d.m.Y H:i'),
            $reservation->end_date->setTimezone($timezone)->format('d.m.Y H:i'),
        );
    }

    private function findOrFail(int $id): Reservation
    {
        $reservation = $this->reservationRepository->find($id);

        abort_if($reservation === null, 404);

        return $reservation;
    }

    /**
     * Cena za dobę dla tej długości wynajmu.
     *
     * @throws ValidationException
     */
    private function rateFor(Car $car, int $days): string
    {
        $tier = $car->prices->first(
            fn ($price) => $price->min_days <= $days && ($price->max_days === null || $price->max_days >= $days),
        );

        if ($tier === null) {
            throw ValidationException::withMessages(['from' => 'Ten samochód nie ma ceny dla tej długości wynajmu.']);
        }

        return $tier->daily_rate;
    }

    /**
     * @param  array<string, int|string>  $options  limity z zakładki Opcje
     * @return array<string, array<int, mixed>>
     */
    private function bookingRules(int $carId, array $options): array
    {
        $earliest = max($options['pickup_earliest'], LocalDate::today());

        return [
            'from' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$earliest,
                'before_or_equal:'.$options['pickup_latest'],
                $this->openDayRule($options, 'Odbiór'),
            ],
            'to' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:from',
                $this->rentalLengthRule($options),
                $this->openDayRule($options, 'Zwrot'),
            ],
            'from_time' => [
                'required',
                'date_format:H:i',
                $this->timeRangeRule($options, 'from', $options['pickup_time_from'], $options['pickup_time_to'], 'Odbiór'),
            ],
            'to_time' => [
                'required',
                'date_format:H:i',
                $this->timeRangeRule($options, 'to', $options['return_time_from'], $options['return_time_to'], 'Zwrot'),
            ],
            'pickup' => [
                'required',
                'integer',
                Rule::exists(Location::class, 'id')->where('is_active', true)->where('is_pickup', true),
                $this->carLocationRule($carId),
            ],
            'dropoff' => [
                'required',
                'integer',
                Rule::exists(Location::class, 'id')->where('is_active', true)->where('is_dropoff', true),
            ],
            'notes' => ['nullable', 'string', 'max:500'],
            'driver_name' => ['required', 'string', 'max:150'],
            'driver_license_number' => ['nullable', 'string', 'max:30'],
            'driver_license_category' => ['required', Rule::enum(DrivingLicense::class)],
        ];
    }

    /**
     * @param  array<string, int|string>  $options
     */
    private function rentalLengthRule(array $options): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail, $validator) use ($options) {
            $from = $validator->getData()['from'] ?? null;

            if (! is_string($from) || ! is_string($value) || strtotime($from) === false || strtotime($value) === false) {
                return;
            }

            $days = max(1, (int) Carbon::parse($from)->diffInDays($value));

            if ($days < $options['rental_min_days']) {
                $fail('Najkrótszy wynajem to '.$options['rental_min_days'].' dni.');
            }

            if ($days > $options['rental_max_days']) {
                $fail('Wynajem może trwać najwyżej '.$options['rental_max_days'].' dni.');
            }
        };
    }

    /**
     * W święta odbiór i zwrot są niemożliwe, a w soboty zależą od zakładki Opcje.
     *
     * @param  array<string, mixed>  $options
     */
    private function openDayRule(array $options, string $label): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($options, $label) {
            if (! is_string($value) || strtotime($value) === false) {
                return;
            }

            $holiday = PolishHolidays::name(Carbon::parse($value)->toDateString());

            if ($holiday !== null) {
                $fail("{$label} nie jest możliwy w święto: {$holiday}.");
            } elseif (Carbon::parse($value)->isSaturday() && ! $options['saturday_enabled']) {
                $fail("{$label} nie jest możliwy w soboty.");
            }
        };
    }

    /**
     * Godziny zwykłego dnia albo soboty, zależnie od daty z pola $dateField.
     *
     * @param  array<string, mixed>  $options
     */
    private function timeRangeRule(array $options, string $dateField, string $from, string $to, string $label): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail, $validator) use ($options, $dateField, $from, $to, $label) {
            $date = $validator->getData()[$dateField] ?? null;

            if (is_string($date) && strtotime($date) !== false && Carbon::parse($date)->isSaturday()) {
                $from = $options['saturday_time_from'];
                $to = $options['saturday_time_to'];
                $label .= ' w sobotę';
            }

            if (is_string($value) && ($value < $from || $value > $to)) {
                $fail("{$label} jest możliwy od {$from} do {$to}.");
            }
        };
    }

    private function carLocationRule(int $carId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($carId) {
            if (is_numeric($value) && ! $this->reservationRepository->carIsAtLocation($carId, (int) $value)) {
                $fail('Tego samochodu nie ma w wybranym miejscu odbioru.');
            }
        };
    }
}
