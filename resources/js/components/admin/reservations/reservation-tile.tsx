import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { buttonClass } from '@/components/ui/button';
import { formatDate, formatDateTime, formatMoney } from '@/lib/format';
import { openOnClick } from '@/lib/open-on-click';
import admin from '@/routes/admin';
import ReservationStatusBadge from './reservation-status-badge';
import type { AdminReservation } from './types';

type Props = {
    reservation: AdminReservation;
    onAccept: () => void;
    onReject: () => void;
};

function Row({ icon, children }: { icon: string; children: ReactNode }) {
    return (
        <div className="flex gap-3">
            <svg className="mt-0.5 size-5 shrink-0 text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.5} aria-hidden="true">
                <path strokeLinecap="round" strokeLinejoin="round" d={icon} />
            </svg>
            <div className="min-w-0 text-sm">{children}</div>
        </div>
    );
}

const icons = {
    user: 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
    car: 'M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12',
    calendar: 'M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
};

export default function ReservationTile({ reservation, onAccept, onReject }: Props) {
    const { client, car } = reservation;
    const pending = reservation.state === 'pending';
    const details = admin.reservations.edit(reservation.id);

    return (
        <article
            onClick={openOnClick(details.url)}
            className="flex h-full cursor-pointer flex-col rounded-2xl border border-gray-200 bg-white transition-colors hover:border-gray-300"
        >
            <header className="flex items-start justify-between gap-3 border-b border-gray-100 p-4">
                <div className="min-w-0">
                    <Link href={details} className="block truncate font-semibold text-gray-900 hover:text-brand-600">
                        {reservation.number}
                    </Link>
                    {reservation.created_at && <p className="text-xs text-gray-500">Złożona {formatDateTime(reservation.created_at)}</p>}
                </div>
                <ReservationStatusBadge state={reservation.state} />
            </header>

            <div className="flex flex-1 flex-col gap-3.5 p-4">
                <Row icon={icons.user}>
                    <Link
                        href={admin.clients.show(client.id)}
                        className="block truncate font-medium text-gray-900 hover:text-brand-600"
                    >
                        {client.name}
                    </Link>
                    <a href={`mailto:${client.email}`} className="block truncate text-gray-500 hover:text-gray-900">
                        {client.email}
                    </a>
                    {client.phone && (
                        <a href={`tel:${client.phone.replaceAll(' ', '')}`} className="block text-gray-500 hover:text-gray-900">
                            {client.phone}
                        </a>
                    )}
                </Row>

                <Row icon={icons.car}>
                    <p className="truncate font-medium text-gray-900">{car.name}</p>
                    <p className="text-gray-500">{car.registration_number}</p>
                </Row>

                <Row icon={icons.calendar}>
                    <p className="font-medium text-gray-900">
                        {formatDate(reservation.start_date)} – {formatDate(reservation.end_date)}
                    </p>
                    <p className="text-gray-500">
                        {reservation.days} dni ·{' '}
                        <span className="font-semibold text-gray-900">{formatMoney(Number(reservation.total_price))}</span>
                    </p>
                </Row>

                {reservation.notes && (
                    <p className="rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600">„{reservation.notes}”</p>
                )}
            </div>

            <footer className="grid grid-cols-2 gap-2 border-t border-gray-100 p-4">
                <button
                    type="button"
                    onClick={onAccept}
                    disabled={!pending}
                    className={buttonClass('primary', 'sm', 'bg-emerald-600 hover:bg-emerald-700 focus-visible:outline-emerald-600')}
                >
                    Akceptuj
                </button>
                <button
                    type="button"
                    onClick={onReject}
                    disabled={!pending}
                    className={buttonClass('outline', 'sm', 'border-brand-600 text-brand-700 hover:border-brand-600 hover:bg-brand-600')}
                >
                    Odrzuć
                </button>
                <Link href={details} className={buttonClass('outline', 'sm', 'col-span-2 border-gray-300')}>
                    Edytuj
                </Link>
            </footer>
        </article>
    );
}
