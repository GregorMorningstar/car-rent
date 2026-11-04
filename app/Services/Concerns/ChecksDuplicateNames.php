<?php

namespace App\Services\Concerns;

use Closure;
use Illuminate\Support\Str;

trait ChecksDuplicateNames
{
    /** "  Czujniki   parkowania " => "Czujniki parkowania" */
    private function cleanName(mixed $name): mixed
    {
        return is_string($name) ? preg_replace('/\s+/u', ' ', trim($name)) : $name;
    }

    /**
     * Reguła walidacji: te same nazwy to także różnica w wielkości liter, spacjach i ogonkach.
     *
     * @param  array<int, string>  $names  pozostałe nazwy w słowniku
     */
    private function uniqueNameRule(array $names): Closure
    {
        $taken = array_map($this->nameKey(...), $names);

        return function (string $attribute, mixed $value, Closure $fail) use ($taken) {
            if (is_string($value) && in_array($this->nameKey($value), $taken, true)) {
                $fail('Taka pozycja już jest na liście.');
            }
        };
    }

    private function nameKey(string $name): string
    {
        return mb_strtolower(Str::ascii($name));
    }
}
