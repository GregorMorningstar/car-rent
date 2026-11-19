<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dziennik działań administracji. Dostęp: "role:administrator" oraz SettingPolicy.
 */
class AuditLogController extends Controller
{
    public function index(AuditLogService $auditLog): Response
    {
        Gate::authorize('manage', Setting::class);

        return Inertia::render('admin/audit', $auditLog->latest());
    }
}
