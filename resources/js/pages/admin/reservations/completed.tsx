import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import ReservationsBrowser from '@/components/admin/reservations/reservations-browser';
import type { AdminReservation } from '@/components/admin/reservations/types';
import PageHeader from '@/components/ui/page-header';
import { formatMoney } from '@/lib/format';
import AdminLayout from '@/layouts/admin-layout';

export default function AdminReservationsCompleted({ reservations, failed }: { reservations: AdminReservation[]; failed: boolean }) {
    const revenue = reservations.reduce((sum, reservation) => sum + Number(reservation.total_price), 0);

    return (
        <>
            <Head title="Zrealizowane rezerwacje" />
            <PageHeader
                title="Zrealizowane"
                description="Potwierdzone rezerwacje, których termin zwrotu już minął."
                action={
                    <div className="rounded-xl bg-white px-4 py-2.5 text-sm ring-1 ring-gray-200">
                        Przychód: <span className="font-semibold text-gray-900">{formatMoney(revenue)}</span>
                    </div>
                }
            />
            <ReservationsBrowser reservations={reservations} failed={failed} />
        </>
    );
}

AdminReservationsCompleted.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
