<?php

namespace App\Services;

use App\Repositories\Contracts\ClientRepositoryInterface;
use Throwable;

/**
 * Dane klientów w panelu administratora.
 */
class ClientService
{
    public function __construct(
        private readonly ClientRepositoryInterface $clientRepository,
        private readonly ReservationService $reservationService,
    ) {}

    /**
     * @return array{clients: array<int, array<string, mixed>>, failed: bool}
     */
    public function list(): array
    {
        try {
            return ['clients' => $this->clientRepository->all(), 'failed' => false];
        } catch (Throwable $exception) {
            report($exception);

            return ['clients' => [], 'failed' => true];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function show(int $id): array
    {
        $client = $this->clientRepository->find($id);

        abort_if($client === null, 404);

        return ['client' => $client, ...$this->reservationService->list(userId: $id)];
    }

    /** Błąd odczytu nie może wyłączyć pulpitu. */
    public function count(): int
    {
        try {
            return $this->clientRepository->count();
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }
    }
}
