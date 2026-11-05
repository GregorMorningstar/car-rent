<?php

namespace App\Repositories;

use App\Models\Setting;
use App\Repositories\Contracts\SettingRepositoryInterface;

class SettingRepository implements SettingRepositoryInterface
{
    public function __construct(
        private readonly Setting $model,
    ) {}

    public function get(string $key): ?string
    {
        return $this->model->newQuery()->find($key)?->value;
    }

    public function put(string $key, ?string $value): void
    {
        $this->model->newQuery()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
