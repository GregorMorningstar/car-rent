<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Services\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rozmowy z klientami. Dostęp: "role:administrator" oraz ConversationPolicy.
 * Walidacja jest w ChatService.
 */
class MessageController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Conversation::class);

        return Inertia::render('admin/messages/index', $this->chatService->staffInbox($request->user(), $request->query()));
    }

    /** Rozmowa z wybranym klientem, także taka, której jeszcze nie było. */
    public function open(Request $request): RedirectResponse
    {
        Gate::authorize('viewAny', Conversation::class);

        return to_route('admin.messages.index', [
            'conversation' => $this->chatService->openConversation($request->user(), $request->all()),
        ]);
    }

    public function store(Request $request, int $conversation): RedirectResponse
    {
        Gate::authorize('viewAny', Conversation::class);

        $this->chatService->sendFromStaff($request->user(), $conversation, $request->all());

        return back(fallback: route('admin.messages.index', ['conversation' => $conversation]));
    }
}
