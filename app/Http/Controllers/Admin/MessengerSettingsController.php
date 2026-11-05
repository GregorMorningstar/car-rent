<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\MessengerException;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\MessengerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dane dostępowe Messengera. Dostęp: "role:administrator" oraz SettingPolicy.
 */
class MessengerSettingsController extends Controller
{
    public function __construct(
        private readonly MessengerService $messengerService,
    ) {}

    public function edit(): Response
    {
        Gate::authorize('manage', Setting::class);

        return Inertia::render('admin/settings/messenger', [
            'settings' => $this->messengerService->settings(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        $this->messengerService->saveSettings($request->all());

        return to_route('admin.settings.messenger.edit')->with('status', 'Zapisano ustawienia.');
    }

    public function test(): RedirectResponse
    {
        Gate::authorize('manage', Setting::class);

        try {
            $this->messengerService->sendTest();
        } catch (MessengerException $exception) {
            report($exception);

            return back()->withErrors(['test' => $exception->getMessage()]);
        }

        return back()->with('status', 'Wiadomość testowa została wysłana.');
    }
}
