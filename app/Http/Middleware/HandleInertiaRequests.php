<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ChatService;
use App\Services\ReservationService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => fn () => $request->session()->get('status'),
            'notifications' => fn () => $this->notifications($request->user()),
        ];
    }

    /**
     * Liczniki panelu: nieprzeczytane wiadomości, a dla administratora też nowe zamówienia.
     *
     * @return array<string, mixed>|null
     */
    private function notifications(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'messages' => app(ChatService::class)->unreadCount($user),
            'reservations' => $user->role === UserRole::Administrator ? app(ReservationService::class)->unseen() : null,
        ];
    }
}
