import { Link, usePage } from '@inertiajs/react';
import { helpPhone } from '@/components/home/data';
import { about, cars, contact, home } from '@/routes';

const navigation = [
    { label: 'Strona główna', href: home() },
    { label: 'Samochody', href: cars() },
    { label: 'Kontakt', href: contact() },
    { label: 'O nas', href: about() },
];

export default function SiteFooter() {
    const { name } = usePage().props;

    return (
        <footer className="mt-14 bg-gray-950 text-gray-400 sm:mt-20">
            <div className="gutter grid gap-10 py-12 sm:grid-cols-2 sm:py-14 lg:grid-cols-[2fr_1fr_1fr]">
                <div>
                    <Link href={home()} className="inline-block">
                        <img
                            src="/images/logo.png"
                            alt={name}
                            width={330}
                            height={66}
                            className="h-8 w-auto sm:h-9"
                        />
                    </Link>
                    <p className="mt-4 max-w-sm text-sm text-pretty">
                        Wynajem samochodów w Krośnie – szybka rezerwacja,
                        sprawdzone auta, pełne wsparcie.
                    </p>
                </div>

                <nav aria-label="Stopka">
                    <h2 className="text-sm font-semibold text-white">Menu</h2>
                    <ul className="mt-3 grid grid-cols-2 gap-x-6 text-sm sm:grid-cols-1">
                        {navigation.map((item) => (
                            <li key={item.label}>
                                <Link
                                    href={item.href}
                                    className="flex min-h-10 items-center transition-colors hover:text-white"
                                >
                                    {item.label}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </nav>

                <div>
                    <h2 className="text-sm font-semibold text-white">
                        Potrzebujesz pomocy z wynajmem?
                    </h2>
                    <a
                        href={`tel:${helpPhone.replaceAll(' ', '')}`}
                        className="mt-3 inline-flex min-h-11 items-center gap-2 rounded-lg bg-white/5 px-4 text-lg font-semibold text-white ring-1 ring-white/10 transition-colors hover:bg-brand-600 hover:ring-brand-600"
                    >
                        <svg
                            className="size-5"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth={1.75}
                            aria-hidden="true"
                        >
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                d="M2.25 6.75c0 8.28 6.72 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.37c0-.52-.35-.97-.85-1.09l-4.42-1.1c-.44-.11-.9.05-1.17.41l-.97 1.29a1.13 1.13 0 0 1-1.21.38 12.04 12.04 0 0 1-7.14-7.14 1.13 1.13 0 0 1 .38-1.21l1.29-.97c.36-.27.52-.73.41-1.17l-1.1-4.42a1.13 1.13 0 0 0-1.09-.85H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"
                            />
                        </svg>
                        {helpPhone}
                    </a>
                </div>
            </div>

            <div className="gutter border-t border-white/10 py-5 pb-[max(1.25rem,env(safe-area-inset-bottom))] text-center text-xs sm:text-left">
                © {new Date().getFullYear()} {name}. Wszelkie prawa
                zastrzeżone.
            </div>
        </footer>
    );
}
