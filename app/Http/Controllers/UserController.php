<?php

namespace App\Http\Controllers;

use App\Services\ChatService;
use App\Services\UserPanelService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Panel użytkownika (rola "user").
 *
 * Pulpit, historia wynajmów, płatności i profil czytają dane z bazy przez UserPanelService.
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserPanelService $userPanelService,
        private readonly ChatService $chatService,
    ) {}

    public function dashboard(Request $request): Response
    {
        return Inertia::render('user/dashboard', $this->userPanelService->dashboard($request->user()->id));
    }

    public function rentals(Request $request): Response
    {
        return Inertia::render(
            'user/rentals',
            $this->userPanelService->rentalHistory($request->user()->id, $request->query()),
        );
    }

    public function messages(Request $request): Response
    {
        return Inertia::render('user/messages', $this->chatService->clientThread($request->user()));
    }

    /** Adresata nie ma w żądaniu - wiadomość zawsze trafia do administracji. */
    public function sendMessage(Request $request): RedirectResponse
    {
        $this->chatService->sendFromClient($request->user(), $request->all());

        return back(fallback: route('user.messages.index'));
    }

    public function payments(Request $request): Response
    {
        return Inertia::render('user/payments', $this->userPanelService->payments($request->user()->id));
    }

    public function profile(Request $request): Response
    {
        return Inertia::render('user/profile', $this->userPanelService->recentRentals($request->user()->id));
    }
}
