import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { buttonClass } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    about,
    cars,
    contact,
    home,
    login,
    logout,
    register,
} from '@/routes';
import admin from '@/routes/admin';
import userRoutes from '@/routes/user';
import type { User } from '@/types';

function initials(name: string) {
    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]!.toUpperCase())
        .join('');
}

/** Zamyka menu po przejściu na inną stronę (layout nie jest montowany od nowa). */
function useCloseOnNavigate(close: () => void) {
    useEffect(() => router.on('navigate', close), [close]);
}

function UserAvatar({ user }: { user: User }) {
    if (user.avatar) {
        return (
            <img
                src={user.avatar}
                alt=""
                className="size-9 shrink-0 rounded-full object-cover"
            />
        );
    }

    return (
        <span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-gray-900 text-xs font-semibold text-white">
            {initials(user.name)}
        </span>
    );
}

function UserMenu({ user }: { user: User }) {
    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    useCloseOnNavigate(() => setOpen(false));

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent | KeyboardEvent) => {
            if (
                event instanceof KeyboardEvent
                    ? event.key === 'Escape'
                    : !ref.current?.contains(event.target as Node)
            ) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);
        document.addEventListener('keydown', close);

        return () => {
            document.removeEventListener('mousedown', close);
            document.removeEventListener('keydown', close);
        };
    }, [open]);

    return (
        <div ref={ref} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-haspopup="menu"
                className="flex items-center gap-2 rounded-full p-1 pr-3 text-sm transition-colors hover:bg-gray-100"
            >
                <UserAvatar user={user} />
                <span className="max-w-40 truncate font-medium">
                    {user.name}
                </span>
                <svg
                    className={cn(
                        'size-4 text-gray-500 transition-transform',
                        open && 'rotate-180',
                    )}
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    aria-hidden="true"
                >
                    <path
                        fillRule="evenodd"
                        d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z"
                        clipRule="evenodd"
                    />
                </svg>
            </button>

            {open && (
                <div
                    role="menu"
                    className="absolute right-0 z-20 mt-2 w-60 overflow-hidden rounded-xl border border-gray-200 bg-white p-1 text-sm shadow-xl"
                >
                    <div className="px-3 py-3">
                        <p className="truncate font-semibold">{user.name}</p>
                        <p className="truncate text-gray-500">{user.email}</p>
                    </div>
                    <div className="border-t border-gray-100 pt-1">
                        {user.role === 'user' && (
                            <Link
                                href={userRoutes.dashboard()}
                                role="menuitem"
                                className="block rounded-lg px-3 py-2 hover:bg-gray-100"
                            >
                                Mój panel
                            </Link>
                        )}
                        <Link
                            href={logout()}
                            as="button"
                            role="menuitem"
                            className="block w-full rounded-lg px-3 py-2 text-left hover:bg-gray-100"
                        >
                            Wyloguj
                        </Link>
                    </div>
                </div>
            )}
        </div>
    );
}

export default function AppHeader() {
    const { auth, name } = usePage().props;
    const path = usePage().url.split('?')[0];
    const [mobileOpen, setMobileOpen] = useState(false);
    const user = auth.user;

    useCloseOnNavigate(() => setMobileOpen(false));

    const navigation = [
        { label: 'Strona główna', href: home(), active: path === home.url() },
        { label: 'Samochody', href: cars(), active: path === cars.url() },
        { label: 'Kontakt', href: contact(), active: path === contact.url() },
        { label: 'O nas', href: about(), active: path === about.url() },
        ...(user?.role === 'administrator'
            ? [
                  {
                      label: 'Panel administratora',
                      href: admin.dashboard(),
                      active: path.startsWith(admin.dashboard.url()),
                  },
              ]
            : []),
    ];

    return (
        <header className="sticky top-0 z-30 border-b border-gray-200 bg-white/95 backdrop-blur supports-backdrop-filter:bg-white/80">
            <div className="gutter flex h-16 items-center justify-between gap-4 lg:h-20">
                <div className="flex items-center gap-10">
                    <Link href={home()} className="shrink-0">
                        <img
                            src="/images/logo.png"
                            alt={name}
                            width={330}
                            height={66}
                            className="h-7 w-auto sm:h-8 lg:h-9"
                        />
                    </Link>

                    <nav className="hidden items-center gap-1 text-sm lg:flex">
                        {navigation.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                aria-current={item.active ? 'page' : undefined}
                                className={cn(
                                    'relative rounded-md px-3 py-2 font-medium text-gray-600 transition-colors hover:text-gray-900',
                                    'after:absolute after:inset-x-3 after:-bottom-[1.4rem] after:h-0.5 after:rounded-full after:bg-brand-600 after:opacity-0 after:transition-opacity',
                                    item.active && 'text-gray-900 after:opacity-100',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>
                </div>

                <div className="hidden items-center gap-3 lg:flex">
                    {user ? (
                        <UserMenu user={user} />
                    ) : (
                        <>
                            <Link
                                href={login()}
                                className="px-3 py-2 text-sm font-medium text-gray-600 hover:text-gray-900"
                            >
                                Zaloguj
                            </Link>
                            <Link
                                href={register()}
                                className={buttonClass('primary', 'sm')}
                            >
                                Zarejestruj się
                            </Link>
                        </>
                    )}
                </div>

                <button
                    type="button"
                    onClick={() => setMobileOpen((value) => !value)}
                    aria-expanded={mobileOpen}
                    aria-controls="mobile-menu"
                    aria-label={mobileOpen ? 'Zamknij menu' : 'Otwórz menu'}
                    className="-mr-2 flex size-11 items-center justify-center rounded-lg text-gray-700 hover:bg-gray-100 lg:hidden"
                >
                    <svg
                        className="size-6"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={1.75}
                        aria-hidden="true"
                    >
                        {mobileOpen ? (
                            <path
                                strokeLinecap="round"
                                d="M6 18 18 6M6 6l12 12"
                            />
                        ) : (
                            <path
                                strokeLinecap="round"
                                d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"
                            />
                        )}
                    </svg>
                </button>
            </div>

            {mobileOpen && (
                <div
                    id="mobile-menu"
                    className="gutter absolute inset-x-0 top-full max-h-[calc(100dvh-4rem)] overflow-y-auto border-b border-gray-200 bg-white pt-2 pb-[max(1.5rem,env(safe-area-inset-bottom))] shadow-xl lg:hidden"
                >
                    <nav className="flex flex-col">
                        {navigation.map((item) => (
                            <Link
                                key={item.label}
                                href={item.href}
                                aria-current={item.active ? 'page' : undefined}
                                className={cn(
                                    'flex min-h-12 items-center rounded-lg px-3 text-base font-medium text-gray-700 active:bg-gray-100',
                                    item.active &&
                                        'bg-brand-50 text-brand-700',
                                )}
                            >
                                {item.label}
                            </Link>
                        ))}
                    </nav>

                    <div className="mt-3 border-t border-gray-200 pt-4">
                        {user ? (
                            <>
                                <div className="flex items-center gap-3 px-3 pb-3">
                                    <UserAvatar user={user} />
                                    <div className="min-w-0">
                                        <p className="truncate font-semibold">
                                            {user.name}
                                        </p>
                                        <p className="truncate text-sm text-gray-500">
                                            {user.email}
                                        </p>
                                    </div>
                                </div>
                                <div className="grid auto-cols-fr grid-flow-col gap-3">
                                    {user.role === 'user' && (
                                        <Link
                                            href={userRoutes.dashboard()}
                                            className={buttonClass('dark')}
                                        >
                                            Mój panel
                                        </Link>
                                    )}
                                    <Link
                                        href={logout()}
                                        as="button"
                                        className={buttonClass('outline')}
                                    >
                                        Wyloguj
                                    </Link>
                                </div>
                            </>
                        ) : (
                            <div className="grid grid-cols-2 gap-3">
                                <Link
                                    href={login()}
                                    className={buttonClass('outline')}
                                >
                                    Zaloguj
                                </Link>
                                <Link
                                    href={register()}
                                    className={buttonClass('primary')}
                                >
                                    Zarejestruj się
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
            )}
        </header>
    );
}
