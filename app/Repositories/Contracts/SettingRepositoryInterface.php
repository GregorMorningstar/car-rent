<?php

namespace App\Repositories\Contracts;

interface SettingRepositoryInterface
{
    public function get(string $key): ?string;

    public function put(string $key, ?string $value): void;
}
