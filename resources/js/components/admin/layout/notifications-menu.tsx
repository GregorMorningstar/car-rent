import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { formatDateHour, formatMoney } from '@/lib/format';
import admin from '@/routes/admin';

/** Chmurka w górnym pasku: nowe zamówienia i nieprzeczytane wiadomości. */
export default function NotificationsMenu() {
    const notifications = usePage().props.notifications;
    const [open, setOpen] = useState(false);
    const menu = useRef<HTMLDivElement>(null);

    const orders = notifications?.reservations?.items ?? [];
    const orderCount = notifications?.reservations?.count ?? 0;
    const removed = notifications?.reservations?.removed.items ?? [];
    const removedCount = notifications?.reservations?.removed.count ?? 0;
    const messages = notifications?.messages ?? 0;
    const total = orderCount + removedCount + messages;

    useEffect(() => {
        if (!open) {
            return;
        }

        const closeOutside = (event: MouseEvent) => {
            if (!menu.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        const closeOnEscape = (event: KeyboardEvent) => event.key === 'Escape' && setOpen(false);

        document.addEventListener('mousedown', closeOutside);
        document.addEventListener('keydown', closeOnEscape);

        return () => {
            document.removeEventListener('mousedown', closeOutside);
            document.removeEventListener('keydown', closeOnEscape);
        };
    }, [open]);

    return (
        <div ref={menu} className="relative">
            <button
                type="button"
                onClick={() => setOpen((value) => !value)}
                aria-expanded={open}
                aria-label={total > 0 ? `Powiadomienia: ${total}` : 'Powiadomienia'}
                className="relative flex size-10 items-center justify-center rounded-lg text-gray-700 ring-1 ring-gray-200 transition-colors hover:bg-gray-100"
            >
                <svg className="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.75} aria-hidden="true">
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.087.16 2.185.283 3.293.369V21l4.076-4.076a1.526 1.526 0 0 1 1.037-.443 48.282 48.282 0 0 0 5.68-.494c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"
                    />
                </svg>
                {total > 0 && (
                    <span className="absolute -top-1.5 -right-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-brand-600 px-1 text-xs font-semibold text-white tabular-nums">
                        {total}
                    </span>
                )}
            </button>

            {open && (
                <div className="absolute top-12 right-0 z-30 w-[min(22rem,calc(100vw-2rem))] rounded-2xl border border-gray-200 bg-white p-2 shadow-xl">
                    {total === 0 && <p className="p-3 text-sm text-gray-500">Brak nowych powiadomień.</p>}

                    {messages > 0 && (
                        <Link href={admin.messages.index()} className="block rounded-lg p-3 text-sm font-medium text-gray-900 hover:bg-gray-100">
                            Nieprzeczytane wiadomości: {messages}
                        </Link>
                    )}

                    {orderCount + removedCount > 0 && (
                        <div className="flex justify-end px-3 pt-1">
                            <button
                                type="button"
                                onClick={() => router.post(admin.reservations.seen().url, {}, { preserveScroll: true })}
                                className="py-1 text-xs font-medium text-brand-600 hover:text-brand-700"
                            >
                                Oznacz jako przeczytane
                            </button>
                        </div>
                    )}

                    {removedCount > 0 && (
                        <>
                            <p className="px-3 pt-1 pb-1 text-sm font-semibold text-gray-900">Usunięte przez klientów: {removedCount}</p>
                            <ul>
                                {removed.map((order) => (
                                    <li key={order.id} className="rounded-lg p-3 text-sm">
                                        <span className="block font-medium text-gray-900">{order.car}</span>
                                        <span className="block text-gray-500">
                                            {order.client}
                                            {order.was_confirmed && ' · była potwierdzona'}
                                        </span>
                                        <span className="block text-gray-500">
                                            {formatDateHour(order.from)} – {formatDateHour(order.to)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}

                    {orderCount > 0 && (
                        <>
                            <p className="px-3 pt-2 pb-1 text-sm font-semibold text-gray-900">Nowe zamówienia: {orderCount}</p>
                            <ul>
                                {orders.map((order) => (
                                    <li key={order.id}>
                                        <Link href={admin.reservations.index()} className="block rounded-lg p-3 text-sm hover:bg-gray-100">
                                            <span className="block font-medium text-gray-900">{order.car}</span>
                                            <span className="block text-gray-500">{order.client}</span>
                                            <span className="block text-gray-500">
                                                {formatDateHour(order.from)} – {formatDateHour(order.to)} · {formatMoney(Number(order.total_price))}
                                            </span>
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
