import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { ReservationState } from '@/components/admin/data/labels';
import { reservationStates } from '@/components/admin/data/labels';
import { allReservations } from '@/components/admin/data/reservations';
import ReservationsBrowser from '@/components/admin/reservations/reservations-browser';
import PageHeader from '@/components/ui/page-header';
import AdminLayout from '@/layouts/admin-layout';

export default function AdminReservationsHistory() {
    return (
        <>
            <Head title="Historia rezerwacji" />
            <PageHeader title="Historia rezerwacji" description="Wszystkie rezerwacje niezależnie od statusu." />
            <ReservationsBrowser
                reservations={allReservations()}
                states={Object.keys(reservationStates) as ReservationState[]}
            />
        </>
    );
}

AdminReservationsHistory.layout = (page: ReactNode) => <AdminLayout>{page}</AdminLayout>;
