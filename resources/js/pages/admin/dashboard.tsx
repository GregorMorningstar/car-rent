import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { ReactNode } from 'react';
import { cars } from '@/components/admin/data/cars';
import { clients } from '@/components/admin/data/clients';
import type { ReservationStatus } from '@/components/admin/data/labels';
import { allReservations } from '@/components/admin/data/reservations';
import ReservationTile from '@/components/admin/reservations/reservation-tile';
import PageHeader from '@/components/ui/page-header';
import Pagination from '@/components/ui/pagination';
import { usePagination } from '@/hooks/use-pagination';
import AdminLayout from '@/layouts/admin-layout';
import admin from '@/routes/admin';

const TILES_PER_PAGE = 4;

function StatTile({ label, value, href }: { label: string; value: number; href: ReturnType<typeof admin.dashboard> }) {
    return (
        <Link
            href={href}
            className="rounded-2xl border border-gray-200 bg-white p-4 transition-colors hover:border-gray-300 sm:p-5"
        >
            <p className="text-sm text-gray-500">{label}</p>
            <p className="mt-1 text-2xl font-bold text-gray-900 tabular-nums sm:text-3xl">{value}</p>
        </Link>
    );
}

export default function AdminDashboard() {
    // Decyzje podjęte na tej stronie (podgląd - bez zapisu do bazy).
    const [decisions, setDecisions] = useState<Record<number, ReservationStatus>>({});

    const reservations = allReservations();
    const latest = reservations
        .filter((reservation) => reservation.status === 'pending')
        .map((reservation) => {
            const decision = decisions[reservation.id];

            return decision ? { ...reservation, status: decision, state: decision } : reservation;
        });
    const pagination = usePagination(latest, TILES_PER_PAGE);

    const decide = (id: number, status: ReservationStatus) =>
        setDecisions((previous) => ({ ...previous, [id]: status }));

    const waiting = latest.filter((reservation) => reservation.state === 'pending').length;
    const ongoing = reservations.filter(
        (reservation) => reservation.state === 'confirmed' && reservation.start_date <= new Date().toISOString().slice(0, 10),
    ).length;

    return (
        <>
            <Head title="Pulpit" />

            <PageHeader title="Pulpit" description="Najnowsze rezerwacje i stan wypożyczalni." />

            <div className="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
                <StatTile label="Do potwierdzenia" value={waiting} href={admin.reservations.index()} />
                <StatTile label="Trwające wynajmy" value={ongoing} href={admin.reservations.index()} />
                <StatTile
                    label="Dostępne auta"
                    value={cars.filter((car) => car.operational_status === 'active').length}
                    href={admin.cars.index()}
                />
                <StatTile label="Klienci" value={clients.length} href={admin.clients.index()} />
            </div>

            <section className="mt-8 lg:mt-10">
                <div className="mb-4 flex flex-wrap items-end justify-between gap-2">
                    <div>
                        <h2 className="text-lg font-semibold text-gray-900">Najnowsze rezerwacje</h2>
                        <p className="text-sm text-gray-500">Oczekujące na decyzję, od najnowszej.</p>
                    </div>
                    <Link
                        href={admin.reservations.index()}
                        className="text-sm font-medium text-brand-600 hover:text-brand-700"
                    >
                        Wszystkie rezerwacje →
                    </Link>
                </div>

                {latest.length === 0 ? (
                    <p className="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-500">
                        Brak nowych rezerwacji.
                    </p>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-4">
                        {pagination.items.map((reservation) => (
                            <li key={reservation.id}>
                                <ReservationTile
                                    reservation={reservation}
                                    onAccept={() => decide(reservation.id, 'confirmed')}
                                    onReject={() => decide(reservation.id, 'cancelled')}
                                />
                            </li>
                        ))}
                    </ul>
                )}

                <Pagination {...pagination} onChange={pagination.setPage} />
            </section>
        </>
    );
}

AdminDashboard.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
