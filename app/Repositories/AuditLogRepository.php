<?php

namespace App\Repositories;

use App\Models\AuditLog;
use App\Repositories\Contracts\AuditLogRepositoryInterface;

class AuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(
        private readonly AuditLog $model,
    ) {}

    public function add(array $entry): void
    {
        $this->model->create([...$entry, 'created_at' => now()]);
    }

    public function latest(int $limit): array
    {
        return $this->model->newQuery()
            ->with('user:id,name')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name,
                'action' => $log->action,
                'subject' => $log->subject,
                'ip_address' => $log->ip_address,
                'succeeded' => $log->succeeded,
                'created_at' => $log->created_at->toJSON(),
            ])
            ->all();
    }
}
