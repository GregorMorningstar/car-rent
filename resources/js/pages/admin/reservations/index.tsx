import { Head, Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { reservationStates } from '@/components/admin/data/labels';
import PaymentsPanel from '@/components/admin/reservations/payments-panel';
import type { ReservationPayment } from '@/components/admin/reservations/payments-panel';
import { buttonClass } from '@/components/ui/button';
import PageHeader from '@/components/ui/page-header';
import StatusBadge from '@/components/ui/status-badge';
import AdminLayout from '@/layouts/admin-layout';
import { formatDateHour, formatDateTime, formatMoney } from '@/lib/format';
import { openOnClick } from '@/lib/open-on-click';
import admin from '@/routes/admin';

/** Rezerwacja z ReservationRepository::current. */
type CurrentReservation = {
    id: number;
    client_id: number;
    number: string;
    status: 'pending' | 'confirmed' | 'cancelled' | 'expired' | 'deleted';
    deleted_at: string | null;
    finished: boolean;
    client: string | null;
    email: string | null;
    car: string;
    registration_number: string | null;
    from: string;
    to: string;
    pickup: string | null;
    dropoff: string | null;
    total_price: string;
    deposit_amount: string;
    confirmation_deadline: string;
    notes: string | null;
    driver_name: string | null;
    payments: ReservationPayment[];
};

function ReservationCard({ reservation, paymentMethods }: { reservation: CurrentReservation; paymentMethods: string[] }) {
    const pending = reservation.status === 'pending';
    const closed = reservation.status === 'cancelled' || reservation.status === 'expired';
    const deleted = reservation.status === 'deleted';
    const open = !closed && !deleted && !reservation.finished;
    const decide = (url: string) => router.post(url, {}, { preserveScroll: true });
    const details = admin.reservations.edit(reservation.id);

    return (
        <article
            onClick={openOnClick(details.url)}
            className="flex cursor-pointer flex-col gap-3 rounded-2xl border border-gray-200 bg-white p-5 transition-colors hover:border-gray-300"
        >
            <div className="flex items-start justify-between gap-3">
                <div>
                    <h2 className="font-semibold text-gray-900">
                        <Link href={details} className="hover:text-brand-600">
                            {reservation.number}
                        </Link>
                    </h2>
                    {pending && (
                        <p className="text-xs font-medium text-amber-700">
                            Decyzja do {formatDateTime(reservation.confirmation_deadline)}
                        </p>
                    )}
                </div>
                <StatusBadge {...reservationStates[reservation.status]} />
            </div>

            <div className="text-sm text-gray-700">
                <p className="font-medium text-gray-900">{reservation.car}</p>
                <p className="text-gray-500">{reservation.registration_number}</p>
                <p className="mt-2 font-medium text-gray-900">{reservation.client}</p>
                <p className="text-gray-500">{reservation.email}</p>
                <button
                    type="button"
                    onClick={() => router.post(admin.messages.open().url, { client_id: reservation.client_id })}
                    className="py-1 text-sm font-medium text-brand-600 hover:text-brand-700"
                >
                    Napisz do klienta
                </button>
                <p className="mt-2">
                    {formatDateHour(reservation.from)} – {formatDateHour(reservation.to)} ·{' '}
                    <span className="font-semibold text-gray-900">{formatMoney(Number(reservation.total_price))}</span>
                </p>
                {reservation.pickup && (
                    <p className="text-gray-500">
                        {reservation.pickup}
                        {reservation.dropoff && reservation.dropoff !== reservation.pickup && ` → ${reservation.dropoff}`}
                    </p>
                )}
                {reservation.driver_name && <p className="text-gray-500">Kierowca: {reservation.driver_name}</p>}
                {reservation.notes && <p className="mt-2 rounded-lg bg-gray-50 p-2 whitespace-pre-line">{reservation.notes}</p>}
            </div>

            <PaymentsPanel
                reservationId={reservation.id}
                type="advance"
                client={reservation.client}
                limit={reservation.total_price}
                payments={reservation.payments.filter((payment) => payment.type === 'advance')}
                methods={paymentMethods}
                canAdd={!closed && !deleted}
            />
            <PaymentsPanel
                reservationId={reservation.id}
                type="deposit"
                client={reservation.client}
                limit={reservation.deposit_amount}
                payments={reservation.payments.filter((payment) => payment.type === 'deposit')}
                methods={paymentMethods}
                canAdd={!closed && !deleted}
            />

            {closed && <p className="text-xs font-medium text-amber-700">Rezerwacja nieaktualna: rozlicz wpłaty.</p>}
            {reservation.deleted_at && (
                <p className="text-xs font-medium text-orange-800">Klient usunął rezerwację {formatDateHour(reservation.deleted_at)}. Samochód jest wolny.</p>
            )}
            {reservation.finished && <p className="text-xs font-medium text-amber-700">Wynajem zakończony: rozlicz kaucję.</p>}

            <div className="mt-auto flex gap-2">
                {pending && (
                    <button
                        type="button"
                        onClick={() => decide(admin.reservations.confirm(reservation.id).url)}
                        className={buttonClass('primary', 'sm', 'flex-1')}
                    >
                        Akceptuj
                    </button>
                )}
                {open && (
                    <button
                        type="button"
                        onClick={() => decide(admin.reservations.reject(reservation.id).url)}
                        className={buttonClass('outline', 'sm', 'flex-1 border-gray-300')}
                    >
                        {pending ? 'Odrzuć' : 'Anuluj'}
                    </button>
                )}
            </div>
        </article>
    );
}

export default function AdminReservations({
    reservations,
    failed,
    paymentMethods,
}: {
    reservations: CurrentReservation[];
    failed: boolean;
    paymentMethods: string[];
}) {
    const { errors, notifications } = usePage().props;
    const removed = notifications?.reservations?.removed.items ?? [];

    return (
        <>
            <Head title="Aktualne rezerwacje" />
            <PageHeader
                title="Aktualne rezerwacje"
                description="Rezerwacje oczekujące na decyzję, potwierdzone oraz czekające na rozliczenie zaliczki lub kaucji."
            />

            {errors.reservation && (
                <p role="alert" className="mb-4 text-sm text-red-600">
                    {errors.reservation}
                </p>
            )}

            {removed.length > 0 && (
                <section aria-label="Usunięte przez klientów" className="mb-4 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="font-semibold">Klienci usunęli rezerwacje:</h2>
                        <button
                            type="button"
                            onClick={() => router.post(admin.reservations.seen().url, {}, { preserveScroll: true })}
                            className="h-10 px-2 font-medium underline"
                        >
                            Oznacz jako przeczytane
                        </button>
                    </div>
                    <ul className="mt-1 space-y-1">
                        {removed.map((order) => (
                            <li key={order.id}>
                                {order.client}: {order.car}, {formatDateHour(order.from)} – {formatDateHour(order.to)}
                                {order.was_confirmed && ' (była potwierdzona)'}
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {failed && (
                <p role="alert" className="text-sm text-red-600">
                    Nie udało się wczytać rezerwacji. Spróbuj ponownie później.
                </p>
            )}

            {!failed && reservations.length === 0 && (
                <p className="rounded-2xl border border-gray-200 bg-white p-8 text-center text-sm text-gray-500">
                    Brak aktualnych rezerwacji.
                </p>
            )}

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                {reservations.map((reservation) => (
                    <ReservationCard key={reservation.id} reservation={reservation} paymentMethods={paymentMethods} />
                ))}
            </div>
        </>
    );
}

AdminReservations.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
