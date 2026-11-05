import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { home } from '@/routes';
import admin from '@/routes/admin';
import type { RouteDefinition } from '@/wayfinder';

type Item = {
    label: string;
    route: { (): RouteDefinition<'get'>; url: () => string };
    icon: string;
    /** "prefix" - aktywny także na podstronach (np. szczegóły klienta). */
    match?: 'exact' | 'prefix';
    /** Licznik obok pozycji menu. */
    badge?: 'messages' | 'reservations';
};

type Group = { title?: string; items: Item[] };

const icons = {
    dashboard: 'M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z',
    calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
    history: 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    check: 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
    users: 'M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z',
    car: 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
    plus: 'M12 4.5v15m7.5-7.5h-15',
    location: 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
    chat: 'M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z',
    settings: 'M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
    options: 'M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75',
    road: 'M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z',
    site: 'M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25',
};

const groups: Group[] = [
    { items: [{ label: 'Pulpit', route: admin.dashboard, icon: icons.dashboard }] },
    {
        title: 'Rezerwacje',
        items: [
            { label: 'Aktualne rezerwacje', route: admin.reservations.index, icon: icons.calendar, badge: 'reservations' },
            { label: 'Historia rezerwacji', route: admin.reservations.history, icon: icons.history },
            { label: 'Zrealizowane', route: admin.reservations.completed, icon: icons.check },
        ],
    },
    {
        title: 'Klienci',
        items: [
            { label: 'Dane klientów', route: admin.clients.index, icon: icons.users, match: 'prefix' },
            { label: 'Wiadomości', route: admin.messages.index, icon: icons.chat, badge: 'messages' },
        ],
    },
    {
        title: 'Opcje',
        items: [
            { label: 'Opcje rezerwacji', route: admin.options.edit, icon: icons.settings },
            { label: 'Ustawienia Messengera', route: admin.settings.messenger.edit, icon: icons.settings },
            { label: 'Bezpieczeństwo', route: admin.security.index, icon: icons.settings },
            { label: 'Dziennik działań', route: admin.security.audit, icon: icons.settings },
        ],
    },
    {
        title: 'Samochody',
        items: [
            { label: 'Lista samochodów', route: admin.cars.index, icon: icons.car },
            { label: 'Dodaj samochód', route: admin.cars.create, icon: icons.plus },
            { label: 'Historia wypożyczeń', route: admin.cars.history, icon: icons.road },
            { label: 'Wyposażenie i kolory', route: admin.dictionary.index, icon: icons.options },
            { label: 'Miejsca', route: admin.locations.index, icon: icons.location },
        ],
    },
];

function Icon({ path }: { path: string }) {
    return (
        <svg className="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
            <path strokeLinecap="round" strokeLinejoin="round" d={path} />
        </svg>
    );
}

export default function AdminSidebar() {
    const { name, notifications } = usePage().props;
    const badges = { messages: notifications?.messages ?? 0, reservations: (notifications?.reservations?.count ?? 0) + (notifications?.reservations?.removed.count ?? 0) };
    const path = usePage().url.split('?')[0];

    const isActive = (item: Item) =>
        item.match === 'prefix' ? path.startsWith(item.route.url()) : path === item.route.url();

    return (
        <div className="flex h-full flex-col bg-gray-950 text-gray-400">
            <Link href={admin.dashboard()} className="flex h-16 shrink-0 items-center gap-3 px-5">
                <img src="/images/logo.png" alt={name} width={330} height={66} className="h-7 w-auto" />
                <span className="rounded bg-brand-600 px-1.5 py-0.5 text-[0.65rem] font-semibold tracking-wide text-white uppercase">
                    Admin
                </span>
            </Link>

            <nav aria-label="Panel administratora" className="scrollbar-none flex-1 overflow-y-auto px-3 pb-6">
                {groups.map((group, index) => (
                    <div key={group.title ?? index} className="mt-5 first:mt-2">
                        {group.title && (
                            <p className="mb-1.5 px-3 text-[0.7rem] font-semibold tracking-widest text-gray-500 uppercase">
                                {group.title}
                            </p>
                        )}
                        <ul className="flex flex-col gap-0.5">
                            {group.items.map((item) => (
                                <li key={item.label}>
                                    <Link
                                        href={item.route()}
                                        aria-current={isActive(item) ? 'page' : undefined}
                                        className={cn(
                                            'flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-white/5 hover:text-white',
                                            isActive(item) && 'bg-white/10 text-white',
                                        )}
                                    >
                                        <Icon path={item.icon} />
                                        {item.label}
                                        {item.badge && badges[item.badge] > 0 && (
                                            <span className="ml-auto rounded-full bg-brand-600 px-2 text-xs font-semibold text-white tabular-nums">
                                                {badges[item.badge]}
                                            </span>
                                        )}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </div>
                ))}
            </nav>

            <div className="border-t border-white/10 p-3">
                <Link
                    href={home()}
                    className="flex min-h-10 items-center gap-3 rounded-lg px-3 text-sm font-medium transition-colors hover:bg-white/5 hover:text-white"
                >
                    <Icon path={icons.site} />
                    Przejdź do serwisu
                </Link>
            </div>
        </div>
    );
}
