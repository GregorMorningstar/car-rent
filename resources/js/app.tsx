import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import CookieBar from '@/components/cookie-bar';

// Bez adresu ustawionego przy budowaniu czat łączy się z domeną strony, więc ta sama paczka działa na każdym serwerze.
const reverbScheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '');
const reverbPort = Number(import.meta.env.VITE_REVERB_PORT) || (reverbScheme === 'https' ? 443 : 80);

configureEcho({
    broadcaster: 'reverb',
    wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
    wsPort: reverbPort,
    wssPort: reverbPort,
    forceTLS: reverbScheme === 'https',
    enabledTransports: ['ws', 'wss'],
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    // Poza układami, żeby pasek był też na stronach logowania i rejestracji.
    withApp: (app, { page }) => (
        <>
            {app}
            <CookieBar initial={page.props.cookieConsent} />
        </>
    ),
});
