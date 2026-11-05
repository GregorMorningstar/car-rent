import { useEcho } from '@laravel/echo-react';
import { router } from '@inertiajs/react';

/**
 * Serwer ogłasza tylko, że coś się zmieniło. Dane pobieramy zwykłym żądaniem,
 * dzięki czemu przechodzą przez te same sprawdzenia uprawnień co otwarcie strony.
 */
export default function useLiveUpdates(channel: string) {
    useEcho(
        channel,
        ['.message.sent', '.reservation.created'],
        () => router.reload({ only: ['notifications', 'messages', 'conversations', 'reservations', 'rentals', 'counts', 'stats', 'current', 'next'] }),
        [],
        'private',
    );
}
