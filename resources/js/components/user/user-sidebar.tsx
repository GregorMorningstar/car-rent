import { Link, usePage } from '@inertiajs/react';
import { buttonClass } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { home, logout } from '@/routes';
import user from '@/routes/user';

const icons = {
    messages:
        'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
    dashboard:
        'M2.25 12l8.954-8.955a1.126 1.126 0 0 1 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75',
    rentals:
        'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    payments:
        'M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
    profile:
        'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
    logout:
        'M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9',
};

function Icon({ path, className }: { path: string; className?: string }) {
    return (
        <svg
            className={cn('size-5 shrink-0', className)}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
            aria-hidden="true"
        >
            <path strokeLinecap="round" strokeLinejoin="round" d={path} />
        </svg>
    );
}

const navigation = [
    { label: 'Pulpit', route: user.dashboard, icon: icons.dashboard },
    { label: 'Historia wynajmu', route: user.rentals, icon: icons.rentals },
    { label: 'Wiadomości', route: user.messages.index, icon: icons.messages, unread: true },
    { label: 'Płatności', route: user.payments, icon: icons.payments },
    { label: 'Mój profil', route: user.profile, icon: icons.profile },
];

export default function UserSidebar() {
    const { auth, notifications } = usePage().props;
    const unread = notifications?.messages ?? 0;
    const path = usePage().url.split('?')[0];
    const isActive = (route: (typeof navigation)[number]['route']) =>
        path === route.url();

    const badge = (item: (typeof navigation)[number]) =>
        'unread' in item && unread > 0 ? (
            <span className="ml-auto rounded-full bg-brand-600 px-2 text-xs font-semibold text-white tabular-nums">{unread}</span>
        ) : null;

    return (
        <>
            {/* Telefon i tablet: przewijany pasek zakładek */}
            <nav
                aria-label="Panel użytkownika"
                className="scrollbar-none -mx-4 mb-6 flex snap-x gap-2 overflow-x-auto px-4 sm:-mx-6 sm:px-6 lg:hidden"
            >
                {navigation.map((item) => (
                    <Link
                        key={item.label}
                        href={item.route()}
                        aria-current={isActive(item.route) ? 'page' : undefined}
                        className={cn(
                            'flex h-11 shrink-0 snap-start items-center gap-2 rounded-full px-4 text-sm font-medium ring-1 transition-colors',
                            isActive(item.route)
                                ? 'bg-gray-900 text-white ring-gray-900'
                                : 'bg-white text-gray-700 ring-gray-200 active:bg-gray-100',
                        )}
                    >
                        <Icon path={item.icon} className="size-4" />
                        {item.label}
                        {badge(item)}
                    </Link>
                ))}
            </nav>

            {/* Komputer: boczne menu */}
            <aside className="sticky top-28 hidden flex-col gap-6 self-start lg:flex">
                <div className="rounded-2xl border border-gray-200 bg-white p-5">
                    <p className="text-xs font-semibold tracking-widest text-gray-500 uppercase">
                        Zalogowany jako
                    </p>
                    <p className="mt-1 truncate font-semibold text-gray-900">
                        {auth.user?.name}
                    </p>
                    <p className="truncate text-sm text-gray-500">
                        {auth.user?.email}
                    </p>
                </div>

                <nav
                    aria-label="Panel użytkownika"
                    className="flex flex-col gap-1 rounded-2xl border border-gray-200 bg-white p-2"
                >
                    {navigation.map((item) => (
                        <Link
                            key={item.label}
                            href={item.route()}
                            aria-current={isActive(item.route) ? 'page' : undefined}
                            className={cn(
                                'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors',
                                isActive(item.route)
                                    ? 'bg-brand-50 text-brand-700'
                                    : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900',
                            )}
                        >
                            <Icon path={item.icon} />
                            {item.label}
                            {badge(item)}
                        </Link>
                    ))}

                    <div className="my-1 border-t border-gray-100" />

                    <Link
                        href={logout()}
                        as="button"
                        className="flex items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-900"
                    >
                        <Icon path={icons.logout} />
                        Wyloguj
                    </Link>
                </nav>

                <div className="rounded-2xl bg-gray-900 p-5 text-white">
                    <p className="font-semibold">Potrzebujesz auta?</p>
                    <p className="mt-1 text-sm text-gray-400">
                        Sprawdź dostępne samochody i zarezerwuj online.
                    </p>
                    <Link
                        href={home()}
                        className={buttonClass('primary', 'sm', 'mt-4 w-full')}
                    >
                        Zarezerwuj auto
                    </Link>
                </div>
            </aside>
        </>
    );
}
