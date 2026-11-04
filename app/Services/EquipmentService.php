<?php

namespace App\Services;

use App\Enums\EquipmentIcon;
use App\Exceptions\FontAwesomeException;
use App\Repositories\Contracts\EquipmentRepositoryInterface;
use App\Services\Concerns\ChecksDuplicateNames;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class EquipmentService
{
    use ChecksDuplicateNames;

    private const PER_PAGE = 10;

    public function __construct(
        private readonly EquipmentRepositoryInterface $equipmentRepository,
        private readonly FontAwesomeService $fontAwesome,
    ) {}

    public function paginateEquipment(string $search): array
    {
        return $this->equipmentRepository->paginateEquipment($search, self::PER_PAGE);
    }

    public function activeEquipment(): array
    {
        return $this->equipmentRepository->activeEquipment();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createEquipment(array $data): array
    {
        $validated = $this->validate($data);

        return $this->saveUnique(fn () => $this->equipmentRepository->createEquipment($validated));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateEquipment(int $id, array $data): array
    {
        $validated = $this->validate($data, $this->equipmentRepository->findEquipment($id));

        return $this->saveUnique(fn () => $this->equipmentRepository->updateEquipment($id, $validated));
    }

    public function toggleEquipment(int $id): void
    {
        $this->equipmentRepository->toggleEquipment($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $current  edytowana pozycja
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $data, ?array $current = null): array
    {
        $data['name'] = $this->cleanName($data['name'] ?? null);

        $validated = Validator::make(
            $data,
            [
                'name' => [
                    'required',
                    'string',
                    'max:100',
                    $this->uniqueNameRule($this->equipmentRepository->equipmentNames($current['id'] ?? null)),
                ],
                'icon' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/'],
            ],
            [],
            ['name' => 'nazwa wyposażenia', 'icon' => 'ikona'],
        )->validate();

        $icon = $validated['icon'] ?? null;

        return [...$validated, 'icon' => $icon, 'icon_svg' => $this->iconSvg($icon, $current)];
    }

    /**
     * Ikony wbudowane są w kodzie frontu. Kształt pozostałych pobiera serwer - nie ufamy danym z formularza.
     *
     * @param  array<string, mixed>|null  $current
     * @return array{viewBox: string, path: string}|null
     *
     * @throws ValidationException
     */
    private function iconSvg(?string $icon, ?array $current): ?array
    {
        if ($icon === null || EquipmentIcon::tryFrom($icon) !== null) {
            return null;
        }

        if ($icon === ($current['icon'] ?? null)) {
            return $current['icon_svg'];
        }

        try {
            $svg = $this->fontAwesome->find($icon);
        } catch (FontAwesomeException) {
            throw ValidationException::withMessages(['icon' => 'Nie udało się pobrać ikony. Spróbuj ponownie.']);
        }

        return $svg ?? throw ValidationException::withMessages(['icon' => 'Nie ma takiej darmowej ikony.']);
    }

    /** Dwa zapisy naraz mogą ominąć walidację - wtedy duplikat zatrzymuje unikalny indeks w bazie. */
    private function saveUnique(callable $save): array
    {
        try {
            return $save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['name' => 'Taka pozycja już jest na liście.']);
        }
    }
}
