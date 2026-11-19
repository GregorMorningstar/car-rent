import { Link, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { buttonClass } from '@/components/ui/button';
import { ALL_CHOICES, NO_CHOICES, onOpenCookieSettings, saveConsent } from '@/lib/cookie-consent';
import type { CookieChoices } from '@/lib/cookie-consent';
import { cookies, privacy, terms } from '@/routes';

const categories: { key: keyof CookieChoices; label: string; description: string }[] = [
    { key: 'functional', label: 'Funkcjonalne', description: 'Zapamiętanie logowania, zaufane urządzenia i mapa OpenStreetMap, która przekazuje Twój adres IP dostawcy mapy.' },
    { key: 'analytics', label: 'Analityczne', description: 'Pomagają sprawdzić, jak korzystasz z serwisu. Obecnie ich nie używamy.' },
    { key: 'marketing', label: 'Marketingowe', description: 'Służą do dopasowania reklam. Obecnie ich nie używamy.' },
];

/** Pasek zgody na cookies. Działa poza stroną Inertii, więc pierwszy stan dostaje z danych startowych. */
export default function CookieBar({ initial }: { initial: CookieChoices | null }) {
    const [visible, setVisible] = useState(initial === null);
    const [optionsOpen, setOptionsOpen] = useState(false);
    const [saved, setSaved] = useState(initial);
    const [choices, setChoices] = useState<CookieChoices>(initial ?? NO_CHOICES);

    useEffect(
        () =>
            router.on('navigate', (event) => {
                const consent = event.detail.page.props.cookieConsent as CookieChoices | null;

                setSaved(consent);
                setVisible((current) => current || consent === null);
            }),
        [],
    );

    useEffect(() => {
        // Wcześniejsza wersja paska trzymała wybór w przeglądarce.
        try {
            localStorage.removeItem('cookie-consent');
            localStorage.removeItem('cookies-accepted');
        } catch {
            // Bez dostępu do pamięci przeglądarki nie ma czego sprzątać.
        }
    }, []);

    useEffect(
        () =>
            onOpenCookieSettings(() => {
                setChoices(saved ?? NO_CHOICES);
                setOptionsOpen(true);
                setVisible(true);
            }),
        [saved],
    );

    const decide = (decision: CookieChoices) => {
        saveConsent(decision);
        setSaved(decision);
        setChoices(decision);
        setOptionsOpen(false);
        setVisible(false);
    };

    if (!visible) {
        return null;
    }

    return (
        <section
            aria-label="Zgoda na cookies"
            className="fixed inset-x-0 bottom-0 z-40 max-h-[90dvh] overflow-y-auto border-t border-gray-200 bg-white pb-[env(safe-area-inset-bottom)] shadow-[0_-4px_16px_rgb(0_0_0/0.08)]"
        >
            <div className="flex flex-col gap-3 px-4 py-4 sm:px-6">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                    <p className="text-sm text-pretty text-gray-700">
                        Wymagane cookies są potrzebne, żeby serwis działał, np. do logowania. Pozostałe włączymy tylko za Twoją zgodą, a wybór zapamiętamy na tym urządzeniu przez rok. Więcej znajdziesz na stronach{' '}
                        <Link href={cookies()} className="font-medium text-brand-600 underline hover:text-brand-700">
                            Cookies
                        </Link>
                        ,{' '}
                        <Link href={terms()} className="font-medium text-brand-600 underline hover:text-brand-700">
                            Regulamin
                        </Link>{' '}
                        i{' '}
                        <Link href={privacy()} className="font-medium text-brand-600 underline hover:text-brand-700">
                            Polityka prywatności
                        </Link>
                        .
                    </p>

                    <div className="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <button type="button" onClick={() => setOptionsOpen((open) => !open)} aria-expanded={optionsOpen} className={buttonClass('outline', 'sm', 'border-gray-300')}>
                            Opcje
                        </button>
                        <button type="button" onClick={() => decide(NO_CHOICES)} className={buttonClass('outline', 'sm', 'border-gray-300')}>
                            Odrzuć
                        </button>
                        <button type="button" onClick={() => decide(NO_CHOICES)} className={buttonClass('outline', 'sm', 'border-gray-300')}>
                            Tylko wymagane
                        </button>
                        <button type="button" onClick={() => decide(ALL_CHOICES)} className={buttonClass('dark', 'sm')}>
                            Akceptuję wszystkie
                        </button>
                    </div>
                </div>

                {optionsOpen && (
                    <div className="flex flex-col gap-3 border-t border-gray-100 pt-3 text-sm">
                        <label className="flex items-start gap-3">
                            <input type="checkbox" checked disabled className="mt-0.5 size-5 shrink-0 accent-brand-600" />
                            <span>
                                <span className="block font-medium text-gray-900">Wymagane (zawsze włączone)</span>
                                <span className="block text-gray-600">Logowanie i ochrona formularzy. Bez nich serwis nie zadziała.</span>
                            </span>
                        </label>

                        {categories.map((category) => (
                            <label key={category.key} className="flex cursor-pointer items-start gap-3">
                                <input
                                    type="checkbox"
                                    checked={choices[category.key]}
                                    onChange={(event) => setChoices((current) => ({ ...current, [category.key]: event.target.checked }))}
                                    className="mt-0.5 size-5 shrink-0 accent-brand-600"
                                />
                                <span>
                                    <span className="block font-medium text-gray-900">{category.label}</span>
                                    <span className="block text-gray-600">{category.description}</span>
                                </span>
                            </label>
                        ))}

                        <button type="button" onClick={() => decide(choices)} className={buttonClass('dark', 'sm', 'self-start')}>
                            Zapisz wybór
                        </button>
                    </div>
                )}
            </div>
        </section>
    );
}
