<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Logowanie dwuetapowe własnego konta i dziennik działań. Dostęp: "role:administrator" oraz SettingPolicy.
 */
class SecurityController extends Controller
{
    public function __construct(
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('manage', Setting::class);

        return Inertia::render('admin/security/index', [
            ...$this->twoFactor->status($request->user()),
            'recoveryCodes' => $request->session()->get('recovery_codes'),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $this->twoFactor->start($request->user());

        return to_route('admin.security.index');
    }

    public function confirm(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $codes = $this->twoFactor->confirm($request->user(), $request->all());

        return to_route('admin.security.index')->with('recovery_codes', $codes);
    }

    public function disable(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $this->twoFactor->disable($request->user(), $request->all());

        return to_route('admin.security.index')->with('status', 'Logowanie dwuetapowe zostało wyłączone.');
    }

    public function audit(AuditLogService $auditLog): Response
    {
        Gate::authorize('manage', Setting::class);

        return Inertia::render('admin/security/audit', $auditLog->latest());
    }
}
