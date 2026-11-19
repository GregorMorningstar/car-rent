import { router } from '@inertiajs/react';
import cookiesRoutes from '@/routes/cookies';

const OPEN_EVENT = 'cookie-settings:open';

/** Wybór z zaszyfrowanego ciasteczka - serwer odczytuje go i egzekwuje sam. */
export type CookieChoices = { functional: boolean; analytics: boolean; marketing: boolean };

export const NO_CHOICES: CookieChoices = { functional: false, analytics: false, marketing: false };

export const ALL_CHOICES: CookieChoices = { functional: true, analytics: true, marketing: true };

/** Wybór zostaje na tym urządzeniu przez rok, także po odrzuceniu. */
export const saveConsent = (choices: CookieChoices) => router.post(cookiesRoutes.consent().url, choices, { preserveScroll: true, preserveState: true });

/** Zgodę można zmienić w każdej chwili, linkiem w stopce. */
export const openCookieSettings = () => window.dispatchEvent(new Event(OPEN_EVENT));

export function onOpenCookieSettings(listener: () => void) {
    window.addEventListener(OPEN_EVENT, listener);

    return () => window.removeEventListener(OPEN_EVENT, listener);
}
