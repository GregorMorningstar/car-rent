<?php

namespace App\Services;

use App\Models\Color;
use App\Repositories\Contracts\ColorRepositoryInterface;
use App\Services\Concerns\ChecksDuplicateNames;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ColorService
{
    use ChecksDuplicateNames;

    private const PER_PAGE = 10;

    public function __construct(
        private readonly ColorRepositoryInterface $colorRepository,
    ) {}

    public function paginateColors(string $search): array
    {
        return $this->colorRepository->paginateColors($search, self::PER_PAGE);
    }

    public function activeColors(): array
    {
        return $this->colorRepository->activeColors();
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function createColor(array $data): array
    {
        $validated = $this->validate($data);

        return $this->saveUnique(fn () => $this->colorRepository->createColor($validated));
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateColor(int $id, array $data): array
    {
        $validated = $this->validate($data, $id);

        return $this->saveUnique(fn () => $this->colorRepository->updateColor($id, $validated));
    }

    public function toggleColor(int $id): void
    {
        $this->colorRepository->toggleColor($id);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    private function validate(array $data, ?int $ignoreId = null): array
    {
        $data['name'] = $this->cleanName($data['name'] ?? null);

        if (is_string($data['hex'] ?? null)) {
            $data['hex'] = strtolower($data['hex']);
        }

        return Validator::make(
            $data,
            [
                'name' => ['required', 'string', 'max:50', $this->uniqueNameRule($this->colorRepository->colorNames($ignoreId))],
                'hex' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/', Rule::unique(Color::class)->ignore($ignoreId)],
            ],
            ['hex.unique' => 'Ten wzór ma już inny kolor na liście.'],
            ['name' => 'nazwa koloru', 'hex' => 'wzór koloru'],
        )->validate();
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
